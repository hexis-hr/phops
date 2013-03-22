<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function generateStubs ($destinationDirectory) {

  enforce(isset($_SERVER['basePath']), 'basePath is required for stubs generation');

  $timestamp = microtime(true);
  echo 'Listing files ..';
  
  $files = array();
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($_SERVER['basePath'])) as $file) {

    if (microtime(true) > $timestamp + 1) {
      $timestamp = microtime(true);
      echo '.';
    }

    if (in_array($file->getExtension(), array('php'))) {
      $fileContent = file_get_contents($file);
      if (preg_match('/(?si)function\s*stub/', $fileContent) == 0)
        continue;
      $files[] = $file;
    }
  }
  echo ' ' . count($files) . "\n";
  
  echo 'Including files ..';

  $i = 0;
  
  // php include can overwrite variable values so we need to isolate include in a function
  $includeFile = function ($file) {
    ob_start();
    include_once (string) $file;
    ob_end_clean();
  };
  
  foreach ($files as $file) {
    if (microtime(true) > $timestamp + 1) {
      $timestamp = microtime(true);
      echo '.';
    }
    if (in_array(realpath($file), get_included_files()))
      continue;
    $i++;
    $includeFile($file);
  }
  echo ' ' . $i . "\n";

  $callables = array();
  
  echo 'Listing stub generators ..';
  $functions = get_defined_functions();
  foreach (array_merge($functions['internal'], $functions['user']) as $function) {
    if (microtime(true) > $timestamp + 1) {
      $timestamp = microtime(true);
      echo '.';
    }
    if (strpos(strtolower($function), 'stub') === 0)
      $callables[] = $function;
  }
  
  foreach (get_declared_classes() as $class) {
    foreach (get_class_methods($class) as $method) {
      if (microtime(true) > $timestamp + 1) {
        $timestamp = microtime(true);
        echo '.';
      }
      if (strpos(strtolower($method), 'stub') === 0)
        $callables[] = array($class, $method);
    }
  }
  
  echo ' ' . count($callables) . "\n";
  
  echo "\nRunning " . count($callables) . " stub generator(s):\n";
  
  $stubsTree = (object) array();
  
  foreach ($callables as $callable) {
    $id = (is_string($callable) ? $callable : $callable[0] . '::' . $callable[1]) . '()';
    echo '  ' . $id;
    $stubs = call_user_func($callable);
    foreach (is_array($stubs) ? $stubs : array($stubs) as $stub) {
      if (isset($stub->{'class'})) {
        if (!isset($stubsTree->{$stub->{'class'}}))
          $stubsTree->{$stub->{'class'}} = (object) array(
            'class' => $stub->{'class'},
            'methods' => (object) array(),
            'properties' => (object) array(),
          );
        if (isset($stub->method))
          $stubsTree->{$stub->{'class'}}->methods->{$stub->method} = $stub;
        else if (isset($stub->property))
          $stubsTree->{$stub->{'class'}}->properties->{$stub->property} = $stub;
        else
          assertTrue(false);
      }
    }
    echo "\n";
  }
  
  directory($destinationDirectory);
  
  echo "\nGenerating stub files:\n";
  foreach ($stubsTree as $stub) {
    if (isset($stub->{'class'})) {
      $stubFile = $stub->{'class'};
      $stubString = "class {$stub->{'class'}} {";
      foreach ($stub->properties as $name => $method) {
        $stubString .= "\n\n";
        $stubString .= "  /**\n";
        if (isset($method->{'type'}))
          $stubString .= '   * @var ' . $method->{'type'} . "\n";
        $stubString .= "   */\n";
        $stubString .= "  public \$$name;";
      }
      foreach ($stub->methods as $name => $method) {
        $stubString .= "\n\n";
        $stubString .= "  /**\n";
        if (isset($method->{'return'}))
          $stubString .= '   * @return ' . $method->{'return'} . "\n";
        $stubString .= "   */\n";
        $stubString .= "  function $name ();";
      }
      $stubString .= "\n\n}";
    }
    
    echo "  $stubFile\n";
    file_put_contents($destinationDirectory . '/' . $stubFile . '.stubs.php', "<?php\n\n$stubString\n\n");
  }
  
  echo "Done\n";
  
}

