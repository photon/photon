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

use \photon\test\TestCase;
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

class MiddlewareTranslationTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        // Dummy view to test the middleware
        Conf::set('urls', array(
            array(
                'regex' => '#^/$#',
                'view' => array(__NAMESPACE__ . '\DummyViews', 'simple'),
            ),
        ));

        // Register the middleware to be tested
        Conf::set('middleware_classes', array(
            '\photon\middleware\Translation'
        ));

        // Register the languages availables in the application
        Conf::set('languages', array('fr', 'en', 'dk'));
    }

    public function tearDown()
    {
        parent::tearDown();
    }

    /*
     *  A website with no session backend
     *  A new user with no cookie and no accept-language
     *  He must get the page content in the default language and get a cookie with this language
     */
    public function testDefaultLanguage()
    {
        $req = HTTP::baseRequest('GET', '/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals(200, $resp->status_code);
        $this->assertEquals($req->i18n_code, 'fr');
        $this->assertArrayHasKey('Vary', $resp->headers);
        $this->assertArrayHasKey('Content-Language', $resp->headers);
        $this->assertArrayHasKey('lang', $resp->COOKIE);
        $this->assertEquals('fr', $resp->COOKIE['lang']);
    }

    /*
     *  A website with no session backend
     *  A new user with no cookie but with accept-language
     *  He must get the page content in the requested language and get a cookie with this language
     */
    public function testLanguageFromHttpHeaders()
    {
        $req = HTTP::baseRequest('GET', '/', '', '', array(), array('accept-language' => 'dk'));
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals(200, $resp->status_code);
        $this->assertEquals($req->i18n_code, 'dk');
        $this->assertArrayHasKey('Vary', $resp->headers);
        $this->assertArrayHasKey('Content-Language', $resp->headers);
        $this->assertArrayHasKey('lang', $resp->COOKIE);
        $this->assertEquals('dk', $resp->COOKIE['lang']);
    }

    // FFS : Add tests with sessions
}

class MiddlewareSecurityTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

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
        parent::tearDown();
        \photon\middleware\Security::clearConfig();
    }

    // X-Content-Type-Options
    public function testContentTypeOptions()
    {
        // Enable (default)
        \photon\middleware\Security::clearConfig();
        $req = HTTP::baseRequest('GET', '/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertArrayHasKey('X-Content-Type-Options', $resp->headers);
        $this->assertEquals('nosniff', $resp->headers['X-Content-Type-Options']);

        // Disable
        \photon\middleware\Security::clearConfig();
        Conf::set('middleware_security', array(
            'nosniff' => false,
        ));

        $req = HTTP::baseRequest('GET', '/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertArrayNotHasKey('X-Content-Type-Options', $resp->headers);
    }

    // X-Frame-Options
    public function testFrameOptions()
    {
        // SAMEORIGIN (default)
        \photon\middleware\Security::clearConfig();
        $req = HTTP::baseRequest('GET', '/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertArrayHasKey('X-Frame-Options', $resp->headers);
        $this->assertEquals('SAMEORIGIN', $resp->headers['X-Frame-Options']);

        // DENY
        \photon\middleware\Security::clearConfig();
        Conf::set('middleware_security', array(
            'frame' => 'DENY',
        ));
        $req = HTTP::baseRequest('GET', '/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertArrayHasKey('X-Frame-Options', $resp->headers);
        $this->assertEquals('DENY', $resp->headers['X-Frame-Options']);

        // Disabled
        \photon\middleware\Security::clearConfig();
        Conf::set('middleware_security', array(
            'frame' => false,
        ));
        $req = HTTP::baseRequest('GET', '/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertArrayNotHasKey('X-Frame-Options', $resp->headers);
    }

    // X-XSS-Protection
    public function testXSSProtection()
    {
        // 1 (default)
        \photon\middleware\Security::clearConfig();
        $req = HTTP::baseRequest('GET', '/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertArrayHasKey('X-XSS-Protection', $resp->headers);
        $this->assertEquals('1', $resp->headers['X-XSS-Protection']);

        // 1; mode=block
        \photon\middleware\Security::clearConfig();
        Conf::set('middleware_security', array(
            'xss' => '1; mode=block',
        ));
        $req = HTTP::baseRequest('GET', '/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertArrayHasKey('X-XSS-Protection', $resp->headers);
        $this->assertEquals('1; mode=block', $resp->headers['X-XSS-Protection']);

        // Disabled
        \photon\middleware\Security::clearConfig();
        Conf::set('middleware_security', array(
            'xss' => false,
        ));
        $req = HTTP::baseRequest('GET', '/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertArrayNotHasKey('X-XSS-Protection', $resp->headers);
    }

    // CSP
    public function testCSP()
    {
      // Not set (default)
      \photon\middleware\Security::clearConfig();
      $req = HTTP::baseRequest('GET', '/');
      $dispatcher = new \photon\core\Dispatcher;
      list($req, $resp) = $dispatcher->dispatch($req);
      $this->assertArrayNotHasKey('Content-Security-Policy', $resp->headers);

      // Custom value
      $csp = "default-src 'self'";
      \photon\middleware\Security::clearConfig();
      Conf::set('middleware_security', array(
          'csp' => $csp,
      ));
      $req = HTTP::baseRequest('GET', '/');
      $dispatcher = new \photon\core\Dispatcher;
      list($req, $resp) = $dispatcher->dispatch($req);
      $this->assertArrayHasKey('Content-Security-Policy', $resp->headers);
      $this->assertEquals($csp, $resp->headers['Content-Security-Policy']);
    }

    // SSL Redirection disable (default)
    public function testSSLRedirect_defaultConfig()
    {
        $req = HTTP::baseRequest('GET', '/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals(200, $resp->status_code);
    }

    // SSL Redirection enable (manually)
    public function testSSLRedirect_enable()
    {
        Conf::set('middleware_security', array(
            'ssl_redirect' => true,
        ));

        $req = HTTP::baseRequest('GET', '/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals(302, $resp->status_code);
    }

    // SSL Redirection enable (manually)
    public function testSSLRedirectToSamePath()
    {
        Conf::set('middleware_security', array(
            'ssl_redirect' => true,
        ));

        $req = HTTP::baseRequest('GET', '/foo/bar/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals(302, $resp->status_code);
        $this->assertEquals('https://test.example.com/foo/bar/', $resp->headers['Location']);
    }

    // HTTP Strict Transport Security disable (default)
    public function testHSTS_defaultConfig()
    {
        $req = HTTP::baseRequest('GET', '/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
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
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals(302, $resp->status_code);
        $this->assertArrayHasKey('Strict-Transport-Security', $resp->headers);
    }

    // HTTP Public Key Pinning disable (default)
    public function testHPKP_defaultConfig()
    {
        $req = HTTP::baseRequest('GET', '/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
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
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals(200, $resp->status_code);
        $this->assertArrayHasKey('Public-Key-Pins', $resp->headers);
    }
}
