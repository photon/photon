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
 * Photon Core.
 *
 * The core is just the dispatch loop.
 */
namespace photon\core;

use photon\config\Container as Conf;
use photon\log\Timer as Timer;
use photon\log\Log as Log;
use photon\event\Event;

class Exception extends \Exception {}
class NotImplemented extends \Exception {}

abstract class Middleware
{
    protected $classes;
    protected $middleware;

    public function __construct()
    {
        $this->classes = array();
        $this->middleware = array();
    }

    /**
     * Create the middleware cache
     */
    protected function createCache()
    {
        // Clear previous middleware
        $this->middleware = array();

        // Load middleware
        foreach ($this->classes as $mw) {
            $obj = new $mw();
            $process_request = false;
            $process_response = false;

            if (method_exists($mw, 'process_request')) {
                $process_request = true;
            }

            if (method_exists($mw, 'process_response')) {
                $process_response = true;
            }

            $this->middleware[] = array($obj, $process_request, $process_response);
        }
    }

    /**
     * Process the request for all Middleware installed.
     */
    public function process_request(&$request)
    {
        // direct read
        foreach($this->middleware as $current) {
            list($obj, $process_request, $process_response) = $current;
            if ($process_request) {
                $response = $obj->process_request($request);
                if ($response !== false) {
                    return $response;
                }
            }
        }

        return false;
    }

    /**
     * Process the response for all Middleware installed.
     */
    public function process_response($request, $response)
    {
        // reverse read
        for (end($this->middleware); key($this->middleware) !== null; prev($this->middleware)) {
            list($obj, $process_request, $process_response) = current($this->middleware);
            if ($process_response) {
                $response = $obj->process_response($request, $response);
            }
        }

        return $response;
    }
}

/*
 *  Dummy middleware
 */
class MiddlewareDummy extends Middleware
{
}

/**
 * Load middleware from the configuration
 */
class MiddlewareFromConfig extends Middleware
{
    private $key;

    /*
     *  Create the cache for middleware
     */
    public function __construct($key='middleware_classes')
    {
        parent::__construct();
        $this->key = $key;

        // Create initial cache
        $this->reload();

        // Reload cache if notified
        Event::connect('Middleware::updated', array($this, 'reload'));
    }

    public function reload()
    {
        $this->classes = Conf::f($this->key, array());
        $this->createCache();
    }
}


/**
 * Dispatch a request to the correct handler.
 *
 * Dispatching is based on the requested path and possibly some
 * headers like the host, remote address or whatever.
 */
class Dispatcher
{
    /*
     *  Enable the debug mode of the dispatcher
     *  During debug, the dispatcher show complete backtrace and may include sensitive data.
     *  Ensure to set to false in production
     */
    private $debug = false;

    /*
     *  Middleware to apply to each requests
     */
    private $mw = null;

    /*
     *  Views definitions of the application
     */
    private $views = array();

    /*
     *  URL prefix
     *  Usefull if you are hosted in a sub-folder of a domain
     */
    private $prefix = '';

    /*
     *  Timer configuration
     */
    public $timer = 'photon.dispatch';

    public function __construct($views=null, $middleware=null, $prefix=null, $debug=null)
    {
        // Initialize views routing table
        if ($views === null) {
            $views = Conf::f('urls', array());
        }
        $this->views = $views;

        // Initialize middleware
        if ($middleware === null) {
            $middleware = new MiddlewareFromConfig;
        } 
        $this->mw = $middleware;

        if ($prefix === null) {
            $prefix = Conf::f('base_urls', '');
        }
        $this->prefix = $prefix;

        // Cache the debug status
        if ($debug === null) {
            $debug = Conf::f('debug', false);
        }
        $this->debug = (bool) $debug;
    }

    /**
     * Dispatch a Photon request object and returns the request
     * object and the response object.
     *
     * @param $req Photon request object.
     * @return array(Photon request, Photon response)
     */
    public function dispatch($req)
    {
        Timer::start($this->timer);

        $response = false;
        try {
            $response = $this->mw->process_request($req);
            if ($response === false) {   
                $response = self::match($req);
            }
            $response = $this->mw->process_response($req, $response);
        } catch (\Exception $e) {

            Event::send('\photon\core\Dispatcher::dispatchException', null, $e);

            // Render a HTTP 500 page
            if ($this->debug) {
                $response = new \photon\http\response\ServerErrorDebug($e, $req);
                $response->setContent($e, $req);
            } else {
                $response = new \photon\http\response\ServerError($e);
            }
        }

        $view_name = isset($req->view[0]['name']) 
            ? $req->view[0]['name'] 
            : 'not_defined';
        Log::perf(array($this->timer, $req->uuid, 
                        Timer::stop($this->timer),
                        $view_name,
                        array($req->method, $req->path)));

        return array($req, $response);
    }

