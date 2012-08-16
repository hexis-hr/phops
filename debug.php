<?php

if (!defined('debugMode'))
  define('debugMode', $_SERVER['debugMode']);

// debugMessage ([$type,] $message)
function debugMessage ($message) {
  $args = func_get_args();
  $type = isset($args[1]) ? $args[0] : null;
  $message = isset($args[1]) ? $args[1] : $message;

  $backtrace = debug_backtrace();
  
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

  file_put_contents($_SERVER['debugPath'],
    "[" . date("Y-m-d H:i:s") . "] #{$_SERVER['requestId']}/" . session_id() . " ({$backtrace[0]['file']}:{$backtrace[0]['line']})" . (isset($type) ? " $type" : '') . "\n" .
    "  $message\n", FILE_APPEND | LOCK_EX);

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

