<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function resource () {
  return resource_::instance();
}

class resource_ implements ArrayAccess {

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

  function offsetExists ($offset) {
    $this->autoload($offset);
    return $this->registered($offset);
  }

  function offsetGet ($offset) {
    $this->autoload($offset);
    if (array_key_exists($offset, $this->resources))
      return $this->resources[$offset];
    if (array_key_exists($offset, $this->initializators)) {
      $this->resources[$offset] = $this->initializators[$offset]();
      return $this->resources[$offset];
    }
    assertTrue(false, "Resource *$offset* not found.");
  }

  function offsetSet ($offset, $value) {
    version_assert and assertTrue(!$this->registered($offset), "Resource *$offset* already exists.");
    $this->initializators[$offset] = $value;
  }

  function offsetUnset ($offset) {
    assertTrue(false);
  }

  function registered ($id) {
    return array_key_exists($id, $this->resources) || array_key_exists($id, $this->initializators);
  }

  function autoload ($id) {

    if ($this->registered($id))
      return;

    $cacheKey = 'phops_UqcV7Ql9MoDwpUlhwT08LsoM_resource_' . $id;

    if (apc_exists($cacheKey) && is_file(apc_fetch($cacheKey))) {
      include_once apc_fetch($cacheKey);
      if ($this->registered($id))
        return;
    }

    $file = $this->findFile($id);
    include_once $file;
    if ($file)
      apc_store($cacheKey, $file);

  }

  function findFile ($id) {

    if ($this->registered($id))
      return '';

    foreach ($this->paths as $path) {
      foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($_SERVER['basePath'])) as $file) {

        if (!in_array($file->getExtension(), array('php')))
          continue;

        $fileContent = file_get_contents($file);
        if (preg_match('/(?si)resource\(\)\[/', $fileContent) == 0)
          continue;

        include_once $file;

        if ($this->registered($id)) {
          return $file->getRealPath();
        }

      }
    }

    return '';
  }

}