    /**
     * Match a query against the URL map.
     *
     * @param $req \photon\http\Request
     * @return mixed Response object or false
     */
    private function match($req)
    {
        $checked = array();
        $views = $this->views;
        if ('@' !== $req->path[0]) {
            $to_match = substr($req->path, strlen($this->prefix));
        } else {
            $to_match = $req->path;
        }
        
        $url = '';
        $matchs = array();
        try {
            $n = count($views);
            $i = 0;
            while ($i < $n) {
                $ctl = $views[$i];
                $checked[] = $ctl;
                if (preg_match($ctl['regex'], $to_match, $match)) {
                    $match0 = array_shift($match);
                    $url .= $match0;
                    $matchs = array_merge($matchs, $match);
                
                    if (!isset($ctl['sub'])) {
                        array_unshift($matchs, $url);
                        return self::send($req, $ctl, $matchs);
                    } else {
                        // Go in the subtree
                        $views = $ctl['sub'];
                        $i = 0;
                        $n = count($views);
                        $to_match = substr($to_match, strlen($match0));
                        continue;
                    }
                }
                ++$i;
            }
        } catch (\photon\http\error\NotFound $e) { 
            // We catch only the not found errors at the moment.
        }

        $response = new \photon\http\response\NotFound($req);
        $response->dispatch_path = $checked;

        return $response;
    }

    /**
     * Call the view found by self::match.
     *
     * The called view can throw an exception. This is fine and
     * normal.
     *
     * @param $req Photon request 
     * @param $ctl The url definition matching the request
     * @param $matches The matches found by preg_match
     * @return mixed Response or None
     */
    private function send($req, $ctl, $match)
    {
        Log::debug(array('photon.dispatch.send', $req->uuid, 
                         array($ctl, $match)));
        $req->view = array($ctl, $match);

        if (is_array($ctl['view'])) {
            list($mn, $mv) = $ctl['view'];
            $m = new $mn();

            if (isset($m->{$mv . '_precond'})) {
                // Preconditions to respects. A precondition must return
                // true or a response object.
                $preconds = $m->{$mv . '_precond'};
                foreach ($preconds as $precond) {
                    $res = call_user_func_array($precond, array(&$req));
                    if ($res !== true) {
                        return $res;
                    }
                }
            }

            if (!isset($ctl['params'])) {
                return $m->{$mv}($req, $match);
            } else {
                return $m->{$mv}($req, $match, $ctl['params']);
            }
        } else {
            // simple callable function
            $v = $ctl['view'];
            if (!isset($ctl['params'])) {
                return $v($req, $match);
            } else {
                return $v($req, $match, $ctl['params']);
            }
        }
    }
}


/**
 * Generate a ready to use URL to be used in location/redirect or forms.
 *
 * When redirecting a user, depending of the format of the url with
 * mod_rewrite or not, the parameters must all go in the GET or
 * everything but the action. This class provide a convinient way to
 * generate those url and parse the results for the dispatcher.
 */
class URL
{
    /**
     * Generate the URL.
     *
     * The & is encoded as &amp; in the url.
     *
     * @param $action Action url
     * @param $params Associative array of the parameters (array())
     * @param $encode Encode the & in the url (true)
     * @return string Ready to use URL.
     */
    public static function generate($action, $params=array(), $encode=true)
    {
        $url = $action;
        if (count($params)) {
            $url .= '?' . http_build_query($params, '', ($encode) ? '&amp;' : '&');
        }
        return $url;
    }

    /**
     * Provide the full URL (without domain) to a view.
     *
     * @param string View.
     * @param array Parameters for the view (array()).
     * @param array Extra GET parameters for the view (array()).
     * @param bool Should the URL be encoded (true).
     * @return string URL.
     */
    public static function forView($view, $params=array(), $get_params=array(), $encoded=true)
    {
        return self::generate(Conf::f('base_urls') .
                              self::reverse(Conf::f('urls', array()), $view, $params), 
                              $get_params, $encoded);
    }

    /**
     * Reverse an URL.
     *
     * @param $views Array of all the views
     * @param $view_name Name of the view
     * @param $params Parameters for the view
     * @return string URL.
     */
    public static function reverse($views, $view_name, $params=array())
    {
        $regbase = array();
        $regbase = self::find($views, $view_name, $regbase);
        if (false === $regbase) {
            throw new Exception(sprintf('Error, the view: %s has not been found.', $view_name));
        }

        $regex = implode('', $regbase);
        $regex = str_replace(array('$##^', '##^', '##'), '', $regex);
        $url = self::buildReverse($regex, $params);

        return $url;
    }


    /**
     * Go in the list of views to find the matching one.
     *
     * @param $views Array of the views
     * @param $view_name View to find
     * @param $regbase Regex of the view up to now and base
     * @return mixed Regex of the view or false
     */
    public static function find($views, $view_name, $regbase)
    {
        foreach ($views as $dview) {
            if (isset($dview['sub'])) {
                $regbase2 = $regbase;
                $regbase2[] = $dview['regex'];
                $res = self::find($dview['sub'], $view_name, $regbase2);
                if ($res) {

                    return $res;
                }
                continue;
            }
            if (isset($dview['name']) && $view_name == $dview['name']) {
                $regbase[] = $dview['regex'];

                return $regbase;
            }
        }

        return false;
    }

    /**
     * Build the reverse URL without the path base.
     *
     * Credits to Django, again...
     *
     * @param string Regex for the URL.
     * @param array Parameters
     * @return string URL filled with the parameters.
     */
    public static function buildReverse($url_regex, $params=array())
    {
        $url = str_replace(array('\\.', '\\-'), array('.', '-'), $url_regex);
        if (count($params)) {
            $groups = array_fill(0, count($params), '#\(([^)]+)\)#'); 
            $url = preg_replace($groups, $params, $url, 1);
        }
        preg_match('/^#\^?([^#\$\^]+)/', $url, $matches);

        return isset($matches[1]) ? $matches[1] : '';
    }
}
