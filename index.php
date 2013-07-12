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

// back-compatibility function
// staticIndex($file, $line, [$key,] $callback)
function staticIndex ($file, $line, $key, $callback = null) {
  return staticCache($file, $line, $key, $callback);
}
