<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, High Performance PHP Framework.
# Copyright (C) 2010, 2011 Loic d'Anterroches and contributors.
#
# Photon is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation in version 2.1.
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


namespace photon\tests\middlewareTest;

use \photon\config\Container as Conf;
use \photon\core\Dispatcher;
use \photon\test\HTTP;

class DummyViews
{
    function simple($req, $match)
    {
        return new \photon\http\Response('SIMPLE', 'text/plain; charset=utf-8');
    }
}

class MiddlewareTest extends \PHPUnit_Framework_TestCase
{
    protected $conf;

    public function setUp()
    {
        $this->conf = Conf::dump();

        // Dummy view to test the middleware
        Conf::set('urls', array(
            array(
                'regex' => '#^/$#',
                'view' => array(__NAMESPACE__ . '\DummyViews', 'simple'),
            ),
        ));

        // Register the middleware to be tested
        Conf::set('middleware_classes', array(
            '\photon\middleware\Security'
        ));
    }

    public function tearDown()
    {
        Conf::load($this->conf);
        \photon\middleware\Security::clearConfig();
    }

    // SSL Redirection disable (default)
    public function testSSLRedirect_defaultConfig()
    {
        $req = HTTP::baseRequest('GET', '/');
        list($req, $resp) = Dispatcher::dispatch($req);
        $this->assertEquals(200, $resp->status_code);
    }

    // SSL Redirection enable (manually)
    public function testSSLRedirect_enable()
    {
        Conf::set('middleware_security', array(
            'ssl_redirect' => true,
        ));

        $req = HTTP::baseRequest('GET', '/');
        list($req, $resp) = Dispatcher::dispatch($req);
        $this->assertEquals(302, $resp->status_code);
    }

    // HTTP Strict Transport Security disable (default)
    public function testHSTS_defaultConfig()
    {
        $req = HTTP::baseRequest('GET', '/');
        list($req, $resp) = Dispatcher::dispatch($req);
        $this->assertEquals(200, $resp->status_code);
        $this->assertArrayNotHasKey('Strict-Transport-Security', $resp->headers);
    }

    // HTTP Strict Transport Security enable (manually)
    public function testHSTS_enable()
    {
        Conf::set('middleware_security', array(
            'hsts' => true,
        ));

        $req = HTTP::baseRequest('GET', '/');
        list($req, $resp) = Dispatcher::dispatch($req);
        $this->assertEquals(200, $resp->status_code);
        $this->assertArrayHasKey('Strict-Transport-Security', $resp->headers);
    }

    // HTTP Public Key Pinning disable (default)
    public function testHPKP_defaultConfig()
    {
        $req = HTTP::baseRequest('GET', '/');
        list($req, $resp) = Dispatcher::dispatch($req);
        $this->assertEquals(200, $resp->status_code);
        $this->assertArrayNotHasKey('Public-Key-Pins', $resp->headers);
    }

    // HTTP Public Key Pinning enable (manually)
    public function testHPKP_enable()
    {
        Conf::set('middleware_security', array(
            'hpkp' => true,
            'hpkp_options' => array(
                'pin-sha256' => array(
                    "MASTER_KEY_IN_BASE64",
                    "BACKUP_KEY_IN_BASE64"
                ),
            ),
        ));

        $req = HTTP::baseRequest('GET', '/');
        list($req, $resp) = Dispatcher::dispatch($req);
        $this->assertEquals(200, $resp->status_code);
        $this->assertArrayHasKey('Public-Key-Pins', $resp->headers);
    }
}
