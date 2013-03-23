<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function generateStubs ($destinationDirectory) {

  enforce(isset($_SERVER['basePath']), 'basePath is required for stubs generation');

  $timestamp = microtime(true);
  echo 'Listing files ..';
  
  $files = array();
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($_SERVER['basePath'])) as $file) {

    if (microtime(true) > $timestamp + 1) {
      $timestamp = microtime(true);
      echo '.';
    }

    if (!in_array($file->getExtension(), array('php')))
      continue;
    
    if (strpos(realpath($file), realpath($destinationDirectory) . DIRECTORY_SEPARATOR) === 0)
      continue;
      
    $fileContent = file_get_contents($file);
    if (preg_match('/(?si)function\s*stub/', $fileContent) == 0)
      continue;
    $files[] = $file;

  }
  echo ' ' . count($files) . "\n";
  
  echo 'Including files ..';

  $i = 0;
  
  // php include can overwrite variable values so we need to isolate include in a function
  $includeFile = function ($file) {
    ob_start();
    include_once (string) $file;
    ob_end_clean();
  };
  
  foreach ($files as $file) {
    if (microtime(true) > $timestamp + 1) {
      $timestamp = microtime(true);
      echo '.';
    }
    if (in_array(realpath($file), get_included_files()))
      continue;
    $i++;
    $includeFile($file);
  }
  echo ' ' . $i . "\n";

  $callables = array();
  
  echo 'Listing stub generators ..';
  $functions = get_defined_functions();
  foreach (array_merge($functions['internal'], $functions['user']) as $function) {
    if (microtime(true) > $timestamp + 1) {
      $timestamp = microtime(true);
      echo '.';
    }
    if (strpos(strtolower($function), 'stub') === 0)
      $callables[] = $function;
  }
  
  foreach (get_declared_classes() as $class) {
    foreach (get_class_methods($class) as $method) {
      if (microtime(true) > $timestamp + 1) {
        $timestamp = microtime(true);
        echo '.';
      }
      if (strpos(strtolower($method), 'stub') === 0)
        $callables[] = array($class, $method);
    }
  }
  
  echo ' ' . count($callables) . "\n";
  
  echo "\nRunning " . count($callables) . " stub generator(s):\n";
  
  $stubsTree = (object) array();
  
  foreach ($callables as $callable) {
    $id = (is_string($callable) ? $callable : $callable[0] . '::' . $callable[1]) . '()';
    echo '  ' . $id;
    $stubs = call_user_func($callable);
    foreach (is_array($stubs) ? $stubs : array($stubs) as $stub) {
      generateStub($stubsTree, $stub);
    }
    echo "\n";
  }
  
  directory($destinationDirectory);
  
  echo "\nGenerating stub files:\n";
  foreach ($stubsTree as $stub) {
    if (isset($stub->{'class'})) {
      $stubFile = $stub->{'class'};
      $stubString = generateStubString($stub);
    }
    echo "  $stubFile\n";
    file_put_contents($destinationDirectory . '/' . $stubFile . '.stubs.php', "<?php\n\n$stubString\n\n");
  }
  
  echo "Done\n";
  
}

function generateStub (&$stubsTree, $stubData) {

  if (isset($stubData->{'return'}))
    generateSymbolStub($stubsTree, $stubData->{'return'});

  if (isset($stubData->{'class'})) {
    $stub = generateSymbolStub($stubsTree, $stubData->{'class'});
    
    if (isset($stubData->storageClass) && is_string($stubData->storageClass))
      $stubData->storageClass = explode(' ', $stubData->storageClass);

    if (isset($stubData->doc))
      foreach (parseStubDoc($stubData->doc) as $k => $v)
        $stubData->$k = $v;
    
    if (isset($stubData->method)) {
      $stubData->name = $stubData->method;
      $stubData->symbolType = 'method';
      if (!isset($stubData->arguments))
        $stubData->arguments = array();
      $stub->members->{'method_' . $stubData->method} = $stubData;
    } else if (isset($stubData->property)) {
      $stubData->name = $stubData->property;
      $stubData->symbolType = 'property';
      if (isset($stubData->type))
        generateSymbolStub($stubsTree, $stubData->type);
      $stub->members->{'property_' . $stubData->property} = $stubData;
    } else {
      assertTrue(false);
    }
      
  }
  
}


