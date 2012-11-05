<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

require_once(dirname(__FILE__) . '/externals/php-webdriver/__init__.php');

function runUnitTests () {
  //var_dump($_SERVER['basePath']);
  //exit;
  $timestamp = microtime(true);
  echo 'Listing files ..';
  $files = array();
  //$_fc = 0;
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($_SERVER['basePath'])) as $file) {
    if (microtime(true) > $timestamp + 1) {
      $timestamp = microtime(true);
      echo '.';
    }
    $isExcluded = false;
    foreach (array_merge(array(__DIR__), isset($_SERVER['unitTest_excludePath']) ? (array) $_SERVER['unitTest_excludePath'] : array()) as $excludePath) {
      //echo rtrim(str_replace(array('\\', '/'), array('/', '/'), $excludePath), '/') . "\n";
      //exit;
      if (substr(str_replace(array('\\', '/'), array('/', '/'), $file), 0, strlen(rtrim(str_replace(array('\\', '/'), array('/', '/'), $excludePath), '/') . '/')) == rtrim(str_replace(array('\\', '/'), array('/', '/'), $excludePath), '/') . '/')
        $isExcluded = true;
    }
    //exit;
    if ($isExcluded)
      continue;
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
      if (substr(strtolower($method), 0, 8) == 'unittest' && !in_array("$class::$method", array('unitTest_webBrowser::unitTestElement', 'unitTest_element::unitTestElement', 'unitTest_webContext::unitTestElement')))
        $tests[] = array($class, $method);
    }
  }
  
  echo ' ' . count($tests) . "\n";
  
  echo "Running " . count($tests) . " tests:\n";
  
  foreach ($tests as $test) {
    echo '  ' . (is_string($test) ? $test : $test[0] . '::' . $test[1]) . "()\n";
    call_user_func($test);
  }
  
  
  echo 'Done';
}

function webBrowser () {
  static $browser;
  if (!isset($browser)) {
    assertTrue(isset($_SERVER['unitTest_wdUrl']));
    $browser = new unitTest_webBrowser($_SERVER['unitTest_wdUrl']);
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
    if (preg_match('/\/|\@|\:\:|\.\./', $query))
      $using = 'xpath';
    $results = $this->context->elements($using, $query);
    assertTrue(count($results) > 0, 'no elements found');
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
    return $this->queryOne("[unit-test-element=$id]");
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

}

class unitTest_webBrowser extends unitTest_webContext {
  
  private $driver;
  
  function __construct ($url) {
    $this->driver = new WebDriver($url);
    $this->context = $this->driver->session('firefox');
    $this->browser = $this;
  }
  
  function __destruct () {
    //if (isset($this->context))
    //  $this->context->close();
  }
  
  function redirect ($url) {
    $this->context->open($url);
  }
  
}

class unitTest_elements extends \ArrayObject {

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
    $this->context->value(array('value' => str_split($value)));
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
    $state = (object) array(
      'display' => $this->context->css('display'),
      'visibility' => $this->context->css('visibility'),
    );
    $e = null;
    try {
      if ($state->display == 'none' || $state->visibility == 'hidden')
        $this->browser->execute('arguments[0].style.display = ""; arguments[0].style.visibility = "";', array($this));
      $f();
    } catch (Exception $e) {}
    // finally {
    if ($state->display == 'none' || $state->visibility == 'hidden')
      $this->browser->execute('arguments[0].style.display = ' . json_encode($state->display) . '; arguments[0].style.visibility = ' . json_encode($state->visibility) . ';', array($this));
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

