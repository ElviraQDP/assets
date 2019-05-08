<?php
	require_once('../../vendor/autoload.php');
	require_once('../../globals.php');
	$api = new \SlimAPI\SlimAPI([
		'debug' => API_DEBUG,
		'name' => 'Credentials',
	]);
	$api->addReadme('/','./README.md');
	$api->addRoutes(require('routes.php'));
	$api->run();