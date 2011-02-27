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
        \substr($rest, $len + 1)
    );
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
    public $body; /**< mixed A handler to the in memory storage of the
                   *  body, an empty string or a decoded JSON string.
                   */

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

    public function is_disconnect()
    {
        return (isset($this->headers->METHOD)
                && 'JSON' === $this->headers->METHOD
                && 'disconnect' === $this->body->type);
    }

    /**
     * We close the stream when the message is discarded.
     *
     * This is necessary to avoid accumulation of temp memory segment usage.
     */
    public function __destruct()
    {
        if (is_resource($this->body)) {
            @fclose($this->body);
        }
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
 * The connection is used to retrieve a request and send a
 * response. You can use this class if you do not want to poll. For
 * polling, uses the PollConnection which is a bit more low level for
 * you to integrate it into your poller.
 */
class Connection
{
    public $sender_id;
    public $sub_addr;
    public $pub_addr;
    public $reqs;
    public $resp;
    public $ctx; /**< zeromq context. Reused everywhere if needed. */

    public function __construct($sender_id, $sub_addr, $pub_addr, $ctx=null)
    {
        $this->sender_id = $sender_id;

        if (null === $ctx) {
            $this->ctx = new \ZMQContext();
        } else {
            $this->ctx = $ctx;
        }

        $reqs = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_UPSTREAM);
        $reqs->connect($sub_addr);

        $resp = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_PUB);
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
        $fp = fopen('php://temp/maxmemory:5242880', 'r+');
        fputs($fp, $this->reqs->recv());
        rewind($fp);

        return $this->parse($fp);
    }

    /**
     * Parse the Mongrel2 request and returns a message.
     *
     * @param $fp Open file descriptor to access the message.
     * @return Message object.
     */
    public function parse($fp)
    {
        $body = null;
        $line = fread($fp, 8192);
        list($sender, $conn_id, $path, $smsg) = \explode(' ', $line, 4);
        // From $smsg, get the size of the headers
        list($len, $rest) = \explode(':', $smsg, 2);
        unset($smsg);
        $len = (int) $len;
        $rlen = strlen($rest);
        if ($rlen > $len) {
            $headers = \json_decode(\substr($rest, 0, $len));
            fseek($fp, -$rlen + $len + 1, SEEK_CUR);
        } else {
            // Need to grab the end of the headers
            $toread = $len - $rlen;
            $headers = \json_decode($rest . fread($fp, $toread));
            fread($fp, 1); // The comma
        }
        // Now the body of the request is available by just doing a simple:
        // $body = stream_get_contents($fp);

        // This makes sense if we do not have file upload. With file
        // uploads, we should provide them as file handlers ready to
        // be stored somewhere else.

        // We are going to support only the POST and JSON requests at
        // the moment.
        if ('JSON' === $headers->METHOD) {
            // small request normally
            list($body,) = parse_netstring(stream_get_contents($fp));
            $body = json_decode($body);
            fclose($fp);
        } elseif ('POST' === $headers->METHOD) {
            // Here the parsing of the body should be done.
            //$body = stream_get_contents($fp);
            // just to get the position of the real start of the body
            $line = fread($fp, 100);
            list($len, $rest) = \explode(':', $line, 2);
            fseek($fp, -strlen($rest), SEEK_CUR);
            // The body is parsed in the \photon\http\Request class,
            // only if needed. 
            $body = $fp;
        } else {
            $body = '';
            fclose($fp);
        } 

        return new Message($sender, $conn_id, $path, $headers, $body);
    }

    public function reply($req, $msg)
    {
        return $this->send($req->sender, $req->conn_id, $msg);
    }

    public function send($uuid, $conn_id, $msg)
    {
        $header = \sprintf('%s %d:%s,', $uuid, \strlen($conn_id), $conn_id);

        return $this->resp->send($header . ' ' . $msg);
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
        if (129 > count($idents)) {
            return $this->send($uuid, \join(' ', $idents),  $data);
        }
        // We need to send multiple times the data. We are going to
        // send the data in series of 128 to the clients.
        $a = 1;
        foreach (array_chunk($idents, 128) as $chunk) {
            $a = $a & (int) $this->send($uuid, \join(' ', $chunk),  $data);
        }
        return (bool) $a;
    }

    public function close()
    {
        $this->reqs = null;
        $this->resp = null;
    }
}

