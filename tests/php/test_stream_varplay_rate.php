<?php
// web/ajax/stream.php added 32768 to $_REQUEST['rate'] for CMD_VARPLAY, which is a TypeError
// in PHP 8 for a list (rate[]=1) or text (rate=abc), so the request died without an answer.
// The command switch is run here as it is in the file.
//
// Run as: php tests/php/test_stream_varplay_rate.php

$failures = 0;
function check($name, $got, $want) {
  global $failures;
  if ($got === $want) echo "ok - $name\n";
  else { $failures++; echo "FAIL - $name (got ".var_export($got, true).", want ".var_export($want, true).")\n"; }
}

$source = file_get_contents(__DIR__.'/../../web/ajax/stream.php');
$start = strpos($source, "switch (\$_REQUEST['command']) {");
$end = strpos($source, '$remSockFile =');
$switch = substr($source, $start, $end - $start);

class AjaxError extends Exception {}
function ajaxError($message, $code = 0, $reason = '') { throw new AjaxError($message); }
define('HTTP_STATUS_OK', 200);
define('STREAM_ERR_INVALID', 'invalid');
define('MSG_CMD', 1);
define('CMD_VARPLAY', 15);
define('CMD_ZOOMIN', 8);
define('CMD_PAN', 10);
define('CMD_SCALE', 11);
define('CMD_SEEK', 14);
define('CMD_MAXFPS', 18);
define('CMD_SET_EVENT', 22);
define('ZM_OPT_USE_AUTH', 0);

// Debug() is namespaced as ZM\Debug in the file.
eval('namespace ZM { function Debug($message) {} }');

function run($switch, $request) {
  $_REQUEST = $request;
  try {
    eval($switch);
    return bin2hex($msg);
  } catch (AjaxError $e) {
    return $e->getMessage();
  } catch (Throwable $e) {
    return get_class($e);
  }
}

foreach (array('a list' => array('1'), 'text' => 'abc', 'an empty value' => '') as $name => $rate) {
  check("rate as $name is refused", run($switch, array('command' => '15', 'rate' => $rate)), 'Invalid rate for VARPLAY');
}

// MSG_CMD (4 bytes, host order), the command byte, then rate + 32768 as a big-endian uint16.
$header = bin2hex(pack('lc', 1, 15));
check('rate 100', run($switch, array('command' => '15', 'rate' => '100')), $header.'8064');
check('rate -100', run($switch, array('command' => '15', 'rate' => '-100')), $header.'7f9c');
check('rate 0', run($switch, array('command' => '15', 'rate' => '0')), $header.'8000');
check('rate as an int', run($switch, array('command' => '15', 'rate' => 100)), $header.'8064');
check('a missing rate is rate 0', run($switch, array('command' => '15')), $header.'8000');

echo $failures ? "\n$failures failed\n" : "\nall passed\n";
exit($failures ? 1 : 0);
