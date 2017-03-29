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

use \photon\test\TestCase;
use \photon\config\Container as Conf;
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

function return_true() 
{ 
    return true; 
}

function return_caught() 
{
    return new \photon\http\Response('CAUGHT', 'text/plain; charset=utf-8');
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

    public $withPreconditions_precond = array('\photon\tests\small\coredispatchTest\return_true');
    function withPreconditions($req, $match)
    {
        return new \photon\http\Response('WITHPRE:OK',
                                         'text/plain; charset=utf-8');
    }

    public $withAnswerPreconditions_precond = array('\photon\tests\small\coredispatchTest\return_caught');
    function withAnswerPreconditions($req, $match)
    {
        return new \photon\http\Response('WITHPRE:OK',
                                         'text/plain; charset=utf-8');
    }
}

class coreurlTest extends TestCase
{
    protected $conf;

    public function testSimpleDispatch()
    {
        Conf::set('urls', array(
            array('regex' => '#^/home/(.+)/$#',
                  'view' => function () { return false; },
                  'name' => 'home',
                  ),
            )
        );

        $req = \photon\test\HTTP::baseRequest('GET', '/home/foo/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals(false, $resp);
    }

    public function testSimpleDispatchView()
    {
        Conf::set('urls', array(
            array('regex' => '#^/home/(.+)/$#',
                  'view' => array('\photon\tests\small\coredispatchTest\DummyViews', 'simple'),
                  'name' => 'home',
                  ),
            )
        );

        $req = \photon\test\HTTP::baseRequest('GET', '/home/foo/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
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
            )
        );

        $req = \photon\test\HTTP::baseRequest('GET', '/home/foo/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals('WITHPARAMS:OK', $resp->content);
    }

    public function testSimpleDispatchViewPreconditions()
    {
        Conf::set('urls', array(
            array('regex' => '#^/home/(.+)/$#',
                  'view' => array('\photon\tests\small\coredispatchTest\DummyViews', 'withPreconditions'),
                  'name' => 'home',
                  ),
            )
        );

        $req = \photon\test\HTTP::baseRequest('GET', '/home/foo/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals('WITHPRE:OK', $resp->content);
    }

    public function testSimpleDispatchViewAnswerPreconditions()
    {
        Conf::set('urls', array(
            array('regex' => '#^/home/(.+)/$#',
                  'view' => array('\photon\tests\small\coredispatchTest\DummyViews', 'withAnswerPreconditions'),
                  'name' => 'home',
                  ),
            )
        );

        $req = \photon\test\HTTP::baseRequest('GET', '/home/foo/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals('CAUGHT', $resp->content);
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

        $req = \photon\test\HTTP::baseRequest('GET', '/home/foo/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals('WITHPARAMS:OK', $resp->content);
    }




    public function testSimpleDispatchMiddleware()
    {
        Conf::set('urls', array(
            array('regex' => '#^/home/(.+)/$#',
                  'view' => function () { return false; },
                  'name' => 'home',
                  ),
            )
        );

        Conf::set('middleware_classes', array('\photon\tests\small\coredispatchTest\DummyMiddleware'));

        $req = \photon\test\HTTP::baseRequest('GET', '/home/foo/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals(false, $resp);
    }

    public function testSimpleDispatchMiddlewarePreempt()
    {
        Conf::set('urls', array(
            array('regex' => '#^/home/(.+)/$#',
                  'view' => function () { return false; },
                  'name' => 'home',
                  ),
            )
        );

        Conf::set('middleware_classes', array('photon\tests\small\coredispatchTest\DummyMiddlewarePreempt'));

        $req = \photon\test\HTTP::baseRequest('GET', '/home/foo/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals('OK', $resp->content);
    }

    public function testViewFailure()
    {
        // Part 1
        Conf::set('urls', array(
            array('regex' => '#^/home/(.+)/$#',
                  'view' => function () { throw new \Exception(); },
                  'name' => 'home',
                  ),
            )
        );

        $req = \photon\test\HTTP::baseRequest('GET', '/home/foo/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertNotEquals(false, strpos($resp->content, 'coredispatchTest'));

        // Part 2
        Conf::set('debug', false);
        Conf::set('template_force_compilation', true);
        Conf::set('template_folders', array(dirname(__FILE__)));

        $req = \photon\test\HTTP::baseRequest('GET', '/home/foo/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        // Ensure the answer contains a string in the 500.html template
        $this->assertTrue(false !== strpos($resp->content, 'Server Error!'));
    }

    public function testViewNotFound()
    {
        Conf::set('urls', array(
            array('regex' => '#^/home/(.+)/$#',
                  'view' => function () { throw new \Exception(); },
                  'name' => 'home',
                  ),
            )
        );

        $req = \photon\test\HTTP::baseRequest('GET', '/home/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
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

        $req = \photon\test\HTTP::baseRequest('GET', '/hello/home/foo/');
        $dispatcher = new \photon\core\Dispatcher;
        list($req, $resp) = $dispatcher->dispatch($req);
        $this->assertEquals(false, $resp);
    }
}
