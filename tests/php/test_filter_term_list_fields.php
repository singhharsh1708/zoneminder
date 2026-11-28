<?php
// A filter taken from the request (events, montage review and timeline views, the events ajax)
// can carry a list where a term field holds one value, such as filter[Query][terms][1][cnj][]=and.
// A list in cnj reached array_key_exists() as a key, a list in attr reached urlencode(), both a
// TypeError, a list in collate put "Array" into the SQL, and a list nested in val put "Array"
// into the compared value, each with an "Array to string conversion" warning on the way. Flat
// lists in val and every ordinary term are kept. A list in sort_field, sort_asc or skip_locked went
// into the filter's links as "sort_asc=Array".
//
// Run as: php tests/php/test_filter_term_list_fields.php

namespace ZM;

$failures = 0;
$passes = 0;

function check($name, $got, $want) {
  global $failures, $passes;
  if ($got === $want) {
    $passes++;
    echo "ok - $name\n";
  } else {
    $failures++;
    echo "FAIL - $name (got ".var_export($got, true).", want ".var_export($want, true).")\n";
  }
}

$stubdir = sys_get_temp_dir().'/zm_filter_term_list_stubs_'.getmypid();
@mkdir($stubdir, 0700, true);
foreach (array('database.php', 'Monitor.php') as $stub) {
  file_put_contents($stubdir.'/'.$stub, "<?php\n");
}
set_include_path($stubdir.PATH_SEPARATOR.__DIR__.'/../../web/includes'.PATH_SEPARATOR.get_include_path());

define('ZM_WEB_EVENT_SORT_FIELD', 'StartDateTime');
define('ZM_WEB_EVENT_SORT_ORDER', 'asc');
if (!defined('STRF_FMT_DATETIME_DB')) define('STRF_FMT_DATETIME_DB', 'Y-m-d H:i:s');
function dbEscape($s) { return "'".addslashes($s)."'"; }
function Warning($s) {}
function Error($s) {}
function Debug($s) {}
function Info($s) {}
function translate($s) { return $s; }
function jsonEncode($v) { return json_encode($v); }
function jsonDecode($v) { return json_decode($v, true); }
class Group {
  public function __construct($v=null) {}
  public function MonitorIds() { return array(); }
}

require_once __DIR__.'/../../web/includes/Filter.php';

set_error_handler(function($no, $str) { throw new \ErrorException($str); });

function norm($s) { return trim(preg_replace('/\s+/', ' ', $s)); }

// What the filter makes of the terms: its SQL, its query string and its tree, or the failure.
function outcome($terms) {
  try {
    $f = new Filter();
    $f->Query(array('terms' => $terms));
    $f->querystring();
    $f->tree();
    return norm($f->sql());
  } catch (\Throwable $e) {
    return get_class($e).': '.$e->getMessage();
  }
}

$first = array('attr' => 'Id', 'op' => '=', 'val' => '5');
$second = array('cnj' => 'and', 'attr' => 'Id', 'op' => '=', 'val' => '7');

check('ordinary terms are unchanged', outcome(array($first, $second)), "E.Id = '5' and E.Id = '7'");
check('a list in cnj is ignored', outcome(array($first, array('cnj' => array('and')) + $second)), "E.Id = '5' and E.Id = '7'");
check('a list in attr drops the term', outcome(array($first, array('attr' => array('Id')) + $second)), "E.Id = '5'");
check('a list in op means =', outcome(array($first, array('op' => array('<')) + $second)), "E.Id = '5' and E.Id = '7'");
check('a list in obr and cbr is ignored', outcome(array(array('obr' => array(1), 'cbr' => array(1)) + $first)), "E.Id = '5'");
check('a list in tablename is ignored', outcome(array(array('tablename' => array('E')) + $first)), "E.Id = '5'");
check('a list in collate is ignored', outcome(array(array('collate' => array('utf8mb4_bin')) + $first)), "E.Id = '5'");
check('a flat list in val is kept', outcome(array(array('val' => array('5', '7'), 'op' => 'IN') + $first)), "E.Id IN ('5','7')");
check('an empty list in val is kept', outcome(array(array('val' => array()) + $first)), outcome(array(array('val' => '') + $first)));
check('a nested list in val is dropped', outcome(array(array('val' => array(array('5'))) + $first)), outcome(array(array('val' => '') + $first)));
check('a nested list beside a value in val is dropped', outcome(array(array('val' => array('5', array('7')), 'op' => 'IN') + $first)), "E.Id IN ('5')");

$f = new Filter();
$f->Query(array('terms' => array($first, array('cnj' => array('and'), 'obr' => array(1)) + $second)));
check('terms() has no list left in a single-valued field', $f->terms(), array($first, array('attr' => 'Id', 'op' => '=', 'val' => '7')));

// The same for the sort and lock settings of the filter, which go into links as "sort_asc=Array".
$f = new Filter();
$f->Query(array('sort_field' => array('Id'), 'sort_asc' => array('1'), 'skip_locked' => array('1')));
try {
  check('a list in sort_field, sort_asc and skip_locked gives the defaults',
    array($f->sort_field(), $f->sort_asc(), $f->skip_locked(), norm($f->querystring())),
    array('StartDateTime', 1, false, '&amp;filter%5BQuery%5D%5Bsort_asc%5D=1&amp;filter%5BQuery%5D%5Bsort_field%5D=StartDateTime'));
} catch (\Throwable $e) {
  check('a list in sort_field, sort_asc and skip_locked gives the defaults', get_class($e).': '.$e->getMessage(), '');
}
$f = new Filter();
$f->Query(array('sort_field' => 'Id', 'sort_asc' => '0', 'skip_locked' => '1'));
check('scalar sort and lock settings are kept', array($f->sort_field(), $f->sort_asc(), $f->skip_locked()), array('Id', '0', '1'));

// The filter from the request may also come as Query_json, which is not read through Query().
$f = new Filter();
try {
  $f->set(array('Query_json' => array('x')));
  check('a list as Query_json is no query', array($f->Query_json(), $f->terms()), array('', array()));
} catch (\Throwable $e) {
  check('a list as Query_json is no query', get_class($e).': '.$e->getMessage(), '');
}
$f = new Filter();
$f->set(array('Query_json' => '{"terms":[{"attr":"Id","op":"IN","val":[["5"],"7"],"cnj":["and"]}]}'));
check('a nested list in the val of Query_json is dropped', outcome($f->terms()), "E.Id IN ('7')");

@unlink($stubdir.'/database.php');
@unlink($stubdir.'/Monitor.php');
@rmdir($stubdir);

echo "\n$passes passed, $failures failed\n";
exit($failures ? 1 : 0);
