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

namespace photon\session\storage;

use photon\config\Container as Conf;
use photon\crypto\Crypt;

/**
 * Base storage class.
 *
 * Just extend it to fit your needs or use it as a starting point for your
 * own storage.
 */
class Base
{
    public $data = null; /**< Current data in the session */

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
    public static function generateSessionKey($entropy='')
    {
        return sha1(microtime() . mt_rand() . $entropy);
    }

    /**
     * Get a new key.
     */
    public function getNewKey($entropy='')
    {
        $exists = true;
        $key = '';
        while ($exists) {
            $key = self::generateSessionKey($entropy);
            $exists = $this->keyExists($key);
        }

        return $key;
    }

    /**
     * Check if a session key already exists in the storage.
     *
     * @param $key string
     * @return bool
     */
    public function keyExists($key)
    {
        throw new \photon\core\NotImplemented();
    }

    /**
     * Given a the request object, init itself.
     *
     * @required public function init($key, $request=null) 
     *
     * @param $key Session key
     * @param $request Request object (null)
     * @return Session key
     */
    public function init($key, $request=null) 
    {
        throw new \photon\core\NotImplemented();
    }

    /**
     * Given the response object, save the data.
     *
     * @required public function commit($response=null) 
     * @return Session key
     */
    public function commit($response)
    {
        throw new \photon\core\NotImplemented();
    }

    /**
     * Load the data from the store into $this->data
     */
    public function load()
    {
        throw new \photon\core\NotImplemented();
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
        if (null === $this->data) $this->load();
        $this->data[$offset] = $value;
    }

    /**
     * Informs if a value is available in the storage for this offset.
     *
     * @required public function exists($offset)
     */
    public function exists($offset)
    {
        if (null === $this->data) $this->load();

        return array_key_exists($offset, $this->data);
    }

    /**
     * Delete a value from the storage.
     *
     * @required public function delete($offset)
     */
    public function delete($offset)
    {
        if (null === $this->data) $this->load();
        unset($this->data[$offset]);
    }

    /**
     * Get a value from the storage.
     *
     * @required public function get($offset)
     */
    public function get($offset)
    {
        if (null === $this->data) $this->load();

        return $this->data[$offset];
    }
}

/**
 * File storage of the session data.
 *
 * Totally inefficient, but interesting as example on how the storage
 * is not touching the disk until the first read and writing only if
 * needed at the end.
 */
class File extends Base
{
    public $path = ''; /**< Path to store the session data. */
    public $key = null;

    public function __construct()
    {
        $this->path = Conf::f('session_file_path', 
                              Conf::f('tmp_folder', sys_get_temp_dir()));
    } 

    /**
     * Given a the request object, init itself.
     *
     * @required public function init($key, $request=null) 
     *
     * @param $key Session key
     * @param $request Request object (null)
     * @return Session key
     */
    public function init($key, $request=null) 
    {
        $this->key = $key;
    }

    public function load()
    {
        if (null === $this->key) {
            $this->data = array();

            return false;
        }
        if (!file_exists($this->path . '/photon-session-' . $this->key)) {
            $this->data = array();

            return false;
        }
        $this->data = unserialize(file_get_contents($this->path . '/photon-session-' . $this->key));

        return true;
    }

    /**
     * Check if a session key already exists in the storage.
     *
     * @param $key string
     * @return bool
     */
    public function keyExists($key)
    {
        return file_exists(sprintf('%s/photon-session-%s', $this->path, $key));
    }


    /**
     * Given the response object, save the data.
     *
     * The commit call must ensure that $this->key is set afterwards.
     *
     * @required public function commit($response=null) 
     */
    public function commit($response)
    {
        if (null === $this->key) {
            $this->key = $this->getNewKey(json_encode($response->headers));
        }
        file_put_contents($this->path . '/photon-session-' . $this->key,
                          serialize($this->data), LOCK_EX);

        return $this->key;
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
class Cookies extends Base
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
    public $key = '';

    public function keyExists($key)
    {
        // We anyway store directly in the browser, so, no need to
        // check.
        return false;
    }

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
    public function init($key, $request=null) 
    {
        $this->cookie = $request->COOKIE;
        $this->key = $key;
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
        $timeout = time() + 365 * 24 * 3600;

        foreach ($this->cache as $name => $val) {
            $val = Crypt::encrypt($val, Conf::f('secret_key'));
            $response->COOKIE->setCookie('scs-' . $name, $val, $timeout);
        }
        foreach ($this->deleted as $name => $val) {
            $response->COOKIE->delCookie('scs-' . $name);
        }
        return $this->getNewKey(json_encode($response->headers));
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
        $this->deleted[$offset] = true;
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
            return Crypt::decrypt($this->cookie['scs-' . $offset], Conf::f('secret_key'));
        }

        return null;
    }
}
