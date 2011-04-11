<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/**
 * A Photon view is simply a method of a class with a specific signature.
 */

namespace photonchat\views;

use \photon\shortcuts;

class Chat
{
    /**
     * Just serving the main HTML of the chat window, it could done
     * directly by Mongrel2.
     */
    public function home($request, $match)
    {
        return shortcuts\Template::RenderToResponse('photonchat/home.html',
                                                    array(),
                                                    $request);
    }

    /**
     * Here we serve the request coming from the chat socket.
     */
    public function chatbox($request, $match)
    {
        // We are offloading the work to the chatserver task.

        $runner = new \photon\task\Runner();
        $payload = array($request->sender, $request->client, $request->BODY);
        // The run call will return immediately!
        $runner->run('photonchat_server', $payload);
        return false;
    }


    /**
     * Here we serve the request coming from the chat socket.
     */
    public function singlehandlerchatbox($request, $match)
    {
        // Laziness, for this chatbox to work, you need to have only
        // one handler process running. Run two times $ hnu server
        // less after you did a $ hnu server start to kill 2 of the
        // default 3 handler processes.
        static $user_list = array();

        $data = $request->BODY;
        if ('join' === $data->type) {
            $request->conn->deliver($request->sender, array_keys($user_list),
                                    json_encode($data));
            $user_list[$request->client] = $data->user;
            $res = array('type' => 'userList', 
                         'users' => array_values($user_list));
            return new \photon\http\response\Json($res);
        } elseif ('disconnect' === $data->type) {
            if (isset($user_list[$request->client])) {
                $data->user = $user_list[$request->client];
                unset($user_list[$request->client]);
            }
            $request->conn->deliver($request->sender, array_keys($user_list),
                                    json_encode($data));
        } elseif (!isset($user_list[$request->client])) {
            $user_list[$data->user] = $request->client;
        } elseif ('msg' === $data->type) {
            $request->conn->deliver($request->sender, array_keys($user_list),
                                    json_encode($data));
        }
        return false; // By default, say nothing
    }
}