<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function persistentIndex ($callback, $file = __FILE__, $line = __LINE__) {
  var_dump($file);
  var_dump($line);
  assertTrue(false);
}

// back-compatibility function
function codeBaseTimestamp () {
  return codeTimestamp();
}

function codeTimestamp () {
  static $includedFiles = array();
  static $timestamp = null;
  $newIncludedFiles = get_included_files();
  if (isset($timestamp) && count($includedFiles) == count($newIncludedFiles))
    return $timestamp;
  foreach (array_diff($newIncludedFiles, $includedFiles) as $includedFile) {
    if (!is_file($includedFile))
      continue;
    $mtime = filemtime($includedFile);
    if (!isset($timestamp) || $mtime > $timestamp)
      $timestamp = $mtime;
    $includedFiles[] = $includedFile;
  }
  return $timestamp;
}

// back-compatibility function
function codeBaseChanged () {
  return codeChanged();
}

function codeChanged () {
  static $includedFiles = array();
  static $result = true;
  $newIncludedFiles = get_included_files();
  if (count($includedFiles) != count($newIncludedFiles)) {
    $fingerprintFile = $_SERVER['cachePath'] . '/codeBase_' . sha1(serialize(get_included_files())) . '.timestamp';
    $result = !is_file($fingerprintFile) || codeTimestamp() > filemtime($fingerprintFile);
    if ($result) {
      directory(dirname($fingerprintFile));
      $touchResult = touch($fingerprintFile);
      enforce($touchResult, "Could not touch '$fingerprintFile'");
    }
    $includedFiles = $newIncludedFiles;
  }
  return $result;
}

/*
function codeIncludes ($path = null) {

  static $paths = array();
  static $pathsCacheCount = 0;
  static $filesCache = array();


  if (!isset($path)) {
    if ($pathsCacheCount != count($paths)) {
      version_profile and profileStopwatch('codeIncludes(): list files')->start();
      $filesCache = array();
      foreach ($paths as $path)
        if (is_file($path))
          $filesCache[] = $path;
        else if (is_dir($path))
          foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file)
            $filesCache[] = (string) $file;
      $pathsCacheCount = count($paths);
      version_profile and profileStopwatch('codeIncludes(): list files')->stop();
    }
    return array_merge(get_included_files(), $filesCache);
  }

  $paths[] = $path;
}
/**/

// back-compatibility function
// staticIndex($file, $line, [$key,] $callback)
function staticIndex ($file, $line, $key, $callback = null) {
  return staticCache($file, $line, $key, $callback);
}
