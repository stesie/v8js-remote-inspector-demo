<?php

use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\Message;
use React\EventLoop\Factory;
use React\Http\Message\Response;
use React\Http\Server;
use Voryx\WebSocketMiddleware\WebSocketConnection;
use Voryx\WebSocketMiddleware\WebSocketMiddleware;

require __DIR__ . '/vendor/autoload.php';

$v8 = new V8Js();
$v8->executeString("print('hello cruel world\\n');", "demo script");

$v8->executeString("(function blub() { print('blub!\\n'); })", "blub fn");

$loop = Factory::create();

$ws = new WebSocketMiddleware(["/679b3abc-d0ff-4f21-9d72-cf99f7ac64cf"], function (WebSocketConnection $conn) use ($v8, &$fn) {
    $inspector = $v8->connectInspector();

    $inspector->setNotificationHandler(function($res) use ($conn) {
        printf("received notification from backend: %s\n", $res);
        $conn->send($res);
    });

    $inspector->setResponseHandler(function($res) use ($conn) {
        printf("received response from backend: %s\n", $res);
        $conn->send($res);
    });

    $conn->on('message', function (Message $message) use ($conn, $inspector) {
        /** @var Frame $frame */
        foreach ($message as $frame) {
            \printf("received message from client: %s\n", $frame->getPayload());
            // received message from client: {"id":10,"method":"Profiler.startPreciseCoverage","params":{"callCount":false,"detailed":false,"allowTriggeredUpdates":true}}
            $payload = \json_decode($frame->getPayload());

            if ($payload->method === 'Profiler.startPreciseCoverage') {
                // override callCount reporting to TRUE, so the profiler really reports functions that are invoked
                // directly from PHP.  Don't know why they're not reported otherwise.
                $payload->params->callCount = true;
            }

            $inspector->send(\json_encode($payload));
        }
    });
});


$server = new Server($loop, $ws, function (ServerRequestInterface $request) use ($v8, &$fn) {
    // printf("%s %s\n", $request->getMethod(), $request->getUri()->getPath());
    switch ($request->getUri()->getPath()) {
        case "/json/version":
            return new Response(200, ['Content-Type' => 'application/json'], \json_encode([
                "Browser" => "php-v8js/inspector-demo",
                "Protocol-Version" => "1.1"
            ]));

        case "/json":
            return new Response(200, ['Content-Type' => 'application/json'], \json_encode([[
                "description" => "php-v8js instance",
                "devtoolsFrontendUrl" => "chrome-devtools://devtools/bundled/js_app.html?experiments=true&v8only=true&ws=127.0.0.1:9229/679b3abc-d0ff-4f21-9d72-cf99f7ac64cf",
                "devtoolsFrontendUrlCompat" => "chrome-devtools://devtools/bundled/inspector.html?experiments=true&v8only=true&ws=127.0.0.1:9229/679b3abc-d0ff-4f21-9d72-cf99f7ac64cf",
                "faviconUrl" => "https://nodejs.org/static/favicon.ico",
                "id" => "679b3abc-d0ff-4f21-9d72-cf99f7ac64cf",
                "title" => "v8js[123]",
                "type" => "node",
                "url" => "file://",
                "webSocketDebuggerUrl" => "ws://127.0.0.1:9229/679b3abc-d0ff-4f21-9d72-cf99f7ac64cf"]]));

        case "/execute-string-now":
            $identifier = $request->getQueryParams()['identifier'] ?? 'execute-string-now';
            $v8->executeString("print('blarg!!\\n');", $identifier);
            return new Response(202);

        case "/reset-fn":
            $identifier = $request->getQueryParams()['identifier'] ?? 'blub';
            $fn = $v8->executeString("(function $identifier() {\n  (() => print('$identifier!\\n'))();\n})", $identifier);
            return new Response(202);

        case "/fn":
            $fn();
            return new Response(202);
    }

    return new Response(400, ['Content-Type' => 'text/plain'], "woot?");
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:9229', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();