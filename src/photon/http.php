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

namespace photon\http;

use \photon\config\Container as Conf;

class Exception extends \Exception {}

/**
 * Response object to be constructed by the views.
 *
 * When constructing a view, the response object must be populated and
 * returned. The response is then displayed to the visitor. 
 * The interest of using a response object is that we can run a post
 * filter action on the response. For example you can run a filter that
 * is checking that all the output is valid HTML and write a logfile if
 * this is not the case.
 *
 * Note that this response is the "standard" response when returning
 * simple HTML pages. Photon, by the way of Mongrel2 allows you to
 * stream information back to the client and do way more (long
 * polling, etc.) you can directly return a raw Mongrel2 compatible
 * answer to a request if you want. So think about this response as a
 * thin wrapper on top of the raw Mongrel2 response to simplify your
 * life.
 */
class Response
{
    /**
     * Content of the response.
     */
    public $content = '';

    /**
     * To whom it should be delivered. 
     *
     * By default, it will delivered to the client issuing the
     * request, but one can set it to another client or an array of
     * clients.
     */
    public $deliver_to = null;

    /**
     * Array of the headers to add.
     *
     * For example $this->headers['Content-Type'] = 'text/html; charset=utf-8';
     */
    public $headers = array();

    /**
     * Status code of the answer.
     */
    public $status_code = 200;

    /**
     * Cookies to send.
     *
     * $this->COOKIE['my_cookie'] = 'content of the cookie';
     */
    public $COOKIE = null;

    /**
     * Status code list.
     *
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
     * @see http://tools.ietf.org/html/rfc4918#section-21.4
     */
    public $status_code_list = array(100 => 'Continue',
                                     101 => 'Switching Protocols',
                                     102 => 'Processing',                               // RFC 2518
                                     200 => 'OK',
                                     201 => 'Created',
                                     202 => 'Accepted',
                                     203 => 'Non-Authoritative Information',
                                     204 => 'No Content',
                                     205 => 'Reset Content',
                                     206 => 'Partial Content',
                                     207 => 'Multi-Status',                             // RFC 4918
                                     208 => 'Already Reported',                         // RFC 5842
                                     226 => 'IM Used',                                  // RFC 3229
                                     300 => 'Multiple Choices',
                                     301 => 'Moved Permanently',
                                     302 => 'Found',
                                     303 => 'See Other',
                                     304 => 'Not Modified',
                                     305 => 'Use Proxy',
                                     306 => 'Reserved',
                                     307 => 'Temporary Redirect',
                                     308 => 'Permanent Redirect',                       // RFC 7538
                                     400 => 'Bad Request',
                                     401 => 'Unauthorized',
                                     402 => 'Payment Required',
                                     403 => 'Forbidden',
                                     404 => 'Not Found',
                                     405 => 'Method Not Allowed',
                                     406 => 'Not Acceptable',
                                     407 => 'Proxy Authentication Required',
                                     408 => 'Request Timeout',
                                     409 => 'Conflict',
                                     410 => 'Gone',
                                     411 => 'Length Required',
                                     412 => 'Precondition Failed',
                                     413 => 'Request Entity Too Large',
                                     414 => 'Request-URI Too Long',
                                     415 => 'Unsupported Media Type',
                                     416 => 'Requested Range Not Satisfiable',
                                     417 => 'Expectation Failed',
                                     418 => 'I am a Teapot',                            // RFC 2324
                                     422 => 'Unprocessable Entity',                     // RFC 4918
                                     423 => 'Locked',                                   // RFC 4918
                                     424 => 'Failed Dependency',                        // RFC 4918
                                     426 => 'Upgrade Required',                         // RFC 2817
                                     428 => 'Precondition Required',                    // RFC 6585
                                     429 => 'Too Many Requests',                        // RFC 6585
                                     431 => 'Request Header Fields Too Large',          // RFC 6585
                                     500 => 'Internal Server Error',
                                     501 => 'Not Implemented',
                                     502 => 'Bad Gateway',
                                     503 => 'Service Unavailable',
                                     504 => 'Gateway Timeout',
                                     505 => 'HTTP Version Not Supported',
                                     507 => 'Insufficient Storage',                     // RFC 4918
                                     508 => 'Loop Detected',                            // RFC 5842
                                     510 => 'Not Extended',                             // RFC 2774
                                     511 => 'Network Authentification Required',        // RFC 6585
                                     );

