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
 * Cryptography related functions and classes.
 *
 * Used to sign cookie data.
 */
namespace photon\crypto;

class Exception extends \Exception {}

/**
 * Module to easily and possibly securily sign strings.
 *
 * The goal is to avoid reinventing the wheel each time one needs to
 * sign strings.
 *
 * Usage to sign a string:
 * 
 * <pre>
 * $signed = \photon\crypto\Sign::signs($mystring, $key);
 * // send the string over the wire
 * $mystring = \photon\crypto\Sign::unsigns($signed, $key);
 * </pre>
 *
 * Usage to pack and sign an object:
 * <pre>
 * $signed = \photon\crypto\Sign::dumps($myobject, $key);
 * // send the string over the wire
 * $myobject = \photon\crypto\Sign::loads($signed, $key);
 * </pre>
 *
 * The packing tries to compress the data if it saves bandwith.
 *
 * Based on the work by Simon Willison:
 * http://github.com/simonw/django-openid/blob/master/django_openid/signed.py
 */
class Sign
{
    /**
     * Dump and sign an object.
     *
     * If you want to sign a small string, use directly the
     * sign/unsign function as compression will not help and you will
     * save the overhead of the serialize call.
     *
     * @param mixed Object
     * @param string Key 
     * @param bool Compress with gzdeflate (true)
     * @return string Signed string
     */
    public static function dumps($obj, $key, $compress=true)
    {
        $serialized = serialize($obj);
        $is_compressed = false; // Flag for if it's been compressed or not
        if ($compress) {
            $compressed = gzdeflate($serialized, 9);
            if (strlen($compressed) < (strlen($serialized) - 1)) {
                $serialized = $compressed;
                $is_compressed = true;
            }
        }
        $base64d = urlsafe_b64encode($serialized);
        if ($is_compressed) {
            $base64d = '.'.$base64d;
        }
        return self::signs($base64d, $key);
    }

    /**
     * Reverse of dumps, throw an Exception in case of bad signature.
     *
     * @param string Signed key
     * @param string Key
     * @return mixed The dumped signed object
     */
    public static function loads($s, $key)
    {
        $base64d = self::unsigns($s, $key);
        $decompress = false;
        if ($base64d[0] == '.') {
            // It's compressed; uncompress it first
            $base64d = substr($base64d, 1);
            $decompress = true;
        }
        $serialized = urlsafe_b64decode($base64d);
        if ($decompress) {
            $serialized = gzinflate($serialized);
        }
        return unserialize($serialized);
    }

    /**
     * Sign a string.
     *
     * If the key is not provided, it will use the secret_key
     * available in the configuration file.
     *
     * The signature string is safe to use in URLs. So if the string to
     * sign is too, you can use the signed string in URLs.
     *
     * @param string The string to sign
     * @param string Key
     * @return string Signed string
     */
    public static function signs($value, $key)
    {
        return $value.'.'.self::base64_hmac($value, $key);
    }


    /**
     * Unsign a value.
     *
     * It will throw an exception in case of error in the process.
     * 
     * @return string Signed string
     * @param string Key 
     * @param string The string
     */
    public static function unsigns($signed_value, $key)
    {
        $compressed = ($signed_value[0] == '.') ? '.' : '';
        if ($compressed) {
            $signed_value = substr($signed_value, 1);
        }
        if (false === strpos($signed_value, '.')) {
            throw new Exception('Missing signature (no . found in value).');
        }
        list($value, $sig) = explode('.', $signed_value, 2);
        if (self::base64_hmac($compressed.$value, $key) == $sig) {
            return $compressed.$value;
        } else {
            throw new Exception(sprintf('Signature failed: "%s".', $sig));
        }
    }

    /**
     * Calculate the URL safe base64 encoded SHA1 hmac of a string.
     *
     * @param string The string to sign
     * @param string The key
     * @return string The signature
     */
    public static function base64_hmac($value, $key)
    {
        return urlsafe_b64encode(\hash_hmac('sha1', $value, $key, true));
    }
}

/**
 * URL safe base 64 encoding.
 *
 * Compatible with python base64's urlsafe methods.
 *
 * @link http://www.php.net/manual/en/function.base64-encode.php#63543
 */
function urlsafe_b64encode($string) 
{
    return \str_replace(array('+','/','='), array('-','_',''),
                        \base64_encode($string));
}

/**
 * URL safe base 64 decoding.
 */
function urlsafe_b64decode($string) 
{
    $data = \str_replace(array('-','_'), array('+','/'),
                         $string);
    $mod4 = \strlen($data) % 4;
    if ($mod4) {
        $data .= \substr('====', $mod4);
    }
    return \base64_decode($data);
}
