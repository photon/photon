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


namespace photon\tests\small\coredispatchTest;

use \photon\config\Container as Conf;
use \photon\core\Dispatcher as Dispatcher;
use \photon\mongrel2\Message as Message;

class DummyMiddleware
{
    function process_request($req)    
    {
        return false;
    }

    function process_response($req, $resp)    
    {
        return $resp;
    }
}

class DummyMiddlewarePreempt
{
    function process_request($req)    
    {
        return new \photon\http\Response('OK', 'text/plain; charset=utf-8');
    }
}

class DummyViews
{
    function simple($req, $match)
    {
        return new \photon\http\Response('SIMPLE', 'text/plain; charset=utf-8');
    }

    function withParams($req, $match, $params)
    {
        return new \photon\http\Response('WITHPARAMS:' . $params, 
                                         'text/plain; charset=utf-8');
    }
}

class coreurlTest extends \PHPUnit_Framework_TestCase
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

    public function testSimpleDispatch()
    {
        Conf::set('urls', array(
                                array('regex' => '#^/home/(.+)/$#',
                                      'view' => function () { return false; },
                                      'name' => 'home',
                                      ),
                                ));
        $headers = (object) array('METHOD' => 'GET');
        $msg = new Message('dummy', 'dummy', '/home/foo/', $headers, '');
        list($req, $resp) = Dispatcher::dispatch($msg);
        $this->assertEquals(false, $resp);
    }

    public function testSimpleDispatchView()
    {
        Conf::set('urls', array(
                                array('regex' => '#^/home/(.+)/$#',
                                      'view' => array('\photon\tests\small\coredispatchTest\DummyViews', 'simple'),
                                      'name' => 'home',
                                      ),
                                ));
        $headers = (object) array('METHOD' => 'GET');
        $msg = new Message('dummy', 'dummy', '/home/foo/', $headers, '');
        list($req, $resp) = Dispatcher::dispatch($msg);
        $this->assertEquals('SIMPLE', $resp->content);
    }

    public function testSimpleDispatchViewParams()
    {
        Conf::set('urls', array(
                                array('regex' => '#^/home/(.+)/$#',
                                      'view' => array('\photon\tests\small\coredispatchTest\DummyViews', 'withParams'),
                                      'name' => 'home',
                                      'params' => 'OK',
                                      ),
                                ));
        $headers = (object) array('METHOD' => 'GET');
        $msg = new Message('dummy', 'dummy', '/home/foo/', $headers, '');
        list($req, $resp) = Dispatcher::dispatch($msg);
        $this->assertEquals('WITHPARAMS:OK', $resp->content);
    }

    public function testSimpleDispatchFuncParams()
    {
        Conf::set('urls', array(
                                array('regex' => '#^/home/(.+)/$#',
                                      'view' => function ($req, $match, $params)
    {
        return new \photon\http\Response('WITHPARAMS:' . $params, 
                                         'text/plain; charset=utf-8');
    },

                                      'name' => 'home',
                                      'params' => 'OK',
                                      ),
                                ));
        $headers = (object) array('METHOD' => 'GET');
        $msg = new Message('dummy', 'dummy', '/home/foo/', $headers, '');
        list($req, $resp) = Dispatcher::dispatch($msg);
        $this->assertEquals('WITHPARAMS:OK', $resp->content);
    }




    public function testSimpleDispatchMiddleware()
    {
        Conf::set('urls', array(
                                array('regex' => '#^/home/(.+)/$#',
                                      'view' => function () { return false; },
                                      'name' => 'home',
                                      ),
                                ));
        Conf::set('middleware_classes', 
                  array('\photon\tests\small\coredispatchTest\DummyMiddleware'));
        $headers = (object) array('METHOD' => 'GET');
        $msg = new Message('dummy', 'dummy', '/home/foo/', $headers, '');
        list($req, $resp) = Dispatcher::dispatch($msg);
        $this->assertEquals(false, $resp);
    }

    public function testSimpleDispatchMiddlewarePreempt()
    {
        Conf::set('urls', array(
                                array('regex' => '#^/home/(.+)/$#',
                                      'view' => function () { return false; },
                                      'name' => 'home',
                                      ),
                                ));
        Conf::set('middleware_classes', 
                  array('photon\tests\small\coredispatchTest\DummyMiddlewarePreempt'));
        $headers = (object) array('METHOD' => 'GET');
        $msg = new Message('dummy', 'dummy', '/home/foo/', $headers, '');
        list($req, $resp) = Dispatcher::dispatch($msg);
        $this->assertEquals('OK', $resp->content);
    }

    public function testViewFailure()
    {
        Conf::set('urls', array(
                                array('regex' => '#^/home/(.+)/$#',
                                      'view' => function () { throw new \Exception(); },
                                      'name' => 'home',
                                      ),
                                ));
        $headers = (object) array('METHOD' => 'GET');
        $msg = new Message('dummy', 'dummy', '/home/foo/', $headers, '');
        $req = new \photon\http\Request($msg);
        list($req, $resp) = Dispatcher::dispatch($req);
        $this->assertNotEquals(false, strpos($resp->content, 'coredispatchTest'));
        Conf::set('debug', false);
        list($req, $resp) = Dispatcher::dispatch($msg);
        $this->assertNotEquals(false, strpos($resp->content, 'we will correct'));

    }

    public function testViewNotFound()
    {
        Conf::set('urls', array(
                                array('regex' => '#^/home/(.+)/$#',
                                      'view' => function () { throw new \Exception(); },
                                      'name' => 'home',
                                      ),
                                ));
        $headers = (object) array('METHOD' => 'GET');
        $msg = new Message('dummy', 'dummy', '/home/', $headers, '');
        list($req, $resp) = Dispatcher::dispatch($msg);
        $this->assertNotEquals(false, strpos($resp->content, 'not found'));
    }

    public function testViewFoundInSub()
    {
        $views = array(array('regex' => '#^/hello#',
                             'sub' => array(
                                            array('regex' => '#^/home/(.+)/$#',
                                                  'view' => function () { return false; },
                                                  'name' => 'home',
                                                  ),
                                            )),
                       array('regex' => '#^/foo/(.+)/$#',
                             'view' => function () { throw new \Exception(); },
                             'name' => 'foo_bar',
                             ),
                       );

        Conf::set('urls', $views);

        $headers = (object) array('METHOD' => 'GET');
        $msg = new Message('dummy', 'dummy', '/hello/home/foo/', $headers, '');
        list($req, $resp) = Dispatcher::dispatch($msg);
        $this->assertEquals(false, $resp);
    }





}