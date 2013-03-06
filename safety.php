<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

/**
 * This file contains tools that enforce safe programing.
 *
 */

class throwable extends Exception {}

class error extends throwable {}
class safeException extends throwable {}

class phpError extends error {}
class phpShutdownError extends phpError {}
class assertError extends error {}


// assertation indicates a code bug
// asserts may be compiled out in production environment
// assertTrue ($condition [, $message]);
function assertTrue ($condition, $message = 'Assertion failure') {
  if (!version_assert)
    return;

  if (!is_bool($condition))
        throw new assertError('Condition must be bool');
  if (count(func_get_args()) > 2)
    throw new assertError('assertTrue accepts exactly max 2 arguments');

  if (!$condition)
    throw new assertError($message);
}

function assertThrown ($exception, $callback, $message = 'Assertion failure') {
  try {
    $callback();
  } catch (Exception $e) {
    if (is_a($e, $exception, true))
      return;
  }
  assertTrue(false, $message);
}

// enforcement indicates a data error (caused by external input)
// enforcements will never be compiled out
// enforce ([$class,] $condition, $message);
function enforce ($condition, $message) {
  $class = 'safeException';
  $args = func_get_args();
  if (!is_bool($condition)) {
    assertTrue(count($args) == 3, "wrong enforce arguments type or count");
    $class = $args[0];
    $condition = $args[1];
    $message = $args[2];
  }

  version_assert and assertTrue(is_string($class) || is_object($class), "Class or object instance required");
  version_assert and assertTrue(is_bool($condition), "Condition must be bool");
  version_assert and assertTrue(is_string($message), "message must be a string");
  version_assert and assertTrue(count(func_get_args()) <= 3, "enforce accepts exactly max three arguments");

  if (!$condition)
    throw new $class($message);
}

function initialize_safety () {
  set_exception_handler('uncaught_exception_handler');
  set_error_handler('exception_error_handler');
  register_shutdown_function('shutdown_error_handler');
}

function exception_error_handler ($errno, $errstr, $errfile, $errline) {
  throw new phpError("PHP error", 0, new ErrorException($errstr, 0, $errno, $errfile, $errline));
}

function shutdown_error_handler () {
  $error = error_get_last();
  if (isset($error)) {
    $error = (object) $error;
    
    // $error->type != E_WARNING is a workaround for error_get_last() being set even
    // if error has been handled by exception_error_handler - php bug ?
    if ($error->type == E_WARNING)
      return;
    
    // checking 'Uncaught exception ' is a workaround for error_get_last() being set even
    // if exception has been handled by set_exception_handler - php bug ?
    if (substr($error->message, 0, strlen('Uncaught exception ')) == 'Uncaught exception ')
      return;
    
    uncaught_exception_handler(new phpShutdownError("PHP shutdown error", 0,
      new ErrorException($error->message, 0, $error->type, $error->file, $error->line)));
  }
}

function uncaught_exception_handler ($e) {
  echo "TODO: write a nce exception render";
  echo $e;
  exit;
}


