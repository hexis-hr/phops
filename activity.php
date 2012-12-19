<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function activityLog_handler ($handler = null) {
  static $_handler;
  assertTrue(isset($_handler) xor isset($handler));
  if (!isset($_handler))
    $_handler = $handler;
  return $_handler;
}

function activityLog ($message, $parameters = null) {
  $handler = activityLog_handler();
  $handler($message, $parameters);
}

