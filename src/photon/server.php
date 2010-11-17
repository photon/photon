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
 * Photon Server.
 *
 * The server is the infinite loop to handle the requests from/to the
 * zeromq hub provided by Mongrel2.
 */
namespace photon\server;

/**
 * Simplest server to handle requests without long polling.
 *
 */
class Server
{
    /**
     * The id of this server daemon. Many servers can have the same
     * id, you can group your servers by ids and filter at answer at
     * the Mongrel2 level.
     */
    public $sender_id = '26f97e5e-2ce7-4381-9649-f61673892d2e';

    /**
     * Where the request is provided.
     */
    public $sub_addr = 'tcp://127.0.0.1:9997';

    /**
     * Where the answer is pushed.
     */
    public $pub_addr = 'tcp://127.0.0.1:9996'; 

    /**
     * zeromq connection.
     */
    public $conn = null;

    public function __construct($conf=array())
    {
        foreach ($conf as $key=>$value) {
            $this->$key = $value;
        }
    }

    public function start()
    {
        $this->conn = new \photon\mongrel2\Connection($this->sender_id,
                                                      $this->sub_addr,
                                                      $this->pub_addr);
        //$end = microtime(true);
        while ($mess = $this->conn->recv()) {
            list($req, $response) = \photon\core\Dispatcher::dispatch($mess);
            $this->conn->reply($mess, $response->render());
            unset($mess); // Needed to clean the memory
            //file_put_contents('./perf.log', (microtime(true)-$end)."\n", FILE_APPEND);
            //$end = microtime(true);
        }
    }
}
