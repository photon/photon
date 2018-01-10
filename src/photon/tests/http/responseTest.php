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

use \photon\test\TestCase;
use \photon\config\Container as Conf;
use \photon\http\response;
use \photon\http;
use \photon\mongrel2;
use \photon\tests\mongrel2Test\DummyZMQSocket;


class ResponseTest extends TestCase
{
    public function testJsonResponse()
    {
        $obj = array('foo', 'bar');
        $json = new response\Json($obj);
        $this->assertEquals(json_encode($obj), $json->render());
    }

    public function testNotModified()
    {
        $nm = new response\NotModified('discarded content');
        $this->assertEquals('', $nm->content);
    }

    public function testNotSupported2()
    {
        $request = \photon\test\HTTP::baseRequest('POST','/toto');
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
        $request = \photon\test\HTTP::baseRequest('POST','/toto');
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
        $payload = file_get_contents(__DIR__ . '/../data/example.payload');

        $conn = new mongrel2\Connection('tcp://127.0.0.1:12345', 'tcp://127.0.0.1:12346');
        $conn->pull_socket = new DummyZMQSocket($payload);
        $conn->pub_socket = new DummyZMQSocket();

        $mess = $conn->recv();

        $iter = array('a', 'b');
        $res = new http\Response($iter);
        $res->sendIterable($mess, $conn);
        $res->sendIterable($mess, $conn, false);
    }

    public function testCreatedRequest()
    {
        $res = new response\Created();
        $this->assertSame(201, $res->status_code);
    }

    public function testAcceptedRequest()
    {
        $res = new response\Accepted();
        $this->assertSame(202, $res->status_code);
    }

    public function testNoContentRequest()
    {
        $res = new response\NoContent();
        $this->assertSame(204, $res->status_code);
    }

    public function testMultiStatus()
    {
        $res = new response\MultiStatus('<i love xml/>');
        $this->assertSame(207, $res->status_code);
    }

    public function testBadRequest()
    {
        $res = new response\BadRequest('/');
        $this->assertSame(400, $res->status_code);
    }

    public function testAuthorizationRequired()
    {
        $res = new response\AuthorizationRequired;
        $this->assertSame(401, $res->status_code);
    }

    public function testForbidden()
    {
        $res = new response\Forbidden;
        $this->assertSame(403, $res->status_code);
    }

    public function testNotFound()
    {
        $request = \photon\test\HTTP::baseRequest('GET', '/');
        $res = new response\NotFound($request);
        $this->assertSame(404, $res->status_code);
    }

    public function testNotSupported()
    {
        $request = \photon\test\HTTP::baseRequest('POST', '/');
        $res = new response\NotSupported($request);
        $this->assertSame(405, $res->status_code);
    }

    public function testNotAcceptable()
    {
        $res = new response\NotAcceptable;
        $this->assertSame(406, $res->status_code);
    }

    public function testRequestTimeout()
    {
        $res = new response\RequestTimeout;
        $this->assertSame(408, $res->status_code);
    }

    public function testGone()
    {
        $res = new response\Gone;
        $this->assertSame(410, $res->status_code);
    }

    public function testLengthRequired()
    {
        $res = new response\LengthRequired;
        $this->assertSame(411, $res->status_code);
    }

    public function testPayloadTooLarge()
    {
        $res = new response\PayloadTooLarge;
        $this->assertSame(413, $res->status_code);

        $res = new response\RequestEntityTooLarge;
        $this->assertSame(413, $res->status_code);
    }

    public function testURITooLong()
    {
        $res = new response\URITooLong;
        $this->assertSame(414, $res->status_code);
    }

    public function testUnsupportedMediaType()
    {
        $res = new response\UnsupportedMediaType;
        $this->assertSame(415, $res->status_code);
    }

    public function testExpectationFailed()
    {
        $res = new response\ExpectationFailed;
        $this->assertSame(417, $res->status_code);
    }

    public function testUpgradeRequired()
    {
        $res = new response\UpgradeRequired;
        $this->assertSame(426, $res->status_code);
    }

    public function testInternalServerError()
    {
        $res = new response\InternalServerError('/');
        $this->assertSame(500, $res->status_code);
    }

    public function testServerError()
    {
        $request = \photon\test\HTTP::baseRequest('GET', '/');
        $res = new response\ServerError(new \Exception, $request);
        $this->assertSame(500, $res->status_code);
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
