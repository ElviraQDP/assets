<?php
    require('../../vendor/autoload.php');
    require_once('../../globals.php');

	$api = new \SlimAPI\SlimAPI([
	    'debug' => API_DEBUG,
	    'name' => 'Zaps API',
        'jwt_key' => JWT_KEY,
        'jwt_clients' => JWT_CLIENTS
	]);
	$api->addReadme('/','./README.md');
	$api->addRoutes(require('routes.php'));
	$api->run();