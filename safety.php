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


// we promote correct coding ! :)
//error_reporting(E_ALL | E_STRICT);
//ini_set('display_errors', 1);

_safety_report_data::$_configuration = array(
  'enabled' => true,
  'send_reports' => false,
  //'report_url' => 'http://ipv4.localhost/issues/report/',
  'report_url' => null,
  'display_errors' => true,
  'mode' => 'transitional', // transitional or strict
  'error_reporting' => E_ALL | E_STRICT,
  /*
  'report_exception' => array(
  ),
  'report_error_exception' => array(
  ),
  'report_caught_exception' => array(
  ),
  'report_everything' => array(
  ),
  /**/
);





if (isset($_SERVER['safety']))
  _safety_report_data::$_configuration = array_merge(_safety_report_data::$_configuration, $_SERVER['safety']);




// assertTrue($condition, $message [, $class] [, $types]);
function assertTrue ($condition, $message = 'Assertion failure') {
  $args = func_get_args();
  $class = isset($args[2]) && is_string($args[2]) ? $args[2] : 'AssertException';
  $types = isset($args[2]) && is_array($args[2]) ? $args[2] : (isset($args[3]) ? $args[3] : null);
  if (!$condition) {
    $e = new $class($message);
    if (isset($types))
      $e->types = $types;
    throw $e;
  }
}

function rethrowIfNot (Exception $e, $types) {
  if (is_string($types))
    $types = array($types);
  if (isset($e->types)) {
    assertTrue(is_array($e->types));
    foreach ($types as $type) {
      if (in_array($type, $e->types))
        return;
    }
  }
  throw $e;
}

class AssertException extends Exception {}
class ShutdownException extends ErrorException {}
class TimeLimitException extends Exception {}
class MemoryLimitException extends Exception {}



function exception_error_handler ($errno, $errstr, $errfile, $errline) {
  $e = new ErrorException($errstr, 0, $errno, $errfile, $errline);
  // try-catch should be able to catch php errors in strict mode which are then reported with report_caught_exception - don't call report_exception in this case
  if (_safety_report_data::$_configuration['mode'] == 'strict')
    throw $e;
  if (_safety_report_data::$_configuration['error_reporting'] & $errno)
    report_exception($e);
}

// TODO: move this into optimizer
function shutdown_time_limit () {
  $executionTime = microtime(true) - _safety_report_data::$_initTime;
  if ($executionTime > _safety_report_data::$_configuration['time_limit']) {
    $timeLimitException = new TimeLimitException("Execution time of $executionTime second(s) exceeds time limit of " . _safety_report_data::$_configuration['time_limit'] . " second(s)");
    $timeLimitException->digest = 'H8g94sqWn5dFmRoNIZF539r7';
    report_exception($timeLimitException);
  }
  if (isset(_safety_report_data::$_configuration['memory_limit']) && memory_get_peak_usage() > _safety_report_data::$_configuration['memory_limit']) {
    $memoryLimitException = new MemoryLimitException("Current allocation of " . memory_get_peak_usage() . " bytes exceeds safe memory limit of " . _safety_report_data::$_configuration['memory_limit'] . " bytes");
    $memoryLimitException->digest = 'EMfmGqToVpeRJnrRpoVkiuPb';
    report_exception($memoryLimitException);
  }
}

function shutdown_error_handler () {
  $error = error_get_last();
  if (isset($error)) {
    $error = (object) $error;
    // $error->type != E_WARNING is a workaround for error_get_last() being set even if error has been handled - php bug ?
    // checking 'Uncaught exception ' is a workaround for error_get_last() being set even if exception has been handled by set_exception_handler - php bug ?
    //if ($error->type != E_WARNING && substr($error->message, 0, strlen('Uncaught exception ')) != 'Uncaught exception ')
    if ($error->type != E_WARNING)
      if (_safety_report_data::$_configuration['error_reporting'] & $error->type)
        report_exception(new ShutdownException($error->message, 0, $error->type, $error->file, $error->line));
  }
}

function uncaught_exception_handler ($e) {
  report_exception($e);
}




