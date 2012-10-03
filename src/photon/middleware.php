<?php 
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, High Performance PHP Framework.
# Copyright (C) 2010, 2011 Loic d'Anterroches and contributors.
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
 * Collection of middleware.
 */
namespace photon\middleware;

use \photon\http\response\Forbidden as Forbidden;
use \photon\config\Container as Conf;

/**
 * Compress the rendered page.
 *
 * This middleware compresses content if the browser allows gzip or
 * deflate compression.  It sets the Vary header accordingly, so that
 * caches will base their storage on the Accept-Encoding header. It
 * will use deflate when possible as faster than gzip.
 *
 * It is a rewrite of the corresponding Django middleware.
 */
class Gzip
{
    public function process_response($request, $response)
    {
        // It's not worth compressing non-OK or really short responses.
        if (!$response || $response->status_code != 200 || strlen($response->content) < 200) {

            return $response;
        }
        // We patch already because if we have IE being the first
        // asking through a proxy, the proxy could cache without
        // taking into account the Accept-Encoding as it may not be
        // applied for it and thus a good browser afterward would not
        // benefit from the gzip version.
        \photon\http\HeaderTool::updateVary($response, 
                                            array('Accept-Encoding'));

        // Avoid gzipping if we've already got a content-encoding.
        if (isset($response->headers['Content-Encoding'])) {

            return $response;
        }
        $ctype = strtolower($response->headers['Content-Type']);
        // MSIE have issues with gzipped respones of various content types.
        if (false !== strpos(strtolower($request->getHeader('user-agent')), 'msie')) {
            if (0 !== strpos($ctype, 'text/') 
                || false !== strpos($ctype, 'javascript')) {

                return $response;
            }
        }
        // We do not recompress zip files and compressed files
        if (false !== strpos($ctype, 'zip') ||
            false !== strpos($ctype, 'compressed')) {

            return $response;
        }
        $accept = $request->getHeader('accept-encoding');
        // deflate is the fastest, so first
        $methods = array('deflate' => 'gzdeflate', 
                         'gzip' => 'gzencode'); 

        foreach ($methods as $encoding => $encoder) {
            if (preg_match('/\b' . $encoding . '\b/i', $accept)) {
                $response->content = $encoder($response->content);
                $response->headers['Content-Encoding'] = $encoding;
                $response->headers['Content-Length'] = strlen($response->content);
                break;
            }
        }

        return $response;
    }
}


/**
 * Cross Site Request Forgery Middleware.
 *
 * This class provides a middleware that implements protection against
 * request forgeries from other sites. This middleware must be before
 * your session middleware. It is activated if the user has a session.
 *
 * Based on concepts from the Django CSRF middleware.
 */
class Csrf
{
    public static function makeToken($session_key)
    {
        return \hash_hmac('sha1', $session_key, Conf::f('secret_key'));
    }

    /**
     * Process the request.
     *
     * When processing the request, if a POST request with a session,
     * we will check that the token is available and valid.
     *
     * @param Pluf_HTTP_Request The request
     * @return bool false
     */
    function process_request(&$request)
    {
        if ($request->method != 'POST') {
            return false;
        }
        $cookie_name = Conf::f('session_cookie_name', 'sid');
        if (!isset($request->COOKIE[$cookie_name])) {
            // no session, nothing to do
            return false;
        }
        if (!isset($request->POST['csrfmiddlewaretoken'])) {
            return new Forbidden($request);
        }
        $token = self::makeToken($request->COOKIE[$cookie_name]);
        if ($request->POST['csrfmiddlewaretoken'] != $token) {
            return new Forbidden($request);
        }
        return false;
    }

    /**
     * Process the response of a view.
     *
     * If we find a POST form, add the token to it.
     *
     * @param Pluf_HTTP_Request The request
     * @param Pluf_HTTP_Response The response
     * @return Pluf_HTTP_Response The response
     */
    function process_response($request, $response)
    {
        $cookie_name = Conf::f('session_cookie_name', 'sid');
        if (!isset($request->COOKIE[$cookie_name])) {
            // no session, nothing to do
            return $response;
        }
        if (!isset($response->headers['Content-Type'])) {
            return $response;
        }
        $ok = false;
        $cts = array('text/html', 'application/xhtml+xml');
        foreach ($cts as $ct) {
            if (false !== strripos($response->headers['Content-Type'], $ct)) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            return $response;
        }
        $token = self::makeToken($request->COOKIE[$cookie_name]);
        $extra = '<div style="display:none;"><input type="hidden" name="csrfmiddlewaretoken" value="'.$token.'" /></div>';
        $response->content = preg_replace('/(<form\W[^>]*\bmethod=(\'|"|)POST(\'|"|)\b[^>]*>)/i', '$1'.$extra, $response->content);
        return $response;
    }
}