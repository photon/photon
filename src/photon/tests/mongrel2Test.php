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


namespace photon\tests\mongrel2Test;

use \photon\test\TestCase;
use \photon\mongrel2;

/*
 * DummyZMQSocket is used to mock the connect of mongrel2/Connection
 */
class DummyZMQSocket
{
    public $payload = false;
    public $maxsend = null;

    public function __construct($payload=null)
    {
        if ($payload !== null) {
            $this->setNextRecv($payload);
        }
    }

    public function setNextRecv($payload)
    {
        $this->payload = $payload;
    }

    public function recv()
    {
        $payload = $this->payload;
        $this->payload = false;
        return $payload;
    }

    public function send($payload)
    {
        if ($this->maxsend === null) {
            return true;
        }
        if ($this->maxsend > 0) {
            $this->maxsend--;
            return true;
        }
        return false;
    }
}

class mongrel2Test extends TestCase
{
    public function testMessage()
    {
        $datafile = fopen(__DIR__ . '/data/example.payload', 'rb');
        $front = strlen('34f9ceee-cd52-4b7f-b197-88bf2f0ec378 6 /handlertest/foo 422:{"PATH":"/handlertest/foo","user-agent":"curl/7.19.7 (i486-pc-linux-gnu) libcurl/7.19.7 OpenSSL/0.9.8k zlib/1.2.3.3 libidn/1.15","host":"localhost:6767","accept":"*/*","content-type":"multipart/form-data; boundary=----------------------------b9069e918c9e","x-forwarded-for":"::1","content-length":"21894","METHOD":"POST","VERSION":"HTTP/1.1","URI":"/handlertest/foo?toto=titi","QUERY":"toto=titi","PATTERN":"/handlertest"},21894:');
        fseek($datafile, $front, SEEK_CUR);
        $mess = new mongrel2\Message('34f9ceee-cd52-4b7f-b197-88bf2f0ec378',
                                     '6', '/handlertest/foo', (object) array(),
                                     $datafile);
        unset($mess);
        $this->assertEquals(false, is_resource($datafile));
    }

    public function testConnectionRecv()
    {
        $payload = file_get_contents(__DIR__ . '/data/example.payload');

        $conn = new mongrel2\Connection('tcp://127.0.0.1:12345');
        $conn->pull_socket = new DummyZMQSocket($payload);

        $mess = $conn->recv();
        $this->assertEquals($mess->path, '/handlertest/foo');
    }

    public function testConnectionRecvGet()
    {
        $payload = '34f9ceee-cd52-4b7f-b197-88bf2f0ec378 6 /handlertest/foo 421:{"PATH":"/handlertest/foo","user-agent":"curl/7.19.7 (i486-pc-linux-gnu) libcurl/7.19.7 OpenSSL/0.9.8k zlib/1.2.3.3 libidn/1.15","host":"localhost:6767","accept":"*/*","content-type":"multipart/form-data; boundary=----------------------------b9069e918c9e","x-forwarded-for":"::1","content-length":"21894","METHOD":"GET","VERSION":"HTTP/1.1","URI":"/handlertest/foo?toto=titi","QUERY":"toto=titi","PATTERN":"/handlertest"},0:';

        $conn = new mongrel2\Connection('tcp://127.0.0.1:12345');
        $conn->pull_socket = new DummyZMQSocket($payload);

        $mess = $conn->recv();
        $this->assertEquals($mess->path, '/handlertest/foo');
    }

    public function testConnectionRecvJson()
    {
        $payload = '06a11cf0-1eb6-11e3-8224-0800200c9a66 4 /api/1.0/project/ 640:{"PATH":"/api/1.0/project/","x-forwarded-for":"127.0.0.1","content-type":"application/json","accept-language":"en-US,en;q=0.5","connection":"keep-alive","accept-encoding":"gzip, deflate","content-length":"12","accept":"text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8","user-agent":"Mozilla/5.0 (X11; Linux x86_64; rv:50.0) Gecko/20100101 Firefox/50.0","host":"127.0.0.1","cookie":"rock_format=json; sid=czo0MDoiZDEyZWJlY2ZiZmEwYzIwMzUyNGI4ZTc0MWI5NzhjOTdlNTg0M2ZhMyI7.JDFmXQObWzt0EpTmNkNUvWHWfns","METHOD":"POST","VERSION":"HTTP/1.1","URI":"/api/1.0/project/","PATTERN":"/api/","URL_SCHEME":"http","REMOTE_ADDR":"127.0.0.1"},12:{"test": 42},';

        $conn = new mongrel2\Connection('tcp://127.0.0.1:12345');
        $conn->pull_socket = new DummyZMQSocket($payload);

        $mess = $conn->recv();
        $this->assertEquals($mess->path, '/api/1.0/project/');

        $req = new \photon\http\Request($mess);
        $this->assertEquals($req->getHeader('content-type', null), 'application/json');
        $this->assertEquals($req->JSON->test, 42);
    }

    /**
     * Artificial big headers in a message to test the parser.
     */
    public function testBigHeaders()
    {
        $headers = array('METHOD' => 'POST');
        $headers = json_encode($headers);

        $body = array('a' => 1);
        $body = json_encode($body);

        $payload = sprintf('34f9ceee-cd52-4b7f-b197-88bf2f0ec378 6 /handlertest/foo %d:%s,%d:%s,',  strlen($headers), $headers, strlen($body), $body);

        $conn = new mongrel2\Connection('tcp://127.0.0.1:12345');
        $conn->pull_socket = new DummyZMQSocket($payload);

        $mess = $conn->recv();
        $this->assertEquals($mess->path, '/handlertest/foo');
    }

    public function testConnectionSend()
    {
        $req = \photon\test\HTTP::baseRequest();

        $conn = new mongrel2\Connection('tcp://127.0.0.1:12345', 'tcp://127.0.0.1:12346');
        $conn->pull_socket = new DummyZMQSocket();
        $conn->pub_socket = new DummyZMQSocket();

        $rc = $conn->reply($req->mess, 'Hello !');
        $this->assertEquals($rc, true);
    }

    public function testConnectionDeliver()
    {
        $req = \photon\test\HTTP::baseRequest();

        $conn = new mongrel2\Connection('tcp://127.0.0.1:12345', 'tcp://127.0.0.1:12346');
        $conn->pull_socket = new DummyZMQSocket();
        $conn->pub_socket = new DummyZMQSocket();

        $connection_ids = range(1, 300);
        $rc = $conn->deliver('34f9ceee-cd52-4b7f-b197-88bf2f0ec378', $connection_ids, 'Hello !');
        $this->assertEquals($rc, true);

        $conn->pub_socket->maxsend = 2;
        $rc = $conn->deliver('34f9ceee-cd52-4b7f-b197-88bf2f0ec378', $connection_ids, 'Hello !');
        $this->assertEquals($rc, false);
    }
}
