<!doctype html>
<html lang="en">
<head>
  <title>DBView Example 1</title>
</head>
<body>

<h1>DBView Example 1</h1>

<p>This simple example displays the contents of a database table using
<a href='example1.dbview'>DBView code</a> to describe the table and
columns.  Adding more columns or modifying how a column appears can be
easily achieved by editing the DBView code, allowing the main PHP code
to remain fairly generic in tructure.</p>

<p>Steps to run this example:</p>

<ol>
<li>Compile the dbview code in dbview/examples/example1:<br>
<pre>../../dbview example1.dbview example1_dbview.php</pre></li>

<li>Create dbview/examples/db/db_params.php, using db_params_example.php as a template.</li>

<li>Create the dbview_example1 table in the database, using example1.sql or similar sql.</li>

</ol>

<p>Once the above has been done, a table will appear below containing
the data in the dbview_example1 table:</p>

<?php

ini_set('display_errors', 'On');

require_once "../db/db.php";

if( !file_exists(__DIR__ . DIRECTORY_SEPARATOR . "example1_dbview.php") ) {
  throw new Exception("You must generate example1_dbview.php in " . __DIR__ . " by running the command: ../../dbview example1.dbview example1_dbview.php");
}

require_once "example1_dbview.php";

$dbh = connectDB();
$sql = view_example1::sql();
$stmt = $dbh->query($sql);

echo "<table border='1'>\n";
echo "<tr>";
$columns = view_example1::columns();
foreach( $columns as $col ) {
  echo "<th>",$col::html_colname(),"</th>";
}
echo "</tr>\n";

while( ($row=$stmt->fetch()) ) {
  echo "<tr>";
  foreach( $columns as $col ) {
    echo "<td>",$col::html_value($row),"</td>";
  }
  echo "</tr>\n";
}

?>
</table>

</body>
</html>
