<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */

namespace photonchat\task;
use photon\task\AsyncTask;
use photon\mongrel2;

photonLoadFunction('\photon\mongrel2\send');


/**
 * To centralize the list of users, we delegate all the work of
 * sending messages to a Chatserver task.
 *
 */

/** 
 * Chat server task.
 *
 */
class Server extends AsyncTask
{
    public $name = 'photonchat_server';
    public $m2_pub = 'tcp://127.0.0.1:9996';
    public $m2 = null; /**< Socket to talk to Mongrel2. */
    public $sender = null;
    public $uuid = 'photonchat_server';
    static $n = 0;

    /** 
     * Static to be available with a sigterm. 
     */
    static $user_list = array(); 

    public function __construct()
    {
        parent::__construct();
        // We need to connect to Mongrel2 to send the answers.
        $this->m2 = new \ZMQSocket($this->ctx, \ZMQ::SOCKET_PUB);
        $this->m2->connect($this->m2_pub);
        $this->m2->setSockOpt(\ZMQ::SOCKOPT_IDENTITY, $this->uuid);
    }

    /**
     * The payload is a thin wrapping over the payload sent over the
     * jsSocket to add the client connection id.
     */
    public function work($socket)
    {
        list($taskname, $client, $payload) = explode(' ', $socket->recv(), 3);
        list($sender, $client, $data) = json_decode($payload);
        $this->sender = $sender;
        if ('join' === $data->type) {
            if (count(array_keys(self::$user_list))) {
                mongrel2\deliver($this->m2, $sender, 
                                 array_keys(self::$user_list),
                                 json_encode($data));
            }
            self::$user_list[$client] = $data->user;
            $res = array('type' => 'userList', 
                         'users' =>  array_merge(array_values(self::$user_list),
                                                 array('PhotonBot')));
            mongrel2\send($this->m2, $sender, $client, json_encode($res));
        } elseif ('disconnect' === $data->type) {
            if (isset(self::$user_list[$client])) {
                $data->user = self::$user_list[$client];
                unset(self::$user_list[$client]);
            }
            mongrel2\deliver($this->m2, $sender, array_keys(self::$user_list),
                                    json_encode($data));
        } elseif (!isset(self::$user_list[$client])) {
            self::$user_list[$data->user] = $client; // auto join
        } elseif ('msg' === $data->type) {
            mongrel2\deliver($this->m2, $sender, array_keys(self::$user_list),
                                    json_encode($data));
        }

    }

    /**
     * We use the loop to inform people of the time.
     *
     * You could use this loop() to ping old users and check that they
     * are still there, etc.
     */
    public function loop()
    {
        self::$n++;
        if (null === $this->sender) {
            // We haven't yet received any request
            return;
        }
        if (100 < self::$n) { // approx every 20 seconds
            $data = new \stdClass();
            $data->type = 'msg';
            $data->user = 'PhotonBot';
            $data->msg = sprintf("It is now: %s.", date('c'));
            mongrel2\deliver($this->m2, $this->sender, 
                             array_keys(self::$user_list),
                             json_encode($data));
            self::$n = 0; 
        }
    }
}
