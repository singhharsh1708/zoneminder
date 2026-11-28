<?php
// EventsController::index() finds the DateTime pseudo-field and Tags.Id by their keys in the conditions
// buildFilter() returns, but buildFilter() writes a list as array('Field' => list) under a numeric key and
// puts the operator into the key. So events/index/DateTime[]:x left DateTime in the WHERE clause and
// events/index/Tags.Id[]:1 (or Tags.Id >:1) skipped the Tags join: "Unknown column 'DateTime'" and
// "Unknown column 'Tags.Id'", a 500. A list is now an overlap test on DateTime like a plain value, and any
// Tags.Id term adds the join, once even when TagId is also given.
// index() is run against stubs and the conditions and joins given to find() are checked.
//   php tests/php/test_api_events_index_list_terms.php

$root = dirname(__DIR__, 2);
$controller_file = $root . '/web/api/app/Controller/EventsController.php';
$filter_file = $root . '/web/api/app/Controller/Component/FilterComponent.php';

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
    $cols = array('Id', 'MonitorId', 'StartDateTime', 'EndDateTime', 'Length');
    return array_combine($cols, $cols);
  }
  function getDataSource() { return new StubDataSource(); }
  function find($type, $settings) { $this->found = $settings; return array(); }
}
class StubComponents {
  function load($name) { return new FilterComponent(); }
}
class StubRequest {
  public $params = array('named' => array());
  function query($k) { return null; }
}
class AppController {
  public $request;
  public $Components;
  public $FilterComponent;
  public $Event;
  public $Paginator;
  function __construct() {
    $this->Paginator = new stdClass();
    $this->request = new StubRequest();
    $this->Components = new StubComponents();
    $this->Event = new StubEvent();
  }
  function viewableMonitorCondition($f) { return array(); }
  function set($v) {}
}

function load_php($file) {
  $src = file_get_contents($file);
  if ($src === false) {
    fwrite(STDERR, "cannot read $file\n");
    exit(2);
  }
  $src = preg_replace('/^<\?php/', '', $src);
  $src = preg_replace('/\?>\s*$/', '', $src);
  return $src;
}
eval(load_php($filter_file));
$src = load_php($controller_file);
$src = str_replace("require_once __DIR__ .'/../../../includes/Event.php';", '', $src);
eval($src);


$failures = 0;
$passes = 0;
function check($name, $ok, $detail = '') {
  global $failures, $passes;
  if ($ok) { $passes++; echo "ok - $name\n"; }
  else { $failures++; echo "not ok - $name" . ($detail !== '' ? ": $detail" : '') . "\n"; }
}

// Run index() with named parameters; the find() settings, or the exception message.
function run_index($named) {
  $c = new EventsController();
  $c->request->params['named'] = $named;
  try {
    $c->index();
  } catch (Exception $e) {
    return get_class($e) . ': ' . $e->getMessage();
  }
  return $c->Event->found;
}

// Whether a key starting with $prefix is anywhere in the conditions.
function has_key($conds, $prefix) {
  foreach ($conds as $k => $v) {
    if (is_string($k) and 0 === strpos($k, $prefix)) return true;
    if (is_array($v) and has_key($v, $prefix)) return true;
  }
  return false;
}

// How many times the joins name the table.
function joins_of($settings, $table) {
  $n = 0;
  foreach (isset($settings['joins']) ? $settings['joins'] : array() as $j) {
    if ($j['table'] === $table) $n++;
  }
  return $n;
}

foreach (array(
  'a list' => array('DateTime' => array('2024-01-01 00:00:00')),
  'two values' => array('DateTime' => array('2024-01-01 00:00:00', '2024-01-02 00:00:00')),
  'IN' => array('DateTime IN' => array('2024-01-01 00:00:00')),
  '!= and a list' => array('DateTime !=' => array('2024-01-01 00:00:00')),
) as $label => $named) {
  $r = run_index($named);
  check("DateTime with $label is accepted", is_array($r), is_string($r) ? $r : '');
  if (is_array($r)) {
    check("DateTime with $label leaves no DateTime column", !has_key($r['conditions'], 'DateTime'), var_export($r['conditions'], true));
    check("DateTime with $label tests the event start", has_key($r['conditions'], 'Event.StartDateTime'), var_export($r['conditions'], true));
  }
}

$r = run_index(array('DateTime' => '2024-01-01 00:00:00'));
check('DateTime with a plain value still tests the start', is_array($r) and has_key($r['conditions'], 'Event.StartDateTime') and !has_key($r['conditions'], 'DateTime'));

foreach (array(
  'a list' => array('Tags.Id' => array('1')),
  'two values' => array('Tags.Id' => array('1', '2')),
  'IN' => array('Tags.Id IN' => array('1')),
  'an operator' => array('Tags.Id >' => '1'),
  'a plain value' => array('Tags.Id' => '1'),
) as $label => $named) {
  $r = run_index($named);
  check("Tags.Id with $label is accepted", is_array($r), is_string($r) ? $r : '');
  if (is_array($r)) {
    check("Tags.Id with $label joins Tags once", joins_of($r, 'Tags') === 1 and joins_of($r, 'Events_Tags') === 1, var_export($r['joins'], true));
  }
}

$r = run_index(array('TagId' => '1', 'Tags.Id' => array('2')));
check('TagId with Tags.Id joins Tags once', is_array($r) and joins_of($r, 'Tags') === 1 and joins_of($r, 'Events_Tags') === 1, is_string($r) ? $r : var_export($r['joins'], true));
$r = run_index(array('TagId' => '1'));
check('TagId alone joins Tags once', is_array($r) and joins_of($r, 'Tags') === 1);
$r = run_index(array('MonitorId' => '3'));
check('a filter on neither joins no Tags', is_array($r) and joins_of($r, 'Tags') === 0);

echo "\n$passes passed, $failures failed\n";
exit($failures ? 1 : 0);
?>
