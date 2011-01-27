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

use photon\http\Response;
use photon\config\Container as Conf;

class Forbidden extends Response
{
    public function __construct($request)
    {
        $mimetype = 'text/plain';
        $content = 'You are not authorized to view this page. You do not have permission' . "\n"
            . 'to view the requested directory or page using the credentials supplied.' . "\n\n"
            . '403 - Forbidden';
        parent::__construct($content, $mimetype);
        $this->status_code = 403;
    }
}

class NotFound extends Response
{
    public function __construct($request)
    {
        $mimetype = 'text/plain';
        $content = sprintf('The requested URL %s was not found on this server.' . "\n" .
                           'Please check the URL and try again.' . "\n\n" . '404 - Not Found',
                           str_replace(array('&',     '"',      '<',    '>'),
                                       array('&amp;', '&quot;', '&lt;', '&gt;'),
                                       $request->path));
        parent::__construct($content, $mimetype);
        $this->status_code = 404;
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
        $content = '';
        $admins = Conf::f('admins', array());
        if (count($admins) > 0) {
            // Get a nice stack trace and send it by emails.
            $stack = pretty_server_error($exception);
            $subject = $exception->getMessage();
            $subject = substr(strip_tags(nl2br($subject)), 0, 50).'...';
            foreach ($admins as $admin) {
                $email = new Pluf_Mail($admin[1], $admin[1], $subject);
                $email->addTextMessage($stack);
                $email->sendMail();
            }
        }
        try {
            $context = new Pluf_Template_Context(array('message' => $exception->getMessage()));
            $tmpl = new Pluf_Template('500.html');
            $content = $tmpl->render($context);
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
               '$src = nl2br(highlight_file($file, TRUE));
               return explode("<br />", $src);');
    $clean = create_function('$line',
               'return html_entity_decode(str_replace("&nbsp;", " ", $line));');
    $desc = get_class($e)." making ".$req->METHOD." request to ".$req->PATH;
    $out = $desc."\n";
    if ($e->getCode()) { 
        $out .= $e->getCode(). ' : '; 
    }
    $out .= $e->getMessage()."\n\n";
    $out .= 'PHP: '.$e->getFile().', line '.$e->getLine()."\n";
    $out .= 'URI: '.$req->METHOD.' '.$req->PATH."\n\n";
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
                if ( $k < $start && isset($frames[$frame_id+1]["function"])
                     && preg_match('/function( )*'.preg_quote($frames[$frame_id+1]["function"]).'/',
                                   $line) ) {
                    $start = $k;
                }
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
    $out .= 'Sender:   ' . $req->mreq->sender . "\n";
    $out .= 'Path:     ' . $req->mreq->path . "\n";
    $out .= 'Conn id:  ' . $req->mreq->conn_id . "\n";

    $out .= "\n".'* Request headers *'."\n\n";
    foreach ($req->mreq->headers as $hdr => $val) {
        $out .= 'Variable: ' . $hdr . "\n";
        $out .= 'Value:    ' . $val . "\n";
    }
    $out .= "\n".'* Request body *'."\n\n";
    $out .= $body . "\n\n";

    return $out;
}

