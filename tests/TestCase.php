<?php

namespace yii1tech\error\handler\test;

use CConsoleApplication;
use CMap;
use Yii;

class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockApplication();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        $this->destroyApplication();
    }

    /**
     * Populates Yii::app() with a new application
     * The application will be destroyed on tearDown() automatically.
     * @param array $config The application configuration, if needed
     * @param string $appClass name of the application class to create
     */
    protected function mockApplication($config = [], $appClass = CConsoleApplication::class)
    {
        Yii::setApplication(null);

        new $appClass(CMap::mergeArray([
            'id' => 'testapp',
            'basePath' => __DIR__,
            'components' => [
                'errorHandler' => [
                    'class' => \yii1tech\error\handler\ErrorHandler::class,
                ],
            ],
        ], $config));
    }

    /**
     * Destroys Yii application by setting it to null.
     */
    protected function destroyApplication()
    {
        Yii::setApplication(null);
    }

    /**
     * Registers Yii application error handler.
     */
    protected function registerYiiErrorHandler(): void
    {
        error_reporting(-1);

        set_error_handler([Yii::app()->getErrorHandler(), 'handleErrorGlobal'], error_reporting());
        set_exception_handler([Yii::app(), 'handleException']);
    }

    /**
     * Restores previous error handler.
     */
    protected function restoreErrorHandler(): void
    {
        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * Executes given PHP callback inside the scope of Yii error handler enabled.
     * Restores original error handler afterwards.
     *
     * @param callable $callback PHP callback to be executed.
     * @return mixed callback result.
     */
    protected function withYiiErrorHandler(callable $callback)
    {
        $this->registerYiiErrorHandler();

        try {
            $result = call_user_func($callback);
        } catch (\Throwable $exception) {
            $this->restoreErrorHandler();

            throw $exception;
        }

        $this->restoreErrorHandler();

        return $result;
    }
}