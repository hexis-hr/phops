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
  //else if (is_object($entity) && method_exists($entity, 'opDispatch'))
  //  return $entity->opDispatch($member) !== null;
  else if (is_string($entity) || is_object($entity))
    return property_exists($entity, $member) || method_exists($entity, $member);
  else
    assertTrue(false);
  
}

function isImplicitlyConvertible ($from, $to) {
  // todo
  assertTrue(false);
}

function isString ($value) {
  if (is_string($value))
    return true;
  if (is_scalar($value))
    return false;
  if (hasMember($value, 'isString')) {
    $result = opAccess($value, 'isString');
    version_assert and assertTrue(is_bool($result));
    return $result;
  }
  return false;
}

function isInteger ($value) {
  return !is_object($value) && !is_array($value) && floor($value) == $value;
}

function isIterable ($value) {
  return is_array($value) || $value instanceof Iterator;
}

