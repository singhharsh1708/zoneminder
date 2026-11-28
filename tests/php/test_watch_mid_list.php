<?php
// views/watch.php read the monitor id with intval($_REQUEST['mid']), which is 1 for any
// non-empty array: ?view=watch&mid[]=3 opened monitor 1, or the error view when there is
// no monitor 1. A mid that is a list is no mid, as when none is given: the first monitor
// is watched. A numeric mid still selects its monitor.
//
// Run: php tests/php/test_watch_mid_list.php   (from the repo root)

namespace ZM {
  function Error($m) {}
  function Debug($m) {}
  class Monitor {
    public static $known = array();
    public $row = array();
    public function __construct($idOrRow = 0) {
      if (is_array($idOrRow)) $this->row = $idOrRow;
      else if (isset(self::$known[$idOrRow])) $this->row = self::$known[$idOrRow];
    }
    public static function find_one($params = array()) {
      return isset(self::$known[$params['Id']]) ? new Monitor($params['Id']) : null;
    }
    public function Id() { return isset($this->row['Id']) ? $this->row['Id'] : null; }
  }
}

namespace {
  $passed = 0;
  $failed = 0;
  function check($label, $got, $want) {
    global $passed, $failed;
    if ($got === $want) {
      $passed++;
      echo "  ok $label\n";
    } else {
      $failed++;
      echo "  FAIL $label\n";
      echo "       got:  ".var_export($got, true)."\n";
      echo "       want: ".var_export($want, true)."\n";
    }
  }

  function canView($a, $id = null) { return true; }
  function visibleMonitor($mid) { return true; }
  function translate($s) { return $s; }
  function buildMonitorsFilters() {
    return array('filterBar' => '', 'displayMonitors' => $GLOBALS['displayMonitors']);
  }

  // The view includes the monitor filter bar and requires includes/Monitor.php and
  // includes/MonitorAction.php, which
  // pull in the real database layer; give it stubs from a temporary include path.
  $stubDir = sys_get_temp_dir().'/zm-watch-view-'.getmypid();
  @mkdir($stubDir.'/includes', 0700, true);
  file_put_contents($stubDir.'/includes/Monitor.php', "<?php\n");
  file_put_contents($stubDir.'/includes/MonitorAction.php', "<?php\n");
  file_put_contents($stubDir.'/_monitor_filters.php', "<?php\n");
  set_include_path($stubDir.PATH_SEPARATOR.get_include_path());
  register_shutdown_function(function() use ($stubDir) {
    @unlink($stubDir.'/includes/Monitor.php');
    @unlink($stubDir.'/includes/MonitorAction.php');
    @unlink($stubDir.'/_monitor_filters.php');
    @rmdir($stubDir.'/includes');
    @rmdir($stubDir);
  });

  function run_view($mid) {
    $_REQUEST = $mid === null ? array() : array('mid' => $mid);
    $view = 'watch';
    try {
      require __DIR__.'/../../web/skins/classic/views/watch.php';
    } catch (Throwable $e) {
      // Past the monitor choice the view needs the whole web layer; $mid is already set.
    }
    return array($view, $mid);
  }

  $displayMonitors = array(
    array('Id' => 3, 'Capturing' => 'Always', 'Width' => 640, 'Height' => 480),
    array('Id' => 1, 'Capturing' => 'Always', 'Width' => 640, 'Height' => 480),
  );
  ZM\Monitor::$known = array(1 => array('Id' => 1), 3 => array('Id' => 3));

  check('no mid watches the first monitor', run_view(null), array('watch', 3));
  check('a numeric mid watches that monitor', run_view('1'), array('watch', 1));
  check('a list as mid is no mid', run_view(array('1')), array('watch', 3));
  check('a nested list as mid is no mid', run_view(array(array('1'))), array('watch', 3));
  check('an empty list as mid is no mid', run_view(array()), array('watch', 3));

  echo "\n$passed passed, $failed failed\n";
  exit($failed ? 1 : 0);
}
