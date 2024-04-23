<?php

namespace yii1tech\error\handler\test;

class ErrorHandlerTest extends TestCase
{
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
}