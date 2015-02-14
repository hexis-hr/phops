<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function resource () {
  $arguments = func_get_args();
  $container = resourceContainer::instance();
  if (count($arguments) == 0)
    return $container;
  if (count($arguments) == 1)
    return $container[$arguments[0]];
  return call_user_func_array($container[$arguments[0]], array_slice($arguments, 1));
}

class resourceContainer implements ArrayAccess {

  static $_instance = null;

  static function instance () {
    if (self::$_instance === null) {
      self::$_instance = new self();
    }
    return self::$_instance;
  }

  protected $paths = array();

  function registerPath ($path) {
    $this->paths[] = $path;
  }

  protected $initializators = array();
  protected $resources = array();

  function offsetExists ($id) {
    $this->autoload($id);
    return $this->isRegistered($id);
  }

  function offsetGet ($id) {
    $this->autoload($id);

    if ($this->isInitialized($id)) {
      if (!self::isArray($id))
        return $this->resources[$id];
      $result = array();
      foreach ($this->resources[$id] as $resource)
        $result[] = $resource;
      return $result;
    }

    if ($this->isRegistered($id)) {
      $initialize = function ($id, $initializator) {
        if (resourceContainer::isFunction($id) || !is_callable($initializator))
          return new resourceInvokable($initializator);
        else
          return call_user_func($initializator);
      };
      if (self::isArray($id)) {
        $this->resources[$id] = array();
        foreach ($this->initializators[$id] as $initializator)
          $this->resources[$id][] = $initialize($id, $initializator);
      } else {
        $this->resources[$id] = $initialize($id, $this->initializators[$id]);
      }
      return $this->resources[$id];
    }

    assertTrue(false, "Resource *$id* not found.");
  }

  function offsetSet ($id, $value) {
    version_assert and assertTrue(!$this->isInitialized($id), "Resource *$id* already initialized.");
    if (self::isArray($id)) {
      if (!array_key_exists($id, $this->initializators))
        $this->initializators[$id] = array();
      $this->initializators[$id][] = $value;
    } else {
      $this->initializators[$id] = $value;
    }
  }

  function offsetUnset ($id) {
    assertTrue(false, "Unsetting resource *$id* is prohibited.");
  }

  function isInitialized ($id) {
    return array_key_exists($id, $this->resources);
  }

  function isRegistered ($id) {
    return array_key_exists($id, $this->initializators);
  }

  function isFullyRegistered ($id) {
    if ($this->isInitialized($id))
      return true;
    if (!self::isArray($id) && $this->isRegistered($id))
      return true;
    return false;
  }

  function autoload ($id) {

    if ($this->isFullyRegistered($id))
      return;

    if (apc_exists(self::cacheKey($id))) {
      foreach (apc_fetch(self::cacheKey($id)) as $file)
        if (is_file($file))
          include_once $file;
      if (!version_development || $this->isFullyRegistered($id))
        return;
    }

    $foundInitializators = $this->initializators;

    foreach ($this->paths as $path) {
      foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {

        if (!in_array($file->getExtension(), array('php')))
          continue;

        $fileContent = file_get_contents($file);
        if (preg_match('/(?si)resource\(/', $fileContent) == 0)
          continue;

        include_once $file;

        $newInitializators = array_diff_key($this->initializators, $foundInitializators);

        foreach ($newInitializators as $newInitializatorId => $newInitializator) {
          $initializatorFiles = array();
          if (apc_exists(self::cacheKey($newInitializatorId)))
            $initializatorFiles = apc_fetch(self::cacheKey($newInitializatorId));
          $initializatorFiles[] = $file->getRealPath();
          apc_store(self::cacheKey($newInitializatorId), $initializatorFiles);
        }

        $foundInitializators = $this->initializators;

        if ($this->isFullyRegistered($id))
          return;

      }
    }

  }

  static function unmangledId ($id) {
    $unmangledId = $id;
    if (substr($unmangledId, -2) == '()')
      $unmangledId = substr($unmangledId, 0, -2);
    if (substr($unmangledId, -2) == '[]')
      $unmangledId = substr($unmangledId, 0, -2);
    return $unmangledId;
  }

  static function isFunction ($id) {
    return substr($id, -2) == '()';
  }

  static function isArray ($id) {
    return substr($id, -2) == '[]' || substr($id, -4) == '[]()';
  }

  static function cacheKey ($id) {
    return 'phops_UqcV7Ql9MoDwpUlhwT08LsoM_resource_' . $id;
  }

}

class resourceInvokable {

  protected $invokable;

  function __construct ($invokable) {
    $this->invokable = $invokable;
  }

  function __invoke () {
    return call_user_func_array($this->invokable, func_get_args());
  }

  function invoke () {
    return call_user_func_array($this->invokable, func_get_args());
  }

}