    /**
     * Constructor of the response.
     *
     * @param string Content of the response ('')
     * @param string MimeType of the response (null) if not given will
     * default to the one given in the configuration 'mimetype'
     */
    function __construct($content='', $mimetype='text/html; charset=utf-8')
    {
        $this->content = $content;
        $this->headers['Content-Type'] = $mimetype;
        $this->headers['X-Powered-By'] = 'Photon - http://photon-project.com';
        $this->status_code = 200;
        $this->COOKIE = new Cookie();
    }

    /**
     * Render a response object.
     */
    function render($output_body=true)
    {
        if (200 <= $this->status_code &&
            204 != $this->status_code &&
            304 != $this->status_code) {
            if (!isset($this->headers['Transfer-Encoding']) || $this->headers['Transfer-Encoding'] !== "chunked") {
                $this->headers['Content-Length'] = strlen($this->content);
            }
        }
        $headers = $this->getHeaders();

        if ($output_body) {
            // Only one "\r\n" as the $headers already have a trailing one
            return $headers . "\r\n" . $this->content;
        }

        return $headers;
    }

    function sendIterable($msg, $conn, $output_body=true)
    {
        // Send the header
        $headers = $this->getHeaders();
        $conn->reply($msg, $headers);

        if (!$output_body) {
            return;
        }

        // Send the body
        $conn->reply($msg, "\r\n");
        foreach ($this->content as $chunk) {
            // Ignore empty chunk to avoid to close the mongrel2 connection
            if (strlen($chunk) === 0) {
                continue;
            }

            $conn->reply($msg, $chunk);
        }
    }

    /**
     * Get the headers.
     *
     */
    function getHeaders()
    {
        $hdrs = 'HTTP/1.1 ' . $this->status_code . ' ' .
                $this->status_code_list[$this->status_code] . "\r\n";
        foreach ($this->headers as $header => $ch) {
            $hdrs .= $header . ': ' . $ch . "\r\n";
        }
        $hdrs .=  CookieHandler::build($this->COOKIE, 
                                       Conf::f('secret_key', ''));

        return $hdrs;
    }
}

/**
 * The request object.
 *
 * It is given to the view as first argument.
 */
class Request
{
    public $mess = null;
    public $query = '';
    public $GET = array();
    public $path = '';
    public $POST = array();
    public $FILES = array();
    public $COOKIE = array();
    public $method = '';
    public $BODY = null;
    public $uuid = '';
    public $headers = null;

    /** 
     * Sender id set for the handler in the Mongrel2 conf.
     */
    public $sender = '';
    /**
     * The client connection id which issued the request.
     */
    public $client = '';

    /**
     * Request object provided to the Photon views.
     *
     * @param &$mess Mongrel2 request message object.
     */
    function __construct(&$mess)
    {
        $this->mess = $mess;
        $this->path = $this->mess->path;
        $this->method = $this->mess->headers->METHOD;
        $this->sender = $this->mess->sender;
        $this->client = $this->mess->conn_id;
        $this->headers = $this->mess->headers;

        if (isset($this->mess->headers->QUERY)) {
            \mb_parse_str($this->mess->headers->QUERY, $this->GET);
            $this->query = $this->mess->headers->QUERY;
        }
        if ('POST' === $this->mess->headers->METHOD || 'PUT' === $this->mess->headers->METHOD) {
            if (isset($this->mess->headers->{'content-type'}) === false) {
                $this->BODY =& $mess->body;
            } else if (0 === strpos($this->mess->headers->{'content-type'}, 'multipart/form-data; boundary=')) {
                $parser = new \photon\http\multipartparser\MultiPartParser($mess->headers, $mess->body);
                foreach ($parser->parse() as $part) {
                    if ('FIELD' === $part['of_type']) {
                        $this->add_to_post($part['name'], $part['data']);
                    } else {
                        $this->add_file_to_post($part['name'], $part);
                    }
                }
            } else if (false !== mb_strstr($this->mess->headers->{'content-type'}, 'application/x-www-form-urlencoded')) {
                $this->BODY = substr(stream_get_contents($mess->body), 0, -1);
                $this->parse_form_urlencoded($this->BODY);
            } else if (false !== mb_strstr($this->mess->headers->{'content-type'}, 'application/json')) {
                $this->BODY = substr(stream_get_contents($mess->body), 0, -1);
                $this->JSON = json_decode($this->BODY);
            } else {
                $this->BODY =& $mess->body;
            }
        } else if ('JSON' === $this->mess->headers->METHOD) {
            $this->BODY = $this->mess->body;
        }
        $this->COOKIE = CookieHandler::parse($this->mess->headers, 
                                             Conf::f('secret_key', ''));
    }

