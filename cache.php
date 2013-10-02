<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

/**
 * Static cache that is older than this timestamp is considered expired.
 */
function lastCodeChangeTimestamp () {

  static $resultCache = 0;
  static $resultCacheSet = false;

  $timestampFile = $_SERVER['cachePath'] . '/lastCodeChangeTimestamp';
  $correlationFile = "$timestampFile.correlation";

  if (!$resultCacheSet) {

    if (!is_file($timestampFile) || microtime(true) > filemtime($timestampFile) + 2) {

      file_put_contents($timestampFile, codeTimestamp());

    } else {

      $resultCache = file_get_contents($timestampFile);
      enforce($resultCache !== false, "Could not read '$timestampFile'");
      $resultCache = (float) $resultCache;

    }

    $resultCacheSet = true;
  }

  return $resultCache > 1 ? $resultCache : codeTimestamp();
}


function staticCacheExpired ($key) {

  static $resultCache = array();

  if (!array_key_exists($key, $resultCache)) {

    $timestampFile = $_SERVER['cachePath'] . '/staticCacheExpired_' . sha1($key) . '.timestamp';
    $resultCache[$key] = !is_file($timestampFile) || filemtime($timestampFile) < lastCodeChangeTimestamp();

    directory(dirname($timestampFile));
    $touchResult = touch($timestampFile);
    enforce($touchResult, "Could not touch '$timestampFile'");

  }

  return $resultCache[$key];
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
  $cacheCorrelationFile = "$cacheFile.correlation";

  if (is_file($cacheCorrelationFile))
    foreach (array_diff(unserialize(file_get_contents($cacheCorrelationFile)), includedFile()) as $file)
      includedFile($file);

  if (!is_file($cacheFile) || (version_development && filemtime($cacheFile) < lastCodeChangeTimestamp())) {
    directory(dirname($cacheFile));
    file_put_contents($cacheFile, serialize($callback()));
    file_put_contents($cacheCorrelationFile, serialize(includedFile()));
  }

  $map->{"$file:$line-$key"} = unserialize(file_get_contents($cacheFile));

  return $map->{"$file:$line-$key"};
}
