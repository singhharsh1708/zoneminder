<?php
// web/ajax/trigger.php passed cause, text and showtext straight to the monitor's
// shared memory. A list there (cause[]=x) was written as the text "Array" and
// the event started with that cause. It must be refused before anything is
// written, while plain values still reach the monitor unchanged.
//
// Run as: php tests/php/test_ajax_trigger_list_values.php [web root]

namespace ZM {
  class Monitor {
    public static $calls = array();
    public static function find_one($f) { return new Monitor(); }
    public function canEdit() { return true; }
    public function TriggerEventOn($s, $c, $t) { self::$calls[] = array('on', $s, $c, $t); return true; }
    public function TriggerShowtext($t) { self::$calls[] = array('showtext', $t); }
    public function GetLastEventId() { return 7; }
    public function TriggerEventOff() { self::$calls[] = array('off'); }
    public function InAlarm() { return false; }
    public function TriggerEventCancel() {}
  }
  function Info($s) {}
  function Warning($s) {}
}

namespace {
  class AjaxError extends Exception {}
  class AjaxDone extends Exception {}
  function ajaxError($m, $c = 0, $r = '') { throw new AjaxError($m); }
  function ajaxResponse($d = array()) { throw new AjaxDone(); }
  function canEdit($r) { return true; }
  function validCardinal($v) { return is_scalar($v) ? preg_replace('/\D/', '', $v) : ''; }

  $root = isset($argv[1]) ? $argv[1] : __DIR__.'/../../web';
  $failures = 0;
  function check($name, $got, $want) {
    global $failures;
    if ($got === $want) echo "ok - $name\n";
    else { $failures++; echo "FAIL - $name (got ".var_export($got, true).", want ".var_export($want, true).")\n"; }
  }
  function run($root, $req) {
    $_REQUEST = $req;
    ZM\Monitor::$calls = array();
    try { include $root.'/ajax/trigger.php'; return 'returned'; }
    catch (AjaxError $e) { return 'error'; }
    catch (AjaxDone $e) { return 'ok'; }
  }
  // Warnings are not fatal in the real request, so they must not hide the result.
  set_error_handler(function() { return true; });

  foreach (array('cause', 'text', 'showtext') as $field) {
    $r = run($root, array('mid' => 1, 'action' => 'on', 'score' => 5, $field => array('x')));
    check("on with $field as a list is an error", $r, 'error');
    check("on with $field as a list writes nothing", ZM\Monitor::$calls, array());
  }
  $r = run($root, array('mid' => 1, 'action' => 'off', 'showtext' => array('x')));
  check('off with showtext as a list is an error', $r, 'error');
  check('off with showtext as a list writes nothing', ZM\Monitor::$calls, array());

  $r = run($root, array('mid' => 1, 'action' => 'on', 'score' => 5, 'cause' => 'Motion', 'text' => 'door', 'showtext' => 'hi'));
  check('on with plain values is ok', $r, 'ok');
  check('plain values reach the monitor unchanged', ZM\Monitor::$calls, array(array('on', '5', 'Motion', 'door'), array('showtext', 'hi')));

  echo $failures ? "\n$failures failed\n" : "\nall passed\n";
  exit($failures ? 1 : 0);
}
