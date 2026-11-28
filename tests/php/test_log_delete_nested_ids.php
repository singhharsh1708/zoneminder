<?php
// Deleting selected log rows by id turned each id into an int with intval().
// intval() of a nested list is 1, so ids[][]=x deleted log row 1, a row nobody
// selected. A list inside the id list must be left out, and a request with
// no usable id must delete nothing. Usage: php test_log_delete_nested_ids.php [root]
$root = isset($argv[1]) ? $argv[1] : realpath(__DIR__.'/../..');

if ($argc < 3) {
  $failures = 0;
  foreach (array('plain', 'mixed', 'nested-only') as $case) {
    $out = array();
    $rc = 0;
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($root).' '.escapeshellarg($case).' 2>&1', $out, $rc);
    echo implode("\n", $out)."\n";
    if ($rc !== 0) $failures++;
  }
  echo $failures ? "\n$failures case(s) FAILED\n" : "\nall cases passed\n";
  exit($failures ? 1 : 0);
}
$case = $argv[2];
$ids = array('plain' => array('7', '9'), 'mixed' => array('7', array('x')), 'nested-only' => array(array('x')));
$GLOBALS['queries'] = array();
$GLOBALS['err'] = null;
function dbQuery($sql, $params = null) { $GLOBALS['queries'][] = array($sql, $params); return true; }
function canEdit($a) { return true; }
function validHtmlStr($s) { return htmlspecialchars($s, ENT_QUOTES); }
function ajaxError($m) { $GLOBALS['err'] = $m; }
function ajaxResponse($d = array()) { }
$user = null;
eval('namespace ZM; class Logger { public static $codes = array(-1=>"ERR", 0=>"INF", 1=>"DBG"); }
  function Error($m) {} function Warning($m) {} function Debug($m) {}');
set_error_handler(function() { return true; });

$_REQUEST = array('task' => 'delete', 'ids' => $ids[$case]);
require($root.'/web/ajax/log.php');

$q = $GLOBALS['queries'];
$failures = 0;
function ok($label, $cond) {
  global $failures;
  if (!$cond) $failures++;
  printf("%s - %s\n", $cond ? 'ok' : 'FAIL', $label);
}
switch ($case) {
  case 'plain':
    ok('plain ids delete those rows', count($q) == 1 && $q[0][1] === array(7, 9));
    break;
  case 'mixed':
    ok('a nested list is left out of the delete', count($q) == 1 && $q[0][1] === array(7));
    break;
  case 'nested-only':
    ok('only a nested list deletes nothing', count($q) == 0);
    ok('only a nested list is reported', $GLOBALS['err'] !== null);
    break;
}
exit($failures ? 1 : 0);
