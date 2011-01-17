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
 * Generate a unique session id.
 *
 * There are no guarantees that the provided id is really new and
 * unique, put it should be relatively safe. You must check against
 * your storage to be sure and generate a new one if already in use.
 *
 * For pure cookie based session, you should inject in the extra
 * random string as much information about the client as possible like
 * IP address, user agent, etc. to help prevent 2 clients to have the
 * same session id as you cannot check if the session id was already
 * issued. This is important if you want to reuse the session id
 * somewhere else in your system as a unique key, if not, you do not
 * really care because you will directly use the data from the cookies
 * for the session data and they will be unique to the given client
 * anyway.
 *
 * @param string Optional extra random data to help getting random id ('')
 * @return string Session id (40 hexacharacters)
 */
function generate_session_id($entropy='')
{
    return sha1(microtime() . mt_rand() . mt_rand() . $entropy);
}

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
    public $id; /**< Session id */

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
     * the corresponding id of the session.
     */
    public function init($request)
    {
        $this->id = $this->store->init($request);
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
        $this->store->commit($response);
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
        $this->store->save($offset, $value);
    }

    public function offsetExists($offset) 
    {
        return $this->store->exists($offset);
    }

    public function offsetUnset($offset) 
    {
        $this->store->delete($offset);
    }

    public function offsetGet($offset) 
    {
        return $this->store->get($offset);
    }    
}

/**
 * Full cookie based session storage.
 *
 * Basically, kind of smart cookies which are updated/deleted based on
 * what you do about them. 
 *
 * The session object is initialized before we know about the
 * response, this means that only the request object is available.
 *
 * If you want to implement your own storage, you need to implement
 * all the methods marked @required. The storage is a key, value
 * store.
 */
class CookieStorage
{
    /**
     * Reference to the $request->COOKIE 
     *
     * This where we get the value from if not in the "cache".
     */
    public $cookie;

    /**
     * Cache of the value, if in the cache, the value has been set
     * (new or modified) during this request and must be saved at the
     * end of the request.
     */
    public $cache = array(); 

    /**
     * Deleted values. At the end of the request, the corresponding
     * cookie must be removed.
     */
    public $deleted = array();

    /**
     * Session id.
     */
    protected $sid = '';

    /**
     * Given a the request object, init itself.
     *
     * The request object allows the storage to find the session id
     * from the cookies or request details (headers), it can also be
     * used to generate a new id.
     *
     * @required public function init($request) 
     *
     * @param $request Request object
     * @return Session id
     */
    public function init($request) 
    {
        $this->cookie &= $request->COOKIE;
        $sid = $this->cookie[Conf::f('session_cookie_id', 'sessionid')];
        if (null === $sid) {
            $sid = generate_session_id(json_encode($request->mess->headers));
        }
        $this->sid = $sid;

        return $sid;
    }

    /**
     * Given the response object, save the data.
     *
     * Even if your storage is not cookie based, if you are using a
     * cookie to store the session id, you must imperatively set the
     * cookie to keep track of the session id. 
     *
     * @required public function commit($response) 
     */
    public function commit($response)
    {
        $timeout = time() + Conf::f('session_cookie_timeout', 31536000);
        foreach ($this->cache as $name => $val) {
            $response->COOKIE->setCookie('scs-' . $name, $val, $timeout);
        }
        foreach ($this->deleted as $name => $val) {
            $response->COOKIE->delCookie('scs-' . $name);
        }
        // We always reset the session id cookie. Yes, this means that
        // the session id cookie may last longer than the data
        // cookies, but this means less network traffic and never
        // forget that session data is transient data. If you need to
        // store your data for more than a year, you need to find a
        // more durable way.
        $response->COOKIE->setCookie(Conf::f('session_cookie_id', 'sessionid'),
                                     $this->sid, $timeout);
    }

    /**
     * Store a given value at a given offset in the storage.
     *
     * The offset is an alphanumeric string, the value is any kind of
     * PHP object which can be serialized.
     *
     * @required public function store($offset, $value)
     */
    public function store($offset, $value)
    {
        unset($this->deleted[$offset]);
        $this->cache[$offset] = $value;
    }

    /**
     * Informs if a value is available in the storage for this offset.
     *
     * @required public function exists($offset)
     */
    public function exists($offset)
    {
        return (null !== $this->get($offset));
    }

    /**
     * Delete a value from the storage.
     *
     * @required public function delete($offset)
     */
    public function delete($offset)
    {
        unset($this->cache[$offset]);
        $this->delete[$offset] = true;
    }

    /**
     * Get a value from the storage.
     *
     * @required public function get($offset)
     */
    public function get($offset)
    {
        if (isset($this->deleted[$offset])) {

            return null;
        }
        if (isset($this->cache[$offset])) {

            return $this->cache[$offset];
        }
        if (isset($this->cookie['scs-' . $offset])) {

            return $this->cookie['scs-' . $offset];
        }

        return null;
    }
}