    /**
     * Simple form url encoded decoding of a string.
     *
     * This is only needed for POST requests.
     * 
     * @param $payload Encoded string
     * @return array Decoded string
     */
    private function parse_form_urlencoded($payload)
    {
        foreach (explode('&', $payload) as $field) {
            $data = explode('=', trim($field));
            if (count($data) == 2) {
                $this->add_to_post($data[0], urldecode($data[1]));
            }
        }
    }

    /**
     * Add the current value to the corresponding POST key.
     *
     * When you submit a form with a POST request, multiple values have to
     * be handled as an array. For example, if you submit a select with
     * multiple values.
     *
     * This function takes care of adding the value as array or not to the
     * variables.
     *
     * @param &$post The POST array, modified by reference.
     * @param $key The field name
     * @param $value The field value
     */
    private function add_to_post($key, $value)
    {
        if (!isset($this->POST[$key])) {
            $this->POST[$key] = $value;
        } elseif (is_array($this->POST[$key])) {
            $this->POST[$key][] = $value;
        } else {
            // Defined and not an array, we need to convert and add
            $this->POST[$key] = array($this->POST[$key], $value);
        }
    }

    /**
     * Add the current file upload to the corresponding FILES key.
     *
     * When you submit a form with a POST request, multiple values have to
     * be handled as an array. For example, if you submit a select with
     * multiple values.
     *
     * This function takes care of adding the value as array or not to the
     * variables.
     *
     * @param &$post The FILES array, modified by reference.
     * @param $key The field name
     * @param $value The field value
     */
    private function add_file_to_post($key, $value)
    {
        if (!isset($this->FILES[$key])) {
            $this->FILES[$key] = $value;
        } elseif (is_array($this->FILES[$key]) && !isset($this->FILES[$key]['data'])) {
            $this->FILES[$key][] = $value;
        } else {
            // Defined and not an array, we need to convert and add
            $this->FILES[$key] = array($this->FILES[$key], $value);
        }
    }

    /**
     * Returns the given header or a default value.
     */
    public function getHeader($header, $default='')
    {
        return (isset($this->headers->$header))
            ? $this->headers->$header
            : $default;
    }
}

/**
 * Cookie manager.
 *
 * Cookies are a bit annoying, that is, they store a value, but the
 * value is full of meta data when we set it (secure, comment, path,
 * domain, key+value, expiration time). So, you cannot really just use
 * an associative array to store them. But most of the time, you just
 * want an associative array like way to set them, this means that one
 * needs a flexible way to set them.
 *
 * So, in the $request object, you have the COOKIE property
 * containing an associative array of the cookies. Only the valid
 * cookies are available here as the cookies are automatically signed.
 *
 * In the $response object, you can set cookies with the COOKIE
 * property, this is not an associative array, but act as if it is.
 *
 * <pre>
 * // Set the cookie 'foo' with value 'bar', all the meta data are
 * // default data.
 * $response->COOKIE['foo'] = 'bar'; 
 * // Full control over the cookie info
 * $response->COOKIE->setCookie('foo', 'bar', [$expire = 0 [, string $path 
 *                               [, string $domain [, bool $secure = false 
 *                               [, bool $httponly = false ]]]]]] );
 * // Shortcut to delete a cookie.
 * $response->COOKIE->delCookie('foo');
 * </pre>
 */
class Cookie implements \ArrayAccess
{
    /**
     * A cookie can store multiple values, but you need several
     * cookies to store several values with different expiration date,
     * path or domain. The $all storage is a simple associative array,
     * but the cookies in it are then merged by the CookieManager.
     *
     * The cookie manager is the one doing signature/compression of
     * the cookies.
     */
    private $all = array();

    /**
     * Store the deleted cookies.
     *
     * You want to have isset() returns false after you delete a
     * cookie even so your cookie exists and is marked as going to be
     * deleted.
     */
    private $delete = array();

    public function __construct($cookies=array()) 
    {
        foreach ($cookies as $name => $value) {
            $this->offsetSet($name, $value);
        }
    }
    
    /**
     * Returns all the cookies in a list of arrays.
     *
     * This includes the cookies set in the past for deletion.
     *
     * @return array All the cookies
     */
    public function getAll()
    {
        return $this->all;
    }

