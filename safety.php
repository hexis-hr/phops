<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

/**
 * This file contains tools that enforce safe programing.
 *
 */

class _throwable extends Exception {}

class _error extends _throwable {}
class safeException extends _throwable {}

class phpError extends _error {}
class phpShutdownError extends phpError {}
class assertError extends _error {}


// assertation indicates a code bug
// asserts may be compiled out in production environment
// assertTrue ($condition [, $message]);
function assertTrue ($condition, $message = 'Assertion failure') {
  if (!version_assert)
    return;

  if (!is_bool($condition))
        throw new assertError('Condition must be bool');
  if (count(func_get_args()) > 2)
    throw new assertError('assertTrue accepts exactly max 2 arguments');

  if (!$condition)
    throw new assertError($message);
}

function assertThrown ($throwable = 'safeException', $callback, $message = 'Assertion failure') {
  try {
    $callback();
  } catch (Exception $e) {
    if (is_a($e, $throwable, true))
      return;
  }
  assertTrue(false, $message);
}

function assertEqual ($a, $b) {
  assertTrue(opEquals($a, $b), 'Assertion failure: *' . debugDump($a) . '* === *' . debugDump($b) . '*');
}

function assertNotEqual ($a, $b) {
  assertTrue(!opEquals($a, $b), 'Assertion failure: *' . debugDump($a) . '* !== *' . debugDump($b) . '*');
}

// enforcement indicates a data error (caused by external input)
// enforcements will never be compiled out
// enforce ([$class,] $condition, $message);
function enforce ($condition, $message) {
  $class = 'safeException';
  $args = func_get_args();
  if (!is_bool($condition)) {
    assertTrue(count($args) == 3, "wrong enforce arguments type or count");
    $class = $args[0];
    $condition = $args[1];
    $message = $args[2];
  }

  version_assert and assertTrue(is_string($class) || is_object($class), "Class or object instance required");
  version_assert and assertTrue(is_bool($condition), "Condition must be bool");
  version_assert and assertTrue(is_string($message), "message must be a string");
  version_assert and assertTrue(count(func_get_args()) <= 3, "enforce accepts exactly max three arguments");

  if (!$condition)
    throw new $class($message);
}

function initialize_safety () {
  set_exception_handler('uncaught_exception_handler');
  set_error_handler('exception_error_handler');
  register_shutdown_function('shutdown_error_handler');
}

function exception_error_handler ($errno, $errstr, $errfile, $errline) {
  throw new phpError($errstr, 0, new ErrorException($errstr, 0, $errno, $errfile, $errline));
}

function shutdown_error_handler () {
  $error = error_get_last();
  if (isset($error)) {
    $error = (object) $error;

    // $error->type != E_WARNING is a workaround for error_get_last() being set even
    // if error has been handled by exception_error_handler - php bug ?
    if ($error->type == E_WARNING)
      return;

    // checking 'Uncaught exception ' is a workaround for error_get_last() being set even
    // if exception has been handled by set_exception_handler - php bug ?
    if (substr($error->message, 0, strlen('Uncaught exception ')) == 'Uncaught exception ')
      return;

    uncaught_exception_handler(new phpShutdownError("PHP shutdown error", 0,
      new ErrorException($error->message, 0, $error->type, $error->file, $error->line)));
  }
}

function uncaught_exception_handler ($e) {
  // todo: nice cli exception render
  if (php_sapi_name() == "cli")
    echo $e;
  else
    echo render_exception($e);
  exit;
}

function toString_throw ($e = null) {
  echo "To string must not throw in php (duno why)\n";
  if ($e !== null)
    uncaught_exception_handler($e);
  else
    uncaught_exception_handler(new error('String must not throw in php'));
  exit;
}


