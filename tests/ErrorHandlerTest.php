<?php

namespace yii1tech\error\handler\test;

use Yii;

class ErrorHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ACCEPT']);

        parent::tearDown();
    }

    public function testCovertErrorToException(): void
    {
        try {
            $this->withYiiErrorHandler(function () {
                trigger_error('Test message', E_USER_WARNING);
            });
        } catch (\Throwable $exception) {}

        $this->assertTrue(isset($exception));
        $this->assertSame('Test message', $exception->getMessage());
        $this->assertSame(E_USER_WARNING, $exception->getCode());

        $trace = $exception->getTrace();

        $this->assertSame('trigger_error', $trace[0]['function']);
        $this->assertFalse(empty($trace[0]['args']));
    }

    public function testShouldRenderErrorAsJson(): void
    {
        $errorHandler = Yii::app()->getErrorHandler();

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $this->assertTrue($errorHandler->shouldRenderErrorAsJson());

        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $this->assertFalse($errorHandler->shouldRenderErrorAsJson());

        unset($_SERVER['HTTP_ACCEPT']);
        $this->assertFalse($errorHandler->shouldRenderErrorAsJson());

        $errorHandler->shouldRenderErrorAsJsonCallback = function() {
            return true;
        };
        $this->assertTrue($errorHandler->shouldRenderErrorAsJson());

        $errorHandler->shouldRenderErrorAsJsonCallback = function() {
            return false;
        };
        $this->assertFalse($errorHandler->shouldRenderErrorAsJson());
    }
}