    /**
     * Set a cookie, the API follows the setcookie php function.
     *
     * The extension is that the value of the cookie can be any kind
     * of serializabled object. Beware for it not to be too big, but
     * it means that you can store simple arrays for example.
     * 
     * @see http://www.php.net/setcookie
     *
     * @param $name string Name of the cookie
     * @param $value mixed Value of the cookie
     * @parma $expire int Unix timestamp for expiration day time (session only)
     * @param $path string Path restriction for the cookie (null)
     * @param $domain string Domain to apply the cookie to (null)
     * @param $secure bool Is the cookie a secure cookie (false)
     * @param $httponly bool (false)
     * @return bool Success
     */
    public function setCookie($name, $value, $expire=0, $path=null,
                              $domain=null, $secure=false, $httponly=false)
    {
        $cookie = array('cookies' => array($name => $value),
                        'flags' => 0,
                        'expires' => $expire,
                        'path' => $path,
                        'domain' => $domain);
        if ($secure) {
            $cookie['flags'] = HTTP_COOKIE_SECURE;
        }
        if ($httponly) {
            $cookie['flags'] = $cookie['flags'] | HTTP_COOKIE_HTTPONLY;
        }
        $this->all[$name] = $cookie;

        return true;
    }

    /**
     * Delete a cookie.
     *
     * @param $name string Name of the cookie
     */
    public function delCookie($name)
    {
        $this->setCookie($name, '-', 1);
    }

    /**
     * Set the cookie in the storage.
     *
     * It calls $this->setCookie() with the default parameters. It
     * does not accept the setting of a "null" offset cookie.
     */
    public function offsetSet($offset, $value) 
    {
        if (null === $offset) {
            throw new Exception('You need to provide a cookie name.');
        }
        $this->setCookie($offset, $value);
        unset($this->delete[$offset]);
    }

    public function offsetExists($offset) 
    {
        if (isset($this->delete[$offset])) {
            return false;
        }
        return isset($this->all[$offset]);
    }

    public function offsetUnset($offset) 
    {
        $this->delCookie($offset);
        $this->delete[$offset] = true;
    }

    public function offsetGet($offset) 
    {
        return isset($this->all[$offset]) ? $this->all[$offset]['cookies'][$offset] : null;
    }    
}


/**
 * Handling of the cookies.
 *
 * Generate and parse the cookies. Cookies are automatically signed
 * with a SHA1 HMAC.
 *
 * Usage to get the cookies of a "Cookie:" header.
 *
 * <pre>
 * $request->COOKIE = CookieHandler::parse($req->headers, $key);
 * </pre>
 *
 * To generate the Set-Cookie: headers. In the answer, one can have
 * many "Set-Cookie" headers, so the string of the Set-Cookie headers
 * is returned.
 *
 * <pre>
 * $set_cookie_headers = CookieHandler::build($req->COOKIE, $key);
 * </pre>
 *
 * @see http://curl.haxx.se/rfc/cookie_spec.html
 *
 */
class CookieHandler
{
    /**
     * Parse the request headers and get the cookies.
     */
    public static function parse($headers, $key)
    {
        if (!isset($headers->cookie)) {
            return array();
        }
        $cookies = (array) $headers->cookie;
        $out = array();
        foreach ($cookies as $cookie) {
            $out = array_merge($out, self::parse_cookie($cookie, $key));
        }

        return $out;
    }

    /**
     * Build the header string of the cookies.
     */
    public static function build($cookies, $key)
    {
        $c = $cookies->getAll();
        if (0 === count($c)) {
            return '';
        }
        $headers = '';
        foreach ($c as $ck) {
            foreach ($ck['cookies'] as $name => $val) {
                $ck['cookies'][$name] = \photon\crypto\Sign::dumps($val, $key);
            }
            $headers .= 'Set-Cookie: ' . http_build_cookie($ck) . "\r\n";
        }

        return $headers;
    }

    /**
     * Parse a cookie string.
     *
     * Automatically perform the signature check.
     *
     * @param $cookie Cookie string
     * @param $key Shared key for HMAC signature
     * @return array Valid cookies in associative array
     */
    public static function parse_cookie($cookie, $key)
    {
        $c = \http_parse_cookie($cookie);
        $cookies = array();
        foreach ($c->cookies as $name => $val) {
            if (strlen($val) > 0) {
                try {
                    $cookies[$name] = \photon\crypto\Sign::loads($val, $key);
                }  catch (\Exception $e) { 
                    // We simply ignore bad cookies.
                }
            }
        }

        return $cookies;
    }
}

/**
 * Tools to manipulate the headers.
 *
 * Lot of code lifted from Django:
 * http://code.djangoproject.com/browser/django/trunk/django/utils/cache.py
 */
class HeaderTool
{
    /**
     * Update the Vary header.
     *
     * @param $response Response object
     * @param $newheaders List of headers to add to the Vary header
     */
    public static function updateVary($response, $newheaders)
    {
        $vary = (isset($response->headers['Vary']))
            ? explode(',', $response->headers['Vary'])
            : array();
        foreach ($newheaders as $new) {
            if (!in_array($new, $vary)) {
                $vary[] = $new;
            }
        }
        $response->headers['Vary'] = implode(',', $vary);
    }
}





