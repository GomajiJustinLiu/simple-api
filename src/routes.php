<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

return function (App $app) {
    $container = $app->getContainer();

    $app->get('/[{name}]', function (Request $request, Response $response, array $args) use ($container) {
        // Sample log message
        $container->get('logger')->info("Slim-Skeleton '/' route");

        // Render index view
        return $container->get('renderer')->render($response, 'index.phtml', $args);
    });
    $app->group('/api', function () use ($app, $container) {
        $app->get('/put-redis', function (Request $request, Response $response, array $args) use ($container) {
            $key = $request->getParam('key');
            $value = $request->getParam('value');

            $redisHost = getenv('REDIS_HOST');

            $client = new Predis\Client(['host' => $redisHost, 'port' => 6379]);
            $client->connect();
            $result = $client->set($key, $value);
            $client->disconnect();

            return $response->withJson($result);
        });

        $app->get('/get-redis', function (Request $request, Response $response, array $args) use ($container) {
            $key = $request->getParam('key');

            $redisHost = getenv('REDIS_HOST');

            $client = new Predis\Client(['host' => $redisHost, 'port' => 6379]);
            $client->connect();
            $value = $client->get($key);
            $client->disconnect();

            return $response->withJson($value);
        });
    });

};
