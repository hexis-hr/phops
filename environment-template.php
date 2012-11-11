<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

/**
 * environment example
 * make a copy of this file and specify it's location using the following apache directive
 *
 *   SetEnv environment_configuration /var/sites/my_web_site/environment.php
 *
 */

# $_SERVER['project'] = 'my_project';

# $_SERVER['instance'] = 'server_no_1';

# $_SERVER['environment'] = 'production';
# $_SERVER['environment'] = 'testing';
# $_SERVER['environment'] = 'development';

# $_SERVER['baseHostname'] = 'www.example.com';
# $_SERVER['baseHostname'] = 'test.example.com';

# $_SERVER['baseUrl'] = 'http://www.example.com';
# $_SERVER['baseUrl'] = 'http://test.example.com';
# no trailing slash !

# $_SERVER['robots'] = 'empty';
# $_SERVER['robots'] = 'deny';
# $_SERVER['robots'] = 'robots.txt';

# $_SERVER['dataPath'] = '/var/sites/my_web_site/data';
# no trailing slash !

# $_SERVER['debugMode'] = false;
# $_SERVER['debugPath'] = '/var/sites/my_web_site/debug.log';

# $_SERVER['safety']['enabled'] = true;
# $_SERVER['safety']['send_reports'] = false;
# $_SERVER['safety']['display_errors'] = true;
# $_SERVER['safety']['mode'] = 'transitional';
# $_SERVER['safety']['mode'] = 'strict';
# $_SERVER['safety']['error_reporting'] = E_ALL | E_STRICT;
# $_SERVER['safety']['time_limit'] = 10;
# $_SERVER['safety']['report_url'] = 'http://issues.mysite.com/';
# $_SERVER['safety']['exclude_path'] = array('/path/to/libraries', '/path/to/libraries2');


# $_SERVER['safety']['report_exception'] = array("address@example.com");
# $_SERVER['safety']['report_error_exception'] = array("address@example.com");
# $_SERVER['safety']['report_caught_exception'] = array("address@example.com");
# $_SERVER['safety']['report_everything'] = array("address@example.com");

# $_SERVER['unitTest_wdUrl'] = "http://localhost:4444/wd/hub";

