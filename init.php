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


require_once(dirname(__FILE__) . '/functions.php');
require_once(dirname(__FILE__) . '/mail.php');
require_once(dirname(__FILE__) . '/environment.php');
require_once(dirname(__FILE__) . '/debug.php');
require_once(dirname(__FILE__) . '/safety.php');
require_once(dirname(__FILE__) . '/setup.php');
require_once(dirname(__FILE__) . '/unit-test.php');
require_once(dirname(__FILE__) . '/activity.php');

chdir($_SERVER['basePath']);


// auto setup

if (is_file($_SERVER['setupPath']))
  require($_SERVER['setupPath']);

if (function_exists('setup_run_once')) {
  assertTrue(isset($_SERVER['dataPath']));
  if (!is_file($_SERVER['dataPath'] . "/_setup_run_once_" . sha1($_SERVER['basePath']))) {
    $autoSetupLock = fopen($_SERVER['dataPath'] . "/_setup_run_once_lock_" . sha1($_SERVER['basePath']), 'w+');
    flock($autoSetupLock, LOCK_EX);
    if (!is_file($_SERVER['dataPath'] . "/_setup_run_once_" . sha1($_SERVER['basePath']))) {
      try {
        touch($_SERVER['dataPath'] . "/_setup_run_once_" . sha1($_SERVER['basePath']));
        setup_run_once();
      } catch (Exception $e) {
        remove($_SERVER['dataPath'] . "/_setup_run_once_" . sha1($_SERVER['basePath']));
        throw $e;
      }
    }
    fclose($autoSetupLock);
  }
}

