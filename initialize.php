<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

// use UTC as default timezone if no timezone is set
if (!ini_get("date.timezone"))
  ini_set("date.timezone", "UTC");

date_default_timezone_set(date_default_timezone_get());


// merge include paths
$newIncludePaths = get_include_path();
restore_include_path();
set_include_path('.' . PATH_SEPARATOR . $newIncludePaths . PATH_SEPARATOR . get_include_path());

// no code is run, only definitions
require_once(dirname(__FILE__) . '/activity.php');
require_once(dirname(__FILE__) . '/cache.php');
require_once(dirname(__FILE__) . '/compile.php');
require_once(dirname(__FILE__) . '/context.php');
require_once(dirname(__FILE__) . '/functions.php');
require_once(dirname(__FILE__) . '/index.php');
require_once(dirname(__FILE__) . '/mail.php');
require_once(dirname(__FILE__) . '/overload.php');
require_once(dirname(__FILE__) . '/profile.php');
require_once(dirname(__FILE__) . '/resource.php');
require_once(dirname(__FILE__) . '/safety.php');
require_once(dirname(__FILE__) . '/setup.php');
require_once(dirname(__FILE__) . '/stub.php');
require_once(dirname(__FILE__) . '/test.php');
require_once(dirname(__FILE__) . '/traits.php');

// initialize
require_once(dirname(__FILE__) . '/environment.php');
require_once(dirname(__FILE__) . '/debug.php'); // depends on environment

initialize_safety();

chdir($_SERVER['basePath']);
