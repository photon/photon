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

use \photon\config\Container as Conf;
use \photon\http\Response as Response;
use \photon\http\Request as Request;

include_once __DIR__ . '/../../http/response.php';

class baseTest extends \PHPUnit_Framework_TestCase
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
        $mess = (object) array('headers' => (object) array('QUERY' => 'a=b&c=d',
                                                           'METHOD' => 'GET'),
                               'sender' => 'mongrel2',
                               'conn_id' => '2',
                               'path' => '/home');
        $req = new Request($mess);
        $this->assertEquals($req->path, '/home');
    }

    public function testSimplePost()
    {
        $fp = fopen('php://temp/maxmemory:5242880', 'r+');
        fputs($fp, 'a=b&c=d');
        rewind($fp);
        
        $mess = (object) array('headers' => (object) array('QUERY' => 'a=b&c=d',
                                                           'content-type' => 'application/x-www-form-urlencoded',
                                                           'METHOD' => 'POST'),
                               'path' => '/home',
                               'sender' => 'mongrel2',
                               'conn_id' => '2',
                               'body' => $fp);
        $req = new Request($mess);
        $this->assertEquals($req->path, '/home');
        $this->assertEquals(array('a' => 'b', 'c' => 'd'), $req->POST);
        fclose($fp);
    }

    public function testComplexPost()
    {
        $datafile = fopen(__DIR__ . '/../data/multi_video.upload', 'rb');
        $mess = (object) array('headers' => (object) array('QUERY' => 'a=b&c=d',
                                                           'content-type' => 'multipart/form-data; boundary=---------------------------10102754414578508781458777923',
                                                           'METHOD' => 'POST'),
                               'path' => '/home',
                               'sender' => 'mongrel2',
                               'conn_id' => '2',
                               'body' => $datafile);
        $req = new Request($mess);
        $this->assertEquals($req->path, '/home');
        $this->assertEquals(array('title' => ''), $req->POST);
        fclose($datafile);
    }

    public function testForbidden()
    {
        $resp = new \photon\http\response\Forbidden('##content##');
        $out = $resp->render();
        $this->assertEquals(0, strpos($out, 'HTTP/1.1 403 FORBIDDEN'));
    }

    public function testServerError()
    {
        $mess = (object) array('headers' => (object) array('QUERY' => 'a=b&c=d',
                                                           'METHOD' => 'GET'),
                               'path' => '/home',
                               'sender' => 'mongrel2',
                               'body' => '',
                               'conn_id' => '1234');

        $req = new Request($mess);
        try {
            throw new \Exception('Bad exception', 123);
        } catch (\Exception $e) {
        }
        $res = \photon\http\response\pretty_server_error($e, $req);
    }

    public function testServerErrorWithTemplate()
    {
        $mess = (object) array('headers' => (object) array('QUERY' => 'a=b&c=d',
                                                           'METHOD' => 'GET'),
                               'path' => '/home',
                               'sender' => 'mongrel2',
                               'body' => '',
                               'conn_id' => '1234');

        $req = new Request($mess);
        try {
            throw new \Exception('Bad exception', 123);
        } catch (\Exception $e) {
        }
        Conf::set('template_folders', array(__DIR__));
        $res = new \photon\http\response\ServerError($e);
        $this->assertEquals('Server Error!'."\n", $res->content);
    }

}