function error_report ($e) {

  $exception = exception_to_stdclass($e);

  if (!session_id() && !headers_sent())
    session_start();

  $report = (object) array();

  $file = str_replace(array('\\', '/'), array('/', '/'), $exception->file);
  if (isset($_SERVER['SCRIPT_FILENAME']))
    $baseCodePath = str_replace(array('\\', '/'), array('/', '/'), realpath(dirname($_SERVER['SCRIPT_FILENAME'])));

  if (is_file($file)) {
    $fileContent = file_get_contents($file);
    $codeSnippetBeginLine = $exception->line - 10 >= 0 ? $exception->line - 10 : 0;
    $codeSnippetLines = array_slice(explode("\n", $fileContent), $codeSnippetBeginLine, 20);
    $codeSnippet = implode("\n", $codeSnippetLines);
  }

  $report->time = microtime(true);

  if (isset($_SERVER['project']))
    $report->project = $_SERVER['project'];

  $report->serverInformation = (object) array();
  $report->serverInformation->uname = php_uname();
  if (function_exists('gethostname'))
    $report->serverInformation->hostname = gethostname();
  $report->serverInformation->phpVersion = phpversion();

  $report->request = (object) array();
  if (isset($_SERVER['REQUEST_METHOD']))
    $report->request->method = strtolower($_SERVER['REQUEST_METHOD']);
  if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI']))
    $report->request->url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http')
      . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
  if (isset($_SERVER['HTTP_REFERER']))
    $report->request->referer = $_SERVER['HTTP_REFERER'];
  if (isset($_SERVER['requestId']))
    $report->request->id = $_SERVER['requestId'];
  if (session_id())
    $report->request->session = session_id();

  $report->exception = $exception;

  if (isset($report->exception->digest))
    $report->digest = $report->exception->digest;

  $code = $report->exception;
  foreach (array_merge(array($report->exception), $report->exception->trace) as $traceItem) {
    if (!$traceItem->isLibrary) {
      $code = $traceItem;
      break;
    }
  }

  $report->code = (object) array();
  if (isset($baseCodePath))
    $report->code->basePath = $baseCodePath;
  if (isset($code->file))
    $report->code->file = $code->file;
  if (isset($code->line))
    $report->code->line = $code->line;
  if (isset($code->snippet))
    $report->code->snippet = $code->snippet;

  $report->rawData = (object) array();

  if (php_sapi_name() != "cli") {
    if (isset($_GET))
      $report->rawData->get = $_GET;
    if (isset($_POST))
      $report->rawData->post = $_POST;
    if (isset($_SESSION))
      $report->rawData->session = $_SESSION;
    if (isset($_COOKIE))
      $report->rawData->cookie = $_COOKIE;
    if (isset($_FILES))
      $report->rawData->files = $_FILES;
  }
  if (isset($_SERVER))
    $report->rawData->server = $_SERVER;

  $report->additional = (object) array();
  foreach (safety_additional_data() as $name => $value)
    $report->additional->$name = $value;

  return $report;

}

function safety_additional_data () {
  static $data;
  if (!isset($data))
    $data = (object) array();
  return $data;
}


