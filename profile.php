<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function profileStopwatches () {

  static $stopwatches = null;
  if (!isset($stopwatches)) {
    $stopwatches = (object) array();
    register_shutdown_function('profileSave');
  }

  return $stopwatches;
}

function profileStopwatch ($name) {
  if (!isset(profileStopwatches()->$name))
    profileStopwatches()->$name = new profileStopwatch_($name);

  static $lastSaveTime = 0;
  if (microtime(true) > $lastSaveTime + 3) {
    profileSave();
    $lastSaveTime = microtime(true);
  }

  return profileStopwatches()->$name;
}

class profileStopwatch_ {

  function __construct ($name) {
    $this->name = $name;
    $this->_total = 0;
    $this->_calls = 0;
    $this->stack = array();
  }

  function start () {
    $this->stack[] = microtime(true);
  }

  function stop () {
    $time = array_pop($this->stack);
    if (count($this->stack) == 0) {
      $this->_calls++;
      $this->_total += (microtime(true) - $time);
    }
  }

  function calls () {
    return $this->_calls + (count($this->stack) > 0 ? 1 : 0);
  }

  function total () {
    return $this->_total + (count($this->stack) > 0 ? microtime(true) - $this->stack[0] : 0);
  }

}

function profileSave () {

  if (!array_key_exists('profile_result', $_SERVER))
    return;

  $pads = array(STR_PAD_RIGHT, STR_PAD_LEFT, STR_PAD_LEFT);
  $summary = array(
    array('name', 'calls', 'total'),
  );

  $stopwatchs = array();
  foreach (profileStopwatches() as $stopwatch)
    $stopwatchs[] = $stopwatch;

  usort($stopwatchs, function ($lhs, $rhs) { return $rhs->total() - $lhs->total(); });

  foreach ($stopwatchs as $stopwatch)
    $summary[] = array(
      $stopwatch->name,
      $stopwatch->calls(),
      $stopwatch->total() > 2 ? round($stopwatch->total(), 2) . ' s' : round($stopwatch->total() * 1000, 2) . ' ms',
    );

  $columnWidths = array_fill(0, count($summary[0]), 0);
  foreach ($summary as $summaryEntry)
    foreach ($columnWidths as $columnWidthIndex => $columnWidth)
      $columnWidths[$columnWidthIndex] = max($columnWidth, strlen($summaryEntry[$columnWidthIndex]));

  $output = '';

  if (array_key_exists('HTTP_HOST', $_SERVER) && array_key_exists('REQUEST_URI', $_SERVER))
    $output .= 'url: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http')
      . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}\n";

  $output .= 'codeTimestamp: ' . codeTimestamp() . "\n";
  $output .= 'codeChanged: ' . (codeChanged() ? 'true' : 'false') . "\n";
  $output .= 'staticCacheExpired: ' . (staticCacheExpired('profile') ? 'true' : 'false') . "\n";
  $output .= "\n";

  foreach ($summary as $summaryIndex => $summaryEntry) {
    if ($summaryIndex == 1) {
      $columns = array();
      foreach ($summaryEntry as $columnIndex => $column)
        $columns[] = str_pad('-', $columnWidths[$columnIndex], '-');
      $output .= implode('-+-', $columns) . "\n";
    }
    $columns = array();
    foreach ($summaryEntry as $columnIndex => $column)
      $columns[] = str_pad($column, $columnWidths[$columnIndex], ' ', $pads[$columnIndex]);
    $output .= implode(' | ', $columns) . "\n";
  }

  file_put_contents($_SERVER['profile_result'], "\n\n" . $output, FILE_APPEND + LOCK_EX);

}

