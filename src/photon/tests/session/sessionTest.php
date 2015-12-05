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


namespace photon\tests\session\sessionTest;

use \photon\config\Container as Conf;

class DummyStore
{
    public function __get($prop)
    {
        return $prop;
    }

    public function __set($prop, $val)
    {
        return true;
    }

    public function __call($prop, $val)
    {
        if ($prop == 'commit') {
            return 'session-key';
        }
        if (isset($val[1])) {
            return $val[1];
        }
        return $val[0];
    }
}

class SessionTest extends \PHPUnit_Framework_TestCase
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

    /**
     * Just testing the high level interface.
     */
    public function testSession()
    {
        $session = new \photon\session\Session(new DummyStore);
        $this->assertEquals('foo', $session['foo']);
        $this->assertEquals('bing', $session['bing']);
        $this->assertEquals(true, isset($session['bing']));
        $session['foo'] = 'bar';
        unset($session['foo']);
        $session->init(null, null);
        $session->commit(null);
        $this->setExpectedException('\Exception');
        $session[] = 'bar';
    }

    public function testMiddleware()
    {
        Conf::set('session_storage', '\photon\tests\session\sessionTest\DummyStore');
        $mid = new \photon\session\Middleware();
        $req = \photon\test\HTTP::baseRequest();
        $this->assertEquals(false, $mid->process_request($req));
        $res = new \photon\http\Response('Hello!');
        $mid->process_response($req, $res);
        $this->assertEquals(false, isset($res->COOKIE['sid']));
        $this->assertEquals(false, isset($res->headers['Vary']));
        $req->session->accessed = true;
        $res = new \photon\http\Response('Hello!');
        $mid->process_response($req, $res);
        $this->assertEquals(true, isset($res->headers['Vary']));
        $this->assertEquals(false, isset($res->COOKIE['sid']));
        $req->session->modified = true;
        $req->session->key = 'foo'; // the storage is dummy and generate a bad key
        $res = new \photon\http\Response('Hello!');
        $mid->process_response($req, $res);
        $this->assertEquals(true, isset($res->headers['Vary']));
        $this->assertEquals(true, isset($res->COOKIE['sid']));
        $this->assertEquals('session-key', $res->COOKIE['sid']);
    }

    public function testStorageBase()
    {
        $store = new \photon\session\storage\Base();
        try {
            $store->init(null, null);
        } catch (\Exception $e) { }
        try {
            $store->keyExists('foo');
        } catch (\Exception $e) { }
        try {
            $store->commit(null);
        } catch (\Exception $e) { }
        try {
            $store->load();
        } catch (\Exception $e) { }
        $this->assertEquals(true, true);

    }

    public function testStorageCookie()
    {
        Conf::set('session_storage', '\photon\session\storage\Cookies');
        Conf::set('secret_key', 'dummy'); // used to crypt/sign the cookies
        $req = \photon\test\HTTP::baseRequest();
        $mid = new \photon\session\Middleware();
        $this->assertEquals(false, $mid->process_request($req));
        $this->assertEquals(false, isset($req->session['foo']));
        $req->session['foo'] = 'bar';
        $this->assertEquals(true, isset($req->session['foo']));
        $this->assertEquals('bar', $req->session['foo']);
        unset($req->session['foo']);
        $this->assertEquals(null, $req->session['foo']);
        $this->assertEquals(false, isset($req->session['foo']));
        $req->session['foo'] = 'bar';
        unset($req->session['todelete']);
        $res = new \photon\http\Response('Hello!');
        $mid->process_response($req, $res);
        $this->assertEquals(true, isset($res->headers['Vary']));
        $this->assertEquals(true, isset($res->COOKIE['sid']));
        $this->assertEquals(true, isset($res->COOKIE['scs-foo']));
        $this->assertEquals(true, isset($res->COOKIE['scs-todelete']));
        $iv = $res->COOKIE['scsiv'];
        // Now we generate a new request with an iv to test the retrieval
        $req = \photon\test\HTTP::baseRequest();
        $req->COOKIE['scsiv'] = $iv;
        $req->COOKIE['scs-foo'] = \photon\crypto\Crypt::encrypt('bar', Conf::f('secret_key'), $iv);
        $this->assertEquals(false, $mid->process_request($req));
        $this->assertEquals('bar', $req->session['foo']);
    }

    public function testStorageFile()
    {
        Conf::set('session_storage', '\photon\session\storage\File');
        $req = \photon\test\HTTP::baseRequest();
        $mid = new \photon\session\Middleware();
        $this->assertEquals(false, $mid->process_request($req));
        $this->assertEquals(false, isset($req->session['foo']));
        $req->session['foo'] = 'bar';
        $this->assertEquals(true, isset($req->session['foo']));
        $this->assertEquals('bar', $req->session['foo']);
        unset($req->session['foo']);
        $this->assertEquals(false, isset($req->session['foo']));
        $req->session['foo'] = 'bar';
        unset($req->session['todelete']);
        $res = new \photon\http\Response('Hello!');
        $mid->process_response($req, $res);
        $this->assertEquals(true, isset($res->headers['Vary']));
        $this->assertEquals(true, isset($res->COOKIE['sid']));
        $sid = $res->COOKIE['sid'];
        // We now have a session stored on file, we can load it
        $data = $req->session->store->data;
        $req->session->store->load();
        $this->assertEquals($data, $req->session->store->data);
        // We remove the session store 
        unlink($req->session->store->path . '/photon-session-' . $sid);
        $req->session->store->load();
        $this->assertEquals(array(), $req->session->store->data);
    }
}

abstract class SessionHighLevelTestCase extends \photon\test\TestCase
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

class SessionFileTest extends SessionHighLevelTestCase
{
    public function setup()
    {
        parent::setup();
        Conf::set('session_storage', '\photon\session\storage\File');
    }
}
