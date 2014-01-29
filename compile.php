<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function compile () {

  enforce(isset($_SERVER['basePath']), 'basePath is required for compile');

  $isExcluded = function ($path) {
    $sPath = rtrim(str_replace(array('\\', '/'), array('/', '/'), $path), '/');
    if (array_key_exists('excludePath', $_SERVER))
      foreach ($_SERVER['excludePath'] as $excludePath) {
        $sExcludePath = rtrim(str_replace(array('\\', '/'), array('/', '/'), $excludePath), '/');
        if (substr($sPath, 0, strlen($sExcludePath)) === $sExcludePath)
          return true;
      }
    return false;
  };

  $timestamp = microtime(true);

  echo 'Listing files ..';

  $files = array();
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($_SERVER['basePath'])) as $file) {

    if (microtime(true) > $timestamp + 1) {
      $timestamp = microtime(true);
      echo '.';
    }

    if ($isExcluded($file))
      continue;

    if (!in_array($file->getExtension(), array('php')))
      continue;

    $fileContent = file_get_contents($file);
    if (preg_match('/(?si)function\s*compile_/', strtolower($fileContent)) === 0)
      continue;

    $files[] = $file;

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

  echo 'Listing compilers ..';
  $functions = get_defined_functions();
  foreach (array_merge($functions['internal'], $functions['user']) as $function) {
    if (microtime(true) > $timestamp + 1) {
      $timestamp = microtime(true);
      echo '.';
    }
    if (strpos(strtolower($function), 'compile_') === 0)
      $callables[] = $function;
  }

  foreach (get_declared_classes() as $class) {
    foreach (get_class_methods($class) as $method) {
      if (microtime(true) > $timestamp + 1) {
        $timestamp = microtime(true);
        echo '.';
      }
      if (strpos(strtolower($method), 'compile_') === 0)
        $callables[] = array($class, $method);
    }
  }

  echo ' ' . count($callables) . "\n";

  echo "\nRunning " . count($callables) . " compiler(s):\n";

  foreach ($callables as $callable) {
    $id = (is_string($callable) ? $callable : $callable[0] . '::' . $callable[1]) . '()';
    echo '  ' . $id;
    call_user_func($callable);
    echo "\n";
  }

  echo "Done\n";

}
