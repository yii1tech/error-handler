<?php

namespace yii1tech\error\handler\test;

use yii1tech\error\handler\ErrorTraceFilter;

class ErrorTraceFilterTest extends TestCase
{
    protected function prepareException($exceptionMessage = 'Test exception', $exceptionCode = 12345) : \Throwable
    {
        $args = [
            'object' => new \stdClass(),
            'bool' => true,
            'string' => 'a string',
            // Managed argument count being at 5, create a sub bucket
            'sub' => [
                'a_too_long_string' => str_repeat('a', 100),
                'null' => null,
                'resource' => tmpfile(),
                'sub' => [
                    'list' => ['foo', 'bar'],
                    'list_with_holes' => [9 => 'nine', 5 => 'five'],
                    'mixed_array' => ['foo', 'name' => 'bar']
                ]
            ],
            'extra_param' => 'will not be normalized!'
        ];

        try {
            // create a long stack trace
            $closure = function ($exceptionMessage, $exceptionCode, array $allTypesOfArgs) {
                throw new \RuntimeException($exceptionMessage, $exceptionCode);
            };

            call_user_func($closure, $exceptionMessage, $exceptionCode, $args);
        } catch (\Throwable $exception) {
            // shutdown test exception as prepared
        }

        return $exception;
    }

    public function testFilter(): void
    {
        $traceFilter = new ErrorTraceFilter();

        $exceptionMessage = 'Test exception';
        $exceptionCode = 12345;

        $exception = $this->prepareException($exceptionMessage, $exceptionCode);

        $trace = $traceFilter->filter($exception->getTrace());

        $this->assertFalse(empty($trace[0]));
    }

    /**
     * @depends testFilter
     */
    public function testShouldRestrictTraceSize(): void
    {
        $exception = $this->prepareException();

        $traceFilter = new ErrorTraceFilter();
        $traceFilter->maxTraceSize = 1;

        $trace = $traceFilter->filter($exception->getTrace());

        $this->assertCount($traceFilter->maxTraceSize, $trace);
    }

    /**
     * @depends testShouldRestrictTraceSize
     */
    public function testShouldShowTraceArguments(): void
    {
        ini_set('zend.exception_ignore_args', 0); // Be sure arguments will be available on the stack trace
        $exception = $this->prepareException();

        $traceFilter = new ErrorTraceFilter();
        $traceFilter->maxTraceSize = 1;

        $trace = $traceFilter->filter($exception->getTrace());

        $argsFound = false;
        foreach ($trace as $entry) {
            if (isset($entry['args'])) {
                $argsFound = true;
                break;
            }
        }

        $this->assertTrue($argsFound);
    }
}