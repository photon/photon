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
 * Collection of response objects.
 */
namespace photon\http\response;

use \photon\config\Container as Conf;
use \photon\core\URL as URL;
use \photon\http\Response as Response;
use \photon\mail\EMail as Mail;
use \photon\template as template;

class Created extends Response
{
    /**
     * The request has been fulfilled and resulted in a new resource being created.
     *
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.2.2
     */
    function __construct($content='')
    {
        parent::__construct($content);
        $this->status_code = 201;
    }
}

class Accepted extends Response
{
    /**
     * The request has been accepted for processing, but the processing has not been completed.
     * The request might or might not eventually be acted upon,
     * as it might be disallowed when processing actually takes place.
     *
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.2.3
     */
    function __construct($content='')
    {
        parent::__construct($content);
        $this->status_code = 202;
    }
}

class NoContent extends Response
{
    /**
     * The server successfully processed the request, but is not returning any content.
     * Usually used as a response to a successful delete request.
     *
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.2.5
     */
    function __construct()
    {
        parent::__construct();
        $this->status_code = 204;
    }
}

class MultiStatus extends Response
{
    /**
     * Define in WebDav HTTP Extensions
     * A Multi-Status response conveys information about multiple resources.
     * The default Multi-Status response body is a text/xml or application/xml HTTP entity with a 'multistatus' root element.
     *
     * @see http://www.webdav.org/specs/rfc4918.html#STATUS_207
     */
    public function __construct($content, $mimetype='application/xml; charset=utf-8')
    {
        parent::__construct($content, $mimetype);
        $this->status_code = 207;
    }
}

class Redirect extends Response
{
    /**
     * Redirect response to a given URL.
     *
     * @param string  $url  URL
     * @param integer $code A valid redirect code among 301, (302), 303 and 307.
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.3
     */
    function __construct($url, $code=302)
    {
        parent::__construct();
        $this->headers['Location'] = $url;
        $this->status_code = $code;
    }
}

class FormRedirect extends Redirect
{
    /**
     * Redirect response to a given URL.
     *
     * @param string  $url  URL
     * @param integer $code A valid redirect code among 301, 302, (303) and 307.
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.3
     */
    function __construct($url, $code=303)
    {
        parent::__construct($url, $code=303);
    }
}

class NotModified extends Response
{
    /**
     * Indicates that the resource has not been modified since the version specified by
     * the request headers If-Modified-Since or If-Match. This means that there is no need
     * to retransmit the resource, since the client still has a previously-downloaded copy.
     *
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.3.5
     */
    public function __construct($content='', $mimetype='text/html; charset=utf-8')
    {
        parent::__construct('', $mimetype);
        $this->status_code = 304;
    }
}

class BadRequest extends Response
{
    /**
     * The request could not be understood by the server due to malformed syntax.
     * The client SHOULD NOT repeat the request without modifications.
     *
     * @param Request The request object of the current page.
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.1
     */
    public function __construct($request=null)
    {
        $content = 'The request could not be understood by the server due to malformed syntax.'
            . "\n\n" . '400 - BadRequest';
        parent::__construct($content, 'text/plain');
        $this->status_code = 400;
    }
}

class AuthorizationRequired extends Response
{
    public function __construct()
    {
        $content = 'This server could not verify that you are authorized to access the document requested.' . "\n" .
                   'Either you supplied the wrong credentials (e.g., bad password), or your browser ' . 
                   'doesn\'t understand how to supply the credentials required.' . "\n\n" .
                   '401 - Authorization Required';
        parent::__construct($content, 'text/plain');
        $this->status_code = 401;
    }
}

class Forbidden extends Response
{
    /**
     * The server understood the request, but is refusing to fulfill it.
     *
     * @param Request The request object of the current page.
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.4
     */
    public function __construct($request=null)
    {
        $content = 'You are not authorized to view this page. You do not have permission' . "\n"
            . 'to view the requested directory or page using the credentials supplied.' . "\n\n"
            . '403 - Forbidden';
        parent::__construct($content, 'text/plain');
        $this->status_code = 403;
    }
}

