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


namespace photon\tests\http\cookiesTest;

use photon\config\Container as Conf;
use photon\http\Cookie as Cookie;
use photon\http\CookieHandler as CookieHandler;


class cookiesTest extends \PHPUnit_Framework_TestCase
{
    protected $conf;

    public function setUp()
    {
        $this->conf = Conf::dump();
    }

    public function tearDown()
    {
        Conf::load($this->conf);
    }

    public function testSimpleSetGet()
    {
        $cookies = new Cookie();
        $cookies['foo'] = 'bar';
        $this->assertEquals('bar', $cookies['foo']);
        $this->assertEquals(true, isset($cookies['foo']));
        unset($cookies['foo']);
        $this->assertEquals(false, isset($cookies['foo']));

        $this->setExpectedException('\photon\http\Exception');
        $cookies[] = 'bar';
    }

    public function testComplexSet()
    {
        $cookies = new Cookie(array('foo' => 'bar'));
        $this->assertEquals('bar', $cookies['foo']);
        $cookies->setCookie('bar', 'foo', time() + 86400, null, 
                            null, true, true);
        $this->assertEquals('foo', $cookies['bar']);
        $this->assertEquals(2, count($cookies->getAll()));
        unset($cookies['foo']);
        $this->assertEquals(2, count($cookies->getAll()));
    }

    public function testBuildSimpleCookie()
    {
        $cookies = new Cookie();
        $this->assertEquals('', CookieHandler::build($cookies, 'my-key'));
        $cookies = new Cookie(array('foo' => 'bar'));
        $headers = CookieHandler::build($cookies, 'my-key');
        $this->assertEquals('Set-Cookie: foo=czozOiJiYXIiOw.6o_2mL7ZL4HgcezUZT4Nn9VcIuM; '."\r\n", $headers);
        $cookies['bar'] = 'foo';
        $headers = CookieHandler::build($cookies, 'my-key');
        $this->assertEquals('Set-Cookie: foo=czozOiJiYXIiOw.6o_2mL7ZL4HgcezUZT4Nn9VcIuM; bar=czozOiJmb28iOw.pa7EFOZK0OkBpqpaS_P2Qo1Zccw; '."\r\n", $headers);
    }

    public function testParseSimpleCookie()
    {
        $headers = (object) array('cookie' => 'foo=czozOiJiYXIiOw.6o_2mL7ZL4HgcezUZT4Nn9VcIuM; bar=czozOiJmb28iOw.pa7EFOZK0OkBpqpaS_P2Qo1Zccw;');
        $cookies = CookieHandler::parse($headers, 'my-key');
        $this->assertEquals('bar', $cookies['foo']);
    }
}