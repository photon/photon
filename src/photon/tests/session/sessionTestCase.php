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


namespace photon\tests\session\sessionTestCase;

use \photon\test\TestCase;
use \photon\config\Container as Conf;

abstract class SessionHighLevelTestCase extends TestCase
{
    /*
     *  Simple page which do not used session
     */
    public function testEmptySession()
    {
        $req = \photon\test\HTTP::baseRequest();
        $mid = new \photon\session\Middleware();
        $this->assertEquals(false, $mid->process_request($req));

        $res = new \photon\http\Response('Hello!');
        $mid->process_response($req, $res);
        $this->assertEquals(false, isset($res->COOKIE['sid']));
        $this->assertEquals(false, isset($res->headers['Vary']));
    }

    /*
     *  Ensure a unknown session cookie do not throw exception or errors
     */
    public function testUnknownSession()
    {
        $req = \photon\test\HTTP::baseRequest('GET', '/', '', '', array(), array('cookie' => 'sid=42'));
        $mid = new \photon\session\Middleware();
        $this->assertEquals(false, $mid->process_request($req));
        $res = new \photon\http\Response('Hello!');
        $mid->process_response($req, $res);
    }

    public function testSimpleExchange()
    {
        /*
         * Request 1 :  Receive a request without session cookie
         *              Create a session and store a counter into it
         */
        $req = \photon\test\HTTP::baseRequest();
        $mid = new \photon\session\Middleware();
        $this->assertEquals(false, $mid->process_request($req));

        $req->session['cpt'] = 1234;
        $res = new \photon\http\Response('Hello!');
        $mid->process_response($req, $res);
        $this->assertEquals(true, isset($res->COOKIE['sid']));
        $sid = $this->getSessionId($res);
        unset($req);
        unset($res);
        unset($mid);

        /*
         * Request 2 :  Receive a request with the previous session cookie
         *              Access to the counter
         */
        $req = \photon\test\HTTP::baseRequest('GET', '/', '', '', array(), array('cookie' => 'sid=' . $sid));
        $mid = new \photon\session\Middleware();
        $this->assertEquals(false, $mid->process_request($req));
        $cpt = $req->session['cpt'];
        $this->assertEquals(1234, $cpt);
        $res = new \photon\http\Response('Hello!');
        $mid->process_response($req, $res);
        $this->assertEquals(true, isset($res->headers['Vary']));
        unset($req);
        unset($res);
        unset($mid);

        /*
         * Request 3 :  Receive a request with the previous session cookie
         *              Access to the counter and edit it
         */
        $req = \photon\test\HTTP::baseRequest('GET', '/', '', '', array(), array('cookie' => 'sid=' . $sid));
        $mid = new \photon\session\Middleware();
        $this->assertEquals(false, $mid->process_request($req));
        $cpt = $req->session['cpt'];
        $this->assertEquals(1234, $cpt);
        $req->session['cpt'] = 5678;
        $res = new \photon\http\Response('Hello!');
        $mid->process_response($req, $res);
        $this->assertEquals(true, isset($res->headers['Vary']));
        unset($req);
        unset($res);
        unset($mid);

        /*
         * Request 4 :  Receive a request with the previous session cookie
         *              Access to the new value of the counter
         */
        $req = \photon\test\HTTP::baseRequest('GET', '/', '', '', array(), array('cookie' => 'sid=' . $sid));
        $mid = new \photon\session\Middleware();
        $this->assertEquals(false, $mid->process_request($req));
        $cpt = $req->session['cpt'];
        $this->assertEquals(5678, $cpt);
        $res = new \photon\http\Response('Hello!');
        $mid->process_response($req, $res);
        $this->assertEquals(true, isset($res->headers['Vary']));
        unset($req);
        unset($res);
        unset($mid);
    }

    private function getSessionId($res)
    {
        $headers = $res->getHeaders();
        $rc = preg_match('/Set-Cookie: sid=([\w\.\-_]+);/', $headers, $sid);
        $this->assertEquals($rc, 1);
        return $sid[1];   
    }
}