class NotFound extends Response
{
    /**
     * The server has not found anything matching the Request-URI.
     * No indication is given of whether the condition is temporary or permanent.
     *
     * @param Request The request object of the current page.
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.5
     */
    public function __construct($request)
    {
        $content = sprintf('The requested URL %s was not found on this server.' . "\n" .
                           'Please check the URL and try again.' . "\n\n" . '404 - Not Found',
                           str_replace(array('&',     '"',      '<',    '>'),
                                       array('&amp;', '&quot;', '&lt;', '&gt;'),
                                       $request->path));
        parent::__construct($content, 'text/plain');
        $this->status_code = 404;
    }
}

class NotSupported extends Response
{
    /**
     * The method specified in the Request-Line is not allowed for the resource
     * identified by the Request-URI. The response MUST include an Allow header containing
     * a list of valid methods for the requested resource.
     *
     * @param Request The request object of the current page.
     * @param Allow The list of HTTP method allow for this URI.
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.6
     */
    public function __construct($request, $allow=array('GET'))
    {
        $content = sprintf('HTTP method %s is not supported for the URL %s.' 
                           . "\n" .
                           'Supported methods are: %s.' . "\n" .
                           '405 - Not Supported',
                           htmlspecialchars($request->method), 
                           str_replace(array('&',     '"',      '<',    '>'),
                                       array('&amp;', '&quot;', '&lt;', '&gt;'),
                                       $request->path),
                           implode ($allow, ', ')
                           );
        parent::__construct($content, 'text/plain');
        $this->headers['Allow'] = implode ($allow, ', ');
        $this->status_code = 405;
    }
}

class RequestEntityTooLarge extends Response
{
    /**
     * @param Request The request object of the current page.
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.14
     */
    public function __construct($body=null)
    {
        if ($body === null) {
            $body = 'The request is larger than the server is willing or able to process.' . "\n" .
                    '413 - Request Entity Too Large';
        }

        parent::__construct($body, 'text/plain');
        $this->status_code = 413;
    }
}

class NotImplemented extends Response
{
    /**
     * The server does not support the functionality required to fulfill the request.
     * This is the appropriate response when the server does not recognize the request
     * method and is not capable of supporting it for any resource.
     *
     * @param Request The request object of the current page.
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.5.2
     */
    public function __construct($request)
    {
        $content = 'The server either does not recognize the request method, or it lacks the ability to fulfill the request' . "\n\n"
                   . '501 - Not Implemented';
        parent::__construct($content, 'text/plain');
        $this->status_code = 501;
    }
}


class ServiceUnavailable extends Response
{
    /**
     * The server is currently unable to handle the request due to a temporary overloading
     * or maintenance of the server. The implication is that this is a temporary condition
     * which will be alleviated after some delay. If known, the length of the delay MAY
     * be indicated in a Retry-After header.
     *
     * @param Request The request object of the current page.
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.5.4
     */
    public function __construct($request, $retryAfter=300)
    {
        $content = 'The server is currently unable to handle the request' . "\n\n"
                   . '501 - Service Unavailable';
        parent::__construct($content, 'text/plain');
        $this->headers['Retry-After'] = $retryAfter;
        $this->status_code = 503;
    }
}


class RedirectToLogin extends Response
{
    /**
     * The $request object is used to know what the post login
     * redirect url should be.
     *
     * If the action url of the login page is not set, it will try to
     * get the url from the login view from the 'login_view'
     * configuration key.
     *
     * @param Request The request object of the current page.
     * @param string The full url of the login page (null)
     */
    function __construct($request, $loginurl=null)
    {
        $redirect = array('_redirect_after' => 
                          \photon\crypto\Sign::dumps($request->path, 
                                                     Conf::f('secret_key')));
        if ($loginurl !== null) {
            $url = URL::generate($loginurl, $redirect, false);
            $encoded = URL::generate($loginurl, $redirect);
        } else {
            $url = URL::forView(Conf::f('login_view', 'login_view'),
                                array(), $redirect, false);
            $encoded = URL::forView(Conf::f('login_view', 'login_view'), 
                                    array(), $redirect);
        }
        $content = sprintf(__('<a href="%s">Please, click here to be redirected</a>.'), $encoded);
        parent::__construct($content);
        $this->headers['Location'] = $url;
        $this->status_code = 302;
    }
}


