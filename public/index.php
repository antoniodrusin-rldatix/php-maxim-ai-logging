<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;

$app = AppFactory::create();

// GET endpoint that returns 200
$app->get('/query', function ($request, $response, $args) {
    return $response->withStatus(200);
});

$app->run();
