*afb-lib-php*

**PHP Library to access to AFB binder protocol**

This library is a PHP implementation of the AFB Websocket protocol.
It provides access to APIs/verbs calls, and reception of asynchronous events.

The given 'test.php' is a self-speaking example on how to use it, it just
assumes that there is a running and accessible binder that runs the 
"hello" binding example.


It relies on the following external packages:

* ratchet/pawl
* react/promise