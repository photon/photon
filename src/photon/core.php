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

class Exception extends \Exception {}

/**
 * Dispatch a request to the correct handler.
 *
 * Dispatching is based on the requested path and possibly some
 * headers like the host, remote address or whatever.
 */
class Dispatcher
{
    /**
     * Dispatch a Mongrel2 request object and returns the request
     * object and the response object.
     *
     * @param $req Photon request object.
     * @return array(Photon request, Photon response)
     */
    public static function dispatch($req)
    {
        // FUTUREOPT: One can generate the lists at the initialisation
        // of the server to avoid the repetitive calls to
        // method_exists.
        $middleware = array();
        foreach (Conf::f('middleware_classes', array()) as $mw) {
            $middleware[] = new $mw();
        }
        $response = false;
        try {
            foreach ($middleware as $mw) {
                if (method_exists($mw, 'process_request')) {
                    $response = $mw->process_request($req);
                    if ($response !== false) {
                        // $response is a response, the middleware has
                        // preempted the request and the possible
                        // corresponding view will not called.
                        break;
                    }    
                }
            }
            if ($response === false) {   
                $response = self::match($req);
            }
            // // FUTURE: Need an elegant way to handle these vary etc.
            // if ($response !== false && !empty($req->response_vary_on)) {
            //     $response->headers['Vary'] = $req->response_vary_on;
            // }
            $middleware = array_reverse($middleware);
            foreach ($middleware as $mw) {
                if (method_exists($mw, 'process_response')) {
                    $response = $mw->process_response($req, $response);
                }    
            }
        } catch (\Exception $e) {
            if (true !== Conf::f('debug', false)) {
                $response = new \photon\http\response\ServerError($e, $req);
            } else {
                $response = new \photon\http\response\ServerErrorDebug($e->getMessage());
                $response->setContent($e, $req);
            }
        }

        return array($req, $response);
    }

    /**
     * Match a query against the actions controllers.
     *
     * @see Pluf_HTTP_URL_reverse
     *
     * @param Pluf_HTTP_Request Request object
     * @return Pluf_HTTP_Response Response object
     */
    public static function match($req)
    {
        $checked = array();
        $views = Conf::f('urls', array());
        if ('@' !== $req->path[0]) {
            $to_match = substr($req->path, strlen(Conf::f('base_urls', '')));
        } else {
            $to_match = $req->path;
        }
        try {
            $n = count($views);
            $i = 0;
            while ($i < $n) {
                $ctl = $views[$i];
                $checked[] = $ctl;
                if (preg_match($ctl['regex'], $to_match, $match)) {
                    if (!isset($ctl['sub'])) {
                        return self::send($req, $ctl, $match);
                    } else {
                        // Go in the subtree
                        $views = $ctl['sub'];
                        $i = 0;
                        $n = count($views);
                        $to_match = substr($to_match, strlen($match[0]));
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
    public static function send($req, $ctl, $match)
    {
        $req->view = array($ctl, $match);

        if (is_array($ctl['view'])) {
            list($mn, $mv) = $ctl['view'];
            $m = new $mn();
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

        

        // $m = new $ctl['model']();
        // if (isset($m->{$ctl['method'] . '_precond'})) {
        //     // Here we have preconditions to respects. If the "answer"
        //     // is true, then ok go ahead, if not then it a response so
        //     // return it or an exception so let it go.
        //     $preconds = $m->{$ctl['method'] . '_precond'};
        //     if (!is_array($preconds)) {
        //         $preconds = array($preconds);
        //     }
        //     foreach ($preconds as $precond) {
        //         if (!is_array($precond)) {
        //             $res = call_user_func_array(explode('::', $precond),
        //                                         array(&$req)
        //                                         );
        //         } else {
        //             $res = call_user_func_array(explode('::', $precond[0]),
        //                                         array_merge(array(&$req),
        //                                                     array_slice($precond, 1))
        //                                         );
        //         }

        //         if ($res !== true) {
        //             return $res;
        //         }
        //     }
        // }

        // if (!isset($ctl['params'])) {
        //     return $m->$ctl['method']($req, $match);
        // } else {
        //     return $m->$ctl['method']($req, $match, $ctl['params']);
        // }
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
        $url = '';
        foreach ($regbase as $regex) {
            $url .= self::buildReverse($regex, $params);
        }
        //$url = $regbase[0] . $url;

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
            if ($view_name == $dview['name']) {
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
        preg_match('/^#\^?([^#\$]+)/', $url, $matches);

        return $matches[1];
    }
}