function exception_to_stdclass ($exception) {
  if (isset($exception->__uLkwfIktHDm7mUUfbN0T2WTS_isException))
    return $exception;
  $rawException = (object) array();
  $rawException->__uLkwfIktHDm7mUUfbN0T2WTS_isException = true;
  $rawException->{'class'} = get_class($exception);
  if (isset($exception->caught))
    $rawException->caught = $exception->caught;
  $rawException->message = $exception->getMessage();
  $rawException->code = $exception->getCode();
  $rawException->file = str_replace(array('\\', '/'), array('/', '/'), $exception->getFile());
  $rawException->line = $exception->getLine();
  $rawException->isLibrary = false;
  foreach ($_SERVER['librariesPath'] as $libraryPath) {
    $libraryPath = realpath($libraryPath);
    assertTrue($libraryPath !== false);
    $cleanPath = rtrim(str_replace(array('\\', '/'), array('/', '/'), $libraryPath), '/');
    if (substr($rawException->file, 0, strlen($cleanPath . '/')) == $cleanPath . '/')
      $rawException->isLibrary = true;
  }
  if (is_file($rawException->file))
    $rawException->snippet = extract_code_snippet($rawException->file, $rawException->line);
  if (isset($exception->types))
    $rawException->types = $exception->types;
  if (isset($exception->digest))
    $rawException->digest = $exception->digest;
  $rawException->trace = $exception->getTrace();

  foreach ($rawException->trace as $traceIndex => $traceItem) {
    $traceItem = (object) $traceItem;

    $args = array();
    if (isset($traceItem->args))
      foreach ($traceItem->args as $argumentIndex => $argument) {
        $args[$argumentIndex] = (object) array('type' => gettype($argument));
        if (is_object($argument))
          $args[$argumentIndex]->{'class'} = get_class($argument);
        else if (is_array($argument))
          $args[$argumentIndex]->length = count($argument);
        else if (is_string($argument)) {
          $args[$argumentIndex]->length = strlen($argument);
          $args[$argumentIndex]->value = substr($argument, 0, 300);
        } else {
          // todo: rewrite
          $args[$argumentIndex]->length = 0;
          $args[$argumentIndex]->value = 'unknown';
        }
      }
    unset($traceItem->args);
    $traceItem->arguments = $args;

    if (isset($traceItem->file))
      $traceItem->file = str_replace(array('\\', '/'), array('/', '/'), $traceItem->file);

    $traceItem->isLibrary = false;
    if (!isset($traceItem->file))
      $traceItem->isLibrary = true;
    else
      foreach ($_SERVER['librariesPath'] as $libraryPath) {
        $libraryPath = realpath($libraryPath);
        assertTrue($libraryPath !== false);
        $cleanPath = rtrim(str_replace(array('\\', '/'), array('/', '/'), $libraryPath), '/');
        if (substr($traceItem->file, 0, strlen($cleanPath . '/')) == $cleanPath . '/')
          $traceItem->isLibrary = true;
      }

    if (isset($traceItem->file, $traceItem->line) && is_file($traceItem->file))
      $traceItem->snippet = extract_code_snippet($traceItem->file, $traceItem->line);

    $rawException->trace[$traceIndex] = $traceItem;
  }

  if ($exception->getPrevious() !== null) {
    $rawException->previous = exception_to_stdclass($exception->getPrevious());
    $rawException->previous->next = $rawException;
  }

  $rawException->stringOf = (string) $exception;

  return $rawException;
}

function extract_code_snippet ($file, $line) {
  $snippet = (object) array();
  $snippet->beginLine = $line - 10 >= 0 ? $line - 10 : 0;
  $snippet->content = implode("\n", array_slice(explode("\n", file_get_contents($file)), $snippet->beginLine, 20));
  return $snippet;
}

