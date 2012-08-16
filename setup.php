<?php

function remove ($path) {
  if (!file_exists($path) && !is_link($path))
    return;
  assertTrue(trim($path, "/ ") != "");
  switch (strtolower(strtok(php_uname('s'), ' '))) {
    case 'linux': case 'mac': case 'darwin':
      `rm -fR $path`;
      break;
    case 'windows':
      if (is_file($path) || is_link($path))
        unlink($path);
      else if (is_dir($path))
        rmdir($path);
      else
        assertTrue(false);
      break;
    default:
      assertTrue(false);
  }
}

function directory ($dir) {
  if (is_dir($dir))
    return;
  switch (strtolower(strtok(php_uname('s'), ' '))) {
    case 'linux': case 'mac': case 'darwin':
      `mkdir -p $dir`;
      break;
    case 'windows':
      mkdir($dir, 0777, true);
      break;
    default:
      assertTrue(false);
  }
}

function directoryLink ($link, $dir) {
  remove($link);
  directory(dirname($link));
  directory($dir);
  symbolicLink($link, $dir);
}

function symbolicLink ($link, $dir) {
  $_setup_bin_path = _setup_bin_path();
  switch (strtolower(strtok(php_uname('s'), ' '))) {
    case 'linux': case 'mac': case 'darwin':
      `ln -s $dir $link`;
      break;
    case 'windows':
      assertTrue(is_file("$_setup_bin_path/junction.exe"));
      `$_setup_bin_path/junction.exe $link $dir`;
      break;
    default:
      assertTrue(false);
  }
}


function _setup_bin_path () {
  return dirname(__FILE__) . '/' . strtolower(strtok(php_uname('s'), ' ')) . '/';
}

