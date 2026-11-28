<?php
// Options > Menu turned submitted values into ints with intval(). intval() of a
// list is 1, so deleteIds[][]=x deleted menu entry 1 and a list in SortOrder
// moved that entry to position 1. A list must be left out of the delete and
// must keep the stored SortOrder.
//
// The real actions/options.php is loaded with the database layer stubbed.
//
// Run as: php tests/php/test_menu_items_nested_values.php   (from the repo root)

namespace ZM {
  $GLOBALS['warnings'] = array();
  function Warning($s) { $GLOBALS['warnings'][] = $s; }

  class MenuItem {
    public static $items = array();
    public $d;
    public $saved = null;
    public function __construct($d = array()) { $this->d = $d + array('Link' => null, 'Label' => null, 'Icon' => null, 'IconType' => 'material', 'SortOrder' => 0, 'Enabled' => 1); }
    public static function find($p = array(), $o = array()) { return self::$items; }
    public function __call($n, $a) { return $this->d[$n] ?? null; }
    public function save($v) { $this->saved = $v; $this->d = $v + $this->d; return true; }
    public function get_last_error() { return ''; }
    public function effectiveIcon() { return 'menu'; }
    public function effectiveIconType() { return 'material'; }
    public function displayLabel() { return ($this->d['Label'] ?? '') !== '' ? $this->d['Label'] : $this->d['MenuKey']; }
  }
}

namespace {
  $failures = 0;
  $passes = 0;
  function check($name, $got, $want) {
    global $failures, $passes;
    if ($got === $want) { $passes++; echo "  ok $name\n"; }
    else { $failures++; echo "  FAIL $name\n    got:  " . var_export($got, true) . "\n    want: " . var_export($want, true) . "\n"; }
  }

  $GLOBALS['queries'] = array();
  function canView($a) { return true; }
  function canEdit($a) { return true; }
  function translate($s) { return $s; }
  function validHtmlStr($i) { return $i === null ? '' : htmlspecialchars($i, ENT_QUOTES); }
  function dbQuery($sql, $params = array()) { $GLOBALS['queries'][] = array($sql, $params); return true; }
  function dbLastError() { return ''; }

  $skin = 'classic';
  $view = 'montage';
  require_once(__DIR__.'/../../web/skins/classic/includes/functions.php');

  function run_action($action) {
    global $error_message;
    $error_message = '';
    include(__DIR__.'/../../web/includes/actions/options.php');
  }
  set_include_path(sys_get_temp_dir().'/zmstub'.PATH_SEPARATOR.get_include_path());
  $stub = sys_get_temp_dir().'/zmstub/includes';
  @mkdir($stub, 0777, true);
  file_put_contents($stub.'/config_values.php', '<?php');
  file_put_contents($stub.'/MenuItem.php', '<?php');

  $GLOBALS['queries'] = array();
  $_REQUEST = array('deleteIds' => array('4', array('x')));
  run_action('deletemenuitems');
  check('a nested list is left out of the delete', array_map(function($q) { return $q[1]; }, $GLOBALS['queries']), array(array(4)));

  $GLOBALS['queries'] = array();
  $_REQUEST = array('deleteIds' => array(array('x')));
  run_action('deletemenuitems');
  check('only a nested list deletes nothing', $GLOBALS['queries'], array());

  $a = new ZM\MenuItem(array('Id' => 1, 'MenuKey' => 'Console', 'Enabled' => 1, 'SortOrder' => 25));
  ZM\MenuItem::$items = array($a);
  $_REQUEST = array('items' => array(1 => array('Enabled' => '1', 'SortOrder' => array('x'))));
  run_action('menuitems');
  check('a list in SortOrder keeps the stored order', $a->saved['SortOrder'] ?? null, 25);

  $_REQUEST = array('items' => array(1 => array('Enabled' => '1', 'SortOrder' => '40')));
  run_action('menuitems');
  check('a plain SortOrder is saved', $a->saved['SortOrder'] ?? null, 40);

  echo "\n$passes passed, $failures failed\n";
  exit($failures ? 1 : 0);
}
