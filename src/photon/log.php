<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, the High Speed PHP Framework.
# Copyright (C) 2010, 2011 Loic d'Anterroches and contributors.
#
# Photon is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License.
#
# Photon is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * Logging module.
 *
 * Logging is important, really important, you must log everything in
 * your application and rotate your logs. This will not cost you that
 * much performance penalty but will save your day when you will need
 * to debug or predict growth.
 *
 * Logs in an application can be collected for later writing or
 * directly sent to the backend. Photon provides file, MongoDB and zmq
 * logging backends. You can easily write your own or even chain
 * them. Chaining of backends allow you to write to a main backend and
 * fail over a second one in case of failure of the main backend.
 *
 * The usage is has one would expect:
 *
 * <pre>
 * \photon\log\Log::plog('Whatever you want to log.'); // Log::ALL level
 * \photon\log\Log::info('Whatever you want to log.');
 * \photon\log\Log::debug('Debug information.');
 * \photon\log\Log::fatal('Runtime situation which may crash the app.');
 * </pre>
 *
 * The log levels in priority order are:
 *
 * - `Log::ALL`: Lowest level, for low level debbugging.
 * - `Log::DEBUG`: Recommended for debug information.
 * - `Log::INFO`: Not so detailed as debug information but interesting.
 * - `Log::PERF`: You should always start to log at this level.
 * - `Log::EVENT`: Application events, maybe creation of an item or so.
 * - `Log::WARN`: Warning, something could break, need to review.
 * - `Log::ERROR`: Application error, the user is normally getting a 501.
 * - `Log::FATAL`: Fatal error leading to application crash.
 * - `Log::OFF`: Logging is disabled.
 *
 * For each lever a corresponding `Log::level` method is
 * available. For example, `Log::event('New sign up!')`.
 *
 * The real interesting part of the logging is that it directly
 * serialize the information as JSON. Serialized JSON is easy to read
 * for a human and easy to parse for a program. This way you can log:
 *
 * \photon\log\Log::debug(array('Remote call' => 'http://foo', 
 *                              'return_code' => 200));
 */
namespace photon\log;

use photon\config\Container as Conf;

/**
 * The main class, it will load the backends as needed.
 *
 * The default backend is the text file backend.
 */
class Log
{
    /**
     * The log stack.
     *
     * A logger function is just pushing the data in the log stack,
     * the writers are then called to write the data later.
     */
    public static $stack = array();

    /**
     * Different log levels.
     */
    const ALL = 1;
    const DEBUG = 3;
    const INFO = 4;
    const PERF = 5;
    const EVENT = 6;
    const WARN = 7;
    const ERROR = 8;
    const FATAL = 9;
    const OFF = 10;
    
    /**
     * Used to reverse the log level to the string.
     */
    public static $reverse = array(1 => 'ALL',
                                   3 => 'DEBUG',
                                   4 => 'INFO',
                                   5 => 'PERF',
                                   6 => 'EVENT',
                                   7 => 'WARN',
                                   8 => 'ERROR',
                                   9 => 'FATAL');

    /**
     * Current log level.
     *
     * By default, logging is not enabled.
     */
    public static $level = 10;

    /**
     * A simple storage to track stats.
     *
     * A good example is to store stats and at the end of the request,
     * push the info back in the log. You can for example store the
     * total time doing SQL or other things like that.
     *
     * See Timer::start() and Timer::stop().
     */
    public static $store = array();

    /**
     * Set the log level.
     */
    public static function setLevel($level)
    {
        $levels = array_flip(self::$reverse);
        self::$level = $levels[$level];
    }

    /**
     * Log the information in the stack.
     *
     * Flush the information if needed.
     *
     * @param $level Level to log
     * @param $message Message to log
     */
    public static function _log($level, $message)
    {

        if (10 !== self::$level && self::$level <= $level) {
            self::$stack[] = array(time(), $level, $message);
            if (!Conf::f('log_delayed', false)) {
                self::flush();
            }
        }
    }

    /**
     * Log at the ALL level.
     *
     * @param $message Message to log
     */
    public static function plog($message)
    {
        return self::_log(self::ALL, $message);
    }

    /**
     * Log at the DEBUG level.
     *
     * @param $message Message to log
     */
    public static function debug($message)
    {
        self::_log(self::DEBUG, $message);
    }

    public static function info($message)
    {
        self::_log(self::INFO, $message);
    }

    public static function perf($message)
    {
        self::_log(self::PERF, $message);
    }

    public static function event($message)
    {
        self::_log(self::EVENT, $message);
    }

    public static function warn($message)
    {
        self::_log(self::WARN, $message);
    }

    public static function error($message)
    {
        self::_log(self::ERROR, $message);
    }

    public static function fatal($message)
    {
        self::_log(self::FATAL, $message);
    }