class Json extends Response
{
    function render($output_body=true)
    {
        return json_encode($this->content);
    }
}

/**
 * Display a simple server error page.
 */
class InternalServerError extends Response
{
    function __construct()
    {
        $content = 'The server encountered an unexpected condition which prevented it from fulfilling your request.'."\n\n"
                .'An email has been sent to the administrators, we will correct this error as soon as possible. Thank you for your comprehension.'
                ."\n\n".'500 - Internal Server Error';

        parent::__construct($content, 'text/plain');
        $this->status_code = 500;
    }
}

/**
 * Display a server error page.
 *
 * If a 500.html template is available it will try to render it to
 * display a nice error. The error stack is sent to the administrators
 * of the website.
 */
class ServerError extends Response
{
    function __construct($exception, $mimetype=null)
    {
        $admins = Conf::f('admins', array());
        if (count($admins) > 0) {
            // Get a nice stack trace and send it by emails.
            $stack = pretty_server_error($exception);
            $subject = $exception->getMessage();
            $subject = substr(strip_tags(nl2br($subject)), 0, 50).'...';
            foreach ($admins as $admin) {
                $email = new Mail($admin[1], $admin[1], $subject);
                $email->addTextMessage($stack);
                $email->sendMail();
            }
        }

        try {
            $context = new template\Context(array('message' => $exception->getMessage()));
            $renderer = new template\Renderer('500.html');
            $content = $renderer->render($context);
            $mimetype = null;
        } catch (\Exception $e) {
            $mimetype = 'text/plain';
            $content = 'The server encountered an unexpected condition which prevented it from fulfilling your request.'."\n\n"
                .'An email has been sent to the administrators, we will correct this error as soon as possible. Thank you for your comprehension.'
                ."\n\n".'500 - Internal Server Error';
        }

        parent::__construct($content, $mimetype);
        $this->status_code = 500;
    }
}

/**
 * Generate a nice error message to be sent by email.
 *
 * @param $e Exception
 * @param $req Photon request
 * @return Error message
 */