function render_error_report ($report) {

  ob_start();

  echo '<div>';

  if (isset($report->title))
    echo "<h1>" . (isset($report->title) ? htmlspecialchars($report->title) : '(no title)') . "</h1>";

  //echo '<button onclick="jQuery(\'.extra-information\').show();">Show everything</button>';

  echo "<h1>General info</h1>";
  echo "<pre>";
  ob_start();
  if (isset($report->exception->message))
    echo "Message: " . $report->exception->message . "\r\n";
  else
    echo "No message\r\n";
  if (isset($report->request->url))
    echo "URL"
      . (isset($report->request->method) ? ' (' . strtoupper($report->request->method) . ')' : '')
      . ': '
      . $report->request->url . "\r\n";
  if (isset($report->request->referer))
    echo "URL Referer: " . $report->request->referer . "\r\n";
  if (isset($report->request->id) || isset($report->request->session))
    echo (isset($report->request->id) ? "Request" : '')
      . (isset($report->request->id) && isset($report->request->session) ? '/' : '')
      . (isset($report->request->session) ? 'Session' : '') . ' id: '
      . (isset($report->request->id) ? $report->request->id : '')
      . (isset($report->request->id) && isset($report->request->session) ? '/' : '')
      . (isset($report->request->session) ? $report->request->session : '')
      . "\r\n";
  echo "Time: " . date('Y-m-d H:i:s', $report->time) . " (" . date('r', $report->time) . ")" . "\r\n";
  if (isset($report->exception->types))
    echo "Exception types: " . implode(", ", $report->exception->types) . "\r\n";
  if (isset($report->rawData->post) || isset($report->rawData->session)) {
    if (isset($report->rawData->post))
      echo "POST " . (count($report->rawData->post) == 0 ? 'is empty' : 'is not empty');
    if (isset($report->rawData->post) && isset($report->rawData->session))
      echo ", ";
    if (isset($report->rawData->session))
      echo "SESSION " . (count($report->rawData->session) == 0 ? 'is empty' : 'is not empty');
    echo "\r\n";
  }
  echo htmlspecialchars(ob_get_clean());
  echo "</pre>";

  echo "<h1>Trace</h1>";
  echo render_exception($report->exception);
  /*
  echo "<ul>";
  $firstItem = true;
  foreach (array_merge(array($report->exception), $report->exception->trace) as $traceItem) {
    $isLibrary = isset($traceItem->isLibrary) && $traceItem->isLibrary;
    echo '<li class="' . (!$isLibrary ? '' : 'outside-base-path extra-information') . '">';
    $traceItemId = randomKey(24);
    echo '<a href="#' . $traceItemId . '" onclick="jQuery(\'.' . $traceItemId . '\').toggle(); return false;">'
      . (isset($traceItem->file) ? htmlspecialchars($traceItem->file . ':' . $traceItem->line) : '[internal function]')
      . '</a>';
    echo '<div class="' . $traceItemId . '" style="' . (!$isLibrary && $firstItem ? '' : 'display: none;') . '">';

    echo "<pre><h2>";
    ob_start();
    if (isset($traceItem->{'function'})) {
      echo (isset($traceItem->{'class'}) ? $traceItem->{'class'} . $traceItem->{'type'} : '')
        . $traceItem->{'function'} . '(';
      $arguments = array();
      foreach ($traceItem->arguments as $argument) {
        $arguments[] = dumpArgument($argument);
      }
      echo implode(', ', $arguments);
      echo ')';
    }
    echo htmlspecialchars(ob_get_clean());
    echo "</h2></pre>";

    if (isset($traceItem->snippet->content)) {
      echo '<pre class="snippet">';
      $content = htmlspecialchars($traceItem->snippet->content);
      $lines = explode("\n", $content);
      $lines[$traceItem->line - $traceItem->snippet->beginLine - 1] = "<strong>"
        . $lines[$traceItem->line - $traceItem->snippet->beginLine - 1] . "</strong>\r";
      echo implode("\n", $lines);
      echo "</pre>";
    }
    echo '</div>';
    echo "</li>";

    if (!$isLibrary && $firstItem)
      $problemTraceItem = $traceItem;

    if (!$isLibrary)
      $firstItem = false;
  }
  echo "</ul>";
  /**/

  if (isset($report->debug)) {
    echo "<h1>Debug</h1>";
    echo '<ul>';
    foreach ($report->debug as $debugEntry) {
      echo "<li>";
      if (is_string($debugEntry)) {
        echo $debugEntry;
      } else {
        echo "<div" . (isset($problemTraceItem, $problemTraceItem->file, $debugEntry->file)
          && $problemTraceItem->file == $debugEntry->file ? ' style="color: red;" ' : '') . ">";
        echo "[" . date("Y-m-d H:i:s", $debugEntry->time) . "."
          . substr(round($debugEntry->time - floor($debugEntry->time), 6), 2) . "]"
          . (isset($debugEntry->file) ? " ({$debugEntry->file}:{$debugEntry->line})" : '')
          . (isset($debugEntry->type) ? " {$debugEntry->type}" : '') . "<br />" .
          "  " . (isset($debugEntry->call) ? htmlentities($debugEntry->call) . ': ' : '')
          . htmlentities($debugEntry->message) . "\n";
        echo "</div>";
      }
      echo "</li>";
    }
    echo '</ul>';
  }

  if (isset($report->rawData->get)) {
    echo "<h1>GET</h1>";
    echo "<pre>";
    ob_start();
    print_r($report->rawData->get);
    echo htmlspecialchars(ob_get_clean());
    echo "</pre>";
  }

  if (isset($report->rawData->post)) {
    echo "<h1>POST</h1>";
    echo "<pre>";
    ob_start();
    print_r($report->rawData->post);
    echo htmlspecialchars(ob_get_clean());
    echo "</pre>";
  }

  if (isset($report->rawData->session)) {
    echo "<h1>SESSION</h1>";
    echo "<pre>";
    ob_start();
    print_r($report->rawData->session);
    echo htmlspecialchars(ob_get_clean());
    echo "</pre>";
  }

  if (isset($report->rawData->cookie)) {
    echo "<h1>COOKIE</h1>";
    echo "<pre>";
    ob_start();
    print_r($report->rawData->cookie);
    echo htmlspecialchars(ob_get_clean());
    echo "</pre>";
  }

  if (isset($report->additional->request)) {
    echo "<h1>Request</h1>";
    echo "<pre>";
    ob_start();
    print_r($report->additional->request);
    echo htmlspecialchars(ob_get_clean());
    echo "</pre>";
  }

  if (isset($report->additional->parameters)) {
    echo "<h1>Parameters</h1>";
    echo "<pre>";
    ob_start();
    print_r($report->additional->parameters);
    echo htmlspecialchars(ob_get_clean());
    echo "</pre>";
  }

  if (isset($report->rawData->files)) {
    echo "<h1>FILES</h1>";
    echo "<pre>";
    ob_start();
    print_r($report->rawData->files);
    echo htmlspecialchars(ob_get_clean());
    echo "</pre>";
  }

  if (isset($report->rawData->server)) {
    echo "<h1>SERVER</h1>";
    echo "<pre>";
    ob_start();
    print_r($report->rawData->server);
    echo htmlspecialchars(ob_get_clean());
    echo "</pre>";
  }

  echo "<h1>Server info</h1>";
  echo "<pre>";
  ob_start();
  echo "Uname: " . $report->serverInformation->uname . "\r\n";
  if (function_exists('gethostname'))
    echo "Hostname: " . $report->serverInformation->hostname . "\r\n";
  echo "PHP version: " . $report->serverInformation->phpVersion . "\r\n";
  if (isset($report->code->basePath))
    echo "Base code path: " . $report->code->basePath . "\r\n";
  echo htmlspecialchars(ob_get_clean());
  echo "</pre>";

  echo '</div>';

  return ob_get_clean();
}

