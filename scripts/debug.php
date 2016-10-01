<?php

error_reporting(-1);

assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_WARNING, 0);
assert_options(ASSERT_BAIL, 0);
assert_options(ASSERT_QUIET_EVAL, 0);
assert_options(ASSERT_CALLBACK, 'assert_callcack');

set_error_handler('error_handler');
set_exception_handler('exception_handler');
register_shutdown_function('shutdown_handler');

function assert_callcack($file, $line, $message) {
  throw new Customizable_Exception($message, null, $file, $line);
}

function error_handler($errno, $error, $file, $line, $vars) {
  if ($errno === 0 || ($errno & error_reporting()) === 0) {
      return;
  }
  throw new Customizable_Exception($error, $errno, $file, $line);
}

function exception_handler(Exception $e) {
  WriteInLog($e->getMessage());
  exit;
}

function shutdown_handler() {
  try {
    if (null !== $error = error_get_last()) {
      throw new Customizable_Exception($error['message'], $error['type'], $error['file'], $error['line']);
    }
  } catch (Exception $e) {
    exception_handler($e);
  }
}

class Customizable_Exception extends Exception {
  public function __construct($message = null, $code = null, $file = null, $line = null) {
    if ($code === null) {
      parent::__construct($message);
    } else {
      parent::__construct($message, $code);
    }
    if ($file !== null) {
      $this->file = $file;
    }
    if ($line !== null) {
      $this->line = $line;
    }
  }
}
