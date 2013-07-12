<?php

/*
 * This software is the property of its authors.
 * See the copyright.txt file for more details.
 *
 */

function finally_ ($try, $finally) {
  try { call_user_func($try); }
  catch (Exception $e) {}
  call_user_func($finally);
  if (isset($e))
    throw $e;
}

class scopeExit {
  function __construct ($callback) {
    $this->callback = $callback;
  }
  function __destruct () {
    // todo: crashes on exceptions
    call_user_func($this->callback);
  }
}

function function_alias ($original, $alias) {
  
  $args = func_get_args();
  assert('count($args) == 2', 'function_alias(): requires exactly two arguments');
  assert('is_string($original) && is_string($alias)', 'function_alias(): requires string arguments');
  
  // valid function name - http://php.net/manual/en/functions.user-defined.php
  assert('preg_match(\'/^[a-zA-Z_\x7f-\xff][\\\\\\\\a-zA-Z0-9_\x7f-\xff]*$/\', $original) > 0',
    "function_alias(): '$original' is not a valid function name");
  assert('preg_match(\'/^[a-zA-Z_\x7f-\xff][\\\\\\\\a-zA-Z0-9_\x7f-\xff]*$/\', $alias) > 0',
    "function_alias(): '$alias' is not a valid function name");
  
  $aliasNamespace = substr($alias, 0, strrpos($alias, '\\') !== false ? strrpos($alias, '\\') : 0);
  $aliasName = substr($alias, strrpos($alias, '\\') !== false ? strrpos($alias, '\\') + 1 : 0);
  $serializedOriginal = var_export($original, true);
  
  eval("
    namespace $aliasNamespace {
      function $aliasName () {
        return call_user_func_array($serializedOriginal, func_get_args());
      }
    }
  ");
  
}

function import_namespace ($source, $destination) {

  $args = func_get_args();
  assert('count($args) == 2', 'import_namespace(): requires exactly two arguments');
  assert('is_string($source) && is_string($destination)', 'import_namespace(): requires string arguments');
  
  // valid function name - http://php.net/manual/en/functions.user-defined.php
  assert('preg_match(\'/^([a-zA-Z_\x7f-\xff][\\\\\\\\a-zA-Z0-9_\x7f-\xff]*)?$/\', $source) > 0',
    "import_namespace(): '$destination' is not a valid namespace name");
  assert('preg_match(\'/^([a-zA-Z_\x7f-\xff][\\\\\\\\a-zA-Z0-9_\x7f-\xff]*)?$/\', $destination) > 0',
    "import_namespace(): '$source' is not a valid namespace name");

  foreach(get_declared_classes() as $class)
    if (strpos($class, $source . '\\') === 0)
      class_alias($class, $destination . ($destination ? '\\' : '') . substr($class, strlen($source . '\\')));

  $functions = get_defined_functions();
  foreach(array_merge($functions['internal'], $functions['user']) as $function)
    if (strpos($function, $source . '\\') === 0)
      function_alias($function, $destination . ($destination ? '\\' : '') . substr($function, strlen($source . '\\')));
}

function uuid () {
  return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    // 32 bits for "time_low"
    mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

    // 16 bits for "time_mid"
    mt_rand( 0, 0xffff ),

    // 16 bits for "time_hi_and_version",
    // four most significant bits holds version number 4
    mt_rand( 0, 0x0fff ) | 0x4000,

    // 16 bits, 8 bits for "clk_seq_hi_res",
    // 8 bits for "clk_seq_low",
    // two most significant bits holds zero and one for variant DCE1.1
    mt_rand( 0, 0x3fff ) | 0x8000,

    // 48 bits for "node"
    mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
  );
}

function uuid_binary () {
  return uuid_to_binary(uuid());
}

function uuid_to_binary ($uuid) {
  return pack('H*', str_replace('-', '', $uuid));
}

function uuid_from_binary ($uuid) {
  $value= unpack('H*', $uuid);
  $value= array_shift($value);
  // 8-4-4-4-12
  return substr($value, 0, 8) . '-' . substr($value, 8, 4) . '-' . substr($value, 12, 4) . '-' . substr($value, 16, 4) . '-' . substr($value, 20, 12);
}

