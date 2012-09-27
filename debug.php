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
    if (isset($backtraceEntry->file) && substr($backtraceEntry->file, 0, strlen(realpath($_SERVER['basePath']))) != realpath($_SERVER['basePath']))
      continue;
    if (isset($backtraceEntry->file) && realpath($backtraceEntry->file) == realpath(__FILE__))
      continue;
    break;
  }
  
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
  
  $GLOBALS['__7iPYslyzfKzZBtZBc7T6aglQ_debugLog'][] = (object) array(
    'time' => $timestamp,
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

function debug_instance_shutdown () {
  debugMessage("instance shutdown");
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

