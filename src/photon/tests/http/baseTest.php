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


namespace photon\tests\http\baseTest;

use \photon\test\TestCase;
use \photon\config\Container as Conf;
use \photon\http\Response;
use \photon\test\HTTP as TestRequest;

class baseTest extends TestCase
{
    public function testSimpleRender()
    {
        $resp = new Response('##content##');
        $this->assertEquals('##content##', $resp->content);

        $out = $resp->render();
        $this->assertEquals(0, strpos($out, 'HTTP/1.1 200 OK'));
        $this->assertNotEquals(false, strpos($out, '##content##'));

        $out = $resp->render(false); // no body, good for HEAD requests
        $this->assertEquals(0, strpos($out, 'HTTP/1.1 200 OK'));
        $this->assertEquals(false, strpos($out, '##content##'));
    }

    public function testSimpleRequest()
    {
        $req = TestRequest::baseRequest('GET', '/home');
        $this->assertEquals($req->path, '/home');
    }

    public function testSimplePost()
    {
        $fp = fopen('php://temp/maxmemory:5242880', 'r+');
        fputs($fp, 'a=b&c=d,');
        rewind($fp);
        
        $req = TestRequest::baseRequest(
            'POST',
            '/home',
            null,
            $fp,
            array(),
            array(
                'content-type' => 'application/x-www-form-urlencoded'
            )
        );

        $this->assertEquals($req->path, '/home');
        $this->assertEquals($req->getHeader('content-type'), 'application/x-www-form-urlencoded');
        $this->assertEquals($req->getHeader('foo-bar'), '');
        $this->assertEquals(array('a' => 'b', 'c' => 'd'), $req->POST);

        fclose($fp);
    }

    public function testComplexPost()
    {
        $datafile = fopen(__DIR__ . '/../data/multi_video.upload', 'rb');

        $req = TestRequest::baseRequest(
            'POST',
            '/home',
            null,
            $datafile,
            array(),
            array(
                'content-type' => 'multipart/form-data; boundary=---------------------------10102754414578508781458777923'
            )
        );

        $this->assertEquals($req->path, '/home');
        $this->assertEquals(1, count($req->POST));
        $this->assertEquals(2, count($req->FILES['upload']));

        fclose($datafile);
    }

    public function testBadPost()
    {
        $datafile = fopen(__DIR__ . '/../data/small.upload', 'rb');

        $req = TestRequest::baseRequest(
            'POST',
            '/home',
            'a=b&c=d',
            $datafile,
            array(),
            array(
                'content-type' => 'foobar/form-data; boundary=---------------------------10102754414578508781458777923'
            )
        );

        $this->assertEquals($req->path, '/home');
        $this->assertEquals(0, count($req->POST));
        $this->assertEquals(true, is_resource($req->BODY));
        fclose($datafile);
    }

    public function testForbidden()
    {
        $resp = new \photon\http\response\Forbidden('##content##');
        $out = $resp->render();
        $this->assertEquals(0, strpos($out, 'HTTP/1.1 403 Forbidden'));
    }

    public function testRedirect()
    {
        $url  = 'http://photon-project.com';

        $resp = new \photon\http\response\Redirect($url);
        $out  = $resp->render();
        $this->assertStringStartsWith("HTTP/1.1 302 Found\r\n", $out);
        $this->assertContains("Location: http://photon-project.com\r\n", $out);

        $resp = new \photon\http\response\Redirect($url, 301);
        $out  = $resp->render();
        $this->assertStringStartsWith("HTTP/1.1 301 Moved Permanently\r\n", $out);
    }

    public function testServerError()
    {
        $req = TestRequest::baseRequest('GET', '/home');

        $e = new \Exception('Bad exception', 123);
        $res = \photon\http\response\pretty_server_error($e, $req);
    }

    public function testServerErrorWithTemplate()
    {
        Conf::set('template_folders', array(__DIR__));

        $req = TestRequest::baseRequest(
            'GET',
            '/home',
            'a=b&c=d'
        );

        $e = new \Exception('Bad exception', 123);
        $res = new \photon\http\response\ServerError($e, $req);
        $this->assertEquals("Server Error!\n", $res->content);
    }
}
