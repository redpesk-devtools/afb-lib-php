<?php

/*
 * Copyright (C) 2021 IoT.bzh Company
 * Author: Thierry Bultel <thierry.bultel@iot.bzh>
 *
 * SPDX-License-Identifier: MIT
 */

require_once('AFB_websocket.php');
require_once('vendor/autoload.php');


class Test {

    public function onopen() {
        error_log("Test::onopen");

        try {
            $this->send_requests();
        } catch ( Exception $e) {
            error_log("EXC");
        }
    }

    public function onabort($why) {
        error_log("Test::onabort ".$why);
    }

    public function on_event($msg) {
        error_log("Test::onevent:". json_encode($msg));
    }

    public function __invoke() {

        error_log("Creating afb websocket");
        $afb = new AFB_websocket("localhost", 1234, "ws:", "token", "uuid");
        $afb->onevent("hello/event", [$this, 'on_event']);

        $this->afb = $afb;

        error_log("Connecting...");
        $afb([$this, 'onopen'], [$this, 'onabort']);

    }

    public function send_requests() {

        $afb = $this->afb;

        $afb->call('hello/call', '{"class":"a"}')->then(
            function($result) {
                error_log("a resolved: ".json_encode($result)); },
            function($error) {
                error_log("a error:".json_encode($error));
            });

        $afb->call('hello/hello', '{"class":"b"}')->then(
            function($result) {
                error_log("b resolved:".json_encode($result)); },
            function($error) {
                error_log("b error:".json_encode($error));
            });

        // this verb does not exist, this must report an error
        $afb->call('hello/dummy', '{"class":"b"}')->then(
            function($result) {
                error_log("fail resolved:".json_encode($result)); },
            function($error) {
                error_log("fail error:".json_encode($error));
            });

        $afb->call('hello/subscribe', '{}')->then(
            function($result) {
                error_log("c resolved:".json_encode($result)); },
            function($error) {
                error_log("c error:".json_encode($error));
            });

        // When calling this verb, an event is triggered from the server

        $afb->call('hello/evpush', '{}')->then(
            function($result) {
                error_log("d resolved:".json_encode($result)); },
            function($error) {
                error_log("d error:".json_encode($error));
            });
    
    }

}

error_log("Launching test ... !");

$test  = new Test();
$test();
