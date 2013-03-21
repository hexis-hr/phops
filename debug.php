<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

if (!defined('debugMode'))
  define('debugMode', $_SERVER['debugMode']);

// debugMessage ([$type,] $message)
function debugMessage ($message) {
  $args = func_get_args();
  $type = isset($args[1]) ? $args[0] : null;
  $message = isset($args[1]) ? $args[1] : $message;

  $backtrace = debug_backtrace();
  
  $i = 0;
  while (isset($backtrace[$i])) {
    $backtraceEntry = (object) $backtrace[$i];
    $i++;
    //if (isset($backtraceEntry->file) && substr($backtraceEntry->file, 0, strlen(realpath($_SERVER['basePath']))) != realpath($_SERVER['basePath']))
    //  continue;
    if (isset($backtraceEntry->file) && realpath($backtraceEntry->file) == realpath(__FILE__))
      continue;
    break;
  }
  
  if (isset($backtrace[$i], $backtrace[$i]['class']))
    $backtraceEntry->{'class'} = $backtrace[$i]['class'];
  if (isset($backtrace[$i], $backtrace[$i]['type']))
    $backtraceEntry->{'type'} = $backtrace[$i]['type'];
  if (isset($backtrace[$i], $backtrace[$i]['function']))
    $backtraceEntry->{'function'} = $backtrace[$i]['function'];
    
  // format:
  // info is the message type (optional)
  // #5/6 is the request id / session id
  // 123 is the line number
  //
  // [2000-01-01 18:23:54] #5 (/path/to/file:123) info
  //   message text
  
  if (!isset($_SERVER['requestId']))
    $_SERVER['requestId'] = randomKey(24);
  
  if (!session_id())
    session_start();
    
  $timestamp = microtime(true);
  
  if (!isset($GLOBALS['__7iPYslyzfKzZBtZBc7T6aglQ_debugLog']))
    $GLOBALS['__7iPYslyzfKzZBtZBc7T6aglQ_debugLog'] = array();
    
  static $counter = 0;
  
  $GLOBALS['__7iPYslyzfKzZBtZBc7T6aglQ_debugLog'][] = (object) array(
    'time' => $timestamp,
    'number' => ++$counter,
    'file' => isset($backtraceEntry, $backtraceEntry->file) ? str_replace(array('\\', '/'), array('/', '/'), realpath($backtraceEntry->file)) : null,
    'line' => isset($backtraceEntry, $backtraceEntry->line) ? $backtraceEntry->line : null,
    'type' => isset($type) ? $type : null,
    'call' => (isset($backtraceEntry->{'class'}) ? $backtraceEntry->{'class'} . $backtraceEntry->{'type'} : '') . $backtraceEntry->{'function'} . '()',
    'message' => $message,
  );

  if (isset($_SERVER['debugPath']))
    file_put_contents($_SERVER['debugPath'],
      "[" . date("Y-m-d H:i:s", $timestamp) . "." . substr(round($timestamp - floor($timestamp), 6), 2) . "] #{$_SERVER['requestId']}/" . session_id() . (isset($backtraceEntry->file) ? " ({$backtraceEntry->file}:{$backtraceEntry->line})" : '') . (isset($type) ? " $type" : '') . "\n" .
      "  " . (isset($backtraceEntry->{'class'}) ? $backtraceEntry->{'class'} . $backtraceEntry->{'type'} : '') . $backtraceEntry->{'function'} . "(): $message\n", FILE_APPEND | LOCK_EX);

}

function debugObject ($object) {
  if (!isset($GLOBALS['__7iPYslyzfKzZBtZBc7T6aglQ_debugObjects']))
    $GLOBALS['__7iPYslyzfKzZBtZBc7T6aglQ_debugObjects'] = (object) array();
  $id = randomKey(24);
  $GLOBALS['__7iPYslyzfKzZBtZBc7T6aglQ_debugObjects']->$id = $object;
  if (isset($_SERVER['debugObjectsPath']))
    file_put_contents($_SERVER['debugObjectsPath'] . "/$id.json", json_encode($object), FILE_APPEND | LOCK_EX);
  return "[object: $id]";
}

function debugDump ($symbol) {
  if ($symbol === null)
    return 'null';
  if (in_array(gettype($symbol), array('string', 'integer', 'double', 'float')))
    return json_encode($symbol);
  if (is_object($symbol))
    return get_class($symbol) . ' {}';
  assertTrue(false, gettype($symbol));
}


function debugUntraceableMessage ($message) {

  $timestamp = microtime(true);

  $GLOBALS['__7iPYslyzfKzZBtZBc7T6aglQ_debugLog'][] = (object) array(
    'time' => $timestamp,
    'message' => $message,
  );

  if (isset($_SERVER['debugPath']))
    file_put_contents($_SERVER['debugPath'],
      "[" . date("Y-m-d H:i:s", $timestamp) . "." . substr(round($timestamp - floor($timestamp), 6), 2) . "]" . "\n" .
      "  $message\n", FILE_APPEND | LOCK_EX);

}

function debugStopwatch_start ($name) {
  debugStopwatch('start', $name);
}

function debugStopwatch_end ($name) {
  debugStopwatch('end', $name);
}

// TODO: move this to optimizer
function debugStopwatch ($do, $name) {

  static $stopwatches = null;
  if (!isset($stopwatches))
    $stopwatches = (object) array();

  static $isRegistered = false;
  if (!$isRegistered) {
    debug_onShutdown(function () use ($stopwatches) {
      foreach ($stopwatches as $stopwatch)
        debugUntraceableMessage("stopwatch {$stopwatch->name} measured {$stopwatch->total} seconds in {$stopwatch->calls} measurements");
    });
    $isRegistered = true;
  }

  if (!isset($stopwatches->$name))
    $stopwatches->$name = (object) array(
      'name' => $name,
      'total' => 0,
      'calls' => 0,
      'stack' => array(),
    );
  switch ($do) {
    case 'all':
      return $stopwatches;
    case 'start':
      $stopwatches->$name->stack[] = microtime(true);
      break;
    case 'end':
      $time = array_pop($stopwatches->$name->stack);
      if (count($stopwatches->$name->stack) == 0) {
        $stopwatches->$name->calls++;
        $stopwatches->$name->total += (microtime(true) - $time);
      }
      break;
    default:
      assertTrue(false);
  }
}

function debugBacktrace () {
  return new debugBacktrace_class();
}

class debugBacktrace_class {
  
  function __construct () {
    $this->backtrace = debug_backtrace();
  }
  
  function __toString () {
    foreach ($this->backtrace as $k => $v)
      $this->backtrace[$k]['object'] = null;
    ob_start();
    var_dump($this->backtrace);
    return ob_get_clean();
  }
  
}

function debug_onShutdown ($callback) {
  static $list = array();
  if ($callback == 'P28dLKkZZPjKsQzjkIELGRpC') {
    foreach ($list as $f)
      call_user_func($f);
    return;
  }
  assertTrue(is_callable($callback));
  $list[] = $callback;
}

function debug_instance_shutdown () {
  debug_onShutdown('P28dLKkZZPjKsQzjkIELGRpC');
  debugMessage("instance shutdown");
  debugMessage("peak memory usage: " . memory_get_peak_usage() . ' bytes');
}

if (debugMode) {
  static $debugInit;
  if (!isset($debugInit)) {
    $url = null;
    if (isset($_SERVER['HTTP_HOST']))
			$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    debugMessage("instance startup" . ($url ? " on url: $url" : ''));    
    register_shutdown_function('debug_instance_shutdown');
  }
  $debugInit = true;
}

