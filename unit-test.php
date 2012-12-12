<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

require_once(dirname(__FILE__) . '/externals/php-webdriver/__init__.php');

class unitTestEnvironmentException extends Exception {}

function runUnitTests () {
  //assertTrue(isset($_SERVER['unitTest_result']), "Result file not set");
  $result = (object) array(
    'tests' => (object) array(
      'count' => 0,
      'all' => array(),
      'successes' => array(),
      'failures' => array(),
      'errors' => array(),
    ),
  );
  //var_dump($_SERVER['basePath']);
  //exit;
  $timestamp = microtime(true);
  echo 'Listing files ..';
  $files = array();
  //$_fc = 0;

  $unitTest_excludePath = array();
  if (isset($_SERVER['unitTest_excludePath'])) {
    $addPaths = function ($paths) use (&$unitTest_excludePath, &$addPaths) {
      if (is_array($paths) || is_object($paths))
        foreach ($paths as $path)
          $addPaths($path);
      else
        $unitTest_excludePath[] = rtrim(str_replace(array('\\', '/'), array('/', '/'), $paths), '/') . '/';
    };
    $addPaths($_SERVER['unitTest_excludePath']);
  }

  $isExcluded = function ($file) use ($unitTest_excludePath) {
    $sFile = str_replace(array('\\', '/'), array('/', '/'), $file);
    foreach ($unitTest_excludePath as $excludePath) {
      //echo rtrim(str_replace(array('\\', '/'), array('/', '/'), $excludePath), '/') . "\n";
      //exit;
      if (substr($sFile, 0, strlen($excludePath)) == $excludePath) {
        //$lastExcludedPath = $excludePath;
        //var_dump($lastExcludedPath);
        //$isExcluded = true;
        //break;
        return true;
      }
    }
    return false;
  };

  //$directoryIterator = new RecursiveDirectoryIterator($_SERVER['basePath']);
  //$iterator = new RecursiveIteratorIterator($directoryIterator);
  //$skipIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($_SERVER['basePath']));
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($_SERVER['basePath'])) as $file) {
    //$skipIterator->nextElement();
    //$sFile = str_replace(array('\\', '/'), array('/', '/'), $file);
    //unset($lastExcludedPath);
    if (microtime(true) > $timestamp + 1) {
      $timestamp = microtime(true);
      echo '.';
    }
    
    if ($isExcluded($file)) {
      /*
      var_dump($iterator->current());
      $directoryIterator->next();
      $iterator->next();
      var_dump($iterator->current());
      exit;
      /**/
      continue;
      //$skipIterator = new RecursiveIteratorIterator(clone $iterator);
      //var_dump($iterator->current());
      //var_dump($skipIterator->current());
      //exit;
      /*
      $skipIterator->next();
      $skipIterator->next();
      $skipIterator->next();
      $skipIterator->next();
      var_dump($skipIterator->current());
      exit;
      for ($i = 0; $i < 50; $i++)
        $skipIterator->next();
        //var_dump($skipIterator->current());exit;
      if ($isExcluded($skipIterator->current())) {
        for ($i = 0; $i < 50; $i++)
          $iterator->next();
      }
      /**/
    }

    /*
    $isExcluded = false;
    if (isset($lastExcludedPath) && substr($sFile, 0, strlen($lastExcludedPath)) == $lastExcludedPath) {
      //var_dump("o");
      $isExcluded = true;
    }
    if (!$isExcluded)
      foreach ($unitTest_excludePath as $excludePath) {
        //echo rtrim(str_replace(array('\\', '/'), array('/', '/'), $excludePath), '/') . "\n";
        //exit;
        if (substr($sFile, 0, strlen($excludePath)) == $excludePath) {
          $lastExcludedPath = $excludePath;
          //var_dump($lastExcludedPath);
          $isExcluded = true;
          break;
        }
      }
    //exit;
    if ($isExcluded) 
      continue;
    /**/
    if (in_array($file->getExtension(), array('php'))) {
      //$files[] = $file;
      //echo $file . "\n";
      $fileContent = file_get_contents($file);
      if (preg_match('/(?si)unittest/', $fileContent) == 0)
        continue;
      //if (preg_match_all('/(?si)(extends|implements)\s+([a-z0-9_\x80-\xff]+)/', $fileContent, $matches) == 0) {
      //  //var_dump($matches);
      //  //$files[] = $file;
      //  array_unshift($files, $file);
      //  continue;
      //}
      $files[] = $file;
    }
    //if (count($files) > 100)
    //  break;
    //var_dump($file);
  }
  echo ' ' . count($files) . "\n";
  
  echo 'Including files ..';
  //while (count($files) > 0) {
  $i = 0;
  foreach ($files as $file) {
    if (microtime(true) > $timestamp + 1) {
      $timestamp = microtime(true);
      echo '.';
    }
    //$file = array_shift($files);
    //echo $file . "\n";
    //$fileContent = file_get_contents($file);
    //if (preg_match_all('/(?si)extends\s+([a-z0-9_\x80-\xff]+)/', $fileContent, $matches) > 0) {
    //  //var_dump($matches);
    //  $files[] = $file;
    //}
    //var_dump(in_array(realpath($file), get_included_files()));
    if (in_array(realpath($file), get_included_files()))
      continue;
    $i++;
    ob_start();
    include_once (string) $file;
    ob_end_clean();
  }
  echo ' ' . $i . "\n";
  
  $tests = array();
  
  echo 'Listing tests ..';
  $functions = get_defined_functions();
  foreach (array_merge($functions['internal'], $functions['user']) as $function) {
    if (microtime(true) > $timestamp + 1) {
      $timestamp = microtime(true);
      echo '.';
    }
    if (substr(strtolower($function), 0, 8) == 'unittest')
      $tests[] = $function;
  }
  
  foreach (get_declared_classes() as $class) {
    foreach (get_class_methods($class) as $method) {
      if (microtime(true) > $timestamp + 1) {
        $timestamp = microtime(true);
        echo '.';
      }
      if (substr(strtolower($method), 0, 8) == 'unittest' && !in_array("$class::$method", array(
        'unitTest_webBrowser::unitTestElement',
        'unitTest_element::unitTestElement',
        'unitTest_webContext::unitTestElement',
        'unitTest_webBrowser::unitTestElements',
        'unitTest_element::unitTestElements',
        'unitTest_webContext::unitTestElements',
      )))
        $tests[] = array($class, $method);
    }
  }
  
  echo ' ' . count($tests) . "\n";
  
  $result->tests->count = count($tests);
  
  echo "\nRunning " . count($tests) . " tests:\n";
  
  foreach ($tests as $test) {
    $id = (is_string($test) ? $test : $test[0] . '::' . $test[1]) . '()';
    echo '  ' . $id;
    resetUnitTestEnvironment();
    $result->tests->all[$id] = (object) array('status' => 'unknown');
    try {
      call_user_func($test);
      echo ": success";
      $result->tests->all[$id]->status = 'success';
      $result->tests->successes[] = $id;
    } catch (unitTestEnvironmentException $e) {
      echo ": error";
      $result->tests->all[$id]->status = 'error';
      $result->tests->all[$id]->message = $e->getMessage();
      $result->tests->all[$id]->trace = (string) $e;
      $result->tests->errors[] = $id;
    } catch (Exception $e) {
      echo ": failure";
      $result->tests->all[$id]->status = 'failure';
      $result->tests->all[$id]->message = $e->getMessage();
      $result->tests->all[$id]->trace = (string) $e;
      $result->tests->failures[] = $id;
    }
    echo "\n";
  }
  
  echo "\nResults: \n";
  
  if (isset($_SERVER['unitTest_result']))
    file_put_contents($_SERVER['unitTest_result'], json_encode($result) . "\n");
  
  echo "  Total:   " . $result->tests->count . "\n";
  echo "  Success: " . count($result->tests->successes) . "\n";
  echo "  Failure: " . count($result->tests->failures) . "\n";
  echo "  Errors:  " . count($result->tests->errors) . "\n";
  
  if (count($result->tests->successes) != $result->tests->count)
    echo "\nTests completed with failures or errors !\n\n";
  else
    echo "\nEverything completed successfully !\n\n";
    
  echo "Done\n";
  
}

