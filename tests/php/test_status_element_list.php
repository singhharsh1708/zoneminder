<?php
// ajax/status.php: element[][]=x (a list as one of the requested elements) went to strtolower(),
// a TypeError on PHP 8, and an element the entity does not have read an undefined array key.
// Both are answered with "Bad <entity> element" now; a flat list of real elements still works.
//
// status.php declares functions, so each scenario runs in a child php process.
//
// Run as: php tests/php/test_status_element_list.php

namespace ZM {
  function Debug($m) {}
  function Error($m) {}
  class Event {
    public function __construct($id = null) {}
    public function Id() { return 7; }
    public function canView() { return true; }
  }
}

namespace {
  if (!isset($argv[1])) {
    $failures = 0;
    foreach (array('nested', 'unknown', 'flat') as $scenario) {
      passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.$scenario, $rc);
      if ($rc) $failures++;
    }
    exit($failures ? 1 : 0);
  }

  class StopRequest extends Exception {}
  define('ZM_OPT_USE_AUTH', false);
  define('ZM_AUTH_RELAY', 'none');
  define('MYSQL_FMT_DATETIME_SHORT', '%y/%m/%d %H:%i:%S');

  function canView($area) { return true; }
  function daemonCheck() { return true; }
  function getLoad() { return 0; }
  function getDiskPercent() { return 0; }
  function translate($s) { return $s; }
  function validJsStr($v) { return (is_null($v) or is_array($v)) ? '' : strip_tags(addslashes($v)); }
  function validInt($v) { return is_array($v) ? '' : preg_replace('/[^\-\d]/', '', $v); }
  function validCardinal($v) { return is_scalar($v) ? preg_replace('/\D/', '', $v) : ''; }
  $sqls = array();
  function dbFetchOne($sql, $col = null, $params = array()) { $GLOBALS['sqls'][] = $sql; return array(); }
  function dbFetchAll($sql, $col = null, $params = array()) { $GLOBALS['sqls'][] = $sql; return array(); }
  function ajaxResponse($data = array()) { throw new StopRequest('ok'); }
  function ajaxError($m) { $GLOBALS['errors'][] = $m; throw new StopRequest($m); }
  $errors = array();

  $scenario = $argv[1];
  $elements = array('nested' => array(array('x')), 'unknown' => array('NoSuchElement'), 'flat' => array('FrameId', 'Score'));
  $_REQUEST = array('entity' => 'frames', 'id' => '7', 'element' => $elements[$scenario]);

  set_error_handler(function($no, $str) { throw new ErrorException($str); });
  $thrown = '';
  try {
    set_include_path(__DIR__.'/../../web'.PATH_SEPARATOR.get_include_path());
    include __DIR__.'/../../web/ajax/status.php';
  } catch (StopRequest $e) {
  } catch (Throwable $e) {
    $thrown = get_class($e).': '.$e->getMessage();
  }

  if ($scenario == 'flat') {
    $sql = $sqls ? $sqls[0] : '';
    $ok = ($thrown === '') && !$errors && strpos($sql, '`FrameId`, `Score`') !== false;
    echo ($ok ? 'ok' : 'FAIL').' - a list of real elements is selected'.($ok ? '' : " (sql: $sql; threw: $thrown)")."\n";
  } else {
    $ok = ($thrown === '') && $errors && strpos($errors[0], 'Bad frames element') === 0;
    echo ($ok ? 'ok' : 'FAIL').' - '.$scenario.' element is answered with an error'.($ok ? '' : " (errors: ".implode('; ', $errors)."; threw: $thrown)")."\n";
  }
  exit($ok ? 0 : 1);
}
