<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function allMembers ($entity) {
  
  $result = array();
  
  if (is_array($entity)) {
    return array_keys($entity);
  } else if (is_object($entity)) {
    if (method_exists($entity, 'allMembers'))
      return $entity->allMembers();
    else
      return opConcat(array_keys(get_object_vars($entity)), get_class_methods($entity));
  } else {
    assertTrue(false);
  }

}

function hasMember ($entity, $member) {
  version_assert and assertTrue(is_string($member));
  
  if (is_array($entity))
    return array_key_exists($member, $entity);
  else if (is_object($entity) && method_exists($entity, 'hasMember'))
    return $entity->hasMember($member);
  else if (is_object($entity) && method_exists($entity, 'opDispatch'))
    return $entity->opDispatch($member) !== null;
  else if (is_object($entity))
    return property_exists($entity, $member) || method_exists($entity, $member);
  else
    assertTrue(false);
  
}

function _toCorrectClass ($object) {
  version_assert and assertTrue(count(func_get_args()) == 1);
  version_assert and assertTrue(is_object($object));
  if (get_class($object) != 'stdClass')
    return $object;
  version_assert and assertTrue(isset($object->__class));
  $class = $object->__class;
  return new $class($object);
}

function toRaw ($value) {
  version_assert and assertTrue(count(func_get_args()) == 1);
  if (is_object($value) && hasMember($value, 'toRaw'))
    return $value->toRaw();
  else
    return $value;
}

function toString ($value) {
  version_assert and assertTrue(count(func_get_args()) == 1);
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
    $result += $argument;
  return $result;
}

function opConcat () {
  $arguments = func_get_args();
  
  version_assert and assertTrue(count($arguments) >= 1);
  $result = $arguments[0];
    
  foreach (array_slice($arguments, 1) as $argument) {
    if (is_object($result) && hasMember($result, 'opConcat'))
      $result = $result->opConcat($argument);
    else if (is_object($argument) && hasMember($argument, 'opConcat'))
      $result = $argument->opConcat($result);
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
  if (is_object($lhs) && hasMember($lhs, 'opEquals'))
    return $lhs->opEquals($rhs);
  else if (is_object($rhs) && hasMember($rhs, 'opEquals'))
    return $rhs->opEquals($lhs);
  else
    return toRaw($lhs) === toRaw($rhs);
}

function opDispatch ($symbol) {
  version_assert and assertTrue(count(func_get_args()) == 1);
  if (is_array($symbol)) {
    version_assert and assertTrue(count($symbol) == 2);
    if (method_exists($symbol[0], $symbol[1]))
      return $symbol;
    if (method_exists($symbol[0], 'opDispatch')) {
      $dispatched = $symbol[0]->opDispatch($symbol[1]);
      if ($dispatched !== null)
        return $dispatched;
    }
  }
  assertTrue(false);
}