function pretty_server_error($e, $req) 
{
    $sub = create_function('$f',
               '$loc = "";
               if (isset($f["class"])) {
                   $loc .= $f["class"].$f["type"];
               }
               if (isset($f["function"])) {
                   $loc.=$f["function"];
               }
               return $loc;');
    $src2lines = create_function('$file',
               '$src = nl2br(@highlight_file($file, TRUE));
               return explode("<br />", $src);');
    $clean = create_function('$line',
               'return html_entity_decode(str_replace("&nbsp;", " ", $line));');
    $desc = get_class($e)." making ".$req->method." request to ".$req->path;
    $out = $desc."\n";
    $out .= $e->getMessage()."\n\n";
    $out .= 'PHP: '.$e->getFile().', line '.$e->getLine()."\n";
    $out .= 'URI: '.$req->method.' '.$req->path."\n\n";
    $out .= '** Stacktrace **'."\n\n";
    $frames = $e->getTrace(); 
    foreach ($frames as $frame_id=>$frame) { 
        if (!isset($frame['file'])) {
            $frame['file'] = 'No File';
            $frame['line'] = '0';
        }
        $out .= '* '.$sub($frame).'
        ['.$frame['file'].', line '.$frame['line'].'] *'."\n";
        if (is_readable($frame['file']) ) { 
            $out .= '* Src *'."\n";
            $lines = $src2lines($frame['file']);
            $start = $frame['line'] < 5 ?
                0 : $frame['line'] -5; $end = $start + 10;
            $out2 = '';
            $i = 0;
            foreach ( $lines as $k => $line ) {
                if ( $k > $end ) { break; }
                $line = trim(strip_tags($line));
                // if ( $k < $start && isset($frames[$frame_id+1]["function"])
                //      && preg_match('/function( )*'.preg_quote($frames[$frame_id+1]["function"]).'/',
                //                    $line) ) {
                //     $start = $k;
                // }
                if ( $k >= $start ) {
                    if ( $k != $frame['line'] ) {
                        $out2 .= ($start+$i).': '.$clean($line)."\n"; 
                    } else {
                        $out2 .= '>> '.($start+$i).': '.$clean($line)."\n"; 
                    }
                    $i++;
                }
            }
            $out .= $out2;
        } else { 
            $out .= 'No src available.';
        } 
        $out .= "\n";
    } 
    $out .= "\n\n\n\n";
    $out .= '** Request **'."\n\n";
    $out .= 'Sender:   ' . $req->mess->sender . "\n";
    $out .= 'Path:     ' . $req->mess->path . "\n";
    $out .= 'Conn id:  ' . $req->mess->conn_id . "\n";

    $out .= "\n".'* Request headers *'."\n\n";
    foreach ($req->mess->headers as $hdr => $val) {
        $out .= 'Variable: ' . $hdr . "\n";
        $out .= 'Value:    ' . $val . "\n";
    }
    $out .= "\n".'* Request body *'."\n\n";
    $out .= (string) $req->mess->body . "\n\n";

    return $out;
}

/**
 * Debug version of a server error.
 *
 * Returns a nice HTML page with information about the error.
 */
class ServerErrorDebug extends Response
{
    /**
     * Debug version of a server error.
     *
     * @param Exception The exception being raised.
     * @param $mimetype string Mime type
     */
    function __construct($e, $req)
    {
        $content = html_pretty_server_error($e, $req);

        parent::__construct($content, 'text/html');
        $this->status_code = 500;
    }
}

/**
 * @credits http://www.sitepoint.com/blogs/2006/04/04/pretty-blue-screen/
 */
function html_pretty_server_error($e, $req) 
{
    $o = function ($in) {
        return htmlspecialchars($in);
    };

    $sub = function($f) {
        $loc = '';
        if (isset($f['class'])) {
            $loc .= $f['class'] . $f['type'];
        }
        if (isset($f['function'])) {
            $loc .= $f['function'];
        }
        if (!empty($loc)) {
            $loc = htmlspecialchars($loc);
            $loc = '<strong>' . $loc . '</strong>';
        }
        return $loc;
    };

    $parms = function ($f) {
        $params = array();
        if (isset($f['function'])) {
            try {
                if (isset($f['class'])) {
                    $r = new \ReflectionMethod($f['class'] 
                                              . '::' . $f['function']);
                } else {
                    $r = new \ReflectionFunction($f['function']);
                }
                return $r->getParameters();
            } catch (\Exception $e) {
                // Do nothing
            }
        }
        return $params;
    };

    $src2lines = function ($file) {
        $src = nl2br(@highlight_file($file, true));
        return explode('<br />', $src);
    };

    $clean = function ($line) { 
        return trim(strip_tags($line)); 
    };

    $desc = get_class($e)." making ".$req->method." request to ".$req->path;
    $out = '
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
  "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8" />
  <meta name="robots" content="NONE,NOARCHIVE" />
     <title>' . $o($desc) . '</title>
  <style type="text/css">
    html * { padding:0; margin:0; }
    body * { padding:10px 20px; }
    body * * { padding:0; }
    body { font:small sans-serif; background: #70DBFF; }
    body>div { border-bottom:1px solid #ddd; }
    h1 { font-weight:normal; }
    h2 { margin-bottom:.8em; }
    h2 span { font-size:80%; color:#666; font-weight:normal; }
    h2 a { text-decoration:none; }
    h3 { margin:1em 0 .5em 0; }
    h4 { margin:0.5em 0 .5em 0; font-weight: normal; font-style: italic; }
    table { 
        border:1px solid #ccc; border-collapse: collapse; background:white; }
    tbody td, tbody th { vertical-align:top; padding:2px 3px; }
    thead th { 
        padding:1px 6px 1px 3px; background:#70FF94; text-align:left; 
        font-weight:bold; font-size:11px; border:1px solid #ddd; }
    tbody th { text-align:right; color:#666; padding-right:.5em; }
    table.vars { margin:5px 0 2px 40px; }
    table.vars td, table.req td { font-family:monospace; }
    table td { background: #70FFDB; }
    table td.code { width:95%;}
    table td.code div { overflow:hidden; }
    table.source th { color:#666; }
    table.source td { 
        font-family:monospace; white-space:pre; border-bottom:1px solid #eee; }
    ul.traceback { list-style-type:none; }
    ul.traceback li.frame { margin-bottom:1em; }
    div.context { margin:5px 0 2px 40px; background-color:#70FFDB; }
    div.context ol { 
        padding-left:30px; margin:0 10px; list-style-position: inside; }
    div.context ol li { 
        font-family:monospace; white-space:pre; color:#666; cursor:pointer; }
    div.context li.current-line { color:black; background-color:#70FF94; }
    div.commands { margin-left: 40px; }
    div.commands a { color:black; text-decoration:none; }
    p.headers { background: #70FFDB; font-family:monospace; }
    #summary { background: #00B8F5; }
    #summary h2 { font-weight: normal; color: #666; }
    #traceback { background:#eee; }
    #request { background:#f6f6f6; }
    #response { background:#eee; }
    #summary table { border:none; background:#00B8F5; }
    #summary td  { background:#00B8F5; }
    .switch { text-decoration: none; }
    .whitemsg { background:white; color:black;}
  </style>
  <script type="text/javascript">
  //<!--
    function getElementsByClassName(oElm, strTagName, strClassName){
        // Written by Jonathan Snook, http://www.snook.ca/jon; 
        // Add-ons by Robert Nyman, http://www.robertnyman.com
        var arrElements = (strTagName == "*" && document.all)? document.all :
        oElm.getElementsByTagName(strTagName);
        var arrReturnElements = new Array();
        strClassName = strClassName.replace(/\-/g, "\\-");
        var oRegExp = new RegExp("(^|\\s)" + strClassName + "(\\s|$)");
        var oElement;
        for(var i=0; i<arrElements.length; i++){
            oElement = arrElements[i];
            if(oRegExp.test(oElement.className)){
                arrReturnElements.push(oElement);
            }
        }
        return (arrReturnElements)
    }
    function hideAll(elems) {
      for (var e = 0; e < elems.length; e++) {
        elems[e].style.display = \'none\';
      }
    }
    function toggle() {
      for (var i = 0; i < arguments.length; i++) {
        var e = document.getElementById(arguments[i]);
        if (e) {
          e.style.display = e.style.display == \'none\' ? \'block\' : \'none\';
        }
      }
      return false;
    }
    function varToggle(link, id, prefix) {
      toggle(prefix + id);
      var s = link.getElementsByTagName(\'span\')[0];
      var uarr = String.fromCharCode(0x25b6);
      var darr = String.fromCharCode(0x25bc);
      s.innerHTML = s.innerHTML == uarr ? darr : uarr;
      return false;
    }
    function sectionToggle(span, section) {
      toggle(section);
      var span = document.getElementById(span);
      var uarr = String.fromCharCode(0x25b6);
      var darr = String.fromCharCode(0x25bc);
      span.innerHTML = span.innerHTML == uarr ? darr : uarr;
      return false;
    }
    
    window.onload = function() {
      hideAll(getElementsByClassName(document, \'table\', \'vars\'));
      hideAll(getElementsByClassName(document, \'div\', \'context\'));
      hideAll(getElementsByClassName(document, \'ul\', \'traceback\'));
      hideAll(getElementsByClassName(document, \'div\', \'section\'));
    }
    //-->
  </script>
</head>
<body>

<div id="summary">
  <h1>' . $o($desc) . '</h1>
  <h2>';
    $out .= ' ' . $o($e->getMessage()) . '</h2>
  <table>
    <tr>
      <th>PHP</th>
      <td>' . $o($e->getFile()) . ', line ' . $o($e->getLine()) . '</td>
    </tr>
    <tr>
      <th>URI</th>
      <td>' . $o($req->method . ' ' . $req->path) . '</td>
    </tr>
  </table>
</div>

<div id="traceback">
  <h2>Stacktrace
    <a href=\'#\' onclick="return sectionToggle(\'tb_switch\',\'tb_list\')">
    <span id="tb_switch">▶</span></a></h2>
  <ul id="tb_list" class="traceback">';
    $frames = $e->getTrace(); 
    foreach ($frames as $frame_id=>$frame) { 
        if (!isset($frame['file'])) {
            $frame['file'] = 'No File';
            $frame['line'] = '0';
        }
        $out .= '<li class="frame">'.$sub($frame).'
        ['.$o($frame['file']).', line '.$o($frame['line']).']';
        if (isset($frame['args']) && count($frame['args']) > 0) {
            $params = $parms($frame);
            $out .= '
          <div class="commands">
              <a href=\'#\' onclick="return varToggle(this, \''.
              $o($frame_id).'\',\'v\')"><span>▶</span> Args</a>
          </div>
          <table class="vars" id="v'.$o($frame_id).'">
            <thead>
              <tr>
                <th>Arg</th>
                <th>Name</th>
                <th>Value</th>
              </tr>
            </thead>
            <tbody>';
            foreach ($frame['args'] as $k => $v) {
                $name = (isset($params[$k]) and isset($params[$k]->name)) ? '$'.$params[$k]->name : '?';
                $out .= '
                <tr>
                  <td>'.$o($k).'</td>
                  <td>'.$o($name).'</td>
                  <td class="code">
                    <pre>' . 'N/A' /* $o(print_r($v, true))*/ . '</pre>
                  </td>
                  </tr>'; 
            }
            $out .= '</tbody></table>';
        } 
        if (is_readable($frame['file']) ) { 
            $out .= '
        <div class="commands">
            <a href=\'#\' onclick="return varToggle(this, \''
                .$o($frame_id).'\',\'c\')"><span>▶</span> Src</a>
        </div>
        <div class="context" id="c'.$o($frame_id).'">';
            $lines = $src2lines($frame['file']);
            $start = $frame['line'] < 5 ?
                0 : $frame['line'] -5; $end = $start + 10;
            $out2 = '';
            foreach ( $lines as $k => $line ) {
                if ( $k > $end ) { break; }
                $line = trim(strip_tags($line));
                // if ( $k < $start && isset($frames[$frame_id+1]["function"])
                //      && preg_match('/function( )*'.preg_quote($frames[$frame_id+1]["function"]).'/',
                //                    $line) ) {
                //     $start = $k;
                // }
                if ( $k >= $start ) {
                    if ( $k != $frame['line'] ) {
                $out2 .= '<li><code>'.$clean($line).'</code></li>'."\n"; }
              else {
                $out2 .= '<li class="current-line"><code>'.
                  $clean($line).'</code></li>'."\n"; }
            }
          }
            $out .= "<ol start=\"$start\">\n".$out2. "</ol>\n";
            $out .= '</div>';
        } else { 
            $out .= '<div class="commands">No src available</div>';
        } 
        $out .= '</li>';
    } // End of foreach $frames
    $out .= '
  </ul>
  
</div>

<div id="request">
  <h2>Request
    <a href=\'#\' onclick="return sectionToggle(\'req_switch\',\'req_list\')">
    <span id="req_switch">▶</span></a></h2>
  <div id="req_list" class="section">';
    $out .= '
    <h3>Request <span>(parsed)</span></h3>
      <table class="req">
        <thead>
          <tr>
            <th>Variable</th>
            <th>Value</th>
          </tr>
        </thead>
        <tbody>
        <tr><td>Sender</td>
        <td class="code"><div>'.$o(print_r($req->mess->sender,TRUE)).'</div></td>
        </tr>
        <tr><td>Path</td>
        <td class="code"><div>'.$o(print_r($req->mess->path,TRUE)).'</div></td>
        </tr>
        <tr><td>Connection Id</td>
        <td class="code"><div>'.$o(print_r($req->mess->conn_id,TRUE)).'</div></td>
        </tr>
        </tbody>
      </table>
    <h4>Request Headers</h4>
      <table class="req">
        <thead>
          <tr>
            <th>Variable</th>
            <th>Value</th>
          </tr>
        </thead>
        <tbody>
';
    foreach ($req->mess->headers as $hdr => $val) {
        $out .= '        <tr><td>' . $o($hdr) . '</td>
        <td class="code"><div>' . $o($val) . '</div></td>
        </tr>';
    }
        $out .= '        </tbody>
      </table>
   <h4>Request Body</h4>
<pre>';
        if (is_string($req->mess->body)) {
            $out .= (string) $o($req->mess->body) . '</pre>';
        } else {
            $out .= (string) $o((string)$req->mess->body) . '</pre>';
        }
    $out .= '
      
  </div>
</div>';
    $out .= '
</body>
</html>
';
    return $out;
}

