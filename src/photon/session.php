<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, the High Speed PHP Framework.
# Copyright (C) 2010 Loic d'Anterroches and contributors.
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

/**
 * Session related classes and functions.
 *
 * Session data is stored in the pseudo associative array
 * $request->session. This object will be smart enough to update the
 * data in the session storage when created/modified/deleted.
 *
 * Do not forget that the session storages are for a small amount of
 * data which can be lost. If you need to store a lot of data, just
 * use the session id (possibly linked to a user account to respawn
 * it) as a key to store the data in your persistent storage and load
 * these data on demand in your application.
 */ 
namespace photon\session;

use photon\config\Container as Conf;


/**
 * The class storing the session data in the request object.
 *
 * This class will use the underlying storage mechanisms like cookie
 * or memory based on your configuration. By default the storage is
 * cookies based. See the CookieStorage class for the required
 * interface of a storage. 
 *
 */
class Session implements \ArrayAccess
{
    public $store; /**< Storage */
    public $key; /**< Session id */
    public $accessed = false; /**< Was the session data used */
    public $modified = false; /**< Was the session data modified */

    /**
     * Construct the session object.
     *
     * It takes the storage object as parameter.
     *
     * @param $store Storage engine
     */
    public function __construct($store)
    {
        $this->store = $store;
    }

    /**
     * Initialize the session storage based on the request.
     *
     * This step is very important. Basically, it will provide the
     * request to the storage and let the storage initialize
     * itself. Once the storage has initialized itself, it must return
     * the corresponding id of the session if available.
     */
    public function init($key, $request)
    {
        $this->store->init($key, $request);
    }

    /**
     * Save the data at the end of the request processing.
     *
     * The data can already be saved in the storage but if the data
     * are saved at the client side through the response object. The
     * storage engine has now the opportunity to access the response
     * object to do the job. It is also the mark that the session
     * storage is not used anymore, for example the storage can commit
     * the transaction.
     *
     * @param $response Response object
     */
    public function commit($response)
    {
        $this->key = $this->store->commit($response);
    }

    /**
     * Set the value in the storage.
     *
     */
    public function offsetSet($offset, $value) 
    {
        if (null === $offset) {
            throw new \Exception('You need to set the session key by value.');
        }
        $this->modified = true;
        $this->store->store($offset, $value);
    }

    public function offsetExists($offset) 
    {
        $this->accessed = true;
        return $this->store->exists($offset);
    }

    public function offsetUnset($offset) 
    {
        $this->modified = true;
        $this->store->delete($offset);
    }

    public function offsetGet($offset) 
    {
        $this->accessed = true;
        return $this->store->get($offset);
    }    
}


/**
 * Session middleware.
 *
 * It is important to have a smart session middleware, that is, it
 * should do nothing if not really needed. If the user has no session
 * information and no need to store session information, we should not
 * touch the storage.
 *
 * Fully inspired by the Django session middleware.
 */
class Middleware
{
    public function process_request($request)
    {
        // By not defining a default session store, the system will
        // crash directly if not well configured. This is better.
        $store = Conf::f('session_storage'); 
        $key = (isset($request->COOKIE[Conf::f('session_cookie_name', 'sid')]))
            ? $request->COOKIE[Conf::f('session_cookie_name', 'sid')]
            : null;
        $request->session = new Session(new $store());
        $request->session->init($key, $request);
        
        return false;
    }

    public function process_response($request, $response)
    {
        $accessed = $request->session->accessed;
        $modified = $request->session->modified;
        if ($request->session->accessed) {
            // This view used session data to render, this means it
            // varies on the cookie information.
            \photon\http\HeaderTool::updateVary($response, array('Cookie'));
        }
        if ($request->session->modified 
            || Conf::f('session_save_every_request', false)) {
            // Time to store
            $request->session->commit($response);
            $expire = Conf::f('session_cookie_expire', 1209600);
            if ($expire) {
                $expire += time();
            }
            $response->COOKIE->setCookie(Conf::f('session_cookie_name', 'sid'),
                                         $request->session->key, $expire,
                                         Conf::f('session_cookie_path', ''),
                                         Conf::f('session_cookie_domain', ''),
                                         Conf::f('session_cookie_secure', false),
                                         Conf::f('session_cookie_httponly', false));
        }

        return $response;
    }
}


