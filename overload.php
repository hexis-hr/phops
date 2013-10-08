<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function _toCorrectClass ($object) {
  version_assert and assertTrue(count(func_get_args()) == 1);
  version_assert and assertTrue(count(debug_backtrace()) < 1024, "Infinite recursion detected");
  version_assert and assertTrue(is_object($object));
  if (get_class($object) != 'stdClass')
    return $object;
  version_assert and assertTrue(isset($object->__class));
  $class = $object->__class;
  return new $class($object);
}

function toRaw ($value) {
  version_assert and assertTrue(count(func_get_args()) == 1);
  version_assert and assertTrue(count(debug_backtrace()) < 1024, "Infinite recursion detected");
  if (is_object($value) && hasMember($value, 'toRaw'))
    return $value->toRaw();
  else
    return $value;
}

function toString ($value) {
  version_assert and assertTrue(count(func_get_args()) == 1);
  version_assert and assertTrue(count(debug_backtrace()) < 1024, "Infinite recursion detected");
  if (is_object($value))
    $value = _toCorrectClass($value);
  if (is_object($value) && hasMember($value, 'toString'))
    return $value->toString();
  else if (is_object($value) && hasMember($value, 'toRaw'))
    return toString($value->toRaw());
  else if (is_array($value)) {
    $intermediate = array();
    foreach ($value as $v)
      $intermediate[] = toString($v);
    return '[' . implode(', ', $intermediate) . ']';
  } else
    return (string) $value;
}

function sum () {
  $result = 0;
  foreach (func_get_args() as $argument)
    if (is_array($argument))
      $result += call_user_func_array('sum', $argument);
    else
      $result += $argument;
  return $result;
}

function opConcat () {
  version_assert and assertTrue(count(debug_backtrace()) < 1024, "Infinite recursion detected");
  $arguments = func_get_args();

  version_assert and assertTrue(count($arguments) >= 1);
  $result = $arguments[0];

  foreach (array_slice($arguments, 1) as $argument) {
    if (is_object($result) && hasMember($result, 'opConcat'))
      $result = $result->opConcat($argument);
    else if (is_object($argument) && hasMember($argument, 'opConcatRight'))
      $result = $argument->opConcatRight($result);
    else if (is_array($argument)) {
      version_assert and assertTrue(is_array($result) && is_array($argument));
      $result = array_merge($result, $argument);
    } else {
      version_assert and assertTrue(is_string(toRaw($result)) && is_string(toRaw($argument)));
      $result = toRaw($result) . toRaw($argument);
    }
  }

  return $result;
}

function opEquals ($lhs, $rhs) {
  version_assert and assertTrue(count(func_get_args()) == 2);
  version_assert and assertTrue(count(debug_backtrace()) < 1024, "Infinite recursion detected");
  if (is_object($lhs) && hasMember($lhs, 'opEquals'))
    return $lhs->opEquals($rhs);
  else if (is_object($rhs) && hasMember($rhs, 'opEquals'))
    return $rhs->opEquals($lhs);
  else
    return toRaw($lhs) === toRaw($rhs);
}

function opDispatch ($symbol, $member) {
  version_assert and assertTrue(count(func_get_args()) == 2);
  version_assert and assertTrue(count(debug_backtrace()) < 1024, "Infinite recursion detected");
  if (is_array($symbol) && array_key_exists($member, $symbol))
    return $symbol[$member];
  if (is_object($symbol) && method_exists($symbol, $member))
    return array($symbol, $member);
  if (is_object($symbol) && property_exists($symbol, $member))
    return $symbol->$member;
  if (method_exists($symbol, 'opDispatch')) {
    version_assert and assertTrue(hasMember($symbol, $member));
    return $symbol->opDispatch($member);
  }
  assertTrue(false, get_class($symbol));
}

function opAccess ($symbol, $member) {
  version_assert and assertTrue(count(debug_backtrace()) < 1024, "Infinite recursion detected");
  $proxy = opDispatch($symbol, $member);
  return is_callable($proxy) ? call_user_func($proxy) : $proxy;
}
