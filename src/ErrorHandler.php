<?php

namespace yii1tech\error\handler;

use CErrorHandler;
use ErrorException;
use Yii;

/**
 * Application configuration example:
 *
 * ```
 * [
 *     'preload' => [
 *         'errorHandler', // override default error handler, allowing error to exception conversion
 *         // ...
 *     ],
 *     'components' => [
 *         'errorHandler' => [
 *             'class' => \yii1tech\error\handler\ErrorHandler::class,
 *         ],
 *         // ...
 *     ],
 *     // ...
 * ]
 * ```
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class ErrorHandler extends CErrorHandler
{
    /**
     * @var bool whether to convert PHP Errors into Exceptions.
     * @see \ErrorException
     */
    public $convertErrorToException = true;

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
}