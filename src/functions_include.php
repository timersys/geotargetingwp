<?php
use Dotenv\Dotenv;
// load end files
$dotenv = new Dotenv(dirname(__DIR__));
$dotenv->load();

// Don't redefine the functions if included multiple times.
if ( !function_exists('GeotargetingWP\get_user_ip') ) {
	require __DIR__ . '/functions.php';
}