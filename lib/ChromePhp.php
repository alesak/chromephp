<?php
/**
 * Copyright 2010-2013 Craig Campbell
 * Copyright 2018-2021 Ramil Aliyakberov
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Server Side Chrome PHP debugger class
 *
 * @package ChromePhp
 * @author Craig Campbell <iamcraigcampbell@gmail.com>
 * @author Ramil Aliyakberov <r@me0.biz>
 */
class ChromePhp
{
    /**
     * @var string
     */
    const VERSION = '5';

    /**
     * @var string
     */
    const HEADER_NAME = 'X-ChromeLogger-Data';

    /**
     * @var string
     */
    const BACKTRACE_LEVEL = 'backtrace_level';

    /**
     * @var string
     */
    const LOG = 'log';

    /**
     * @var string
     */
    const WARN = 'warn';

    /**
     * @var string
     */
    const ERROR = 'error';

    /**
     * @var string
     */
    const GROUP = 'group';

    /**
     * @var string
     */
    const INFO = 'info';

    /**
     * @var string
     */
    const GROUP_END = 'groupEnd';

    /**
     * @var string
     */
    const GROUP_COLLAPSED = 'groupCollapsed';

    /**
     * @var string
     */
    const TABLE = 'table';

    /**
     * @var string
     */
    protected $_php_version;

    /**
     * @var int
     */
    protected $_timestamp;

    /**
     * @var array
     */
    protected array $_json = array(
        'version' => self::VERSION,
        'columns' => array('log', 'backtrace', 'type'),
        'rows' => array()
    );

    /**
     * @var array
     */
    protected array $_backtrace = array();

    /**
     * @var bool
     */
    protected bool $_error_triggered = false;

    /**
     * @var array
     */
    protected array $_settings = array(
        self::BACKTRACE_LEVEL => 1
    );

    /**
     * Prevent recursion when working with objects referring to each other
     *
     * @var array
     */
    protected array $_processed = array();

    /**
     * constructor
     */
    public function __construct()
    {
        $this->_php_version = phpversion();
        $this->_timestamp = $_SERVER['REQUEST_TIME'];
        $this->_json['request_uri'] = $_SERVER['REQUEST_URI'];
    }

    /**
     * logs a variable to the console
     *
     * @param array $args unlimited OPTIONAL number of additional logs [...]
     * @return void
     */
    public function log(...$args)
    {

        $this->_log(self::LOG, $args);
    }

    /**
     * logs a warning to the console
     *
     * @param array $args unlimited OPTIONAL number of additional logs [...]
     * @return void
     */
    public function warn(...$args)
    {
        $this->_log(self::WARN, $args);
    }

    /**
     * logs an error to the console
     *
     * @param array $args unlimited OPTIONAL number of additional logs [...]
     * @return void
     */
    public function error(...$args)
    {
        $this->_log(self::ERROR, $args);
    }

    /**
     * sends a group log
     *
     * @param array $args
     */
    public function group(...$args)
    {
        $this->_log(self::GROUP, $args);
    }

    /**
     * sends an info log
     *
     * @param array $args unlimited OPTIONAL number of additional logs [...]
     * @return void
     */
    public function info(...$args)
    {
        $this->_log(self::INFO, $args);
    }

    /**
     * sends a collapsed group log
     *
     * @param array $args
     */
    public function groupCollapsed(...$args)
    {
        $this->_log(self::GROUP_COLLAPSED, $args);
    }

    /**
     * ends a group log
     *
     * @param array $args
     */
    public function groupEnd(...$args)
    {
        $this->_log(self::GROUP_END, $args);
    }

    /**
     * sends a table log
     *
     * @param array $args
     */
    public function table(...$args)
    {
        $this->_log(self::TABLE, $args);
    }

    /**
     * Log Exception
     * @param Throwable $exception Exception object
     */
    public function exception(Throwable $exception)
    {
        $this->group($exception->getFile() . ' : ' . $exception->getLine());
        $this->warn('Message: ' . $exception->getMessage());
        $this->log('PHP Stack trace:');
        foreach ($exception->getTrace() as $key => $val) {
            $line = '#' . $key . "\t";


            $args = [];
            if (!empty($val['args'])) {
                foreach ($val['args'] as $arg) {
                    $type = gettype($arg);

                    switch ($type) {
                        case"array":
                            $args[] = "(array) [...]";
                            break;
                        case"object":
                            $args[] = '(' . get_class($arg) . ') {...}';
                            break;
                        case"resource":
                            $args[] = "(resource)";
                            break;
                        case"unknown type":
                            $args[] = "*unknown type*";
                            break;
                        default:
                            $args[] = "($type) $arg";
                            break;
                    }
                }
            }

            $args = '(' . join(', ', $args) . ') ';

            if (!empty($val['class'])) {
                $line .= $val['class'] . $val['type'] . $val['function'] . $args;
            } elseif (!empty($val['function'])) {
                $line .= $val['function'] . $args;
            }

            if (!empty($val['file'])) {
                $line .= $val['file'] . ':' . $val['line'];
            }
            $this->log($line);
        }
        $this->groupEnd();
    }