function resetUnitTestEnvironment () {
  //if ($webBrowser = webBrowser(false))
  //  $browser->redirect($_SERVER['baseUrl']);
}

//function webBrowser ($autoCreate = true) {
function webBrowser () {
  static $browser;
  if (!isset($browser)) {
    //if (!$autoCreate)
    //  return;
    assertTrue(isset($_SERVER['unitTest_wdUrl']));
    $browser = new unitTest_webBrowser($_SERVER['unitTest_wdUrl']);
    //$browser->redirect($_SERVER['baseUrl']);
    //$browser = new WebDriver($_SERVER['unitTest_wdUrl']);
    //$session = $webDriver->session('firefox');
    //assertTrue(isset($_SERVER['unitTest_wdSession']));
  }
  //$session = $webDriver->session('firefox');
  return $browser;
}

class unitTest_webContext {

  protected $browser;
  protected $context;

  function query ($query) {
    $using = 'css selector';
    if (in_array($query, array('.', '..')) || preg_match('/\/|\@|\:\:|\.\./', $query))
      $using = 'xpath';
    $results = $this->context->elements($using, $query);
    //assertTrue(count($results) > 0, 'no elements found');
    $wrappedResults = new unitTest_elements();
    foreach ($results as $result)
      $wrappedResults[] = new unitTest_element($this->browser, $result);
    return $wrappedResults;
  }

