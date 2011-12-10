<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, The High Speed PHP Framework.
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


namespace photon\tests\logTest;

use \photon\config\Container as Conf;
use \photon\log\Log;
use \photon\log\FileBackend;
use \photon\log\Timer;

class LogTest extends \PHPUnit_Framework_TestCase
{
    protected $conf;
    protected $level;

    public function setUp()
    {
        $this->conf = Conf::dump();
        $this->level = Log::$level;
    }

    public function tearDown()
    {
        Conf::load($this->conf);
        Log::$stack = array();
        Log::$store = array();
        Log::$level = $this->level;
    }

    public function testLog()
    {
        Log::setLevel('ALL');
        Conf::set('log_delayed', true);
        Conf::set('log_handlers', array('\photon\log\NullBackend'));
        $message = 'dummy message';
        $i = 0;
        Log::plog($message);
        $i++;
        $this->assertEquals($i, count(Log::$stack));
        Log::debug($message);
        $i++;
        $this->assertEquals($i, count(Log::$stack));
        Log::info($message);
        $i++;
        $this->assertEquals($i, count(Log::$stack));
        Log::perf($message);
        $i++;
        $this->assertEquals($i, count(Log::$stack));
        Log::event($message);
        $i++;
        $this->assertEquals($i, count(Log::$stack));
        Log::warn($message);
        $i++;
        $this->assertEquals($i, count(Log::$stack));
        Log::error($message);
        $i++;
        $this->assertEquals($i, count(Log::$stack));
        Log::fatal($message);
        $i++;
        $this->assertEquals($i, count(Log::$stack));
        FileBackend::$return = true;
        Log::flush();
        $this->assertEquals(0, count(Log::$stack));
        FileBackend::$return = false;
        Log::flush();
        $this->assertEquals(0, count(Log::$stack));
        Log::flush();
        $this->assertEquals(0, count(Log::$stack));
        Conf::set('log_delayed', false);
        Log::info($message);
        $this->assertEquals(0, count(Log::$stack));
    }

    public function testTimer()
    {
        Timer::start();
        $this->assertEquals(true, isset(Timer::$store['default']));
        Timer::stop();
        $this->assertEquals(false, isset(Timer::$store['default']));
        Timer::start('foo');
        $this->assertEquals(true, isset(Timer::$store['foo']));
        Timer::stop('foo', 'sql');
        $this->assertEquals(false, isset(Timer::$store['foo']));
        $this->assertEquals(true, isset(Timer::$store['total_sql']));
    }

    public function testTimerCount()
    {
        Timer::inc('sql_queries');
        $this->assertEquals(1, Timer::get('sql_queries'));
        $this->assertEquals(3, Timer::get('not_set', 3));
        Timer::inc('sql_queries');
        $this->assertEquals(2, Timer::get('sql_queries'));
    }
}