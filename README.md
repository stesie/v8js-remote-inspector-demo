remote-inspector-demo
=====================

This is a minimalist demo script, that shows how php-v8js' new V8Inspector object can be proxied
via a WebSocket server so Chrome DevTools can be connected to it.

Launch the `inspector-demo.php` script after running `composer install` once.

This should spin up a V8Js instance as well as bind port 9229, ready for Chrome to connect to.
Then navigate to chrome://inspect/#devices, where it should list your v8js instance as a
"remote target".  Click the "inspect" link there to open the actual inspector window.

Use the "/reset-fn?identifier=foo" route to evaluate a script and "/fn" route to call it.

The newly evaluated scripts should pop up in the "Sources" tab and you should also be
able to collect coverage information.