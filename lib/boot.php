<?php
require_once("../lib/base.php");

//header('Access-Control-Allow-Origin: https://next.mineria.gd.pe');

// function exception_handler($exception) {
//    go(false,$exception);
// }
// set_exception_handler('exception_handler');

$config = require_once("config.php");
$app = new \lib\App($config);

return $app;