function randomKey ($length) {
  $random = '';
  // 48 - 57, 65 - 90, 97 - 122
  for ($i = 0; $i < $length; $i++) {
    //$random .= chr(mt_rand(33, 126));
    $n = mt_rand(0, 10 + 26 + 26 - 1);
    $random .= chr($n < 10 ? 48 + $n : ($n - 10 < 26 ? 65 + ($n - 10) : 97 + ($n - 10 - 26)));
    //$random .= ($n < 10 ? 48 + $n : ( $n < 10 + 26 ? 65 + $n - 10 : 97 + $n - (10 + 26))) . " ";
  }
  return $random;
}

function uniqueId () {
  return randomKey(24);
}

function generateRandomUnit () {
  if (rand(0, 1) == 0)
    return rand(0, 1048576);
  else
    return randomKey(rand(5, 20));
}

function generateRandomStructure ($size) {
  
  if ($size == 1)
    return generateRandomUnit();
  
  if (rand(0, 1) == 0) {
    $result = array();
    while ($size > 0) {
      $subSize = rand(1, ceil($size / 2));
      $result[] = generateRandomStructure($subSize);
      $size -= $subSize;
    }
  } else {
    $result = (object) array();
    while ($size > 0) {
      $subSize = rand(1, ceil($size / 2));
      $result->{generateRandomUnit()} = generateRandomStructure($subSize);
      $size -= $subSize;
    }
  }
  
  return $result;
}

/**
 * Allowed 21 characters: 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, a, b, c, d, e, f, g, j, m, s, u
 * Equivalents: 0 = o, 1 = i = l, b = p, d = t, g = h = k, m = n, f = v, r is banned, s = z
 */
function userFriendyId_fromNumeric ($numericId) {
  assertTrue(is_numeric($numericId) && floor($numericId) == $numericId);
  $numericId = floor($numericId);
  $userFriendyId = '';
  static $map = array(
    'h' => 'j',
    'i' => 'm',
    'j' => 's',
    'k' => 'u',
  );
  foreach (str_split(base_convert($numericId, 10, 21)) as $char) {
    $char = strtolower($char);
    $userFriendyId .= isset($map[$char]) ? $map[$char] : $char;
  }
  $control = strtolower(substr(sha1($userFriendyId), 0, 2));
  foreach (str_split($control) as $char) {
    $char = strtolower($char);
    $userFriendyId .= isset($map[$char]) ? $map[$char] : $char;
  }
  return strtolower($userFriendyId);
}

/**
 * Allowed 21 characters: 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, a, b, c, d, e, f, g, j, m, s, u
 * Equivalents: 0 = o, 1 = i = l, b = p, d = t, g = h = k, m = n, f = v, r is banned, s = z
 */
function userFriendyId ($userFriendyId) {
  assertTrue(is_string($userFriendyId) && strlen($userFriendyId) >= 3);
  static $map = array(
    'o' => '0',
    'i' => '1',
    'l' => '1',
    'p' => 'b',
    't' => 'd',
    'h' => 'g',
    'k' => 'g',
    'n' => 'm',
    'v' => 'f',
    'z' => 's',
  );
  $userFriendyId = strtolower($userFriendyId);
  $control = substr($userFriendyId, -2);
  foreach (str_split($control) as $index => $char)
    $control[$index] = isset($map[$char]) ? $map[$char] : $char;
  $userFriendyId = substr($userFriendyId, 0, -2);
  foreach (str_split($userFriendyId) as $index => $char)
    $userFriendyId[$index] = isset($map[$char]) ? $map[$char] : $char;
  assertTrue($control == strtolower(substr(sha1($userFriendyId), 0, 2)));
  return strtolower($userFriendyId . $control);
}

function hamming_encodeNumber ($number) {
  assertTrue(is_numeric($number) && floor($number) == $number);
  $number = floor($number);
  $binaryString = base_convert($number, 10, 2);
  $result = hamming_encode(str_pad($binaryString, strlen($binaryString) + 4 - (strlen($binaryString) % 4), '0', STR_PAD_LEFT));
  return base_convert($result, 2, 10);
}

