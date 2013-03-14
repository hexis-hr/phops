<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function persistentIndex ($callback, $file = __FILE__, $line = __LINE__) {
  var_dump($file);
  var_dump($line);
  exit;
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

// staticIndex($file, $line, [$key,] $callback)
function staticIndex ($file, $line, $key, $callback = null) {

  if (!isset($callback)) {
    $callback = $key;
    $key = null;
  }
  
  static $map;
  if (!isset($map))
    $map = (object) array();
  
  if (isset($map->{"$file:$line-$key"}))
    return $map->{"$file:$line-$key"};
    
  $indexFile = sys_get_temp_dir() . '/' . substr(pathinfo($file, PATHINFO_FILENAME), 0, 10) . '__'
    . substr(preg_replace('/(?i)[^a-z0-9]+/', '', $key), 0, 10) . '__' . sha1("$file:$line-$key") . '.index';
  
  if (!is_file($indexFile) || codeBaseTimestamp() >= filemtime($indexFile))
    file_put_contents($indexFile, serialize($callback()));
  
  $map->{"$file:$line-$key"} = unserialize(file_get_contents($indexFile));
  
  return $map->{"$file:$line-$key"};
}
