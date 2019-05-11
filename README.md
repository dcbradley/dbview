# DBView
A simple language for generating PHP code to view/edit data in a database.

A DBView file contains a concise description of tables and columns in
a database.  It defines how to read the data with SQL and how to
format it for viewing and editing.  The language contains a small base
set of properties that can be easily extended.

The generated PHP code is in the form of static class definitions.
The intention is to make good use of the web server's opcode cache by
avoiding a lot of object creation and initialization at runtime.

# Installation

Requirements: PHP.  The DBView compiler has been tested in PHP 7 under
Linux and OS X.  It has not been tested in Windows.

Clone the DBView repository:

    git clone https://github.com/dcbradley/dbview.git

Compile a DBView example:

    cd examples/example1
    ../../dbview example1.dbview example1_dbview.php

See examples/example1/index.php to run the example.

# DBView Language Overview

There are three DBView types: columns, tables, and views.  Columns and
tables correspond to data in a relational database.  Views are used to
define groups of columns that are loaded from the database and
displayed or otherwise used together.  Objects of these types are
declared as

    column tablename.colname
    table tablename
    view viewname

The column type is the default if no type is specified.  The following
two lines are equivalent:

    column tablename.colname
    tablename.colname

Objects become PHP classes in the generated code:

    table tablename          -->  table_tablename
    view viewname            -->  view_viewname
    column tablename.colname -->  tablename_colname
    tablename.colname        -->  tablename_colname

