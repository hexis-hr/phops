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

  static function enter ($type, $value) {
    if (!isset(self::$stacks[$type]))
      self::$stacks[$type] = array();
    // having context depth of 16 is most likely a memory leak bug
    assertTrue(count(self::$stacks[$type]) < 16, "Context too deep");
    array_push(self::$stacks[$type], $value);
  }

  static function exit_ ($type) {
    assertTrue(isset(self::$stacks[$type]) && count(self::$stacks[$type]) > 0);
    array_pop(self::$stacks[$type]);
  }

}

