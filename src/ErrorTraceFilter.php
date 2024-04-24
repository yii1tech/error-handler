<?php

namespace yii1tech\error\handler;

use CComponent;

/**
 * ErrorTraceFilter creates simplified representation of the stack trace.
 *
 * @see \yii1tech\error\handler\ErrorHandler::filterErrorTrace()
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class ErrorTraceFilter extends CComponent
{
    /**
     * @var int maximum number of trace source code lines to be displayed. Defaults to 10.
     */
    public $maxTraceSize = 10;

    /**
     * Creates simplified representation of the given stack trace.
     *
     * @param array $trace raw trace.
     * @return array simplified trace.
     */
    public function filter(array $trace): array
    {
        $trace = array_slice($trace, 0, $this->maxTraceSize);

        $result = [];
        foreach ($trace as $entry) {
            if (array_key_exists('args', $entry)) {
                $entry['args'] = $this->simplifyArguments($entry['args']);
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Converts arguments array to their simplified representation.
     *
     * @param array $args arguments array to be converted.
     * @return string string representation of the arguments array.
     */
    private function simplifyArguments(array $args) : string
    {
        $count = 0;

        $isAssoc = $args !== array_values($args);

        foreach ($args as $key => $value) {
            $count++;

            if ($count >= 5) {
                if ($count > 5) {
                    unset($args[$key]);
                } else {
                    $args[$key] = '...';
                }

                continue;
            }

            $args[$key] = $this->simplifyArgument($value);

            if (is_string($key)) {
                $args[$key] = "'" . $key . "' => " . $args[$key];
            } elseif ($isAssoc) {
                $args[$key] = $key.' => '.$args[$key];
            }
        }

        return implode(', ', $args);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function simplifyArgument($value)
    {
        if (is_object($value)) {
            return get_class($value);
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_string($value)) {
            if (strlen($value) > 64) {
                return "'" . substr($value, 0, 64) . "...'";
            }

            return "'" . $value . "'";
        } elseif (is_array($value)) {
            return '[' . $this->simplifyArguments($value) . ']';
        } elseif ($value === null) {
            return 'null';
        } elseif (is_resource($value)) {
            return 'resource';
        }

        return $value;
    }
}