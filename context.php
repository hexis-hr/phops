<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

class context {

  static $stacks = array();

  static function exists ($type) {
    return hasMember(self::$stacks, $type) && count(self::$stacks[$type]) > 0;
  }

  static function current ($type) {
    version_assert and assertTrue(self::exists($type));
    return end(self::$stacks[$type]);
  }

  static function __callStatic ($name, $arguments) {
    version_assert and assertTrue(count($arguments) == 0);
    return self::current($name);
  }

  static function enter ($type, $value) {
    if (!hasMember(self::$stacks, $type))
      self::$stacks[$type] = array();
    // having context depth of 128 is most likely a memory leak bug
    version_assert and assertTrue(count(self::$stacks[$type]) < 128, "Context too deep");
    array_push(self::$stacks[$type], $value);
  }

  static function leave ($type) {
    version_assert and assertTrue(self::exists($type));
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