See [object name conflicts](#object-name-conflicts) for some technical details.

The attributes of an object are specified in an indented block after
the line declaring the object.  They may be set to

* a string
* a numeric value
* an object name (i.e. a column, table, or view)
* an array of values
* a PHP function body
* a macro of the form `<<macroname>>`

The following example illustrates attribute assignment by defining a
column named `EXPECTED_MINOR1` in the `student` table and setting the
value of some of its attributes:

    student.EXPECTED_MINOR1
      # override default display name of "Expected Minor1"
      html_colname: "Expected Minor&nbsp;1"

      # define a custom attribute to be used elsewhere (no special meaning in DBView)
      in_csv: 0

      # this column does not actually exist in the database, so override the default SQL select clause and make it empty
      select: ""

      # this column requires two other columns to be loaded from the database
      requires: [
        EXPECTED_MINOR1_CODE,
        EXPECTED_MINOR1_DESCR,
      ]

      # compose the value of this column from the values of two other columns
      value($row) {
        return student_EXPECTED_MINOR1_CODE::value($row) . " " . student_EXPECTED_MINOR1_DESCR::value($row);
      }

Columns that only need default attributes can be declared without any
attribute assignments.  For example the two additional columns
required by the above example could be defined with all default
attributes as follows:

    student.EXPECTED_MINOR1_CODE
    student.EXPECTED_MINOR1_DESCR

# DBView Syntax

## Numeric Values

Numeric values may be specified using the same syntax as in PHP.

Examples:

    integer: 123
    float:   123.456
    sci:     123.456e78
    hex:     0x1FA
    oct:     0766
    binary:  0b1101

## Strings

String values are surrounded by either single or double quotes.  The
strings are copied verbatim into the generated PHP code, so the same
escape sequences are used.

Example:

    student.EXPECTED_MINOR1
      # override default display name of "Expected Minor1"
      html_colname: "Expected Minor&nbsp;1"

## Object class references

A value that refers to a column, table, or view uses the syntax
illustrated on the right hand side of the following attribute
assignments:

    student.COLUMN
      other_student_column: OTHER_COLUMN
      other_table_column:   tablename.OTHER_COLUMN
      some_table:           tablename
      some_view:            viewname

When translated into PHP, the value is replaced by the class that it
refers to.  For example, the above definitions are equivalent to the
following PHP blocks:

    student.COLUMN
      other_student_column: { return student_OTHER_COLUMN::class; }
      other_table_column:   { return tablename_OTHER_COLUMN::class; }
      some_table:           { return tablename::class; }
      some_view:            { return viewname::class; }

Objects may be referenced before they are declared.

## Arrays

Array values begin with `[` and end with `]`.  The values in the array
are separated by commas.  Empty values are ignored, so an extra comma
after the last item has no effect.  Newlines may appear before the
first value in the array, after a comma, and before the final `]`.

Example:

    view student_directory
      columns: [
        student.NAME,
        student.EMAIL,
        student.PHONE,
        student.OFFICE,
      ]

## PHP code blocks

A PHP code block is used to insert code directly into the PHP that is
generated from the DBView file.  This can be done to define attribute
values that are more complicated than simple constants.  It may also
be done outside of an object definition to inject supporting code such
as function or class definitions.

PHP code blocks begin with `{` and end with `}`.  Braces must be
balanced within the code block, so that the end of the PHP code block
is correctly detected by the DBView compiler.  The DBView compiler
does not attempt to parse the PHP code other than to count opening and
closing braces.  Even braces within comments and strings count.  If
the PHP code contains unbalanced braces, balance them by using braces
in a comment.

When used to define an attribute value, the PHP code should return the
desired value.  The following two attribute definitions are equivalent,
one using a constant and one using a PHP code block.

    student.EXAMPLE
      attrname: "This is the value."

      attrname: { return "This is the value."; }

All attributes become static member functions in the generated PHP
code, so the above definitions truly are the same.  In both cases,
the value of the attribute can be accessed in PHP as
`student_EXAMPLE::attrname()`.

From within another attribute of the same object, the value of the
attribute can be accessed in PHP as `static::attrname()`.  For examle:

    student.EXAMPLE
      attrname: "This is the value."
      attrname2: { return "attrname is " . static::attrname(); }

PHP code blocks can also be declared in functional form.  For
attributes that take no arguments, this is just a choice of style, but
for attributes that take arguments, it is required.  The following
example defines an attribute that takes two arguments:

    student.EXAMPLE
      attrname($arg1,$arg2) { return $arg1 . $arg2; }

If the attribute has a default that was declared with arguments, then
when overriding the attribute it is not necessary to define the
arguments explicitly.  In the following example, the `html_format`
attribute is overridden.  The default definition of `html_format` (not
shown here) declares an argument with the name `$value`, so the
following code can refer to that argument without explicitly declaring
it.

    student.EXAMPLE
      html_format: { return str_replace(" ","&nbsp;",$value); }

That is equivalent to the following statement with an explicit
argument definition:

    student.EXAMPLE
      html_format($value) { return str_replace(" ","&nbsp;",$value); }

A PHP code block can also appear outside of an object declaration.  In
this case, the `php` keyword must precede the code block.  The
following example uses a PHP code block to define a function.

    php {
      function formatSemester($term_id) {
        # code here to convert $term_id to the form "Fall 2020"
      }
    }

### eval

The `eval` keyword may be used to cause a PHP code block to be
evaluated at compile-time.  This is purely for efficiency &mdash; doing
work once at compile-time rather than every time a web page is loaded.
This will only work for values that do not depend on runtime data.

The following example uses `eval` to generate a list of columns from
the `student` and `student_qual` tables.  Only columns having the
attribute `in_csv` set to a true value (e.g. non-zero) are included.
The columns are sorted by the value of their `in_csv` attribute.  The
PHP functions `filterSortCols()` and `getCols()` that are used in this
example are part of the DBView standard library.

    view csv
      tables: [ student, student_qual ]
      columns: eval { return filterSortCols(getCols(static::tables()),"in_csv"); }

## Macros

Macros are inserted using the syntax `<<macroname>>` or
`<<macroname()>>`.  Macros may appear wherever a value is expected or
embedded in a PHP code block.

The following special macros are defined:

    <<name>>    - the name of the column, table, or view being defined
    <<table>>   - a reference to the table of the column or table being defined
    <<columns>> - an array of columns that are defined for the table or view that is being defined

The following example shows some default attributes of tables that are
defined using macros:

    table *
      name:      <<name>>
      table:     <<table>>
      columns:   <<columns>>

When a macro is followed by `()`, it refers to an attribute of the
current object that is being defined.  It is evaluated at compile-time
and the resulting PHP value is inserted in place of the macro.

In the following example, `combined1` and `combined2` are equivalent:

    student.EXAMPLE
      val1: "One"
      val2: "Two"
      combined1: { return <<val1()>> . " and " . <<val2()>>; }
      combined2: { return "One" . " and " . "Two"; }

## @compiletime

The @compiletime keyword indicates that a statement should only
generate PHP code at compile-time.  This is useful if the code is used
at compile-time for evaluating other attributes but is not needed at
runtime.

In the following example, the `val1` attribute is only defined at
compile-time and is used when evaluating `val2`:

    student.EXAMPLE
      @compiletime val1: "One"
      val2: eval { return static::val1() . " Two"; }

## @runtime

The @runtime keyword indicates that a statement should only generate
PHP code at runtime.  This could be used to insert code that generates
output without having it generate the output at compile-time during
the evaluation phase.

## require

The require statement is used to insert code from another DBView file.

Most DBView projects should require `std1`.  This defines the
`std1` default attributes.  Example:

    require "std1"

The extension `.dbview` is automatically added to the filename
specified.

Files are searched for in the same directory as the file that requires
them and also in the dbview compiler directory.

When the same file is required more than once, it is only inserted the
first time.

## require_php

The `require_php` statement is used to insert PHP code from another
file.

If the file is found in the dbview compiler directory, it is read from
there.  Otherwise, it will be searched for in the usual way that PHP
finds required files.

When the same file is required more than once, it is only inserted the
first time.

## Defining default attributes

Default attributes may be specified by defining an object with a
wildcard in its name.  All subsequent objects of the same type with a
matching name will inherit the specified defaults.

All defaults that match an object definition are applied in the order
they were defined.  If the same attribute is defined in multiple
default definitions, the last one to be defined takes effect.

The following example sets a custom-defined attribute `input_type` to
`"text"` by default.  For all columns with names ending in `"_DATE"`,
`input_type` is set to `"date"`.

    column *.*
      input_type: "text"

    column *.*_DATE
      input_type: "date"

Like any attribute, default attributes can be defined as member
functions that take arguments.  In the following example, the
`echo_td` member function is defined to take an argument `$row` that
contains the associative array of data loaded from the database.

    column *.*
      echo_td($row) {
        echo "<td>",static::html_value($row),"</td>";
      }

When a default member function has been defined with arguments,
functions that override the default can be declared with or without
arguments.  When declared without arguments, the overriding function
is declared with the same argument definition as the default.  In the
following example, a column, `course.GRADE` is defined with an
`echo_td` member function that overrides the default defined above and
which implicitly uses the same argument definition.

    course.GRADE
      echo_td: {
        $cl = gradeClass(static::value($row));
        echo "<td class='$cl'>",static::html_value($row),"</td>";
      }

## join

The `join` keyword is used to specify the SQL needed to join one table
to another.

The following example indicates how to join the `student_hr` table to
the `student` table.

    table student
      join student_hr: "LEFT JOIN student_hr ON student_hr.ID = student.ID"

Some views might not require all possible tables to be joined.  Only
the required tables will be joined when creating the SQL for a
particular view.

## comments

Comments are ignored by the DBView compiler.  The following
comment styles are supported:

    # single-line comment

    // another single-line comment

    /* multi-line
       comment */

    /* multi-line comment
       /* containing /*nested*/ comments */
    */

## Object name conflicts

Tables and views must have different DBView object names.  If a view
name conflicts with a table name, simple call the view `view_viewname`
to avoid conflict.  When a view object name begins with `view_` then
an extra `view_` is <em>not</em> prepended to form the PHP class name.
Similarly for tables.

Example:

    table example
    view view_example
      table: example
      columns: { return table_example::columns(); }

# std1

The DBView `std1` library defines a set of default attributes and
provides a set of standard PHP functions.  The reason it is called
`std1` rather than just `std` is in case future versions are created
that diverge enough from `std1` to warrant a different version number
or name.  It is intended that code designed for `std1` would still
continue to work in the future, because it explicitly uses a specific
version of the standard library, which would continue to exist
alongside other versions of the standard.

The default attributes are described in the following sections.

## column

A column corresponds to a column in a relational database.  The
default attributes in DBView `std1` are described in the following
sections.

### column attribute: `name`

The name of the column.  This should match the name of the column in
the database (or the alias used when reading the column from the
database).

The default value is the name of the column object, as provided by the
macro `<<name>>`.

### column attribute: `table`

The table object to which the column belongs.  The default value is
the table specified in the column definition, as provided by the macro
`<<table>>`.

### column attribute: `select`

The SQL used in the select clause to get the value of this column from
the database.  The default is `tablename.colname`.

For columns that are not actually stored in the database but which are
constructed from other columns, the select attribute should be set to
an empty string.

### column attribute: `text_colname`

The display name of this column in plain text.  The default is the
column name with underscores replaced by spaces and with the first
letter of each word capitalized.

### column attribute: `html_rowname`

The display name of this column when shown in an html table as a
row, with the column name and the column value side by side.  The
default is `text_colname` with any special html characters escaped.

### column attribute: `html_colname`

The display name of this column when shown in the header at the top of
an html table.  The default is `html_rowname`.

The reason for having different attributes for `html_rowname` and
`html_colname` is that it is sometimes desirable to make
`html_colname` compact, so that the column is not too wide.  To
achieve that, it might contain explicit `<br>` tags, which would not
be wanted in `html_rowname`.

### column attribute: `value($row)`

The value loaded from the database for this column.  A `$row`
argument is required.  It is the associative array of values read from
the database for the particular record being displayed.

Example usage in PHP code:

    tablename_COLNAME::value($row)

### column attribute `text_value($row)`

The value of this column formatted in plain text.  A `$row` argument
is required.  It is the associative array of values read from the
database for the particular record being displayed.

The default value is `text_format(value($row))`.  Since the default
`text_format` does not change the value, this function too does not
change the value by default.

### column attribute: `html_value($row)`

The value of this column formatted as html.  A `$row` argument
is required.  It is the associative array of values read from the
database for the particular record being displayed.

The default value is `html_format(text_value($row))`.  The default
`html_format` escapes special html characters.

### column attribute: `text_format($value)`

Formats a value in plain text.  A `$value` argument is required.  The
return value of this attribute is a transformation of `$value`.  The
default is to return `$value` unchanged.

### column attribute: `html_format($value)`

Formats a value in html.  A `$value` argument is required.  The return
value of this attribute is a transformation of `$value`.  The default
is to return `$value` with html special characters escaped.

### column attribute: `requires`

An array of tables and/or columns required by this column.  Required
tables will be joined.  Required columns will be loaded from the
database.  The table of a required column is automatically added to
the list of tables to be joined, so it is not necessary to explicitly
specify that a table is required unless the join is desired but no
column from that table is required.

This attribute is only defined at compile-time.

Example usage:

    student.EXPECTED_MINOR1
      requires: [
        EXPECTED_MINOR1_CODE,
        EXPECTED_MINOR1_DESCR,
      ]

### column attribute: `is_table`

False.  This is only defined at compile-time.

## table

A table corresponds to a table in a relational database.  The
default attributes in DBView `std1` are described in the following
sections.

### table attribute: `name`

The name of the table (or the alias of the table as defined in `from`
attribute).  The default value is the name of the table being defined,
as provided by the `<<name>>` macro.

### table attribute: `table`

A reference to this table object.  The default value is provided by
the `<<table>>` macro.

### table attribute: `columns`

An array of columns defined for this table.  The default value is
provided by the `<<columns>>` macro.

### table attribute: `from`

The SQL used for this table in the `from` clause.  The default is the
name of the table.

Example usage:

    table grad
      from: "graduate_student grad"

### table attribute: `is_table`

True.  This is only defined at compile-time.

## view

A view describes a database query used to load a set of columns.  The
default attributes in DBView `std1` are described in the following
sections.

### view attribute: `name`

The name of this view object.  The default is provided by the `<<name>>` macro.

### view attribute: `table`

This attribute is required but has no default.  It should be set to
the primary table for this view.

### view attribute: `columns`

The array of columns visible in this view.  Additional columns may be
automatically included in `hidden_columns` due to some columns in this
array requiring other columns that are not included in this array.

The default value is to include the columns that are defined with this
view as their table.  Usually, the default is overridden.  It is rare to
define columns with a view as their table.

Example usage:

    view student_directory
      table: student
      columns: [
        student.NAME,
        student.EMAIL,
        student.PHONE,
      ]

### view attribute: `requires`

The array of columns and tables required by this view but not visible
in it.  This does not necessarily include the requirements of all the
columns in this view.  It is intended to be used to specify additional
requirements.

### view attribute: `hidden_columns`

The array of columns and tables not visible but required by visible
columns in this view.  The default combines all the `requires`
attributes of this view and the columns listed in `columns`.

This attribute is rarely overridden.  Instead, the `requires`
attribute of this view or of the visible columns is set.

### view attribute: `select`

The SQL to use for the select clause for this view.  The default value
combines the select clauses of all the columns (both visible and
hidden) in this view.

This attribute is rarely overridden.

### view attribute: `from`

The SQL to use for the `from` clause of this view.  The default value
combines the `from` clause of the primary table and the required
tables joined to it.

### view attribute: `where`

The SQL to use in the `where` clause for this view.  The default is empty.

### view attribute: `order_by`

The SQL to use in the `order by` clause of this view.  The default is empty.

### view attribute: `sql($and_where="")`

The SQL statement for this view.  The default combines all the other
SQL attributes (`select`, `from`, `where`, `order_by`) into a complete
SQL statement.  This attribute takes an optional argument `$and_where`
that specifies additional SQL to use in the `where` clause.

Example usage in PHP code:

    $sql = view_student_directory::sql("LAST_NAME LIKE :SEARCH_STRING");
    $stmt = $dbh->prepare($sql);
    $stmt->bindValue(":SEARCH_STRING",$search_string);
    $stmt->execute();

### view attribute: `is_table`

False.  This is only defined at compile-time.
