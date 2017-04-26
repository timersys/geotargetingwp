GeotargetingWP
=========================

PHP package with the necessary files to play with [GeotargetingWP](https://geotargetingwp.com) API.
It can be used outside of WordpPress as it uses Guzzle http to communicate with the server.

Installation
--------
Simple type 
`composer require timersys/geotargetingwp`

Usage
--------
Once loaded with composer autoload you could do something like this:

```
<?php
use GeotWP;
$license = '1234'; Your license key
$args = [
    'disable_sessions'  => false, // cache mode turned on by default
    'debug_mode'        => false, // similar to disable sessions but also invalidates cookies
    'bots_country'      => '', // a default country to return if a bot is detected
    'cookie_name'       => 'geot_country' // cookie_name to store country iso code
    'api_secret'        => Secret key
];
$geotWP = new GeotargetingWP( $license, $args );
try {
    $user_data = $geotWP->getData();
} catch( Exception $e ) {
    // Check code for custom exceptions   
}

```

Features
--------

* PSR-4 autoloading compliant structure
* Unit-Testing with PHPUnit
* Comprehensive Guides and tutorial
* Easy to use to any framework or even a plain php file