    /**
     * Flush the data to the writer.
     *
     * This reset the stack.
     */
    public static function flush()
    {
        if (0 === count(self::$stack)) {
            return;
        }
        $writers = Conf::f('log_handlers', array('\photon\log\ConsoleBackend'));
        foreach ($writers as $writer) {
            $res = call_user_func(array($writer, 'write'), self::$stack);
            if (true === $res) {
                break;
            }
        }
        self::$stack = array();
    }

}

/**
 * Used to easily track the time between two events.
 *
 * <code>
 * Timer::start('event');
 * run_long_process();
 * $time = Timer::stop('event');
 * </code>
 *
 */
class Timer
{
    /**
     * A simple storage to track stats.
     *
     * A good example is to store stats and at the end of the request,
     * push the info back in the log. You can for example store the
     * total time doing SQL or other things like that.
     *
     */
    public static $store = array();

   /**
    * Start the time to track.
    *
    * @param $key Tracker ('default')
    */
    public static function start($key='default')
    {
        self::$store[$key] = microtime(true);
    }

   /**
    * End the time to track.
    *
    * @param $key Tracker
    * @param $total Tracker to store the total (null)
    * @return float Time for this track
    */
    public static function stop($key='default', $total=null)
    {
        $t = microtime(true) - self::$store[$key];
        unset(self::$store[$key]);
        if (null !== $total) {
            self::$store['total_' . $total] = (isset(self::$store['total_' . $total])) 
                ? self::$store['total_' . $total] + $t
                : $t;
        }
        return $t;
    }

    /**
     * Increment a key in the store.
     *
     * It automatically creates the key as needed.
     *
     * @param $key Key to increment
     * @param $amount Amount to increase (1)
     */
    public static function inc($key, $amount=1)
    {
        if (!isset(self::$store[$key])) {
            self::$store[$key] = 0;
        }
        self::$store[$key] += $amount;
    }

   /**
    * Get a key from the store.
    *
    * @param $key Key to set
    * @param $value Default value (null)
    */
    public static function get($key, $value=null)
    {
        return (isset(self::$store[$key])) 
            ? self::$store[$key] : $value;
    }
}

/**
 * Very simple backend. Just append the data to a file.
 */
class FileBackend
{
    /**
     * Default return code.
     *
     * A logger can "break" the chain or not. For example, if you have
     * a logger to a remote daemon and something is not working as
     * expected you can default to file logging. The usage is simple,
     * set your remote logger first in the list and return true
     * normally, this will stop the logger chain, if something is not
     * working ok, return false, the next logger, maybe a simple local
     * file logger will take care of the logging.
     *
     * The ability to change the return code here is for unit testing
     * purpose.
     */
    public static $return = false;

    /**
     * Track if the log file has been chmoded.
     *
     * You will most of the time have many different components
     * working together but not necessarily running under the same
     * user id. The file backend will try after the first write to
     * chmod the file for all write access. The try is only performed
     * once.
     */
    public static $chmoded = false;

    /**
     * Log file.
     */
    public static $log_file = null;

    /**
     * Flush the stack to the disk.
     *
     * @param $stack Array
     */
    public static function write($stack)
    {
        if (null === self::$log_file) {
            self::$log_file = Conf::f('photon_log_file', 
                   Conf::f('tmp_folder', sys_get_temp_dir()) . '/photon.log');
        }
        $out = array();
        foreach ($stack as $elt) {
            $out[] = date(DATE_ISO8601, $elt[0]) . ' [' .
                Log::$reverse[$elt[1]] . '] ' . json_encode($elt[2]);
        }
        file_put_contents(self::$log_file, 
                          implode(PHP_EOL, $out) . PHP_EOL, 
                          FILE_APPEND | LOCK_EX);
        if (!self::$chmoded) {
            @chmod($file, 0666);
            self::$chmoded = true;
        }

        return self::$return;
    }
}

/**
 * Display on the console.
 *
 * Code coverage disabled because it annoys me to print stuff on the
 * console to test the console.
 * 
 * @codeCoverageIgnore
 */
class ConsoleBackend
{
    /**
     * Default return code.
     *
     * A logger can "break" the chain or not. For example, if you have
     * a logger to a remote daemon and something is not working as
     * expected you can default to file logging. The usage is simple,
     * set your remote logger first in the list and return true
     * normally, this will stop the logger chain, if something is not
     * working ok, return false, the next logger, maybe a simple local
     * file logger will take care of the logging.
     *
     * The ability to change the return code here is for unit testing
     * purpose.
     */
    public static $return = false;

    /**
     * Flush the stack to the console.
     *
     * @param $stack Array
     */
    public static function write($stack)
    {
        $out = array();
        foreach ($stack as $elt) {
            $out[] = date(DATE_ISO8601, $elt[0]) . ' [' .
                Log::$reverse[$elt[1]] . '] ' . json_encode($elt[2]);
        }
        print implode(PHP_EOL, $out) . PHP_EOL;

        return self::$return;
    }
}

