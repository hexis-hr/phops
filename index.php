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

function codeBaseTimestamp () {
  static $includedFiles = array();
  static $timestamp = null;
  $newIncludedFiles = get_included_files();
  if (isset($timestamp) && count($includedFiles) == count($newIncludedFiles))
    return $timestamp;
  foreach (array_diff($newIncludedFiles, $includedFiles) as $includedFile) {
    $mtime = filemtime($includedFile);
    if (!isset($timestamp) || $mtime > $timestamp)
      $timestamp = $mtime;
    $includedFiles[] = $includedFile;
  }
  return $timestamp;
}

function codeBaseChanged () {
  static $includedFiles = array();
  static $result = true;
  $newIncludedFiles = get_included_files();
  if (count($includedFiles) != count($newIncludedFiles)) {
    $fingerprintFile = $_SERVER['cachePath'] . '/codeBase_' . sha1(serialize(get_included_files())) . '.timestamp';
    $result = !is_file($fingerprintFile) || codeBaseTimestamp() > filemtime($fingerprintFile);
    if ($result) {
      directory(dirname($fingerprintFile));
      $touchResult = touch($fingerprintFile);
      enforce($touchResult, "Could not touch '$fingerprintFile'");
    }
    $includedFiles = $newIncludedFiles;
  }
  return $result;
}

// back-compatibility function
// staticIndex($file, $line, [$key,] $callback)
function staticIndex ($file, $line, $key, $callback = null) {
  return staticCache($file, $line, $key, $callback);
}
