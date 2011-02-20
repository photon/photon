<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, The High Performance PHP Framework.
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
 * Tools and class to facilitate the testing of your code.
 */
namespace photon\test;

use photon\config\Container as Conf;

class Exception extends \Exception {}

/**
 * Load the configuration from the $_ENV variable for the tests.
 */
class Config
{
    public static function load()
    {
        $config = array('tmp_folder' => sys_get_temp_dir(),
                        'debug' => true,
                        'secret_key' => 'SECRET_KEY');
        $init_file = $_ENV['photon_config'];
        if (file_exists($init_file)) {
            $init = include $init_file;
            $config = array_merge($config, $init);
        }
        $config['runtests'] = true;
        \photon\config\Container::load($config);
    }
}

/**
 * Emulates a client to call your views during unit testing.
 * 
 * Usage:
 * <code>
 * $client = new \photon\test\Client($extra_request_headers);
 * $response = $client->get('/the/page/', array('var'=>'toto'));
 * $response is now the Response
 * </code>
 *
 * The system is smart enough to keep track of the cookies. So, if you
 * set cookies, you get them back too. You can set/clear the
 * cookies. Do not forget that the cookies are signed, so when you
 * set, you can set with automatic signature or setRawCookie() to set
 * manually the full cookie data.
 *
 * The urls to be dispatched must be available with Conf::f('urls').
 *
 */
class Client
{
    public $views = '';
    public $dispatcher = '';
    public $cookies = array();
    public $headers = array();

    public function __construct($headers=array())
    {
        $this->headers = $headers;
    }
    
    /**
     * Build the base headers of a request.
     *
     * The headers are then used to build the corresponding mongrel2
     * request object.
     */
    public function buildHeaders($headers=array())
    {
        return (object) array_merge($this->headers, $headers);
    }

    protected function clean($keepcookies=true)
    {
        $_REQUEST = array();
        if (!$keepcookies) {
            $_COOKIE = array();
            $this->cookies = array();
        }
        $_SERVER = array();
        $_GET = array();
        $_POST = array();
        $_FILES = array();
        $_SERVER['REQUEST_METHOD'] = '';
        $_SERVER['REQUEST_URI'] = '';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_HOST'] = 'localhost';
    }

    protected function dispatch($page)
    {
        $GLOBALS['_PX_tests_templates'] = array();
        $_SERVER['REQUEST_URI'] = $page;
        foreach ($this->cookies as $cookie => $data) {
            $_COOKIE[$cookie] = $data;
        }
        ob_implicit_flush(False);
        list($request, $response) = $this->dispatcher->dispatch($page);
        ob_start();
        $response->render();
        $content = ob_get_contents(); 
        ob_end_clean();
        $response->content = $content;
        $response->request = $request;
        if (isset($GLOBALS['_PX_tests_templates'])) {
            if (count($GLOBALS['_PX_tests_templates']) == 1) {
                $response->template = $GLOBALS['_PX_tests_templates'][0];
            } else {
                $response->template = $GLOBALS['_PX_tests_templates'];
            }
        }
        foreach ($response->cookies as $cookie => $data) {
            $_COOKIE[$cookie] = $data;
            $this->cookies[$cookie] = $data;
        }
        return $response;
    }
    
    public function getUriQuery($page, $params)
    {
        $uri = $page;
        $query = '';
        if (count($params)) {
            $query = http_build_query($params);
            $uri .= '?' . $query;
        }
        return array($uri, $query);
    }        

    public function get($page, $params=array(), $follow_redirect=true) 
    {
        list($uri, $query) = $this->getUriQuery($page, $params);
        $headers = array('VERSION' => 'HTTP/1.1',
                         'METHOD' => 'GET',
                         'URI' => $uri,
                         'QUERY' => $query,
                         'PATH' => $page);
        $headers = $this->buildHeaders($headers);
        $msg = new \photon\mongrel2\Message('dummy', 'dummy', 
                                            $page, $headers, '');
        $req = new \photon\http\Request($msg);
        list($req, $resp) = \photon\core\Dispatcher::dispatch($msg);
        if ($follow_redirect && isset($resp->status_code) 
            && 302 === $resp->status_code) {
            list($page, $params) = $this->parseRedirect($response->headers['Location']);
            $resp = $this->get($page, $params);
        }
        return $resp;
    }


    public function post($page, $params=array(), $files=array()) 
    {
        $this->clean();
        $_POST = $params;
        $_REQUEST = $params;
        $_FILES = $files; //FIXME need to match the correct array structure
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $response = $this->dispatch($page);
        if ($response->status_code == 302) {
            list($page, $params) = $this->parseRedirect($response->headers['Location']);
            return $this->get($page, $params);
        }
        return $response;
    }

    public function parseRedirect($location)
    {
        $page = parse_url($location, PHP_URL_PATH);
        $query = parse_url($location, PHP_URL_QUERY);
        $params = array();
        if (strlen($query)) {
            parse_str($query, $params);
        }
        return array($page, $params);
    }
}

