<?php
// REST API FilterComponent::buildFilter(): a list given with = or <> (Id =[]:1, Id <>[]:1) is a 500.
// CakePHP writes a one-element list as Field = (v) unless the key ends in IN, NOT or !=, so the SQL was
// "Id = = ('1')" or "Id <> = ('1')". = is written as the bare field and <> as !=, which CakePHP turns into
// IN and NOT IN for a list of any length. A plain value keeps its operator.
//   php tests/php/test_api_filter_list_operator.php

class App {
  public static function uses($class, $package) {}
}
class Component {}
class BadRequestException extends Exception {}

require_once __DIR__.'/../../web/api/app/Controller/Component/FilterComponent.php';

$failures = 0;
$passes = 0;

function check($name, $got, $expected) {
  global $failures, $passes;
  if ($got === $expected) {
    $passes++;
    echo "ok - $name\n";
  } else {
    $failures++;
    echo "not ok - $name (got ".var_export($got, true)." expected ".var_export($expected, true).")\n";
  }
}

function build($named) {
  return (new FilterComponent())->buildFilter($named);
}

check('<> with one value in a list is NOT IN',
  build(array('Id <>' => array('1'))),
  array(array('Id !=' => array('1')))
);
check('<> with two values in a list is NOT IN',
  build(array('Id <>' => array('1', '2'))),
  array(array('Id !=' => array('1', '2')))
);
check('= with one value in a list is the bare field',
  build(array('Id =' => array('1'))),
  array(array('Id' => array('1')))
);
check('= with two values in a list is the bare field',
  build(array('Id =' => array('1', '2'))),
  array(array('Id' => array('1', '2')))
);
check('<> with a plain value is unchanged', build(array('Id <>' => '1')), array('Id <>' => '1'));
check('= with a plain value is unchanged', build(array('Id =' => '1')), array('Id =' => '1'));
check('!= with a list is unchanged', build(array('Id !=' => array('1'))), array(array('Id !=' => array('1'))));
check('IN with a list is unchanged', build(array('Id IN' => array('1'))), array(array('Id IN' => array('1'))));

echo "\n$passes passed, $failures failed\n";
exit($failures ? 1 : 0);
?>
