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

  // Used by autoloader to infer resource source for cache purposes.
  protected $newInitializators = [];

  protected $initializators = array();
  protected $resources = array();

  function exist ($id) {
    return $this->offsetExists($id);
  }

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
        if (resourceContainer::isFunction($id))
          return new resourceInvokable($initializator);
        else if (is_callable($initializator))
          return call_user_func($initializator);
        else
          return $initializator;
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
    $this->newInitializators[] = $id;
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
    // Array registration can be spread across multiple files so we have to assume that
    // there might be some parts missing.
    if ($this->isRegistered($id) && !self::isArray($id))
      return true;
    // In case there are no code changes (non-development environment) we assume that autoloader
    // loads all registration fully.
    if ($this->isRegistered($id) && !version_development)
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
      if ($this->isFullyRegistered($id))
        return;
    }

    // It is possible that resource is registered before autoload kicks in, in such cases
    // we have to ensure that cache entry exists.
    if (!apc_exists(self::cacheKey($id)))
      apc_store(self::cacheKey($id), []);

    foreach ($this->paths as $path)
      foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {

        if (!in_array($file->getExtension(), array('php')))
          continue;

        $fileContent = file_get_contents($file);
        if (preg_match('/(?si)resource\(/', $fileContent) == 0)
          continue;

        $this->newInitializators = [];

        include_once $file;

        $newInitializators = $this->newInitializators;

        foreach ($newInitializators as $newInitializator) {
          $initializatorFiles = array();
          if (apc_exists(self::cacheKey($newInitializator)))
            $initializatorFiles = apc_fetch(self::cacheKey($newInitializator));
          $initializatorFiles[] = $file->getRealPath();
          apc_store(self::cacheKey($newInitializator), $initializatorFiles);
        }

        // Array registration can be spread across multiple files so we have to scan
        // everything in order to be sure it's fully registered.
        if ($this->isRegistered($id) && !self::isArray($id))
          return;

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

  function toFunction () {
    return $this->invokable;
  }

}
