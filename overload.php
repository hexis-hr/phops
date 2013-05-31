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
      return concat(array_keys(get_object_vars($entity)), get_class_methods($entity));
  } else {
    assertTrue(false);
  }

}

function hasMember ($entity, $member) {
  version_assert and assertTrue(is_string($member));
  
  if (is_array($entity)) {
    return array_key_exists($member, $entity);
  } else if (is_object($entity)) {
    if (method_exists($entity, 'hasMember'))
      return $entity->hasMember($member);
    else
      return property_exists($entity, $member) || method_exists($entity, $member);
  } else {
    assertTrue(false);
  }
  
}

function sum () {
  $result = 0;
  foreach (func_get_args() as $argument)
    $result += $argument;
  return $result;
}

function concat () {
  $arguments = func_get_args();
  
  version_assert and assertTrue(count($arguments) >= 1);
  if (is_string($arguments[0]))
    $result = '';
  else if (is_array($arguments[0]))
    $result = array();
  else
    assertTrue(false);
    
  foreach ($arguments as $argument) {
    if (is_string($argument)) {
      version_assert and assertTrue(is_string($result));
      $result .= $argument;
    } else if (is_array($argument)) {
      version_assert and assertTrue(is_array($result));
      $result = array_merge($result, $argument);
    } else {
      assertTrue(false);
    }
  }
  
  return $result;
}