  function queryOne ($query) {
    $results = $this->query($query);
    assertTrue(count($results) == 1, 'found ' . count($results) . ' results (1 expected)');
    return $results[0];
  }

  function unitTestElement ($id) {
    $results = $this->query("[data-unit-test-element=$id]");
    if (count($results) == 0)
      $results = $this->query("[unit-test-element=$id]");
    assertTrue(count($results) == 1, 'found ' . count($results) . ' results (1 expected)');
    return $results[0];
  }

  function unitTestElements ($id) {
    $results = $this->query("[data-unit-test-element=$id]");
    if (count($results) == 0)
      $results = $this->query("[unit-test-element=$id]");
    assertTrue(count($results) > 0, 'found ' . count($results) . ' results (more then 0 expected)');
    return $results;
  }

  function click () {
    $context = $this->context;
    $this->_ensureVisible(function () use ($context) {
      $context->click();
    });
    return $this;
  }
  
  function execute ($script, $arguments = array()) {
    foreach ($arguments as $key => $value)
      if (is_object($value) && $value instanceof unitTest_element)
        $arguments[$key] = (object) array('ELEMENT' => $value->context->getID());
    return $this->browser->context->execute(array(
      'script' => $script,
      'args' => $arguments,
    ));
  }
  
  function exists () {
    try {
      $this->context->name();
      return true;
    } catch (ObsoleteElementWebDriverError $e) {
      return false;
    }
    assertTrue(false);
  }

}

class unitTest_webBrowser extends unitTest_webContext {
  
  private $driver;
  
  function __construct ($url) {
    try {
      $this->driver = new WebDriver($url);
      $this->context = $this->driver->session('firefox');
    } catch (WebDriverCurlException $e) {
      throw new unitTestEnvironmentException("Could not open a browser session", 0, $e);
    }
    $this->browser = $this;
  }
  
  function __destruct () {
    if (isset($this->context))
      $this->context->close();
  }
  
  function redirect ($url) {
    if (strpos($url, '://') == false) {
      assertTrue(isset($_SERVER['baseUrl']), "baseUrl not set", 'unitTestEnvironmentException');
      $url = $_SERVER['baseUrl'] . (substr($url, 0, 1) == '/' ? substr($url, 1) : $url);
    }
    $this->context->open($url);
  }
  
}

class unitTest_elements extends \ArrayObject {
  
  function query ($query) {
    $results = new unitTest_elements();
    foreach ($this as $queryElement)
      foreach ($queryElement->query($query) as $resultElement)
        $results[] = $resultElement;
    return $results;
  }
  
  function __call ($name, $arguments) {
    assertTrue(count($this) == 1);
    return call_user_func_array(array($this[0], $name), $arguments);
  }

  function __set ($name, $value) {
    assertTrue(count($this) == 1);
    $this[0]->$name = $value;
  }

  function __get ($name) {
    assertTrue(count($this) == 1);
    return $this[0]->$name;
  }
  
  function one () {
    assertTrue(count($this) == 1, 'found ' . count($this) . ' elements (1 expected)');
    return $this[0];
  }
  
  function any () {
    return $this[0];
  }

}

