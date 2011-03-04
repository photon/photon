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
    public function index($request, $match)
    {
        // note: this is not used
        return shortcuts\Template::RenderToResponse('photonchat/index.html',
                                                    array(),
                                                    $request);
    }

    /**
     * Here we serve the request coming from the chat socket.
     */
   public function chatbox($request, $match)
   {
       static $user_list = array();
       
       // TODO: user_list needs to be a shared resource written to a flat file or in mongodb
       
       $data = $request->BODY;
       if ('join' === $request->BODY->type) {
           $request->conn->deliver($request->mess->sender,
                                   array_keys($user_list),
                                   json_encode($request->BODY));
           $user_list[$request->mess->conn_id] = $request->BODY->user;
           $res = array('type' => 'userList', 
                        'users' => array_values($user_list));
           print "JOIN ". $request->mess->conn_id . "\n";
           return new \photon\http\response\Json($res);
       } elseif ('disconnect' === $request->BODY->type) {
           print "DISCONNECTED ". $request->mess->conn_id . "\n";

           if (isset($user_list[$request->mess->conn_id])) {
               $data->user = $user_list[$request->mess->conn_id];
               unset($user_list[$request->mess->conn_id]);
           }
           $request->conn->deliver($request->mess->sender,
                                   array_keys($user_list),
                                   json_encode($data));
       } elseif (!isset($user_list[$request->mess->conn_id])) {
           $user_list[$data->user] = $request->mess->conn_id;
           print "AUTO JOIN ". $request->mess->conn_id . "\n";
       } elseif ('msg' === $data->type) {
           $request->conn->deliver($request->mess->sender,
                                   array_keys($user_list),
                                   json_encode($data));
           print "MESS FROM ". $request->mess->conn_id . "\n";
       }
       return false; // By default, say nothing
   }
}