// report_caught_exception ([$types,] $e)
function report_caught_exception ($e) {

  $arguments = func_get_args();
  
  foreach ($arguments as $index => $argument) {
    if (is_object($argument) && $argument instanceof Exception) {
      $arguments[$index] = exception_to_stdclass($arguments[$index]);
      $arguments[$index]->caught = true;
    }
  }
  
  return call_user_func_array('report_exception', $arguments);

}


// report_exception ([$types,] $e)
function report_exception ($e) {

  if (!_safety_report_data::$_configuration['enabled'])
    return;

  $args = func_get_args();
  $types = isset($args[1]) ? $args[0] : null;
  $e = isset($args[1]) ? $args[1] : $args[0];

  if (isset($types)) {
    if (!isset($e->types))
      $e->types = array();
    $e->types = array_merge($e->types, (array) $types);
  }
  
  if (!isset(_safety_report_data::$_reports))
    _safety_report_data::$_reports = array();
  _safety_report_data::$_reports[] = $e;
  
  if (_safety_report_data::$_configuration['display_errors'] && !($e instanceof TimeLimitException) && !($e instanceof MemoryLimitException)) {
    echo "<pre>";
    echo (isset($e->caught) && $e->caught ? 'Caught' : 'Uncaught') . ' ' . ($e instanceof Exception ? get_class($e) : $e->{'class'}) . ": <br />";
    echo htmlspecialchars($e instanceof Exception ? $e : $e->stringOf);
    echo "</pre>";
  }

  debugMode and debugMessage((isset($e->caught) && $e->caught ? 'Caught' : 'Uncaught') . ' ' . ($e instanceof Exception ? get_class($e) : $e->{'class'}) . ": " . ($e instanceof Exception ? $e->getMessage() : $e->message));

}

function set_safe_time_limit ($seconds) {
  _safety_report_data::$_configuration['time_limit'] = $seconds;
}

function generate_exception_report ($e) {

  $exception = exception_to_stdclass($e);
  
  if (!session_id())
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
    $report->request->url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
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
    //$withinBasePath = isset($baseCodePath, $traceItem->file) && substr($traceItem->file, 0, strlen($baseCodePath)) == $baseCodePath;
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
  
  if (isset($_GET))
    $report->rawData->get = $_GET;
  if (isset($_POST))
    $report->rawData->post = $_POST;
  if (isset($_SESSION))
    $report->rawData->session = $_SESSION;
  if (isset($_COOKIE))
    $report->rawData->cookie = $_COOKIE;
  if (isset($_SERVER))
    $report->rawData->server = $_SERVER;
  if (isset($_FILES))
    $report->rawData->files = $_FILES;

  $report->additional = (object) array();
  foreach (safety_report_data() as $name => $value)
    $report->additional->$name = $value;
  
  if (isset($GLOBALS['__7iPYslyzfKzZBtZBc7T6aglQ_debugLog'])) {
    if (count($GLOBALS['__7iPYslyzfKzZBtZBc7T6aglQ_debugLog']) <= 200)
      $report->debug = $GLOBALS['__7iPYslyzfKzZBtZBc7T6aglQ_debugLog'];
    else
      $report->debug = array_merge(array_slice($GLOBALS['__7iPYslyzfKzZBtZBc7T6aglQ_debugLog'], 0, 100), array('(debug log truncated)'), array_slice($GLOBALS['__7iPYslyzfKzZBtZBc7T6aglQ_debugLog'], -100));
  }

  return $report;
  
}



