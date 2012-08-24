<?php

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





function assertTrue ($condition, $message = null) {
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



function exception_error_handler ($errno, $errstr, $errfile, $errline) {
  $e = new ErrorException($errstr, 0, $errno, $errfile, $errline);
  // try-catch should be able to catch php errors in strict mode which are then reported with report_caught_exception - don't call report_exception in this case
  if (_safety_report_data::$_configuration['mode'] == 'strict')
    throw $e;
  if (_safety_report_data::$_configuration['error_reporting'] & $errno)
    report_exception($e);
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
      $arguments[$index] = clone $arguments[$index];
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
  
  if (_safety_report_data::$_configuration['display_errors']) {
    echo "<pre>";
    echo (isset($e->caught) && $e->caught ? 'Caught' : 'Uncaught') . ' ' . get_class($e) . ": <br />";
    echo htmlspecialchars($e);
    echo "</pre>";
  }

}

function generate_exception_report ($e) {

  if (!session_id())
    session_start();
  
  $report = (object) array();
  
  $file = str_replace(array('\\', '/'), array('/', '/'), $e->getFile());
  if (isset($_SERVER['SCRIPT_FILENAME']))
    $baseCodePath = str_replace(array('\\', '/'), array('/', '/'), dirname($_SERVER['SCRIPT_FILENAME']));
  
  if (is_file($file)) {
    $fileContent = file_get_contents($file);
    $codeSnippetBeginLine = $e->getLine() - 10 >= 0 ? $e->getLine() - 10 : 0;
    $codeSnippetLines = array_slice(explode("\n", $fileContent), $codeSnippetBeginLine, 20);
    $codeSnippet = implode("\n", $codeSnippetLines);
  }
  
  $report->time = microtime(true);
  
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
  
  $report->exception = exception_to_stdclass($e);

  
  $code = $report->exception;
  foreach (array_merge(array($report->exception), $report->exception->trace) as $traceItem) {
    $withinBasePath = isset($baseCodePath) && substr($traceItem->file, 0, strlen($baseCodePath)) == $baseCodePath;
    if ($withinBasePath) {
      $code = $traceItem;
      break;
    }
  }

  $report->code = (object) array();
  if (isset($baseCodePath))
    $report->code->basePath = $baseCodePath;
  $report->code->file = $code->file;
  $report->code->line = $code->line;
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

  return $report;
  
}



function _send_exception_report ($report) {

  if (is_object($report) && $report instanceof Exception)
    $report = generate_exception_report($report);

  if (_safety_report_data::$_configuration['report_url']) {
    $postData = 'data=' . urlencode(json_encode($report));

    $curl = curl_init(rtrim(_safety_report_data::$_configuration['report_url'], '/') . '/report/');
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 0);
    curl_setopt($curl, CURLOPT_TIMEOUT_MS, 3000);

    $response = curl_exec($curl);
    // assertTrue($response !== false);
    
  }

}

function exception_to_stdclass ($exception) {
  $rawException = (object) array();
  $rawException->{'class'} = get_class($exception);
  if (isset($exception->caught))
    $rawException->caught = $exception->caught;
  $rawException->message = $exception->getMessage();
  $rawException->code = $exception->getCode();
  $rawException->file = str_replace(array('\\', '/'), array('/', '/'), $exception->getFile());
  $rawException->line = $exception->getLine();
  if (is_file($rawException->file))
    $rawException->snippet = extract_code_snippet($rawException->file, $rawException->line);
  if (isset($exception->types))
    $rawException->types = $exception->types;
  $rawException->trace = $exception->getTrace();
  
  foreach ($rawException->trace as $traceIndex => $traceItem) {
    $traceItem = (object) $traceItem;
    
    $args = array();
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
    
    if (isset($traceItem->file, $traceItem->line) && is_file($traceItem->file))
      $traceItem->snippet = extract_code_snippet($traceItem->file, $traceItem->line);

    $rawException->trace[$traceIndex] = $traceItem;
  }

  if ($exception->getPrevious() !== null)
    $rawException->previous = exception_to_stdclass($exception->getPrevious());
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


function send_safety_reports () {
  if (isset(_safety_report_data::$_reports))
    foreach (_safety_report_data::$_reports as $report)
      _send_exception_report($report);
}



if (_safety_report_data::$_configuration['enabled']) {
  set_exception_handler('uncaught_exception_handler');
  set_error_handler('exception_error_handler');
  register_shutdown_function('shutdown_error_handler');
  register_shutdown_function('send_safety_reports');
}


