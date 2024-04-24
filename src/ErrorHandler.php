<?php

namespace yii1tech\error\handler;

use CErrorHandler;
use ErrorException;
use Yii;

/**
 * ErrorHandler is an enhanced version of standard Yii error handler.
 *
 * Its main feature is conversion of the PHP errors into exceptions, so they may be processed via `try..catch` blocks.
 *
 * > Note: in order for error to exception conversion to work, the error handler component should be added to the
 *   application "preload" section.
 *
 * Application configuration example:
 *
 * ```
 * return [
 *     'preload' => [
 *         'errorHandler', // preload custom error handler, overriding the default one, allowing error to exception conversion
 *         // ...
 *     ],
 *     'components' => [
 *         'errorHandler' => [
 *             'class' => \yii1tech\error\handler\ErrorHandler::class,
 *         ],
 *         // ...
 *     ],
 *     // ...
 * ];
 * ```
 *
 * In addition, this class provides support for error/exception rendering as JSON output, which is useful for modern
 * XHR and API implementation.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class ErrorHandler extends CErrorHandler
{
    /**
     * @var bool whether to convert PHP Errors into Exceptions.
     *
     * @see \ErrorException
     */
    public $convertErrorToException = true;

    /**
     * @var callable|null a PHP callback, which result should determine whether the error/exception should be displayed as JSON.
     * The callback signature:
     *
     * ```
     * function(): bool
     * ```
     *
     * If not set default condition of matching  'Accept' HTTP request header will be used.
     *
     * @see shouldRenderErrorAsJson()
     */
    public $shouldRenderErrorAsJsonCallback;

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        parent::init();

        if (YII_ENABLE_ERROR_HANDLER) {
            restore_error_handler(); // unset default Yii error handler, which is set to {@see \CApplication::handleError()}

            set_error_handler([$this, 'handleErrorGlobal'], error_reporting());
        }
    }

    /**
     * Handles global PHP execution errors such as warnings, notices.
     *
     * This method is implemented as a PHP error handler. It requires
     * that constant `YII_ENABLE_ERROR_HANDLER` be defined true.
     *
     * @param int $code the level of the error raised
     * @param string $message the error message
     * @param string $file the filename that the error was raised in
     * @param int $line the line number the error was raised at
     * @return bool whether the normal error handler continues.
     */
    public function handleErrorGlobal(int $code, string $message, string $file, int $line): bool
    {
        if (!$this->convertErrorToException) {
            Yii::app()->handleError($code, $message, $file, $line);

            return false;
        }

        if (error_reporting() & $code) {
            $exception = new ErrorException($message, $code, $code, $file, $line);

            $trace = debug_backtrace();
            array_shift($trace);
            $this->setExceptionTrace($exception, $trace);

            if (PHP_VERSION_ID < 70400) {
                // prior to PHP 7.4 we can't throw exceptions inside of __toString() - it will result a fatal error
                foreach ($trace as $frame) {
                    if ($frame['function'] === '__toString') {
                        Yii::app()->handleException($exception);

                        return false;
                    }
                }
            }

            throw $exception;
        }

        return false;
    }

    /**
     * Sets up the trace for the given exception.
     *
     * > Note: exception trace can't be modified and used directly with PHP, thus a reflection is used here.
     *
     * @param \Exception $exception exception instance.
     * @param array $trace new stack trace.
     */
    protected function setExceptionTrace(\Exception $exception, array $trace): void
    {
        $traceReflection = new \ReflectionProperty(\Exception::class, 'trace');
        $traceReflection->setAccessible(true);
        $traceReflection->setValue($exception, $trace);
    }

    /**
     * Checks if error/exception should be rendered as JSON.
     *
     * @see $shouldRenderErrorAsJsonCallback
     *
     * > Tip: you can invoke this method inside your custom handler specified via {@see $errorAction}.
     *
     * @return bool whether the error/exception should be rendered as JSON.
     */
    public function shouldRenderErrorAsJson(): bool
    {
        if ($this->shouldRenderErrorAsJsonCallback !== null) {
            return call_user_func($this->shouldRenderErrorAsJsonCallback);
        }

        return !empty($_SERVER['HTTP_ACCEPT']) && strcasecmp($_SERVER['HTTP_ACCEPT'], 'application/json') === 0;
    }

    /**
     * Renders current error information as JSON output.
     * This method will display information from current {@see getError()} value.
     *
     * > Note: this method does NOT terminate the script.
     *
     * > Tip: you can invoke this method inside your custom handler specified via {@see $errorAction}.
     */
    public function renderErrorAsJson(): void
    {
        $error = $this->getError();
        if (empty($error)) {
            return;
        }

        unset($error['trace']);

        $responseData = [
            'error' => $this->getHttpHeader($error['code']),
            'code' => $error['code'],
        ];

        $jsonFlags = 0;
        if (YII_DEBUG) {
            $jsonFlags = JSON_PRETTY_PRINT;

            $error['traces'] = $this->filterErrorTrace($error['traces']);

            $responseData = array_merge($responseData, $error);
        }

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($responseData, $jsonFlags);
    }

    /**
     * @param array $trace raw exception stack trace.
     * @return array simplified stack trace.
     */
    private function filterErrorTrace(array $trace): array
    {
        $traceFilter = new ErrorTraceFilter();
        $traceFilter->maxTraceSize = $this->maxTraceSourceLines;

        return $traceFilter->filter($trace);
    }

    /**
     * {@inheritDoc}
     */
    protected function renderException(): void
    {
        $exception = $this->getException();

        if ($exception instanceof \CHttpException || !YII_DEBUG) {
            $this->renderError();

            return;
        }

        if ($this->shouldRenderErrorAsJson()) {
            $this->renderErrorAsJson();

            return;
        }

        if ($this->isAjaxRequest()) {
            Yii::app()->displayException($exception);

            return;
        }

        $this->render('exception', $this->getError());
    }

    /**
     * {@inheritDoc}
     */
    protected function renderError(): void
    {
        if ($this->errorAction !== null) {
            Yii::app()->runController($this->errorAction);

            return;
        }

        if ($this->shouldRenderErrorAsJson()) {
            $this->renderErrorAsJson();

            return;
        }

        $data = $this->getError();
        if ($this->isAjaxRequest()) {
            Yii::app()->displayError($data['code'], $data['message'], $data['file'], $data['line']);

            return;
        }

        if (YII_DEBUG) {
            $this->render('exception', $data);

            return;
        }

        $this->render('error',$data);
    }
}