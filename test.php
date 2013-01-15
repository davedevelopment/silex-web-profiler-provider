<?php

/**
 * Simple test harness for the time being, will delete it when I TDD out the 
 * massive spike that was the development of this provider :)
 */

require "vendor/autoload.php";

$app = new Silex\Application;

$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\ServiceControllerServiceProvider());
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'   => 'pdo_sqlite',
        'path'     => __DIR__.'/app.db',
    ),
));

$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'admin' => array(
            'pattern' => '^/admin',
            'http' => true,
            'users' => array(
                // raw password is foo
                'admin' => array('ROLE_ADMIN', '5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg=='),
            ),
        ),
    ),
));


$wpp = new DaveDevelopment\WebProfilerProvider\WebProfilerProvider();
$app->register($wpp);
$app->mount('/_profiler', $wpp);

$app->get("/", function(Silex\Application $app) {
    return "<html><body>Hello World</body></html>";
});

$app->get("/admin", function() {
    return "<html><body>Hello Admin</body></html>";
});

$app->run();
