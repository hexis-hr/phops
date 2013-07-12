<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

/** defaults - do not change **/

if (isset($_SERVER['default_environment_configuration']) && $_SERVER['default_environment_configuration'])
  require($_SERVER['default_environment_configuration']);

if (isset($_SERVER['environment_configuration']) && $_SERVER['environment_configuration'])
  require($_SERVER['environment_configuration']);

if (!isset($_SERVER['environment']))
  $_SERVER['environment'] = 'development';

if (!isset($_SERVER['baseHostname']) && isset($_SERVER['HTTP_HOST']))
  $_SERVER['baseHostname'] = $_SERVER['HTTP_HOST'];

if (!isset($_SERVER['baseUrl']) && isset($_SERVER['HTTP_HOST']))
  $_SERVER['baseUrl'] = 'http://' . $_SERVER['HTTP_HOST'];

if (!isset($_SERVER['basePath']))
  $_SERVER['basePath'] = realpath(dirname($_SERVER['SCRIPT_FILENAME']));

if (!isset($_SERVER['robots']) || !$_SERVER['robots'])
  $_SERVER['robots'] = 'robots-deny.txt';

if (!isset($_SERVER['debugMode']) || !$_SERVER['debugMode'])
  $_SERVER['debugMode'] = false;

if ((!isset($_SERVER['debugPath']) || !$_SERVER['debugPath']) && isset($_SERVER['dataPath']))
  $_SERVER['debugPath'] = $_SERVER['dataPath'] . '/debug.log';

if (!isset($_SERVER['librariesPath']) || !$_SERVER['librariesPath'])
  $_SERVER['librariesPath'] = array();

$_SERVER['librariesPath'][] = __DIR__;

if (!isset($_SERVER['setupPath']) || !$_SERVER['setupPath'])
  $_SERVER['setupPath'] = $_SERVER['basePath'] . '/setup/auto-setup.php';

if (!isset($_SERVER['cachePath']) || !$_SERVER['cachePath'])
  $_SERVER['cachePath'] = sys_get_temp_dir();

if (!isset($_SERVER['remoteAddress']) || !$_SERVER['remoteAddress'])
  $_SERVER['remoteAddress'] = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR']
    : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);

if (strpos($_SERVER['remoteAddress'], ',') !== false)
  $_SERVER['remoteAddress'] = trim(substr($_SERVER['remoteAddress'], 0, strpos($_SERVER['remoteAddress'], ',')));

if (!isset($_SERVER['unitTest_wdUrl']) || !$_SERVER['unitTest_wdUrl'])
  $_SERVER['unitTest_wdUrl'] = "http://localhost:4444/wd/hub";


define("version_assert", $_SERVER['environment'] != 'production');
define("version_unittest", $_SERVER['environment'] == 'unittest');
define("version_cache", $_SERVER['environment'] == 'production' || $_SERVER['environment'] == 'test'
  || $_SERVER['environment'] == 'unittest');
define("version_test", $_SERVER['environment'] == 'test' || $_SERVER['environment'] == 'unittest');
define("version_development", $_SERVER['environment'] == 'development' || $_SERVER['environment'] == 'design');
define("version_design", $_SERVER['environment'] == 'design');
define("version_functional", $_SERVER['environment'] != 'design');
define("version_production", $_SERVER['environment'] == 'production');

