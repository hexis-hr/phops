<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

class context {
  
  static $stacks = array();
  
  static function current ($type) {
    assertTrue(isset(self::$stacks[$type]) && count(self::$stacks[$type]) > 0);
    return end(self::$stacks[$type]);
  }
  
  static function __callStatic ($name, $arguments) {
    version_assert and assertTrue(count($arguments) == 0);
    return self::current($name);
  }

  static function enter ($type, $value) {
    if (!isset(self::$stacks[$type]))
      self::$stacks[$type] = array();
    // having context depth of 128 is most likely a memory leak bug
    assertTrue(count(self::$stacks[$type]) < 128, "Context too deep");
    array_push(self::$stacks[$type], $value);
  }

  static function leave ($type) {
    assertTrue(isset(self::$stacks[$type]) && count(self::$stacks[$type]) > 0);
    array_pop(self::$stacks[$type]);
  }
  
  static function invoke ($type, $value, $callback) {
    self::enter($type, $value);
    try { $result = call_user_func($callback); }
    // catch safeException only?
    catch (Exception $e) {}
    self::leave($type);
    if (isset($e))
      throw $e;
    return $result;
  }

}


