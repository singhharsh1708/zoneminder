<?php
// Cake assumes a primary key called id unless the model names it. The tables here key on Id, so a
// model that does not declare it leaves pinRequestId() dropping a key that is never there: a body
// Id then renumbers the row an edit was addressed to, and {"Id":null} is written as Id = NULL,
// which MySQL refuses with a 500. Every model with a table must declare its primary key.
//
// Pure source scan:
//   php tests/php/test_api_model_primary_key.php

$passes = 0;
$failures = 0;

foreach (glob(__DIR__.'/../../web/api/app/Model/*.php') as $path) {
  $name = basename($path);
  if ($name == 'AppModel.php') continue;
  $content = file_get_contents($path);
  if (!preg_match('/class\s+\w+\s+extends\s+AppModel/', $content)) continue;
  if (preg_match('/\$useTable\s*=\s*false/', $content)) continue;
  if (preg_match('/\$primaryKey\s*=\s*\'\w+\'/', $content)) {
    $passes++;
    echo "ok - $name declares its primary key\n";
  } else {
    $failures++;
    echo "not ok - $name has a table but does not declare its primary key\n";
  }
}

echo "\n$passes passed, $failures failed\n";
exit($failures ? 1 : 0);
?>
