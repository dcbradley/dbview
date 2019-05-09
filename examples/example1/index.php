<?php

require "example1_dbview.php";

?>
<html>
<head>
<title>DBView Example 1</title>
</head>
<body>

<h1>DBView Example 1</h1>

<p>This simple example displays the contents of a database table using
<a href='example1.dbview'>DBView code</a> to describe the table and
columns.  Adding more columns or modifying how a column appears can be
easily achieved by editing the DBView code, allowing the PHP display
code to remain fairly generic in tructure.</p>

<table>
<?php

$dbh = connectDB();
$sql = view_example1::sql();
$stmt = $dbh->prepare($sql);
$stmt->execute();

echo "<tr>";
$columns = view_example1::columns();
foreach( $columns as $col ) {
  echo "<th>",$col::html_colname(),"</th>";
}
echo "</tr>\n";

while( ($row=$stmt->fetch()) ) {
  echo "<tr>";
  foreach( $columns as $col ) {
    echo "<td>",$col::html_value(),"</td>";
  }
  echo "</tr>\n";
}

?>
</table>

</body>
</html>