function hamming_decodeNumber ($number) {
  assertTrue(is_numeric($number) && floor($number) == $number);
  $number = floor($number);
  $binaryString = base_convert($number, 10, 2);
  $result = hamming_decode(str_pad($binaryString, strlen($binaryString) + 7 - (strlen($binaryString) % 7), '0', STR_PAD_LEFT));
  return base_convert($result, 2, 10);
}

function hamming_encode ($binary) {
  assertTrue(strlen($binary) % 4 == 0);
  assertTrue(preg_match('/^[01]*$/', $binary));
  $result = '';
  foreach (str_split($binary, 4) as $bits) {
    $result .= ($bits[0] + $bits[1] + $bits[3]) % 2;
    $result .= ($bits[0] + $bits[2] + $bits[3]) % 2;
    $result .= $bits[0];
    $result .= ($bits[1] + $bits[2] + $bits[3]) % 2;
    $result .= $bits[1] . $bits[2] . $bits[3];
  }
  return $result;
}

function hamming_decode ($binary) {
  assertTrue(strlen($binary) % 7 == 0);
  assertTrue(preg_match('/^[01]*$/', $binary));
  $result = '';
  foreach (str_split($binary, 7) as $bits) {
    $p1 = ($bits[0] + $bits[2] + $bits[4] + $bits[6]) % 2;
    $p2 = ($bits[1] + $bits[2] + $bits[5] + $bits[6]) % 2;
    $p3 = ($bits[3] + $bits[4] + $bits[5] + $bits[6]) % 2;
    $brokenBit = base_convert("$p3$p2$p1", 2, 10);
    if ($brokenBit != 0)
      $bits[$brokenBit - 1] = ($bits[$brokenBit - 1] + 1) % 2;
    assertTrue(($bits[0] + $bits[2] + $bits[4] + $bits[6]) % 2 == 0, "p1 is wrong");
    assertTrue(($bits[1] + $bits[2] + $bits[5] + $bits[6]) % 2 == 0, "p2 is wrong");
    assertTrue(($bits[3] + $bits[4] + $bits[5] + $bits[6]) % 2 == 0, "p3 is wrong");
    $result .= $bits[2] . $bits[4] . $bits[5] . $bits[6];
  }
  return $result;
}

