<?php

class EvalAtRuntime extends Exception {}

function defaultTextColname($name) {
  $col = str_replace("_"," ",$name);
  $col = ucwords(strtolower($col));
  return $col;
}

# Returns a list of required hidden columns.
# The result may also contain tables that are required.
function getColRequirements($columns,$requires=null) {
  $req = array();
  if( $requires ) {
    foreach( $requires as $rcol ) {
      if( !in_array($rcol,$columns) && !in_array($rcol,$req) ) {
        $req[] = $rcol;
      }
    }
  }
  foreach( $columns as $col ) {
    $r = $col::requires();
    if( $r ) {
      if( !is_array($r) ) {
        throw new Exception("Expecting {$col}::requires() to return an array but got " . gettype($r));
      }
      foreach( $r as $rcol ) {
        if( !in_array($rcol,$columns) && !in_array($rcol,$req) ) {
	  $req[] = $rcol;
	}
      }
    }
  }
  # add requirements of the hidden columns too
  foreach( $req as $col ) {
    if( $col::dbview_type() == "table" ) continue;
    $r = $col::requires();
    if( $r ) {
      if( !is_array($r) ) {
        throw new Exception("Expecting {$col}::requires() to return an array but got " . gettype($r));
      }
      foreach( $r as $rcol ) {
        if( !in_array($rcol,$columns) && !in_array($rcol,$req) ) {
          $req[] = $rcol;
        }
      }
    }
  }
  return $req;
}

# Get the columns for a table or list of tables
# Preserve order.
# Filter out duplicates, keeping first one.
function getCols($tables) {
  if( !$tables ) return array();

  if( !is_array($tables) ) {
    return $tables::columns();
  }
  $added = array();
  $result = array();
  foreach( $tables as $table ) {
    $columns = $table::columns();
    if( !is_array($columns) ) {
      throw new Exception("Expecting {$table}::columns() to return an array but got " . gettype($columns));
    }
    foreach( $columns as $col ) {
      if( array_key_exists($col,$added) ) continue;
      $added[$col] = true;
      $result[] = $col;
    }
  }
  return $result;
}

# Return an array of columns sorted by $column::$filtersortfunc()
# Exclude columns for which $column::filtersortfunc() evaluates false
function filterSortCols($columns,$filtersortfunc) {
  $cols_to_sort = array();
  if( !is_array($columns) ) {
    throw new Exception("Expecting first argument to filterSortCols() to be an array but got " . gettype($columns));
  }
  foreach( $columns as $col ) {
    $sortval = $col::$filtersortfunc();
    if( $sortval ) {
      $cols_to_sort[] = array($sortval,$col);
    }
  }
  sortCols($cols_to_sort);
  $result = array();
  foreach( $cols_to_sort as $item ) {
    $result[] = $item[1];
  }
  return $result;
}

# sort in place an array of items which are two element arrays: array(sort_key,value)
# preserve order of items with the same sort key
function sortCols(&$sort_items) {
  $sorted = false;
  while( !$sorted ) {
    $sorted = true;
    for($i=count($sort_items)-1;$i>0;$i--) {
      if( $sort_items[$i-1][0] > $sort_items[$i][0] ) {
        $sorted = false;
        $tmp = $sort_items[$i-1];
        $sort_items[$i-1] = $sort_items[$i];
        $sort_items[$i] = $tmp;
      }
    }
  }
}



function getSelectSQL($columns,$hidden_columns) {
  $sql = "";
  if( !is_array($columns) ) {
    throw new Exception("Expecting first argument to filterSortCols() to be an array but got " . gettype($columns));
  }
  if( !is_array($hidden_columns) ) {
    throw new Exception("Expecting second argument to filterSortCols() to be an array but got " . gettype($hidden_columns));
  }
  foreach( array_merge($columns,$hidden_columns) as $col ) {
    if( $col::dbview_type() != "column" ) continue; # tables/views can be included via "requires"
    $select = $col::select();
    if( !$select ) continue;
    if( $sql ) $sql .= ",";
    $sql .= $select;
  }
  return $sql;
}

function getFromSQL($primary_table,$columns,$hidden_columns) {
  $tables_referenced = array();
  if( !is_array($columns) ) {
    throw new Exception("Expecting second argument to filterSortCols() to be an array but got " . gettype($columns));
  }
  if( !is_array($hidden_columns) ) {
    throw new Exception("Expecting third argument to filterSortCols() to be an array but got " . gettype($hidden_columns));
  }
  foreach( array_merge($columns,$hidden_columns) as $col ) {
    $table = $col::table();
    if( !in_array($table,$tables_referenced) ) {
      $tables_referenced[] = $table;
    }
  }
  $sql = "";
  if( class_exists($primary_table) ) {
    $sql .= $primary_table::from();
    foreach( $tables_referenced as $tref ) {
      if( $tref == $primary_table ) continue;
      if( $sql ) $sql .= " ";
      $join_func = "join_" . $tref::name();
      $sql .= $primary_table::$join_func();
    }
  }
  return $sql;
}
