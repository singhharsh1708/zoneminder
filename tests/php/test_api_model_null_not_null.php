<?php
// AppModel::beforeValidate() must refuse null and an empty list for a NOT NULL column. Cake saves
// both as NULL, and MySQL answers "Column 'Definition' cannot be null", a 500, so it has to be a
// field error instead. A nullable column still takes them as NULL; the primary key, created,
// modified, updated, a NOT NULL timestamp (which a server may fill in) and a column the model's
// beforeSave() sets itself (serverSetColumns, as Zone does for NumCoords and Area) are left alone.
// The Cake model is stubbed, so this runs from a source checkout:
//   php tests/php/test_api_model_null_not_null.php

class App { public static function uses($class, $package) {} }
class Model {
  public $alias = 'Thing';
  public $primaryKey = 'Id';
  public $data = array();
  public $invalid = array();
  public function schema() {
    return array(
      'Id' => array('type' => 'integer', 'null' => false),
      'Height' => array('type' => 'integer', 'null' => false),
      'Width' => array('type' => 'integer', 'null' => true),
      'Seen' => array('type' => 'datetime', 'null' => false),
      'Ended' => array('type' => 'datetime', 'null' => true),
      'Stamp' => array('type' => 'timestamp', 'null' => false),
      'Method' => array('type' => "enum('Local','Remote')", 'null' => false),
      'Name' => array('type' => 'string', 'length' => 5, 'null' => false),
      'Alias' => array('type' => 'string', 'length' => 5, 'null' => true),
      'Notes' => array('type' => 'text', 'null' => false),
      'Enabled' => array('type' => 'boolean', 'null' => false),
      'Derived' => array('type' => 'integer', 'null' => false),
      'created' => array('type' => 'datetime', 'null' => false),
      'modified' => array('type' => 'datetime', 'null' => false),
    );
  }
  public function invalidate($field, $message) { $this->invalid[$field] = $message; }
}
require_once __DIR__.'/../../web/api/app/Model/AppModel.php';
class Computed extends AppModel { public $serverSetColumns = array('Derived'); }

$passes = 0;
$failures = 0;
function check($name, $got, $expected) {
  global $passes, $failures;
  if ($got === $expected) { $passes++; echo "ok - $name\n"; }
  else { $failures++; echo "not ok - $name (got ".var_export($got, true)." expected ".var_export($expected, true).")\n"; }
}

function accepted($field, $value, $class = 'AppModel') {
  $m = new $class();
  $m->data = array('Thing' => array($field => $value));
  $m->beforeValidate();
  return !isset($m->invalid[$field]);
}

foreach (array('Height', 'Seen', 'Method', 'Name', 'Notes', 'Enabled') as $field) {
  check("null $field (NOT NULL) is refused", accepted($field, null), false);
  check("empty list $field (NOT NULL) is refused", accepted($field, array()), false);
}
foreach (array('Width', 'Ended', 'Alias') as $field) {
  check("null $field (nullable) is accepted", accepted($field, null), true);
  check("empty list $field (nullable) is accepted", accepted($field, array()), true);
}
foreach (array('Id', 'created', 'modified', 'Stamp') as $field) {
  check("null $field (key, Cake date or timestamp) is accepted", accepted($field, null), true);
  check("empty list $field (key, Cake date or timestamp) is accepted", accepted($field, array()), true);
}
check('null Derived is refused when the model does not set it', accepted('Derived', null), false);
check('null Derived is accepted when the model sets it in beforeSave', accepted('Derived', null, 'Computed'), true);
check('empty list Derived is accepted when the model sets it in beforeSave', accepted('Derived', array(), 'Computed'), true);
check('Height null is still refused on that model', accepted('Height', null, 'Computed'), false);
check('Height 0 is accepted', accepted('Height', 0), true);
check('Name "" is still accepted (text)', accepted('Name', ''), true);
check('Height as a non-empty list is still refused', accepted('Height', array(1)), false);

$m = new AppModel();
$m->data = array('Thing' => array('Name' => null, 'Height' => 5));
$m->beforeValidate();
check('only the null NOT NULL field is flagged', array_keys($m->invalid), array('Name'));

echo "\n$passes passed, $failures failed\n";
exit($failures ? 1 : 0);
?>
