<?php
// A group save turned each submitted MonitorIds entry into an int with intval().
// intval() of a nested list is 1, so newGroup[MonitorIds][][]=x made monitor 1 a
// member of the group (and the membership permission check ran for monitor 1).
// A list among the ids must be left out.
//
// php tests/php/test_group_save_nested_monitor_ids.php [action-file]

namespace ZM {
  function Warning($s) {}
  function Debug($s) {}
  function Error($s) {}

  class Rec {
    public static $queries = array();
    public static $audit = array();
    public static $save_result = true;
    public static $last_error = '';
    public static $rows = array();
    public static $fail = array();
    public static $fail_mid = null;
  }

  function AuditAction($action = null, $type = null, $id = null, $note = null) {
    Rec::$audit[] = array($action, $type, $id, $note);
  }

  class Group {
    private $id = null;
    private $parent = null;
    public function __construct($id = null) {
      $this->id = ($id === null or $id === '') ? null : (int)$id;
      if ($this->id) {
        $this->parent = isset(Rec::$rows[$this->id]) ? Rec::$rows[$this->id] : null;
      }
    }
    public static function validParent($group_id, $parent_id) { return true; }
    public static function canEditMembership($monitor_ids) { return true; }
    public function Id($v = null) {
      if ($v !== null) $this->id = $v;
      return $this->id;
    }
    public function ParentId() { return $this->parent; }
    public function MonitorIds() { return array(); }
    public function save($values) {
      if (!Rec::$save_result) return false;
      if (!$this->id) $this->id = 42;
      return true;
    }
    public function get_last_error() { return Rec::$last_error; }
  }
}

namespace {
  function canEdit($p, $id = null) { return true; }
  function validHtmlStr($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
  // Copied from web/includes/functions.php.
  function validCardinal($input) {
    if (!is_scalar($input)) return '';
    return preg_replace('/\D/', '', $input);
  }
  function dbFetchAll($sql, $col = null, $args = array()) { return array(); }
  function dbLastError() { return 'fk <fail>'; }
  function dbQuery($sql, $args = array()) {
    ZM\Rec::$queries[] = array($sql, $args);
    foreach (ZM\Rec::$fail as $needle) {
      if (strpos($sql, $needle) === 0) {
        if ($needle !== 'INSERT' or ZM\Rec::$fail_mid === null or $args[1] === ZM\Rec::$fail_mid) return null;
      }
    }
    return true;
  }

  $action_file = isset($argv[1]) ? $argv[1] : __DIR__.'/../../web/includes/actions/group.php';
  if (!preg_match('#^/#', $action_file)) $action_file = getcwd().'/'.$action_file;
  if (!is_file($action_file)) { fwrite(STDERR, "no such action file: $action_file\n"); exit(1); }

  $failures = 0;
  function check($name, $got, $want = true) {
    global $failures;
    if ($got === $want) echo "ok - $name\n";
    else { $failures++; echo "FAIL - $name (got ".var_export($got, true).", want ".var_export($want, true).")\n"; }
  }

  function run_action($action_file, $request, $fail, $fail_mid = null) {
    ZM\Rec::$queries = array();
    ZM\Rec::$audit = array();
    ZM\Rec::$save_result = true;
    ZM\Rec::$rows = array(3 => null);
    ZM\Rec::$fail = $fail;
    ZM\Rec::$fail_mid = $fail_mid;
    $GLOBALS['error_message'] = '';
    $_REQUEST = $request;
    $action = 'save';
    $redirect = 'SENTINEL';
    ob_start();
    include $action_file;
    ob_end_clean();
    return array($GLOBALS['error_message'], $redirect);
  }

  function inserted() {
    $m = array();
    foreach (ZM\Rec::$queries as $q) {
      if (strpos($q[0], 'INSERT') === 0) $m[] = $q[1][1];
    }
    return $m;
  }

  run_action($action_file, array('gid' => '3', 'newGroup' => array('Name' => 'Front', 'MonitorIds' => array('2', '5'))), array());
  check('plain monitor ids become members', inserted(), array(2, 5));

  list($msg, $redirect) = run_action($action_file, array('gid' => '3', 'newGroup' => array('Name' => 'Front', 'MonitorIds' => array('2', array('x')))), array());
  check('a nested list is not a member', inserted(), array(2));
  check('the save still redirects', $redirect, '?view=groups');

  run_action($action_file, array('gid' => '3', 'newGroup' => array('Name' => 'Front', 'MonitorIds' => array(array('x')))), array());
  check('only a nested list adds no member', inserted(), array());

  echo $failures ? "\n$failures failed\n" : "\nall passed\n";
  exit($failures ? 1 : 0);
}
