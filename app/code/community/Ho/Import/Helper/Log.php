<?php
/**
 * Ho_Import
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category    Ho
 * @package     Ho_Import
 * @copyright   Copyright © 2012 H&O (http://www.h-o.nl/)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Paul Hachmang – H&O <info@h-o.nl>
 */
class Ho_Import_Helper_Log extends Mage_Core_Helper_Abstract
{
    const LOG_MODE_CLI = 'cli';
    const LOG_MODE_NOTIFICATION = 'notification';

    const LOG_SUCCESS = 8;

    protected $_logfile = 'ho_import.log';

    protected $_minLogLevel = self::LOG_SUCCESS;

    protected $_mode = self::LOG_MODE_NOTIFICATION;

    protected $_logEntries = array();


    /** Constructor */
    public function __construct()
    {
        mb_internal_encoding('UTF-8');
    }

    /**
     * Set the log file
     * @param string $file Path to the log file
     *
     * @return $this
     */
    public function setLogfile($file)
    {
        $this->_logfile = $file;
        return $this;
    }


    /**
     * @return string
     */
    public function getLogfile()
    {
        return $this->_logfile;
    }


    /**
     * @param int $level
     */
    public function setMinLogLevel($level)
    {
        if (! is_numeric($level)) {
            Mage::throwException($this->__('The min log level should be numeric, %s given', $level));
        }

        if ($level > self::LOG_SUCCESS) {
            Mage::throwException($this->__('The log level can be %s maximum, %s given', self::LOG_SUCCESS, $level));
        }

        $this->_minLogLevel = (int) $level;
    }

    /**
     * @param     $message
     * @param int $level
     *
     * @return $this
     */
    public function log($message, $level = Zend_Log::INFO)
    {
        if (empty($message)) {
            return $this;
        }

        if ($this->_mode == self::LOG_MODE_CLI) {
            $date = date("H:i:s", Mage::getModel('core/date')->timestamp(time()));

            if (is_array($message) && is_array(reset($message))) {
                $message = $this->_renderCliTable($message, $level);
            }

            echo "\033[0;37m".$date.' - '.$this->convert(memory_get_usage(true)) . " ";
            echo $this->_getCliColor($level);
            print_r($message);
            echo "\033[37m\n";

        } else {
            $this->_logEntries[$level][] = $message;
            Mage::log($message, $level, $this->_logfile, true);
        }

        return $this;
    }

    protected function _getCliColor($level = Zend_Log::INFO)
    {
        switch($level)
        {
            case Zend_Log::EMERG:
            case Zend_Log::ALERT:
            case Zend_Log::CRIT:
            case Zend_Log::ERR:
            case Zend_Log::WARN:
                return "\033[31m";
                break;
            case Zend_Log::NOTICE:
                return "\033[33m";
                break;
            case Zend_Log::INFO:
                return "\033[36m";
                break;
            case Ho_Import_Helper_Log::LOG_SUCCESS:
                return "\033[32m";
                break;
            default:
                return "\033[37m";
                break;
        }
    }