function render_exception ($e) {

  $e = exception_to_stdclass($e);

  ob_start();

  echo '<!-- error-hmnb9a525V77pG545SXkqmfW: ' . json_encode($e->message) . ' -->';

  echo '<div style=\'font-size: 1em; border: 2px solid black; padding: 5px; background: white;'
    . 'font-family: Consolas, Monaco, "Lucida Console", "Liberation Mono", "DejaVu Sans Mono", '
    . '"Bitstream Vera Sans Mono", "Courier New", monospace;\'>';

  $exceptions = array();
  while (true) {

    ob_start();

    echo "<h1 style='font-size: 1.2em;' data-message='{$e->{'class'}}: ". htmlspecialchars($e->message, ENT_QUOTES)
      . "'>{$e->{'class'}}: " .
      (strlen($e->message) > 80 ? htmlspecialchars(substr($e->message, 0, 80), ENT_QUOTES) .
      ' <a href="#" onclick="this.parentNode.textContent = this.parentNode.getAttribute(\'data-message\');' .
      'return false;">...</a>' : htmlspecialchars($e->message, ENT_QUOTES)) . "</h1>";
    echo "<ul>";
    $firstItem = true;
    foreach (array_merge(array($e), $e->trace) as $traceItem) {
      $traceItemId = randomKey(24);
      $isLibrary = isset($traceItem->isLibrary) && $traceItem->isLibrary;
      echo '<li style="' . ($isLibrary ? 'color: gray;' : '') . '">';
      echo '<a href="#' . $traceItemId . '" style="' . (!$isLibrary ? 'color: black;' : 'color: gray;')
        . '" onclick="if (this.nextSibling.style.display == \'none\') this.nextSibling.style.display = \'\';'
        . ' else this.nextSibling.style.display = \'none\'; return false;">'
        . (isset($traceItem->file) ? htmlspecialchars($traceItem->file . ':' . $traceItem->line)
        : '[internal function]') . '</a>';
      echo '<div class="' . $traceItemId . '" style="' . (!$isLibrary && $firstItem ? '' : 'display: none;') . '">';

      echo "<pre><h2 style='font-size: 1.1em;'>";
      ob_start();
      if (isset($traceItem->{'function'})) {
        echo (isset($traceItem->{'class'}) ? $traceItem->{'class'}
          . $traceItem->{'type'} : '') . $traceItem->{'function'} . '(';
        $arguments = array();
        foreach ($traceItem->arguments as $argument)
          $arguments[] = dumpArgument($argument);
        echo implode(', ', $arguments);
        echo ')';
      }
      echo htmlspecialchars(ob_get_clean());
      echo "</h2></pre>";

      if (isset($traceItem->snippet->content)) {
        echo '<pre class="snippet">';
        $lines = explode("\n", htmlspecialchars($traceItem->snippet->content));
        if (array_key_exists($traceItem->line - $traceItem->snippet->beginLine - 1, $lines))
          $lines[$traceItem->line - $traceItem->snippet->beginLine - 1] = "<strong style='color: red;'>"
            . rtrim($lines[$traceItem->line - $traceItem->snippet->beginLine - 1]) . "</strong>";
        foreach ($lines as $lineIndex => $lineContent) {
          $lineNumber = $traceItem->snippet->beginLine + $lineIndex + 1;
          $lines[$lineIndex] = str_pad($lineNumber, strlen($traceItem->snippet->beginLine) + 1,
            " ", STR_PAD_LEFT) . ' | ' . $lineContent;
        }
        echo implode("\n", $lines);
        echo "</pre>";
      }
      echo '</div>';
      echo "</li>";

      if (!$isLibrary && $firstItem)
        $problemTraceItem = $traceItem;

      if (!$isLibrary)
        $firstItem = false;
    }
    echo "</ul>";

    $exceptions[] = ob_get_clean();

    if (isset($e->previous)) {
      $e = $e->previous;
      continue;
    }

    break;
  }

  echo implode('<hr />', $exceptions);

  echo '</div>';

  return ob_get_clean();
}

function dumpArgument ($argument) {
  if (strtolower($argument->type) == 'null')
    return 'null';
  if (strtolower($argument->type) == 'boolean')
    return 'boolean(' . ($argument->value ? 'true' : 'false') . ')';
  if (strtolower($argument->type) == 'integer')
    return 'integer(' . var_export((int) $argument->value, true) . ')';
  if (strtolower($argument->type) == 'string')
    return 'string(' . var_export((string) $argument->value, true) . ')';

  $return = '';
  if (isset($argument->type))
    $return .= $argument->type;
  if (isset($argument->length))
    $return .= '(' . $argument->length . ')';
  if (isset($argument->value))
    $return .= ' ' . var_export($argument->value, true);

  return $return;
}
