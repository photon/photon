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
 * Mongrel2 Interface.
 *
 * This namespace groups all the functions and classes for the
 * connection between the PHP application server and Mongrel2. What is
 * important to notice is that most of the work is done lazily to
 * really parse the needed data only on demand.
 */
namespace photon\mongrel2;

/**
 * Parse a netstring.
 *
 * The only problem with this function is that you have many copies of
 * the data in memory and this can create a bit of memory consumption.
 *
 */
function parse_netstring($ns) 
{
    list($len, $rest) = \explode(':', $ns, 2);
    unset($ns);
    $len = (int) $len;
    return array(
        \substr($rest, 0, $len),
        \substr($rest, $len+1)
    );
}

function http_response($body, $code, $status, $headers) 
{
    $http = "HTTP/1.1 %s %s\r\n%s\r\n%s";
    $headers = (array) $headers; # If null it will be forced to empty array
    $headers['Content-Length'] = \strlen($body);
    $hd = '';
    foreach($headers as $k => $v) {
        $hd .= \sprintf("%s: %s\r\n", $k, $v);
    }
    return \sprintf($http, $code, $status, $hd, $body);
}

/**
 * Wraps the Mongrel2 message to the application server.
 */
class Message
{
    public $sender;
    public $path;
    public $conn_id;
    public $headers;
    public $body; /**< A handler to the in memory storage of the body */

    /**
     * Called by self::parse
     */
    public function __construct($sender, $conn_id, $path, $headers, $body) 
    {
        $this->sender = $sender;
        $this->path = $path;
        $this->conn_id = $conn_id;
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * We close the stream when the message is discarded.
     *
     * This is necessary to avoid accumulation of temp memory segment usage.
     */
    public function __destruct()
    {
        @fclose($this->body);
    }

    public static function parse($msg) 
    {
        list($sender, $conn_id, $path, $msg) = \explode(' ', $msg, 4);
        list($headers, $msg) = parse_netstring($msg);
        list($body, ) = parse_netstring($msg);
        unset($msg);
        $headers = \json_decode($headers);
        return new Message($sender, $conn_id, $path, $headers, $body);
    }
}

/**
 * ZMQ connection between Mongrel2 and the application server.
 *
 * The connection is used to retrieve a request and send a response.
 */
class Connection 
{
    public $sender_id;
    public $sub_addr;
    public $pub_addr;
    public $reqs;
    public $resp;

    public function __construct($sender_id, $sub_addr, $pub_addr) 
    {
        $this->sender_id = $sender_id;

        $ctx = new \ZMQContext();
        //$reqs = $ctx->getSocket(\ZMQ::SOCKET_UPSTREAM);
        //$reqs->connect($sub_addr);
        $reqs = new \ZMQSocket(new \ZMQContext(), \ZMQ::SOCKET_UPSTREAM);
        $reqs->connect($sub_addr);

        $resp = $ctx->getSocket(\ZMQ::SOCKET_PUB);
        $resp->connect($pub_addr);
        $resp->setSockOpt(\ZMQ::SOCKOPT_IDENTITY, $sender_id);

        $this->sub_addr = $sub_addr;
        $this->pub_addr = $pub_addr;

        $this->reqs = $reqs;
        $this->resp = $resp;
    }

    /**
     * Receive the data from the zeromq backend.
     *
     * Before receiving the data, we have no idea about the real size
     * of the data we are getting. The goal is to be smart and avoid
     * crashing PHP under the load when getting a 50MB or more upload. 
     *
     * The fastest solution is to do all in memory and consider the
     * request as a nice string. The safest solution is to put
     * everything in a stream which will write the data on disk if too
     * large (let say 5MB) but else operate in memory and be smart in
     * parsing the request to not load everything in memory.
     *
     */
    public function recv() 
    {
        $body = null;
        $fp = fopen('php://temp/maxmemory:5242880', 'r+');
        fputs($fp, $this->reqs->recv());
        rewind($fp);
        /*
        $data = stream_get_contents($fp);
        file_put_contents('example.payload.'.time(), $data);
        rewind($fp);
        */
        $line = fread($fp, 8192);
        //        file_put_contents('example.payload', $data);
        list($sender, $conn_id, $path, $smsg) = \explode(' ', $line, 4);
        // From $smsg, get the size of the headers
        list($len, $rest) = \explode(':', $smsg, 2);
        unset($smsg);
        $len = (int) $len;
        $rlen = strlen($rest);
        if ($rlen > $len) {
            $headers = \json_decode(\substr($rest, 0, $len));
            fseek($fp, -$rlen+$len+1, SEEK_CUR);
        } else {
            // Need to grab the end of the headers
            $toread = $len - $rlen;
            $headers = \json_decode(fread($fp, $toread));
            fread($fp, 1); // The comma
        }
        // Now the body of the request is available by just doing a simple:
        // $body = stream_get_contents($fp);

        // This makes sense if we do not have file upload. With file
        // uploads, we should provide them as file handlers ready to
        // be stored somewhere else.

        // We are going to support only the POST and JSON requests at
        // the moment.
        if ($headers->METHOD == 'JSON') {
            // small request normally
            list($body, ) = parse_netstring(stream_get_contents($fp));
            fclose($fp);
        } elseif ($headers->METHOD == 'POST') {
            // Here the parsing of the body should be done.
            //$body = stream_get_contents($fp); 
            // just to get the position of the real start of the body
            $line = fread($fp, 100); 
            list($len, $rest) = \explode(':', $line, 2);
            fseek($fp, -strlen($rest), SEEK_CUR);            
            $body = $fp;
            /*
            unset($line, $rest);
            $parser = new \photon\http\multipartparser\MultiPartParser($headers, $fp);
            $body = $parser->parse();
            */
            //fclose($fp);
        } else {
            $body = '';
            fclose($fp);
        } 
        //        printf("Max memory usage: %s\n", memory_get_peak_usage());
        //        printf("Current memory usage: %s\n", memory_get_usage());
        return new Message($sender, $conn_id, $path, $headers, $body);
    }

    public function reply($req, $msg) 
    {
        $this->send($req->sender, $req->conn_id, $msg);
    }

    public function send($uuid, $conn_id, $msg) 
    {
        $header = \sprintf('%s %d:%s,', $uuid, \strlen($conn_id), $conn_id);
        $this->resp->send($header . ' ' . $msg);
    }

    /**
     * Send the data back to a list of clients.
     *
     * @param $uuid ID of the sender
     * @param $idents Array of the client connection ids
     * @param $data Payload
     */
    public function deliver($uuid, $idents, $data) 
    {
        $this->send($uuid, \join(' ', $idents),  $data);
    }
}


