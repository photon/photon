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


namespace photon\tests\authTest;

use \photon\test\TestCase;
use \photon\config\Container as Conf;
use \photon\auth\ConfigBackend;
use \photon\auth\Middleware;
use \photon\auth\AnonymousUser;

class SessionTest extends TestCase
{
    public function testConfigBackend()
    {
        Conf::set('auth_config_users', 
                  array('foo' => 'hashed',
                        'foobar' => password_hash('secret', PASSWORD_DEFAULT)));;
        $user = ConfigBackend::loadUser('foo');
        $this->assertEquals('foo', $user->login);
        $user = ConfigBackend::loadUser('dummy');
        $this->assertEquals(false, $user);
    }

    public function testConfigBackendAuthenticate()
    {
        Conf::set('auth_config_users', 
                  array('foo' => 'hashed',
                        'foobar' => password_hash('secret', PASSWORD_DEFAULT)));;

        $auth = array('login' => 'foobar',
                      'password' => 'secret');
        $user = ConfigBackend::authenticate($auth);
        $this->assertEquals('foobar', $user->login);

        $badauth = array('login' => 'baduser',
                         'password' => 'badsecret');
        $user = ConfigBackend::authenticate($badauth);
        $this->assertEquals(false, $user);

        $badauth = array('login' => 'foobar',
                         'password' => 'badsecret123');
        $user = ConfigBackend::authenticate($badauth);
        $this->assertEquals(false, $user);
    }

    public function testMiddlewareConfigBackend()
    {
        Conf::set('auth_config_users', 
                  array('foo' => 'hashed',
                        'foobar' => password_hash('secret', PASSWORD_DEFAULT)));
        $req = \photon\test\HTTP::baseRequest();
        $mid = new Middleware();
        $this->assertEquals(false, $mid->process_request($req));
        $this->assertEquals('photon\auth\AnonymousUser',
                            get_class($req->user));

        $req->session = array('_auth_user_id' => 'foobar');
        $this->assertEquals(false, $mid->process_request($req));
        $this->assertEquals('stdClass',
                            get_class($req->user));

        $req->session = array('_auth_user_id' => 'foobarbong');
        $this->assertEquals(false, $mid->process_request($req));
        $this->assertEquals('photon\auth\AnonymousUser',
                            get_class($req->user));

    }

    public function testAuth()
    {
        Conf::set('auth_config_users', 
                  array('foo' => 'hashed',
                        'foobar' => password_hash('secret', PASSWORD_DEFAULT)));
        $user = \photon\auth\Auth::authenticate(array('login' => 'foobar', 
                                                      'password' => 'secret'));
        $this->assertEquals('foobar', $user->login);
        $req = \photon\test\HTTP::baseRequest();
        $req->session = array();
        $this->assertEquals(true, \photon\auth\Auth::login($req, $user));
        $this->assertEquals(false, \photon\auth\Auth::login($req, false));
        $user->is_anonymous = true;
        $this->assertEquals(false, \photon\auth\Auth::login($req, $user));
    }
}
