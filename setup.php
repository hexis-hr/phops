<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function setup () {

  enforce(isset($_SERVER['basePath']), 'basePath is required for setup');

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
    if (preg_match('/(?si)function\s*setup_/', strtolower($fileContent)) === 0 &&
        preg_match('/(?si)function\s*upgrade_/', strtolower($fileContent)) === 0)
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

  $setups = array();
  $upgrades = array();

  echo 'Listing setups ..';
  $functions = get_defined_functions();
  foreach (array_merge($functions['internal'], $functions['user']) as $function) {
    if (microtime(true) > $timestamp + 1) {
      $timestamp = microtime(true);
      echo '.';
    }
    if (strpos(strtolower($function), 'setup_') === 0)
      $setups[] = $function;
    if (strpos(strtolower($function), 'upgrade_') === 0)
      $upgrades[] = $function;
  }

  foreach (get_declared_classes() as $class) {
    foreach (get_class_methods($class) as $method) {
      if (microtime(true) > $timestamp + 1) {
        $timestamp = microtime(true);
        echo '.';
      }
      if (strpos(strtolower($method), 'setup_') === 0)
        $setups[] = array($class, $method);
      if (strpos(strtolower($method), 'upgrade_') === 0)
        $upgrades[] = array($class, $method);
    }
  }

  echo ' ' . (count($setups) + count($upgrades)) . "\n";

  $callables = array();

  if (count($setups) > 0) {
    enforce(isset($_SERVER['dataPath']), 'dataPath is required for setup');
    directory($_SERVER['dataPath'] . '/state/');
    foreach ($setups as $callable) {
      if (is_file($_SERVER['dataPath'] . '/state/setup_' . sha1(serialize($callable))))
        continue;
      $callables[] = $callable;
    }
  }

  foreach ($upgrades as $callable) {
    if (is_file($_SERVER['dataPath'] . '/state/setup_' . sha1(serialize($callable))))
      continue;
    $callables[] = $callable;
  }

  echo "\nRunning " . count($callables) . " setup(s):\n";

  $stubsTree = (object) array();

  foreach ($callables as $callable) {
    $id = (is_string($callable) ? $callable : $callable[0] . '::' . $callable[1]) . '()';
    echo '  ' . $id;
    call_user_func($callable);
    file_put_contents($_SERVER['dataPath'] . '/state/setup_' . sha1(serialize($callable)), '');
    echo "\n";
  }

  echo "Done\n";

}

function remove ($path) {
  if (!file_exists($path) && !is_link($path))
    return;
  enforce(trim($path, "/ ") != "", 'invalid path');
  switch (strtolower(strtok(php_uname('s'), ' '))) {
    case 'linux': case 'mac': case 'darwin':
      `rm -fR $path`;
      break;
    case 'windows':
      if (is_file($path) || is_link($path))
        unlink($path);
      else if (is_dir($path))
        rmdir($path);
      else
        assertTrue(false);
      break;
    default:
      assertTrue(false);
  }
}

function directory ($dir) {
  if (is_dir($dir))
    return;
  switch (strtolower(strtok(php_uname('s'), ' '))) {
    case 'linux': case 'mac': case 'darwin':
      `mkdir -p $dir`;
      break;
    case 'windows':
      mkdir($dir, 0777, true);
      break;
    default:
      assertTrue(false);
  }
}

function directoryLink ($link, $dir) {
  remove($link);
  directory(dirname($link));
  directory($dir);
  symbolicLink($link, $dir);
}

function symbolicLink ($link, $dir) {
  $_setup_bin_path = _setup_bin_path();
  switch (strtolower(strtok(php_uname('s'), ' '))) {
    case 'linux': case 'mac': case 'darwin':
      `ln -s $dir $link`;
      break;
    case 'windows':
      enforce(is_file("$_setup_bin_path/junction.exe"), 'junction executable not found');
      `$_setup_bin_path/junction.exe $link $dir`;
      break;
    default:
      assertTrue(false);
  }
}


function _setup_bin_path () {
  return dirname(__FILE__) . '/' . strtolower(strtok(php_uname('s'), ' ')) . '/';
}