function base64url_encode($data) {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
  return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

  /*
function objectToStdClass ($object) {
  echo serialize($object);
  exit;
  if (get_class($object) == 'stdclass')
    return $object;
  $reflection = new ReflectionObject($object);
  $stdObject = (object) array();
  $stdObject->___class = $reflection->getName();
  foreach ($reflection->getProperties() as $property) {
    $property->setAccessible(true);
    $value = $property->getValue($object);
    if (is_object($value))
      $value = objectToStdClass($value);
    $stdObject->{$property->name} = $value;
  }
  foreach ($reflection->getMethods() as $method) {
    $name = $method->getName();
    if (in_array($name, array('__construct', '__clone', '__toString')))
      continue;
    if (substr($name, 0, 3) == 'get')
      $name = lcfirst(substr($name, 3));
    //$stdObject->
    var_dump($name);
  }
    exit;
  return $stdObject;
}

function jsonEncode ($value, $options = 0) {
  if (is_object($value))
    $value = objectToStdClass($value);
  return json_encode($value, $options);
}
  /**/

function is_integerValue ($value) {
  return !is_object($value) && !is_array($value) && floor($value) == $value;
}

function firstElement ($array) {
  foreach ($array as $element)
    return $element;
}

function setBit ($string, $offset) {
  while (strlen($string) * 8 - 1 < $offset)
    $string .= chr(0);
  return substr($string, 0, floor($offset / 8)) . chr(ord($string[floor($offset / 8)]) | pow(2, 7 - $offset % 8)) . substr($string, floor($offset / 8) + 1);
}

function unsetBit ($string, $offset) {
  while (strlen($string) * 8 - 1 < $offset)
    $string .= chr(0);
  return substr($string, 0, floor($offset / 8)) . chr(ord($string[floor($offset / 8)]) & (255 - pow(2, 7 - $offset % 8))) . substr($string, floor($offset / 8) + 1);
}

function issetBit ($string, $offset) {
  if (strlen($string) * 8 - 1 < $offset)
    return false;
  return ord($string[floor($offset / 8)]) & pow(2, 7 - $offset % 8) ? true : false;
}

function keys ($subject) {
  $keys = array();
  foreach ($subject as $key => $value)
    $keys[] = $key;
  return $keys;
}

function values ($subject) {
  $values = array();
  foreach ($subject as $key => $value)
    $values[] = $value;
  return $values;
}

function array_merge_recursive_overwrite () {
  $args = func_get_args();
  $destination = array();
  foreach ($args as $arr) {
    foreach ($arr as $key => $value) {
      if (array_key_exists($key, $destination) && is_array($value))
        $destination[$key] = array_merge_recursive_overwrite($destination[$key], $arr[$key]);
      else
        $destination[$key] = $value;
    }
  }

  return $destination;
}

function recursive_merge () {
  $args = func_get_args();
  $destination = array_shift($args);
  foreach ($args as $iterable) {
    if (is_string($iterable) || is_int($iterable))
      $destination = $iterable;
    else
      foreach ($iterable as $key => $value) {
        if (is_object($destination))
          $destination->$key = isset($destination->$key) && (is_object($value) || is_array($value)) ? recursive_merge($destination->$key, $value) : $value;
        else if (is_array($destination))
          $destination[$key] = isset($destination[$key]) && (is_object($value) || is_array($value)) ? recursive_merge($destination[$key], $value) : $value;
        else
          assertTrue(false);
      }
  }

  return $destination;
}

function flatten ($subject, $delimiter = '_') {
  assertTrue(is_array($subject) || is_object($subject));
  $result = is_array($subject) ? array() : (object) array();
  _flatten($result, $subject, $delimiter);
  return $result;
}

function _flatten (&$result, $subject, $delimiter = '_', $prefix = '') {
  assertTrue(is_array($result) || is_object($result));
  assertTrue(is_array($subject) || is_object($subject));
  foreach ($subject as $key => $value) {
    if (is_array($value) || is_object($value))
      _flatten($result, $value, $delimiter, $prefix . ($prefix ? $delimiter : '') . $key);
    else
      if (is_array($result))
        $result[$prefix . ($prefix ? $delimiter : '') . $key] = $value;
      else
        $result->{$prefix . ($prefix ? $delimiter : '') . $key} = $value;
  }
}

function unflatten ($subject, $delimiter = '_') {
  assertTrue(is_array($subject) || is_object($subject));
  $result = is_array($subject) ? array() : (object) array();
  foreach ($subject as $key => $value)
    _unflatten($result, $key, $value, $delimiter);
  return $result;
}

function _unflatten (&$result, $key, $value, $delimiter = '_') {
  $stack = array(&$result);
  $keyParts = explode($delimiter, $key);
  if (count($keyParts) > 1) {
    foreach (array_slice($keyParts, 0, -1) as $keyPart) {
      if (is_array($result)) {
        if (!isset($stack[0][$keyPart]))
          $stack[0][$keyPart] = array();
        array_unshift($stack, null);
        $stack[0] = &$stack[1][$keyPart];
      } else {
        if (!isset($stack[0]->$keyPart))
          $stack[0]->$keyPart = (object) array();
        array_unshift($stack, null);
        $stack[0] = &$stack[1]->$keyPart;
      }
    }
  }
  
  if (is_array($result))
    $stack[0][end($keyParts)] = $value;
  else
    $stack[0]->{end($keyParts)} = $value;

}

function getURL ($url, $timeout = 3000) {
  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array(
    'Accept-Charset: utf-8',
    'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
    'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:18.0) Gecko/20100101 Firefox/18.0',
  ));
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($curl, CURLOPT_HEADER, 0);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl, CURLOPT_TIMEOUT, 0);
  curl_setopt($curl, CURLOPT_TIMEOUT_MS, $timeout);
  return curl_exec($curl);
}

function postToURL ($url, $postData, $timeout = 3000) {
  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array(
    'Accept-Charset: utf-8',
    'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
    'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:18.0) Gecko/20100101 Firefox/18.0',
  ));
  curl_setopt($curl, CURLOPT_POST, 1);
  curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData, '', '&'));
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($curl, CURLOPT_HEADER, 0);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl, CURLOPT_TIMEOUT, 0);
  curl_setopt($curl, CURLOPT_TIMEOUT_MS, $timeout);
  return curl_exec($curl);
}

