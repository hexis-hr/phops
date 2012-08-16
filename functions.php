<?php

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

