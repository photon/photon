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
     * $this->cookies['my_cookie'] = 'content of the cookie';
     */
    public $cookies = array();

    /**
     * Status code list.
     *
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
     */
    public $status_code_list = array(
                                     '100' => 'CONTINUE',
                                     '101' => 'SWITCHING PROTOCOLS',
                                     '200' => 'OK',
                                     '201' => 'CREATED',
                                     '202' => 'ACCEPTED',
                                     '203' => 'NON-AUTHORITATIVE INFORMATION',
                                     '204' => 'NO CONTENT',
                                     '205' => 'RESET CONTENT',
                                     '206' => 'PARTIAL CONTENT',
                                     '300' => 'MULTIPLE CHOICES',
                                     '301' => 'MOVED PERMANENTLY',
                                     '302' => 'FOUND',
                                     '303' => 'SEE OTHER',
                                     '304' => 'NOT MODIFIED',
                                     '305' => 'USE PROXY',
                                     '306' => 'RESERVED',
                                     '307' => 'TEMPORARY REDIRECT',
                                     '400' => 'BAD REQUEST',
                                     '401' => 'UNAUTHORIZED',
                                     '402' => 'PAYMENT REQUIRED',
                                     '403' => 'FORBIDDEN',
                                     '404' => 'NOT FOUND',
                                     '405' => 'METHOD NOT ALLOWED',
                                     '406' => 'NOT ACCEPTABLE',
                                     '407' => 'PROXY AUTHENTICATION REQUIRED',
                                     '408' => 'REQUEST TIMEOUT',
                                     '409' => 'CONFLICT',
                                     '410' => 'GONE',
                                     '411' => 'LENGTH REQUIRED',
                                     '412' => 'PRECONDITION FAILED',
                                     '413' => 'REQUEST ENTITY TOO LARGE',
                                     '414' => 'REQUEST-URI TOO LONG',
                                     '415' => 'UNSUPPORTED MEDIA TYPE',
                                     '416' => 'REQUESTED RANGE NOT SATISFIABLE',
                                     '417' => 'EXPECTATION FAILED',
                                     '500' => 'INTERNAL SERVER ERROR',
                                     '501' => 'NOT IMPLEMENTED',
                                     '502' => 'BAD GATEWAY',
                                     '503' => 'SERVICE UNAVAILABLE',
                                     '504' => 'GATEWAY TIMEOUT',
                                     '505' => 'HTTP VERSION NOT SUPPORTED'
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
        $this->cookies = array();
    }

    /**
     * Render a response object.
     */
    function render($output_body=true)
    {
        if ($this->status_code >= 200 
            && $this->status_code != 204 
            && $this->status_code != 304) {
            $this->headers['Content-Length'] = strlen($this->content);
        }
        $headers = $this->getHeaders();
        if ($output_body) {
            // Only one "\r\n" as the $headers already have a trailing one
            return $headers."\r\n".$this->content;
        }
        return $headers;
    }

    /**
     * Get the headers.
     *
     * FIXME: Need the support of the cookies.
     */
    function getHeaders()
    {
        $hdrs = 'HTTP/1.1 '.$this->status_code.' '.$this->status_code_list[$this->status_code]."\r\n";
        foreach ($this->headers as $header => $ch) {
            $hdrs .= $header.': '.$ch."\r\n";
        }
        return $hdrs;
        /*
        foreach ($this->cookies as $cookie => $data) {
            // name, data, expiration, path, domain, secure, http only
            $expire = (null == $data) ? time()-31536000 : time()+31536000;
            $data = (null == $data) ? '' : $data;
            setcookie($cookie, $data, $expire,
                      Pluf::f('cookie_path', '/'), 
                      Pluf::f('cookie_domain', null), 
                      Pluf::f('cookie_secure', false), 
                      Pluf::f('cookie_httponly', true)); 
        }
        */
    }
}


/**
 * The request object. 
 *
 * It is given to the view as first argument.
 */
class Request
{
    public $mreq = null;
    public $path = '';
    public $GET = array();
    public $query = '';
    public $POST = array();
    public $FILES = array();
    /*


    public $REQUEST = array();
    public $COOKIE = array();
    public $FILES = array();

    public $method = '';
    public $uri = '';
    public $view = '';
    public $remote_addr = '';
    public $http_host = '';
    public $SERVER = array();
    public $uid = '';
    public $time = '';
    */

    /**
     * Request object provided to the Photon views.
     *
     * @param &$mess Mongrel2 request message object.
     */
    function __construct(&$mess)
    {
        $this->mess = $mess;
        $this->path = $this->mess->path;

        if (isset($this->mess->headers->QUERY)) {
            \mb_parse_str($this->mess->headers->QUERY, $this->GET);
            $this->query = $this->mess->headers->QUERY;
        }
        if ($this->mess->headers->METHOD == 'POST') {
            if (0 === strpos($this->mess->headers->{'content-type'}, 'multipart/form-data; boundary=')) {
                $parser = new \photon\http\multipartparser\MultiPartParser($mess->headers, $mess->body);
                foreach ($parser->parse() as $part) {
                    if ($part['of_type'] == 'FIELD') {
                        $this->POST[$part['name']] = $part['data'];
                    } else {
                        $this->FILES[$part['name']] = $part;
                    }
                }
            } else {
                \mb_parse_str(stream_get_contents($mess->body), $this->POST);
            }
        }
        /*
        print_r(array($this->GET, $this->POST, $this->FILES));
        printf("Current memory: %s\n", memory_get_usage());
        printf("Max memory: %s\n", memory_get_peak_usage());
        */
        /*
        $this->POST =& $_POST;
        $this->GET =& $_GET;
        $this->REQUEST =& $_REQUEST;
        $this->COOKIE =& $_COOKIE;
        $this->FILES =& $_FILES;
        $this->query = $query;
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->uri = $_SERVER['REQUEST_URI'];
        $this->remote_addr = $_SERVER['REMOTE_ADDR'];
        $this->http_host = (isset($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : '';
        $this->SERVER =& $_SERVER;
        $this->uid = $GLOBALS['_PX_uniqid']; 
        $this->time = (isset($_SERVER['REQUEST_TIME'])) ? $_SERVER['REQUEST_TIME'] : time();
        */
    }
}
