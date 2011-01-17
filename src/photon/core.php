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
     * @param $mreq Request Mongrel2 request object.
     * @return array(Photon request, Photon response)
     */
    public static function dispatch(&$mreq)
    {
        $req = new \photon\http\Request($mreq);

        // FOPT: One can generate the lists at the initialisation of
        // the server to avoid the repetitive calls to method_exists.
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
            if (!empty($req->response_vary_on)) {
                $response->headers['Vary'] = $req->response_vary_on;
            }
            $middleware = array_reverse($middleware);
            foreach ($middleware as $mw) {
                if (method_exists($mw, 'process_response')) {
                    $response = $mw->process_response($req, $response);
                }    
            }
        } catch (\Exception $e) {
            if (true !== Conf::f('debug', false)) {
                $response = new Pluf_HTTP_Response_ServerError($e, $req);
            } else {
                $response = new Pluf_HTTP_Response_ServerErrorDebug($e);
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
    public static function match($req, $firstpass = true)
    {
        $views = Conf::f('urls', array());
        try {
            $to_match = $req->path;
            $n = count($views);
            $i = 0;
            while ($i < $n) {
                $ctl = $views[$i];
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
        } catch (\Exception $e) { // Need to only catch the 404 error exception
            // Need to add a 404 error handler
            // something like Pluf::f('404_handler', 'class::method')
        }

        return new \photon\http\response\NotFound($req);
    }

    /**
     * Call the view found by self::match.
     *
     * The called view can throw an exception. This is fine and
     * normal.
     *
     * @param Pluf_HTTP_Request Current request
     * @param array The url definition matching the request
     * @param array The match found by preg_match
     * @return Pluf_HTTP_Response Response object
     */
    public static function send($req, $ctl, $match)
    {
        $req->view = array($ctl, $match);
        /// $ctl['view'] is a callable.
        

        $m = new $ctl['model']();
        if (isset($m->{$ctl['method'] . '_precond'})) {
            // Here we have preconditions to respects. If the "answer"
            // is true, then ok go ahead, if not then it a response so
            // return it or an exception so let it go.
            $preconds = $m->{$ctl['method'] . '_precond'};
            if (!is_array($preconds)) {
                $preconds = array($preconds);
            }
            foreach ($preconds as $precond) {
                if (!is_array($precond)) {
                    $res = call_user_func_array(explode('::', $precond),
                                                array(&$req)
                                                );
                } else {
                    $res = call_user_func_array(explode('::', $precond[0]),
                                                array_merge(array(&$req),
                                                            array_slice($precond, 1))
                                                );
                }

                if ($res !== true) {
                    return $res;
                }
            }
        }

        if (!isset($ctl['params'])) {
            return $m->$ctl['method']($req, $match);
        } else {
            return $m->$ctl['method']($req, $match, $ctl['params']);
        }
    }
}