function _send_exception_report ($report) {
  debugMode and debugMessage('begin');
  
  if (is_object($report) && ($report instanceof Exception || isset($report->__uLkwfIktHDm7mUUfbN0T2WTS_isException)))
    $report = generate_exception_report($report);

  if (_safety_report_data::$_configuration['report_url']) {
    $jsonReport = json_encode($report);
    if (strlen($jsonReport) > 60000) {
      $report->exception->previous = '(report truncated)';
      $jsonReport = json_encode($report);
      //echo '<pre>';
      //var_dump($report);
      //echo '</pre>';
      //exit;
    }
    if (strlen($jsonReport) > 60000 && count($report->debug) > 60) {
      $report->debug = array_merge(array_slice($report->debug, 0, 30), array('(debug log truncated)'), array_slice($report->debug, -30));
      $jsonReport = json_encode($report);
    }
    debugMode and debugMessage('safety report created - ' . strlen($jsonReport) . ' bytes long');
    $postData = 'data=' . urlencode($jsonReport);

    $curl = curl_init(rtrim(_safety_report_data::$_configuration['report_url'], '/') . '/report/');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 0);
    curl_setopt($curl, CURLOPT_TIMEOUT_MS, 10000);

    debugMode and debugMessage('sending safety report to ' . _safety_report_data::$_configuration['report_url']);
    $response = curl_exec($curl);
    
    if ($response === false)
      debugMode and debugMessage('error sending safety report: ' . curl_error($curl));
    else
      debugMode and debugMessage('sending safety report response: ' . $response);
      
    // assertTrue($response !== false);
    
  }

  debugMode and debugMessage('end');
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
  if (isset(_safety_report_data::$_configuration['exclude_path']))
    foreach (array(_safety_report_data::$_configuration['exclude_path']) as $libraryPath)
      if (substr($rawException->file, 0, strlen(rtrim(str_replace(array('\\', '/'), array('/', '/'), $libraryPath), '/') . '/')) == rtrim(str_replace(array('\\', '/'), array('/', '/'), $libraryPath), '/') . '/')
        $rawException->isLibrary = true;
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
        else {
          $args[$argumentIndex]->length = strlen($argument);
          $args[$argumentIndex]->value = substr($argument, 0, 300);
        }
      }
    unset($traceItem->args);
    $traceItem->arguments = $args;
    
    if (isset($traceItem->file))
      $traceItem->file = str_replace(array('\\', '/'), array('/', '/'), $traceItem->file);

    $traceItem->isLibrary = false;
    if (isset($traceItem->file, _safety_report_data::$_configuration['exclude_path']))
      foreach (array(_safety_report_data::$_configuration['exclude_path']) as $libraryPath)
        if (substr($traceItem->file, 0, strlen(rtrim(str_replace(array('\\', '/'), array('/', '/'), $libraryPath), '/') . '/')) == rtrim(str_replace(array('\\', '/'), array('/', '/'), $libraryPath), '/') . '/')
          $traceItem->isLibrary = true;
    
    if (isset($traceItem->file, $traceItem->line) && is_file($traceItem->file))
      $traceItem->snippet = extract_code_snippet($traceItem->file, $traceItem->line);

    $rawException->trace[$traceIndex] = $traceItem;
  }

  if ($exception->getPrevious() !== null)
    $rawException->previous = exception_to_stdclass($exception->getPrevious());
  
  $rawException->stringOf = (string) $exception;
  
  return $rawException;
}

function extract_code_snippet ($file, $line) {
  $snippet = (object) array();
  $snippet->beginLine = $line - 10 >= 0 ? $line - 10 : 0;
  $snippet->content = implode("\n", array_slice(explode("\n", file_get_contents($file)), $snippet->beginLine, 20));
  return $snippet;
}



function safety_report_data () {
  return _safety_report_data::instance();
}

class _safety_report_data {

  public static $_initTime;
  public static $_reports;
  public static $_variables;
  public static $_configuration;
  private static $_instance;
  
  static function instance () {
    if (!isset(self::$_instance))
      self::$_instance = new self();
    return self::$_instance;
  }
  
  public function touch ($name, $value = null) {
    if (!isset($this->$name))
      $this->$name = $value;
  }
  
}

_safety_report_data::$_initTime = microtime(true);


function send_safety_reports () {
  if (isset(_safety_report_data::$_reports))
    foreach (_safety_report_data::$_reports as $report)
      _send_exception_report($report);
}

function safety_shutdown_handler () {
  shutdown_time_limit();
  shutdown_error_handler();
  send_safety_reports();
}

if (_safety_report_data::$_configuration['enabled']) {
  set_exception_handler('uncaught_exception_handler');
  set_error_handler('exception_error_handler');
  register_shutdown_function('safety_shutdown_handler');
}


