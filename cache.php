<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function staticCacheExpired () {

  static $resultCache = false;
  static $resultCacheSet = false;

  if ($resultCacheSet)
    return $resultCache;

  $timestampFile = $_SERVER['cachePath'] . '/staticCacheTimestamp';
  $resultCache = (!is_file($timestampFile) || microtime(true) > filemtime($timestampFile) + 2) && codeBaseChanged();
  $resultCacheSet = true;

  directory(dirname($timestampFile));
  $touchResult = touch($timestampFile);
  enforce($touchResult, "Could not touch '$timestampFile'");

  return $resultCache;
}

// staticCache($file, $line, [$key,] $callback)
function staticCache ($file, $line, $key, $callback = null) {

  if (!isset($callback)) {
    $callback = $key;
    $key = null;
  }

  static $map;
  if (!isset($map))
    $map = (object) array();

  if (isset($map->{"$file:$line-$key"}))
    return $map->{"$file:$line-$key"};

  $cacheFile = $_SERVER['cachePath'] . '/' . substr(pathinfo($file, PATHINFO_FILENAME), 0, 10) . '__'
    . substr(preg_replace('/(?i)[^a-z0-9]+/', '', $key), 0, 10) . '__' . sha1("$file:$line-$key") . '.cache';

  if (!is_file($cacheFile) || (version_development && staticCacheExpired())) {
    directory(dirname($cacheFile));
    file_put_contents($cacheFile, serialize($callback()));
  }

  $map->{"$file:$line-$key"} = unserialize(file_get_contents($cacheFile));

  return $map->{"$file:$line-$key"};
}