    /**
     * @param array $arrays Multi dimension array to be rendered
     * @param int   $level  Default log level, to make sure the colors are correct.
     *
     * @return string
     */
    protected function _renderCliTable($arrays, $level = Zend_Log::INFO)
    {
        $maxWidth = exec('tput cols') ?: 200;
        if (count($arrays) > 5) {
            $arrays = array_slice($arrays, 0, 5);
        }

        $columnsOrig = array();
        foreach ($arrays as $row) {
            foreach ($row as $col => $value) {
                $columnsOrig[$col] = $col;
            }
        }

        array_unshift($arrays, $columnsOrig);
        $flippedArray = array();
        foreach ($arrays as $row) {
            foreach ($columnsOrig as $column) {
                if (isset($row[$column])) {

                    if (is_array($row[$column]) || is_object($row[$column])) {
                        $row[$column] = json_encode($row[$column]);
                    }

                    if (strpos($row[$column], "\n")) {
                        $row[$column] = str_replace("\n", '\n', $row[$column]);
                    }

                    $flippedArray[$column][] = $row[$column];
                } else {
                    $flippedArray[$column][] = '';
                }
            }
        }

        $columns = array();
        foreach ($flippedArray as $row) {
            foreach ($row as $col => $value) {
                if (! isset($columns[$col])) {
                    $columns[$col] = 0;
                }

                if (mb_strlen($col) > $columns[$col]) {
                    $columns[$col] = mb_strlen($col);
                }

                if (mb_strlen($value) > $columns[$col]) {
                    $columns[$col] = mb_strlen($value);
                }
            }
        }

        $maxColumnWidth = ($maxWidth - reset($columns)) / (count($columns) - 1) - 10;
        foreach ($columns as $col => $width) {
            if ($col == 'key') {
                continue;
            }
            if ($columns[$col] > $maxColumnWidth) {
                $columns[$col] = $maxColumnWidth;
            }
        }

        $lines = "\n";

        $line  = '| '.$this->_mbStrPad('key', $columns[0]).' |';
        $lineTwo = '+-'.$this->_mbStrPad('-', $columns[0], '-').'-+';
        $i = 0;
        array_shift($arrays);
        foreach (array_keys($arrays) as $key) {
            $i++;

            $search = preg_match_all('/__.*?__/', $key, $matches);
            if ($search) {
                $str = $this->_mbStrPad($key, $columns[$i]);
                foreach ($matches[0] as $match) {
                    $str = str_replace(
                        $match,
                        "\033[31m".str_replace('__', '', $match).$this->_getCliColor($level),
                        $str
                    );
                }
                $padding = count($matches[0]) * 4;
                $str = $this->_mbStrPad($str, mb_strlen($str) + $padding);
            } else {
                $str = $this->_mbStrPad($key, $columns[$i]);
            }
            $line .= ' '.$str.' |';

            $lineTwo.= '-'.$this->_mbStrPad('-', $columns[$i], '-').'-+';
        }
        $lines.= $lineTwo . "\n";
        $lines.= $line . "\n";
        $lines.= $lineTwo . "\n";

        foreach ($flippedArray as $row) {
            $line = '|';
            foreach ($columns as $column => $length) {
                if (isset($row[$column])) {
                    $row[$column] = Mage::helper('core/string')->truncate($row[$column], $length, '…');
                    $search = preg_match_all('/__.*?__/', $row[$column], $matches);

                    if ($search) {
                        $str = $this->_mbStrPad($row[$column], $length);
                        foreach ($matches[0] as $match) {
                            $str = str_replace(
                                $match,
                                "\033[31m".str_replace('__', '', $match).$this->_getCliColor($level),
                                $str
                            );
                        }
                        $padding = count($matches[0]) * 4;
                        $str = $this->_mbStrPad($str, mb_strlen($str) + $padding);
                    } else {
                        $str = $this->_mbStrPad($row[$column], $length);
                    }

                    $line .= ' '.$str.' |';
                }
            }
            $lines.= $line . "\n";
        }

        $lines .= $lineTwo;
        return $lines;
    }

    protected function _mbStrPad( $input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT)
    {
        $diff = strlen($input) - mb_strlen($input);
        return str_pad($input, $pad_length + $diff, $pad_string, $pad_type);
    }

    /**
     * When logging to the admin notification inbox.
     */
    public function done()
    {
        $this->_logEntries = array();
    }


    /**
     * @param string $mode
     * @return $this
     */
    public function setMode($mode)
    {
        $this->_mode = $mode;
        return $this;
    }

    public function getMode()
    {
        return $this->_mode;
    }

    public function isModeCli()
    {
        return $this->_mode == self::LOG_MODE_CLI;
    }


    /**
     * @return string
     */
    public function getLogHtml()
    {
        $html = '';
        foreach ($this->_logEntries as $level => $entries) {
            foreach ($entries as $entry) {
                if (is_array($entry)) {
                    $html .= sprintf("%s:<br/>\n <pre>%s</pre><br/>\n", $level, $this->_renderCliTable($entry));
                } else {
                    $html .= sprintf("%s - %s<br/>\n", $level, $entry);
                }
            }

        }

        return $html;
    }


    /**
     * Get a human readable format
     * @param int $size
     * @return string
     */
    public function convert($size)
    {
        $unit=array('B','KB','MB','GB','TB','PB');
        return @round($size/pow(1024, ($i=floor(log($size, 1024))))).$unit[$i];
    }


    public function getExceptionTraceAsString($exception) {
        $rtn = "";
        $count = 0;
        foreach ($exception->getTrace() as $frame) {
            $args = "";
            if (isset($frame['args'])) {
                $args = array();
                foreach ($frame['args'] as $arg) {
                    if (is_string($arg)) {
                        $args[] = "'" . $arg . "'";
                    } elseif (is_array($arg)) {
                        $args[] = "Array";
                    } elseif (is_null($arg)) {
                        $args[] = 'NULL';
                    } elseif (is_bool($arg)) {
                        $args[] = ($arg) ? "true" : "false";
                    } elseif (is_object($arg)) {
                        $args[] = get_class($arg);
                    } elseif (is_resource($arg)) {
                        $args[] = get_resource_type($arg);
                    } else {
                        $args[] = $arg;
                    }
                }
                $args = join(", ", $args);
            }
            $rtn .= sprintf( "#%s %s(%s): %s%s(%s)\n",
                $count,
                isset($frame['file']) ? $frame['file'] : '',
                isset($frame['line']) ? $frame['line'] : '',
                isset($frame['class']) ? $frame['class'] . '->' : '',
                isset($frame['function']) ? $frame['function'] : '',
                $args );
            $count++;
        }
        return $rtn;
    }
}