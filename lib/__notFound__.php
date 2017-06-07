<?php
// all non-existing php file will be directed to this one
if (isset($app_tag) && $app_tag != 'live') {
  echo "<!--\n";
  echo "===== m =====\n\n";
  var_dump($m);
  echo "-->\n";
}

?>
