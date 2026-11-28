<?php
// EventsController::search() tested each named parameter's value with preg_match() to find an
// "interval ..." term, so a list (events/search/Name[]:a) was a TypeError, a 500. A list is not an
// interval: it goes to buildFilter() as for index(), where a flat list is IN and a list holding a list
// is a 400. A string value, including an interval, is unchanged.
// search() is run against stubs and the conditions given to find() are checked.
//   php tests/php/test_api_events_search_list.php

$root = dirname(__DIR__, 2);

class BadRequestException extends Exception {}
class UnauthorizedException extends Exception {}
class NotFoundException extends Exception {}
class Component {}
class App { static function uses() {} }
function __($s) { return $s; }

class StubDataSource {
  function value($v, $type = null) { return "'".addslashes($v)."'"; }
}
class StubEvent {
  public $recursive;
  public $found = null;
  function schema() {
    $cols = array('Id', 'MonitorId', 'Name', 'StartDateTime');
    return array_combine($cols, $cols);
  }
  function getDataSource() { return new StubDataSource(); }
  function find($type, $settings) { $this->found = $settings; return array(); }
}
class StubComponents {
  function load($name) { return new FilterComponent(); }
}
class AppController {
  public $params = array('named' => array());
  public $Components;
  public $FilterComponent;
  public $Event;
  function __construct() {
    $this->Components = new StubComponents();
    $this->Event = new StubEvent();
  }
  function viewableMonitorCondition($f) { return array(); }
  function set($v) {}
}

function load_php($file) {
  $src = file_get_contents($file);
  $src = preg_replace('/^<\?php/', '', $src);
  return preg_replace('/\?>\s*$/', '', $src);
}
eval(load_php($root.'/web/api/app/Controller/Component/FilterComponent.php'));
$src = load_php($root.'/web/api/app/Controller/EventsController.php');
$src = str_replace("require_once __DIR__ .'/../../../includes/Event.php';", '', $src);
eval($src);

$failures = 0;
$passes = 0;
function check($name, $got, $expected) {
  global $failures, $passes;
  if ($got === $expected) { $passes++; echo "ok - $name\n"; }
  else { $failures++; echo "not ok - $name (got ".var_export($got, true)." expected ".var_export($expected, true).")\n"; }
}

// The conditions search() hands to find(), or "ClassName: message" for what it threw.
function run_search($named) {
  $controller = new EventsController();
  $controller->params['named'] = $named;
  try {
    $controller->search();
  } catch (Throwable $e) {
    return get_class($e).': '.$e->getMessage();
  }
  return $controller->Event->found['conditions'];
}

check('a list is an IN filter', run_search(array('Name' => array('a', 'b'))),
  array(array('Name' => array('a', 'b')), array(), array()));
check('a one-element list is an IN filter', run_search(array('MonitorId' => array('1'))),
  array(array('MonitorId' => array('1')), array(), array()));
check('a list holding a list is a bad request', run_search(array('Name' => array('a', array('b')))),
  'BadRequestException: Invalid value for Name: nested array');
check('a string is unchanged', run_search(array('Name' => 'cam')), array('Name' => 'cam', array(), array()));
check('an interval is unchanged', run_search(array('StartDateTime' => 'interval 1 hour')),
  array(array("StartDateTime >= DATE_SUB(NOW(), INTERVAL '1' HOUR)"), array()));

echo "\n$passes passed, $failures failed\n";
exit($failures ? 1 : 0);
?>
