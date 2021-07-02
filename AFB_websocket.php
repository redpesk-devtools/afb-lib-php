<?php

/*
 * Copyright (C) 2021 IoT.bzh Company
 * Author: Thierry Bultel <thierry.bultel@iot.bzh>
 *
 * SPDX-License-Identifier: MIT
 */


use vendor\Ratchet\Client;
use vendor\react\promise;

class AFB_websocket
{
    const CALL = 2;
    const RETOK = 3;
    const RETERR = 4;
    const EVENT = 5;
    const PROTO1 = "x-afb-ws-json1";
    
    protected $api;
    protected $token;
    protected $counter;

    protected $pendings;
    protected $ws;
    protected $conn;
    protected $urlws;

    protected $on_abort = Array();

    /*
    connects to the configured AFB binder 
    param on_open: callback called when communication succeeds
    param on_close: callback called when communication cannot be established
    */

    public function __invoke(callable $on_open, callable $on_abort) {

        /* This does not seem to store a callable into a member data,
          but it works in an array */
        $this->on_abort[0] = $on_abort;

        $ws = \Ratchet\Client\connect($this->urlws, [ self::PROTO1 ] )->then(function ($conn) use ( $on_open) {

            $this->conn=$conn;
            
            $conn->on('message', function($msg) { 
                $this->onmessage($msg); 
            });

            $conn->on('close', function($close_reason) {
                $this->onclose($close_reason);
            });

            $conn->on('error', function() {
                $this->onerror();
            });

            $on_open();

        }, function ($e) use ($on_abort) {
            $on_abort($e->getMessage());
        });

    }

    /* 
        Initialisation of the communication parameter
        param host is a hostname or ip address,
        param port is the http port,
        param scheme is either 'ws' or 'wss'
        param token is deprecated
        param uuid is not implemented yet
    */

    public function __construct(string $host, string $port, string $scheme, string $token, string $uuid) {

        $this->token = $token;
        $this->uuid = $uuid;
        $this->counter = 0;

        $this->urlws = $scheme . "//" . $host . ":" . $port . "/api";

        $this->pendings = array();
        $this->awaitens = array();
        $this->counter = 0;
    }

    protected function reply($id, $answer, bool $success) {

        $_id = '_'.$id;
        $promiseRes = $success ? "resolve":"reject";

        if (!array_key_exists($_id , $this->pendings)) {
            error_log("did not find id ". $id . " in pending requests");
            return;
        }

        $p = $this->pendings[$_id];
        unset($this->pendings[$_id]);
        try { 
            $p[$promiseRes]($answer); 
        } 
        catch (Exception $x) { error_log("except:" .$x->__toString()); }
                    
    }
  
    /*
    * Internal callback upon message reception
    * Will resolve, or reject pending requests that match the message ID
    */

    protected function onmessage($msg) {

        $obj = json_decode($msg);
    
        $code = $obj[0];
        $id = $obj[1];
        $answer = $obj[2];

        switch ($code) {
            case Self::RETOK:
                $this->reply($id, $answer, true);
            break;
            case Self::RETERR;
                $this->reply($id, $answer, false);
            break;
            case Self::EVENT:
                $this->event($id, $answer);
            break;
            default:
                error_log("Unknown message code ". $code ." from AFB");
            break;
        }
    }
    
    protected function onclose($evt) {

        $err = "{ 'jtype' : 'afb-reply', 
                  'request': { 
                     'status': 'disconnected',
                     'info': 'server hung hup'} 
                }";
        
        foreach($this->pendings as $key => $value) {
            $value["reject"]($evt);
            unset($value);
        }

        $this->on_abort[0]($err);

    }

    protected function onerror($err) {
        $this->on_abort[0]($err);
    }

    protected function event($id, $answer) {
        error_log("event !");
        if (array_key_exists($id, $this->awaitens)) {
            $a=$this->awaitens[$id];
            foreach ($a as $callback) {
                $callback($answer);
            }
        }

    }

    /**
     * Sends a request call to a afb binder.
     * param string method: 'api/verb'
     * param string request: the afb verb parameters, in json format, eg '{"class":"all"}'
     * Returns: a Promise (see the provided example for usage)
     */

    public function call(string $method, string $request, int $callid = 0) {

         $resolver = function (callable $resolve, callable $reject) use ($method, $request, $callid) {
            $id = 0;
            if ($callid != 0) {
                $id = string($callid);
                if (array_key_exists('_'.$id, $this->pendings)) {
                    error_log("error, id " .$id. " is already in pending requests");
                    throw kk;// todo
                }
            }
            else {
                do {
                    $this->counter = 4095 & ($this->counter + 1);
                    $id = $this->counter;
                } while (array_key_exists('_'.$id, $this->pendings));
            }

            /* The pre-pend space is a workaround to the php limitations, this marvelous language
               does not support numerical keys, even when they are given as strings,
               and interpret them as indexes */

            $this->pendings['_'.$id] = [ 'resolve' => $resolve, 'reject' => $reject ];

            $arr = "[". Self::CALL ."," . $id . "," . $method . "," . $request;
            if ($this->token)
                $arr .= "," .$this->token;
            $arr .=  "]";

            $this->conn->send($arr);
        };

        $canceller = function() {
            error_log("Cancellation of requests is not implemented yet");
        };

        $p = new React\Promise\Promise($resolver, $canceller);
        return $p;
    }

    /* registers for events reception */

    public function onevent($name, callable $on_event) {
        if (!array_key_exists($name, $this->awaitens))
            $this->awaitens[$name] = Array();

        array_push($this->awaitens[$name], $on_event);
    }

}
