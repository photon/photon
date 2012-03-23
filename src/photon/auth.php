<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, High Performance PHP Framework.
# Copyright (C) 2010-2011 Loic d'Anterroches and contributors.
#
# Photon is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as 
# published by the Free Software Foundation in version 2.1.
#
# Photon is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public 
# License along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * User authentication classes.
 *
 * The authentication is answering the question: Who are you?
 *
 * The authentication step is performed once, a session is then
 * created and used to keep track of whom is accessing the website. 
 *
 * An authentication backend requires:
 *
 * - a user id to retrieve the user data from the user storage. This
 *   mechanism is also used to load the user from the session data.
 *
 * - extra information provided by the client to authenticate the 
 *   client against the user data.
 *
 */
namespace photon\auth;

use photon\config\Container as Conf;

/**
 * Provides authentication and login wrapper.
 */
class Auth
{
    /**
     * Authenticate the user against the current authentication backend.
     */
    public static function authenticate($auth_data)
    {
        $backend = Conf::f('auth_backend', '\photon\auth\ConfigBackend');
        return $backend::authenticate($auth_data);
    }

    /**
     * Login a user in the current session.
     */
    public static function login($request, $user)
    {
        if (false === $user || $user->is_anonymous) {

            return false;
        }
        $key = Conf::f('auth_session_key', '_auth_user_id');
        $request->session[$key] = $user->login;
        
        return true;
    }
}


/**
 * Authentication Middleware.
 *
 * It requires the session middleware as the user id is extracted from
 * the session data.
 */
class Middleware
{
    /**
     * Set the $request->user to the currently authenticated user or to
     * an anonymous user.
     */
    public function process_request($request)
    {
        $key = Conf::f('auth_session_key', '_auth_user_id');
        if (!isset($request->session[$key])) {
            $request->user = new AnonymousUser();

            return false;
        }
        $backend = Conf::f('auth_backend', '\photon\auth\ConfigBackend');
        $user = $backend::loadUser($request->session[$key]);
        $request->user = (false === $user)
            ? new AnonymousUser()
            : $user;

        return false;
    }
}

class AnonymousUser
{
    public $login = '';
    public $password = '';
    public $is_anonymous = true;
}

/**
 * Authentication class against users in the configuration file.
 *
 * This is a very simple example, in the configuration file you put:
 *
 * <pre>
 * 'auth_backend' => '\photon\auth\ConfigBackend',
 * 'auth_config_users' => array('username' => 'hashedpassword',
 *                              'othername' => 'otherpassword'),
 * </pre>
 * 
 * With the hashed password with blowfish. You can run 
 * \photon\crypt\Hash::hashPass('password') to generate the hashed password.
 *
 * @see crypt()
 */
class ConfigBackend
{
    /**
     * Given a user id, retrieve it.
     *
     */
    public static function loadUser($user_id)
    {
        $users = Conf::f('auth_config_users', array());
        if (!isset($users[$user_id])) {

            return false;
        }

        // FUTURE: Need to load the user from the defined backend/storage.
        return (object) array('login' => $user_id, 
                              'password' => $users[$user_id],
                              'is_anonymous' => false);
    }

    /**
     * Given an array with the authentication data, auth the user and
     * return it.
     */
    public static function authenticate($auth)
    {
        $user = self::loadUser($auth['login']);
        if (false === $user || $user->password !== crypt($auth['password'], $user->password)) {

            return false;
        }

        return $user;
    }
}
