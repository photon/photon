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
use \photon\config\Container as Conf;
use \photon\http\response\Forbidden;
use \photon\http\response\Redirect;

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
        if (!$response || $response->status_code != 200 || is_string($response->content) === false || strlen($response->content) < 200) {

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

/**
 * Security Middleware.
 *
 * Various collection of security feature.
 * - HTTP Strict Transport Security (IETF RFC 6797), aka SSL Redirect
 * - HTTP Public Key Pinning (IETF RFC 7469)
 */
class Security
{
    private static $config = null;

    /*
     *  For unit-tests only
     */
    static public function clearConfig()
    {
        self::$config = null;
    }

    /*
     *  Returns the middleware configuration
     */
    static private function getConfig()
    {
        // Cache the config
        if (self::$config !== null) {
            return self::$config;
        }

        // Build the config
        $config = Conf::f('middleware_security', array());
        $default = array(
            'hsts' => false,
            'hsts_options' => array(
                'max-age' => 31536000, /* 365 days */
                'includeSubDomains' => true,
                'preload' => true,
            ),
            'hpkp' => false,
            'hpkp_options' => array(
                'pin-sha256' => array(/* Active Key and Backup Key as base64 string */),
                'max-age' => 31536000, /* 365 days */
                'includeSubDomains' => true,
                'report-uri' => false
            ),
            'ssl_redirect' => false,
            'nosniff' => true,
            'frame' => 'SAMEORIGIN',
            'xss' => '1',
            'csp' => false,
            'referrer' => 'strict-origin-when-cross-origin'
        );

        self::$config = array_replace_recursive($default, $config);
        return self::$config;
    }

    function process_request(&$request)
    {
        $config = self::getConfig();

        // SSL Redirect
        if (($config['ssl_redirect'] === true || $config['hsts'] === true) && isset($request->headers->URL_SCHEME) && isset($request->headers->host)) {
            if ($request->headers->URL_SCHEME === 'http') {
                return new Redirect('https://' . $request->headers->host . $request->path);
            }
        }

        return false;
    }

    function process_response($request, $response)
    {
        if ($response === false) {
            return false;
        }

        $config = self::getConfig();

        // HTTP Strict Transport Security
        if ($config['hsts'] === true) {
            $opts = $config['hsts_options'];

            $value = array(
                'max-age=' . $opts['max-age']
            );
            if ($opts['includeSubDomains'] === true) {
                $value[] = 'includeSubDomains';
            }
            if ($opts['preload'] === true) {
                $value[] = 'preload';
            }

            $response->headers['Strict-Transport-Security'] = implode('; ', $value);
        }

        // HTTP Public Key Pinning
        if ($config['hpkp'] === true) {
            $opts = $config['hpkp_options'];

            if (count($opts['pin-sha256']) > 0) {
                $value = array();
                foreach($opts['pin-sha256'] as $key) {
                    $value[] = 'pin-sha256="' . $key . '"';
                }
                $value[] = 'max-age=' . $opts['max-age'];
                if ($opts['includeSubDomains'] === true) {
                    $value[] = 'includeSubDomains';
                }
                if ($opts['report-uri'] !== false) {
                    $value[] = 'report-uri="' . $opts['report-uri'] . '"';
                }

                $response->headers['Public-Key-Pins'] = implode('; ', $value);
            }
        }

        // X-Content-Type-Options
        // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Content-Type-Options
        if ($config['nosniff'] === true) {
            $response->headers['X-Content-Type-Options'] = 'nosniff';
        }

        // X-Frame-Options
        // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Frame-Options
        if (in_array($config['frame'], array('SAMEORIGIN', 'DENY'))) {
            $response->headers['X-Frame-Options'] = $config['frame'];
        }

        // X-XSS-Protection
        // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-XSS-Protection
        if ($config['xss'] !== false) {
            $response->headers['X-XSS-Protection'] = $config['xss'];
        }

        // CSP
        // https://www.w3.org/TR/CSP2/
        if ($config['csp'] !== false) {
            $response->headers['Content-Security-Policy'] = $config['csp'];
        }

        // Referrer
        // https://www.w3.org/TR/referrer-policy/
        $allowReferrers = array(
            '',
            'no-referrer',
            'no-referrer-when-downgrade',
            'same-origin',
            'origin',
            'strict-origin',
            'origin-when-cross-origin',
            'strict-origin-when-cross-origin',
            'unsafe-url'
        );
        if (in_array($config['referrer'], $allowReferrers)) {
            $response->headers['Referrer-Policy'] = $config['referrer'];
        }

        return $response;
    }
}

/**
* Translation middleware.
*
* Load the translation of the website based on the useragent.
*/
class Translation
{
    private $sessionName = false;
    private $cookieName = false;
    private $langAvailable = false;

    public function __construct()
    {
        $this->sessionName = Conf::f('lang_session', 'lang');
        $this->cookieName = Conf::f('lang_cookie', 'lang');
        $this->langAvailable = Conf::f('languages', array('en'));
    }


    /**
    * Process the request.
    *
    * Find which language to use. By priority:
    * 1. a session value
    * 2. a cookie
    * 3. the browser Accept-Language header
    *
    * @param $request The request
    * @return bool false
    */
    public function process_request(&$request)
    {
        $lang = false;

        // Session
        if (!empty($request->session)) {
            $lang = isset($request->session[$this->sessionName]) ? $request->session[$this->sessionName] : false;
            if ($lang && !in_array($lang, $this->langAvailable)) {
                $lang = false;
            }
        }

        // Cookie
        if ($lang === false && isset($request->COOKIE[$this->cookieName])) {
            $lang = $request->COOKIE[$this->cookieName];
            if ($lang && !in_array($lang, $this->langAvailable)) {
                $lang = false;
            }
        }

        // HTTP Header
        if ($lang === false && isset($request->headers->{'accept-language'})) {
            $lang = \photon\translation\Translation::getAcceptedLanguage($this->langAvailable, $request->headers->{'accept-language'});
        }

        if ($lang === false) {
            $lang = \photon\translation\Translation::getAcceptedLanguage($this->langAvailable);
        }

        if ($lang !== false) {
            \photon\translation\Translation::setLocale($lang);
            $request->i18n_code = $lang;
        }

        return false;
    }

    /**
    * Process the response of a view.
    *
    */
    public function process_response($request, $response)
    {
        if (isset($request->i18n_code) === false) {
            return $response;
        }

        $vary_h = array();

        // Save language in session or Cookie
        if (isset($request->session)) {
            $request->session[$this->sessionName] = $request->i18n_code;
        } else {
            $response->COOKIE[$this->cookieName] = $request->i18n_code;
        }

        // Update HTTP headers : Vary, Content-Language
        if (!empty($response->headers['Vary'])) {
            $vary_h = preg_split('/\s*,\s*/', $response->headers['Vary'], -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!in_array('accept-language', $vary_h)) {
            $vary_h[] = 'accept-language';
        }

        $response->headers['Vary'] = implode(', ', $vary_h);
        $response->headers['Content-Language'] = $request->i18n_code;

        return $response;
    }
}
