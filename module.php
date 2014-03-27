<?php
/**
 * Plugin Name: Moufpress
 * Plugin URI: http://mouf-php.com/packages/mouf/integration.wordpress.moufpress
 * Description: Moufpress is a MVC framework for Wordpress. It is actually an adaptation of Mouf's Splash MVC framework. If you have a project developped with Splash, you can completely reuse your code, right into Wordpress with this plugin.
 * Version: 1.0
 * Author: David Négrier
 * Author URI: http://mouf-php.com
 * License: MIT
 * Depends: WP Router
 */
/*
 * This file is part of the Moufpress package.
*
* (c) 2014 David Negrier <david@mouf-php.com>
*
* For the full copyright and license information, please view the LICENSE.txt
* file that was distributed with this source code.
*/
 
require_once __DIR__.'/../../../mouf/Mouf.php';

// Let's define the ROOT_URL constant
define('ROOT_URL', parse_url(get_bloginfo('url'),PHP_URL_PATH).'/');
define('MOUF_URL', ROOT_URL.'vendor/mouf/mouf/');

function moufpress_execute_action($instanceName, $methodName, $urlParameters, $parameters, $filters) {
	global $moufPress;
	$moufPress->executeAction($instanceName, $methodName, $urlParameters, $parameters, $filters);
}

$moufPress = Mouf::getMoufpress();
