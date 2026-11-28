<?php
// Monitor::validDevicePath() passed the Device straight to preg_match() for a Local monitor, so a
// list or object sent as Device (Monitor[Device][]=1 or {"Device":{"a":1}}) died with a TypeError,
// a 500. AppModel::beforeValidate() already refuses a list as "must be a single value", so the
// rule now leaves a list to it, as validFfmpegPath() does.
//
//   php tests/php/test_api_monitor_device_list.php [repo-root]

class App { public static function uses($class, $package) {} }

class AppModel {
  public $id = false;
  public $data = array();
  public function field($name) { return false; }
}

$repoRoot = $argv[1] ?? realpath(__DIR__.'/../..');
require_once "$repoRoot/web/api/app/Model/Monitor.php";

$passes = 0;
$failures = 0;
function check($ok, $what) {
  global $passes, $failures;
  if ($ok) { $passes++; echo "ok - $what\n"; } else { $failures++; echo "not ok - $what\n"; }
}

function outcome($device) {
  $m = new Monitor();
  $m->data = array('Monitor' => array('Type' => 'Local'));
  try {
    return $m->validDevicePath(array('Device' => $device));
  } catch (Throwable $e) {
    return get_class($e);
  }
}

check(outcome(array(1)) === true, 'a list Device is left to AppModel instead of a TypeError');
check(outcome(array('a' => 1)) === true, 'an object Device is left to AppModel instead of a TypeError');
check(outcome('/dev/video0') === true, 'a /dev/ path is still accepted');
check(outcome('video0; reboot') === false, 'a non-/dev/ path is still refused');
check(outcome('') === true, 'an empty Device is still allowed');

echo "\n$passes passed, $failures failed\n";
exit($failures ? 1 : 0);