function generateSymbolStub (&$stubsTree, $symbol) {

  if (!isset($stubsTree->{'class_' . $symbol})) {
    $stubsTree->{'class_' . $symbol} = (object) array(
      'class' => $symbol,
      'members' => (object) array(),
    );
    
    $stub = $stubsTree->{'class_' . $symbol};
    
    $reflection = new ReflectionClass($symbol);
  
    foreach ($reflection->getMethods() as $method) {
      version_assert and assertTrue(!isset($stub->members->{'method_' . $method->name}));
      
      $protection = null;
      $return = null;
      $autocomplete = null;
      $arguments = array();
      $storageClass = array();
      $comment = null;

      if ($method->isPublic())
        $protection = 'public';
      if ($method->isProtected())
        $protection = 'protected';
      if ($method->isPrivate())
        $protection = 'private';
      
      if ($method->isFinal())
        $storageClass[] = 'final';
      if ($method->isAbstract())
        $storageClass[] = 'abstract';
      if ($method->isStatic())
        $storageClass[] = 'static';
      
      if (strpos($method->name, 'stub') === 0 && in_array('final', $storageClass) && in_array('static', $storageClass))
        $autocomplete = 'invisible';

      $doc = $method->getDocComment();
      if ($doc === false)
        $doc = null;
      
      if (isset($doc)) {
        $comment = preg_replace('/^\s*\/\*\*/', '', $comment);
        $comment = preg_replace('/\*\/\s*$/', '', $comment);
        $comment = preg_replace('/\@\S+/', '', $comment);
        foreach (parseStubDoc($doc) as $k => $v)
          $$k = $v;
      }

      foreach ($method->getParameters() as $argument)
        $arguments[] = '$' . $argument->getName()
          . ($argument->isDefaultValueAvailable() ? ' = ' . var_export($argument->getDefaultValue(), true) : '');
      
      $stub->members->{'method_' . $method->name} = (object) array(
        'name' => $method->name,
        'arguments' => $arguments,
        'symbolType' => 'method',
        'comment' => $comment,
        'doc' => $doc,
        'autocomplete' => isset($autocomplete) ? $autocomplete : null,
        'protection' => $protection,
        'storageClass' => $storageClass,
        'return' => isset($return) ? $return : null,
      );
    }
  
  }

  return $stubsTree->{'class_' . $symbol};
}

function parseStubDoc ($doc) {
  $stub = (object) array();

  if (preg_match('/(?i)\@return\s+(\S+)/', $doc, $match)) {
    $stub->{'return'} = $match[1];
    $doc = preg_replace('/(?i)\@return\s+(\S+)/', '', $doc);
  }

  if (preg_match('/(?i)\@protection\s+(\S+)/', $doc, $match)) {
    $stub->protection = $match[1];
    $doc = preg_replace('/(?i)\@protection\s+(\S+)/', '', $doc);
  }

  if (preg_match('/(?i)\@autocomplete\s+(\S+)/', $doc, $match)) {
    $stub->autocomplete = $match[1];
    $doc = preg_replace('/(?i)\@autocomplete\s+(\S+)/', '', $doc);
  }
  
  $stub->doc = $doc;
  
  return $stub;
}

function generateStubString ($stub) {

  $classString = "{";
  foreach ($stub->members as $member) {
    
    $generatedDoc = '';

    if (isset($member->comment))
      $generatedDoc .= "\n   * " . trim($member->comment);
    if (isset($member->type))
      $generatedDoc .= "\n   * @var " . $member->type . "___stub";
    if (isset($member->{'return'}))
      $generatedDoc .= "\n   * @return " . $member->{'return'} . "___stub";
    
    if (trim($generatedDoc) != '')
      $classString .= "\n\n  /**\n   " . trim($generatedDoc) . "\n   */";
    
    $classString .= "\n ";
    if (isset($member->protection) || isset($member->autocomplete))
      $classString .= ' ' .
        (isset($member->autocomplete) && $member->autocomplete == 'invisible' ? 'private' : $member->protection);
    else if ($member->symbolType == 'property')
      $classString .= ' public';
      
    if (isset($member->storageClass) && count($member->storageClass) > 0)
      $classString .= ' ' . implode(' ', $member->storageClass);
    if ($member->symbolType == 'method')
      $classString .= " function {$member->name} (" . implode(', ', $member->arguments) . ");";
    else if ($member->symbolType == 'property')
      $classString .= " \${$member->name};";
    else 
      assertTrue(false);

  }
  $classString .= "\n\n}";
  
  return "class {$stub->{'class'}} $classString\n\n\nclass {$stub->{'class'}}___stub $classString";
}