    /**
     * internal logging call
     *
     * @param string $type
     * @param array $args
     * @return void
     */
    protected function _log(string $type, array $args)
    {
        // nothing passed in, don't do anything
        if (count($args) == 0 && $type != self::GROUP_END) {
            return;
        }

        $this->_processed = array();

        $logs = array();
        foreach ($args as $arg) {
            $logs[] = $this->_convert($arg);
        }

        $backtrace = debug_backtrace(false);
        $level = $this->getSetting(self::BACKTRACE_LEVEL);

        $backtrace_message = 'unknown';
        if (isset($backtrace[$level]['file']) && isset($backtrace[$level]['line'])) {
            $backtrace_message = $backtrace[$level]['file'] . ' : ' . $backtrace[$level]['line'];
        }

        $this->_addRow($logs, $backtrace_message, $type);
    }

    /**
     * converts an object to a better format for logging
     *
     * @param Object
     * @return array
     */
    protected function _convert($object): array
    {
        // if this isn't an object then just return it
        if (!is_object($object)) {
            return $object;
        }

        //Mark this object as processed so we don't convert it twice and it
        //Also avoid recursion when objects refer to each other
        $this->_processed[] = $object;

        $object_as_array = array();

        // first add the class name
        $object_as_array['___class_name'] = get_class($object);

        // loop through object vars
        $object_vars = get_object_vars($object);
        foreach ($object_vars as $key => $value) {

            // same instance as parent object
            if ($value === $object || in_array($value, $this->_processed, true)) {
                $value = 'recursion - parent object [' . get_class($value) . ']';
            }
            $object_as_array[$key] = $this->_convert($value);
        }

        try {
            $reflection = new ReflectionClass($object);

            // loop through the properties and add those
            foreach ($reflection->getProperties() as $property) {

                // if one of these properties was already added above then ignore it
                if (array_key_exists($property->getName(), $object_vars)) {
                    continue;
                }
                $type = $this->_getPropertyKey($property);

                $property->setAccessible(true);

                $value = $property->getValue($object);

                // same instance as parent object
                if ($value === $object || in_array($value, $this->_processed, true)) {
                    $value = 'recursion - parent object [' . get_class($value) . ']';
                }

                $object_as_array[$type] = $this->_convert($value);
            }
        } catch (Throwable $ex) {

        }
        return $object_as_array;
    }

    /**
     * takes a reflection property and returns a nicely formatted key of the property name
     *
     * @param ReflectionProperty
     * @return string
     */
    protected function _getPropertyKey(ReflectionProperty $property): string
    {
        $static = $property->isStatic() ? ' static' : '';
        if ($property->isPublic()) {
            return 'public' . $static . ' ' . $property->getName();
        } elseif ($property->isProtected()) {
            return 'protected' . $static . ' ' . $property->getName();
        } elseif ($property->isPrivate()) {
            return 'private' . $static . ' ' . $property->getName();
        } else {
            return $static . ' ' . $property->getName();
        }
    }

    /**
     * adds a value to the data array
     *
     *
     * @param array $logs
     * @param $backtrace
     * @param $type
     * @return void
     */
    protected function _addRow(array $logs, $backtrace, $type)
    {
        // if this is logged on the same line for example in a loop, set it to null to save space
        if (in_array($backtrace, $this->_backtrace)) {
            $backtrace = null;
        }

        // for group, groupEnd, and groupCollapsed
        // take out the backtrace since it is not useful
        if ($type == self::GROUP || $type == self::GROUP_END || $type == self::GROUP_COLLAPSED) {
            $backtrace = null;
        }

        if ($backtrace !== null) {
            $this->_backtrace[] = $backtrace;
        }

        $row = array($logs, $backtrace, $type);

        $this->_json['rows'][] = $row;
        $this->_writeHeader($this->_json);
    }

    protected function _writeHeader($data)
    {
        header(self::HEADER_NAME . ': ' . $this->_encode($data));
    }

    /**
     * encodes the data to be sent along with the request
     *
     * @param array $data
     * @return string
     */
    protected function _encode(array $data): string
    {
        return base64_encode(utf8_encode(json_encode($data)));
    }

    /**
     * adds a setting
     *
     * @param string key
     * @param mixed value
     * @return void
     */
    public function addSetting($key, $value)
    {
        $this->_settings[$key] = $value;
    }

    /**
     * add ability to set multiple settings in one call
     *
     * @param array $settings
     * @return void
     */
    public function addSettings(array $settings)
    {
        foreach ($settings as $key => $value) {
            $this->addSetting($key, $value);
        }
    }

    /**
     * gets a setting
     *
     * @param string key
     * @return mixed
     */
    public function getSetting($key)
    {
        if (!isset($this->_settings[$key])) {
            return null;
        }
        return $this->_settings[$key];
    }
}
