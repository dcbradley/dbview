<?php

if( !file_exists(__DIR__ . DIRECTORY_SEPARATOR . "db_params.php") ) {
  throw new Exception("You must define db_params.php in " . __DIR__ . ".  See db_params_example.php.");
}

require_once "db_params.php";

$dbh = NULL;
function connectDB() {
  global $dbh;
  if( $dbh ) return $dbh;

  $opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION
  ];

  $db_params = dbParams();
  $dbh = new PDO($db_params['database'],$db_params['user'],$db_params['password'], $opt);
  return $dbh;
}
