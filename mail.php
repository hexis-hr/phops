<?php

function send_mail ($addresses, $subject, $message, $headers) {

  foreach ((array) $addresses as $address) {
  
    assertTrue(strpos($address, '@') !== false);
    $domain = substr($address, strpos($address, '@') + 1);
    
    assertTrue(getmxrr($domain, $mx) && count($mx) > 0);
    
    $stream = fsockopen($mx[0], 25, $errno, $errstr, 3);
    assertTrue($stream);
    assertTrue($errno == 0);
    
    $lcheaders = array();
    foreach ($headers as $k => $v)
      $lcheaders[strtolower($k)] = $v;
    
    // TODO: autocreate if not exists
    assertTrue(isset($lcheaders['from']));
    
    fwrite($stream, "HELO " . htmlspecialchars($domain) . "\r\n");
    send_mail_process_response($stream, "250");

    fwrite($stream, "MAIL FROM: <" . $lcheaders['from'] . ">\r\n");
    send_mail_process_response($stream, "250");

    fwrite($stream, "RCPT TO: <" . $address . ">\r\n");
    send_mail_process_response($stream, "250");

    fwrite($stream, "DATA\r\n");
    send_mail_process_response($stream, "354");

    foreach ($headers as $k => $v)
      fwrite($stream, "$k: $v\r\n");

    fwrite($stream, "Date: " . date('r') . "\r\n");
    fwrite($stream, "Subject: " . preg_replace('/\r?\n/', ' ', $subject) . "\r\n");
    fwrite($stream, "To: <" . $address . ">\r\n");
    fwrite($stream, "\r\n" . trim($message) . "\r\n");
    fwrite($stream, ".\r\n");
    send_mail_process_response($stream, "250");

    fwrite($stream, "QUIT\r\n");
    send_mail_process_response($stream, "221");

  }

}

function send_mail_process_response ($stream, $exceptedCode) {
  while (true) {
    $line = trim(fread($stream, 1024));
    $code = substr($line, 0, 3);
    if ($code == $exceptedCode)
      return;
    if ($code >= 200 && $code <= 299)
      continue;
    assertTrue(false);
  }
}