class unitTest_element extends unitTest_webContext {

  private $info;
  
  function __construct ($browser, $context) {
    $this->browser = $browser;
    $this->context = $context;
    $this->info = (object) array();
  }
  
  function __set ($name, $value) {
    $this->{'_set_' . $name}($value);
  }
  
  function __get ($name) {
    return $this->{'_get_' . $name}();
  }
  
  function _get_name () {
    return $this->context->name();
  }
  
  function _set_value ($value) {
    $context = $this->context;
    $this->_ensureVisible(function () use ($context, $value) {
      $context->value(array('value' => str_split($value)));
    });
  }

  function _get_value () {
    return $this->context->attribute('value');
  }

  function _get_displayed () {
    return $this->context->displayed();
  }
  
  function attribute ($name) {
    return new unitTest_attribute($this->context->attribute($name));
  }
  
  function select () {
    assertTrue($this->_get_name() == 'option');
    $select = $this->queryOne('..');
    assertTrue($select->_get_name() == 'select');
    $option = $this;
    $select->_ensureVisible(function () use ($option) {
      $option->click();
    });
  }
  
  function _ensureVisible ($f) {
    assertTrue(is_callable($f));
    if ($this->displayed) {
      $f();
      return;
    }
    $state = (object) array(
      'display' => $this->context->css('display'),
      'visibility' => $this->context->css('visibility'),
      'hiddenInput' => $this->context->name() == 'input' && $this->context->attribute('type') == 'hidden',
    );
    $e = null;
    try {
      if ($state->display == 'none' || $state->visibility == 'hidden')
        $this->browser->execute('arguments[0].style.display = "block"; arguments[0].style.visibility = "visible";', array($this));
      if ($state->hiddenInput)
        $this->browser->execute('arguments[0].type = "text";', array($this));
      // todo: rewrite
      if ($this->context->name() != 'html')
        $parentCollection = $this->query('..');
      //try { $e = null; $parentCollection = $this->query('..'); } catch (Exception $e) {}
      //if (isset($e) || count($parentCollection) == 0)
      if (!isset($parentCollection) || count($parentCollection) == 0)
        $f();
        //try { $f(); } catch (Exception $e) { sleep(30); throw $e; }
      else
        $parentCollection->one()->_ensureVisible($f);
    } catch (Exception $e) {}
    // finally {
    //try {
    // we don't care if the elements disappear in the meantime, we only want to make sure
    // to hide set their visibility state as it was if they still exist
    if ($this->exists()) {
      if ($state->display == 'none' || $state->visibility == 'hidden')
        $this->browser->execute('arguments[0].style.display = ' . json_encode($state->display) . '; arguments[0].style.visibility = ' . json_encode($state->visibility) . ';', array($this));
       if ($state->hiddenInput)
        $this->browser->execute('arguments[0].type = "hidden";', array($this));
    }
    //} catch (ObsoleteElementWebDriverError $dontCareE) {
      // we don't care if the elements disappear in the meantime, we only want to make sure
      // to hide set their visibility state as it was if they still exist
    //}
    // }
    if (isset($e))
      throw $e;
  }
  
  /*
  function show () {
    $this->info->shown = (object) array(
      'display' => $this->element->css('display'),
      'visibility' => $this->element->css('visibility'),
    );
    $this->browser->execute('arguments[0].style.display = ""; arguments[0].style.visibility = "";', array((object) array('ELEMENT' => $this->element->getID())));
    return $this;
  }
  
  function hide () {
    $this->browser->context->execute(
        'arguments[0].style.display = ' . json_encode(isset($this->info->shown, $this->info->shown->display) ? $this->info->shown->display : 
          (isset($this->info->shown, $this->info->shown->visibility) && $this->info->shown->visibility == 'hidden' ? '' : 'none')
        ) . ';' .
        'arguments[0].style.visibility = ' . json_encode(isset($this->info->shown, $this->info->shown->visibility) ? $this->info->shown->visibility : '') . ';',
      array((object) array('ELEMENT' => $this->element->getID()))
    );
    return $this;
  }
  /**/
  
  function moveTo () {
    $this->browser->context->moveto(array('element' => $this->context->getID()));
  }

}

class unitTest_attribute {

  public $value;

  function __construct ($value) {
    $this->value = $value;
  }

}
