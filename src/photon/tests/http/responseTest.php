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


namespace photon\tests\http\responseTest;

use \photon\config\Container as Conf;
use \photon\http\response;
use \photon\http;
use \photon\mongrel2;
use \photon\tests\mongrel2\mongrel2Test\DummyZMQSocket;


class ResponseTest extends \PHPUnit_Framework_TestCase
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

    public function testJsonResponse()
    {
        $json = new response\Json(array('foo', 'bar'));
        $this->assertEquals(json_encode(array('foo', 'bar')),
                            $json->render());
    }

    public function testNotModified()
    {
        $nm = new response\NotModified('discarded content');
        $this->assertEquals('', $nm->content);
    }

    public function testNotSupported()
    {
        $request = (object) array('method' => 'POST', 'path' => '/toto');
        $res = new response\NotSupported($request);
        $this->assertSame(0, strpos($res->content, 'HTTP method POST is not supported for the URL /toto.'));
    }

    public function testFormRedirect()
    {
        $res = new response\FormRedirect('/');
        $this->assertSame(303, $res->status_code);
    }

    public function testRedirectToLogin()
    {
        $request = (object) array('method' => 'POST', 'path' => '/toto');
        $res = new response\RedirectToLogin($request, '/login');
        $this->assertSame(302, $res->status_code);
        Conf::set('urls', array(
                                array('regex' => '#^/login$#',
                                      'view' => array('Dummy', 'dummy'),
                                      'name' => 'login_view',
                                      ),
                                ));
        $res = new response\RedirectToLogin($request);
        $this->assertSame(302, $res->status_code);
    }

    public function testSendIterable()
    {
        $iter = array('a', 'b');
        $socket = new DummyZMQSocket();
        $socket->setNextRecv(file_get_contents(__DIR__ . '/../data/example.payload'));
        $conn = new mongrel2\Connection($socket, $socket);
        $mess = $conn->recv();
        
        $res = new http\Response($iter);
        $res->sendIterable($mess, $conn);
        $res->sendIterable($mess, $conn, false);
    }
    
    public function testBadRequest()
    {
        $res = new response\BadRequest('/');
        $this->assertSame(400, $res->status_code);
    }

    public function testNotImplemented()
    {
        $res = new response\NotImplemented('/');
        $this->assertSame(501, $res->status_code);
    }
    
    public function testServiceUnavailable()
    {
        $res = new response\ServiceUnavailable('/');
        $this->assertSame(503, $res->status_code);
    }
}
