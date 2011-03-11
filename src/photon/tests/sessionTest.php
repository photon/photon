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


namespace photon\tests\sessionTest;

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
        Conf::set('session_storage', '\photon\tests\sessionTest\DummyStore');
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
        
        // We now get the encrypted form of 'bar'
        $enc = \photon\crypto\Sign::loads('czoxNjoip5WLehs208349J9RvHTiXiI7.lXki6DbGOOZt3dwC5uM_Co5jWPY', 'dummy');
        $req->session->store->cookie['scs-foobar'] = $enc;
        $this->assertEquals('bar', $req->session['foobar']);
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
