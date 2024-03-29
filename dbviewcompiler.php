<?php

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
  if($errno === E_WARNING) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
  }
  return false; // to execute the regular error handler
}
set_error_handler("exception_error_handler");

const INDENT_TOK = 1;
const NAME_TOK = 2;
const STRING_TOK = 3;
const CODE_TOK = 4;
const EOF_TOK = 5;
const COLON_TOK = 6;
const NEWLINE_TOK = 7;
const ARGS_TOK = 8;
const NUM_TOK = 9;
const LBRACKET_TOK = 10;
const RBRACKET_TOK = 11;
const DOT_TOK = 12;
const COMMA_TOK = 13;
const GLOB_TOK = 14;
const AT_TOK = 15;
const MACRO_TOK = 16;
const MACRO_FUNC_TOK = 17;

class Token {
  public $type;
  public $value;
  function __construct($type,$value) {
    $this->type = $type;
    $this->value = $value;
  }
}

class Lex {
  public $linenum = 1;
  public $fname = null;
  public $F = null;
  public $curtoken = null;

  private $ch;
  private $at_newline = true;
  private $filestack;
  private $searchpath;
  private $already_included;

  function __construct() {
    $this->filestack = array();
    $this->searchpath = array(__DIR__);
    $this->already_included = array();
  }
  function rewind() {
    rewind($this->F);
    $this->linenum = 1;
    $this->already_included = array();
    $this->nextChar();
  }
  function findIncludeFile($fname) {
    $path = $this->fname ? dirname($this->fname) : "";
    if( $path ) {
      $full_fname = $path . DIRECTORY_SEPARATOR . $fname;
      if( file_exists($full_fname . ".dbview") ) return $full_fname . ".dbview";
      if( file_exists($full_fname) ) return $full_fname;
    }
    else {
      if( file_exists($fname . ".dbview") ) return $fname . ".dbview";
      if( file_exists($fname) ) return $fname;
    }
    foreach( $this->searchpath as $path ) {
      $full_fname = $path . DIRECTORY_SEPARATOR . $fname;
      if( file_exists($full_fname . ".dbview") ) return $full_fname . ".dbview";
      if( file_exists($full_fname) ) return $full_fname;
    }
    return false;
  }
  function include($fname) {
    $full_fname = $this->findIncludeFile($fname);
    if( $full_fname === false ) {
      $this->errMsg("Failed to find $fname");
    }

    $real_fname = realpath($full_fname);
    if( in_array($real_fname,$this->already_included) ) {
      return;
    }
    $this->already_included[] = $real_fname;

    $fname = $full_fname;
    $F = fopen($fname,"r");
    if( !$F ) {
      $this->errMsg("Failed to open $fname");
    }
    $this->pushfilestack();

    $this->fname = $fname;
    $this->F = $F;
    $this->nextChar();
  }
  function pushfilestack() {
    if( $this->F ) {
      $this->filestack[] = array($this->linenum,$this->fname,$this->F,$this->curtoken,$this->ch,$this->at_newline);
    }
    $this->linenum = 0;
    $this->fname = null;
    $this->F = null;
    $this->curtoken = null;
    $this->ch = null;
    $this->at_newline = true;
  }
  function popfilestack() {
    if( count($this->filestack) == 0 ) return false;

    $state_idx = count($this->filestack)-1;
    $state = $this->filestack[$state_idx];
    array_splice($this->filestack,$state_idx,1);

    fclose($this->F);

    $this->linenum = $state[0];
    $this->fname = $state[1];
    $this->F = $state[2];
    $this->curtoken = $state[3];
    $this->ch = $state[4];
    $this->at_newline = $state[5];
    return true;
  }
  function nextChar() {
    if( $this->ch == "\n" || $this->ch === null ) {
      $this->linenum += 1;
      $this->at_newline = true;
    } else {
      $this->at_newline = false;
    }
    $this->ch = fread($this->F,1);
  }
  function eof() {
    return feof($this->F);
  }
  function nextToken() {
    if( $this->at_newline && ($this->ch == " " || $this->ch == "\t") ) {
      $this->nextChar();
      $this->curtoken = new Token(INDENT_TOK," ");
      return $this->curtoken;
    }

    while( $this->ch == " " || $this->ch == "\t" ) $this->nextChar();

    if( preg_match("/^[[:alpha:]_*]/",$this->ch) ) {
      $value = "";
      $type = NAME_TOK;
      while( preg_match("/^[[:alnum:]_*]/",$this->ch) ) {
        $value .= $this->ch;
	if( $this->ch == "*" ) $type = GLOB_TOK;
	$this->nextChar();
      }
      $this->curtoken = new Token($type,$value);
      return $this->curtoken;
    }

    if( preg_match("/^[[:digit:]-]/",$this->ch) ) {
      $base = $this->ch;
      $value = $this->ch;
      $this->nextChar();
      if( $value == "-" ) {
        if( preg_match("/^[[:digit:]]/",$this->ch) ) {
          $base = $this->ch;
          $value .= $this->ch;
          $this->nextChar();
        }
      }
      if( $base == "0" && $this->ch == "x" ) { # hexidecimal
        $value .= $this->ch;
	$this->nextChar();
        while( preg_match("/^[[:digit:]a-fA-F]/",$this->ch) ) {
          $value .= $this->ch;
          $this->nextChar();
        }
      } else if( $base == "0" && $this->ch == "b" ) { # binary
        $value .= $this->ch;
	$this->nextChar();
        while( preg_match("/^[01]/",$this->ch) ) {
          $value .= $this->ch;
          $this->nextChar();
        }
      } else if( $base == "0" && preg_match("/^[0-7]/",$this->ch) ) { # base 8
        while( preg_match("/^[0-7]/",$this->ch) ) {
          $value .= $this->ch;
          $this->nextChar();
        }
      } else { # base 10
        while( preg_match("/^[[:digit:].]/",$this->ch) ) {
          $value .= $this->ch;
          $this->nextChar();
        }
        if( $this->ch == "e" || $this->ch == "E" ) {
          $value .= $this->ch;
          $this->nextChar();
          if( $this->ch == "-" || $this->ch == "+" ) {
            $value .= $this->ch;
            $this->nextChar();
          }
          while( preg_match("/^[[:digit:]]/",$this->ch) ) {
            $value .= $this->ch;
            $this->nextChar();
          }
        }
      }
      $this->curtoken = new Token(NUM_TOK,$value);
      return $this->curtoken;
    }

    if( $this->ch == ":" ) {
      $this->nextChar();
      $this->curtoken = new Token(COLON_TOK,":");
      return $this->curtoken;
    }

    if( $this->ch == "@" ) {
      $this->nextChar();
      $this->curtoken = new Token(AT_TOK,"@");
      return $this->curtoken;
    }

    if( $this->ch == "." ) {
      $this->nextChar();
      $this->curtoken = new Token(DOT_TOK,".");
      return $this->curtoken;
    }

    if( $this->ch == "," ) {
      $this->nextChar();
      $this->curtoken = new Token(COMMA_TOK,",");
      return $this->curtoken;
    }

    if( $this->ch == "[" ) {
      $this->nextChar();
      $this->curtoken = new Token(LBRACKET_TOK,"[");
      return $this->curtoken;
    }

    if( $this->ch == "]" ) {
      $this->nextChar();
      $this->curtoken = new Token(RBRACKET_TOK,"]");
      return $this->curtoken;
    }

    if( $this->ch == '<' ) {
      $this->nextChar();
      if( $this->ch != '<' ) {
        $this->errMsg("expected <<");
      }
      $this->nextChar();
      $value = "";
      $macro_type = MACRO_TOK;
      while( $this->ch == " " ) $this->nextChar();
      while( preg_match("/^[[:alnum:]_*]/",$this->ch) ) {
        $value .= $this->ch;
	$this->nextChar();
      }
      if( $this->ch == '(' ) {
        $this->nextChar();
	if( $this->ch != ')' ) {
	  $this->errMsg("Expected () after macro " . $value);
	}
	$this->nextChar();
	$macro_type = MACRO_FUNC_TOK;
      }
      while( $this->ch == " " ) $this->nextChar();
      if( $this->ch != '>' ) {
        $this->errMsg("expected <<");
      }
      $this->nextChar();
      if( $this->ch != '>' ) {
        $this->errMsg("expected <<");
      }
      $this->nextChar();
      $this->curtoken = new Token($macro_type,$value);
      return $this->curtoken;
    }

    if( $this->ch == '"' || $this->ch == "'" ) {
      $begin_line = $this->linenum;
      $quote = $this->ch;
      $this->nextChar();
      $str = "";
      while( $this->ch != $quote ) {
        if( $this->ch == "\\" ) {
	  $str .= "\\";
	  $this->nextChar();
	}
        $str .= $this->ch;
	$this->nextChar();
        if( $this->eof() ) {
          $this->errMsg("end of file reached inside string",$begin_line);
        }
      }
      $this->nextChar();
      $this->curtoken = new Token(STRING_TOK,$quote . $str . $quote);
      return $this->curtoken;
    }

    if( $this->ch == '{' ) {
      $begin_line = $this->linenum;
      $level = 0;
      $code = "{";
      $this->nextChar();
      while( $this->ch != '}' || $level > 0 ) {
        if( $this->eof() ) {
          $this->errMsg("end of file reached inside code block",$begin_line);
        }
        if( $this->ch == '{' ) $level += 1;
        if( $this->ch == '}' ) $level -= 1;
        $code .= $this->ch;
        $this->nextChar();
      }
      $code .= "}";
      $this->nextChar();
      $this->curtoken = new Token(CODE_TOK,$code);
      return $this->curtoken;
    }

    if( $this->ch == '(' ) {
      $begin_line = $this->linenum;
      $level = 0;
      $code = "(";
      $this->nextChar();
      while( $this->ch != ')' || $level > 0 ) {
        if( $this->eof() ) {
          $this->errMsg("end of file reached inside ()'s",$begin_line);
        }
        if( $this->ch == '(' ) $level += 1;
        if( $this->ch == ')' ) $level -= 1;
        $code .= $this->ch;
        $this->nextChar();
      }
      $code .= ")";
      $this->nextChar();
      $this->curtoken = new Token(ARGS_TOK,$code);
      return $this->curtoken;
    }

    if( $this->ch == "\n" ) {
      $this->nextChar();
      $this->curtoken = new Token(NEWLINE_TOK,"\n");
      return $this->curtoken;
    }

    if( $this->ch == "#" ) {
      while( $this->ch != "\n" && !$this->eof() ) {
        $this->nextChar();
      }
      return $this->nextToken();
    }

    if( $this->ch == "/" ) {
      $this->nextChar();
      if( $this->ch == "/" ) {
        while( $this->ch != "\n" && !$this->eof() ) {
          $this->nextChar();
        }
        return $this->nextToken();
      }
      else if( $this->ch == "*" ) {
        $begin_line = $this->linenum;
        $this->nextChar();
        $level = 1;
        while( !$this->eof() ) {
          if( $this->ch == "*" ) {
            $this->nextChar();
            if( $this->ch == "/" ) {
              $this->nextChar();
              if( --$level == 0 ) break;
            }
          }
          else if( $this->ch == "/" ) {
            $this->nextChar();
            if( $this->ch == "*" ) {
              $this->nextChar();
              $level += 1;
            }
          }
          else {
            $this->nextChar();
          }
        }
        if( $level > 0 ) {
          $this->errMsg("end of file reached inside /* comment",$begin_line);
        }
        return $this->nextToken();
      }
      else {
        $this->errMsg("unexpected character /");
      }
    }

    if( $this->eof() ) {
      if( $this->popfilestack() ) {
        return $this->nextToken();
      }
      $this->curtoken = new Token(EOF_TOK,"");
      return $this->curtoken;
    }

    $this->errMsg("unexpected character");
  }
  function errMsg($msg,$line_num=null,$fname=null) {
    if( $line_num === null ) $line_num = $this->linenum;
    if( $fname === null ) $fname = $this->fname;
    echo "{$fname}:{$line_num}: $msg\n";
    exit(1);
  }
}

abstract class Expr {
  abstract function getPHP($compiler,$objectdef,$default_table,$tables);
  function getPHPFuncBody($compiler,$objectdef,$default_table,$tables) {
    $php = $this->getPHP($compiler,$objectdef,$default_table,$tables);
    $nl = strstr($php,"\n")===FALSE ? " " : "\n    ";
    $nllast = strstr($php,"\n")===FALSE ? " " : "\n  ";
    return "{{$nl}return " . $php . ";$nllast}";
  }
  function getSrcLine() {
    return $this->line;
  }
  function getSrcFile() {
    return $this->fname;
  }
}

class LiteralExpr extends Expr {
  private $value;
  private $line;
  private $fname;
  function __construct($value,$line,$fname) {
    $this->value = $value;
    $this->line = $line;
    $this->fname = $fname;
  }
  function getPHP($compiler,$objectdef,$default_table,$tables) {
    return $this->value;
  }
}

class CodeExpr extends Expr {
  private $value;
  function __construct($value,$line,$fname) {
    $this->value = $value;
    $this->line = $line;
    $this->fname = $fname;
  }
  function getPHP($compiler,$objectdef,$default_table,$tables) {
    $code = "";
    $len = strlen($this->value);
    for($i=0; $i<$len; ) {
      $p1 = strpos($this->value,"<<",$i);
      if( $p1 === false ) {
        $code .= substr($this->value,$i);
        break;
      }
      $p2 = strpos($this->value,">>",$p1);
      if( $p2 === false ) {
        $code .= substr($this->value,$i);
        break;
      }
      $macro = trim(substr($this->value,$p1+2,$p2-$p1-2));
      if( substr_compare($macro,"()",-2)==0 ) {
        $expr = new MacroFuncExpr(substr($macro,0,strlen($macro)-2),$this->line,$this->fname);
      } else {
        $expr = new MacroExpr($macro,$this->line,$this->fname);
      }
      $code .= substr($this->value,$i,$p1-$i);
      $code .= $expr->getPHP($compiler,$objectdef,$default_table,$tables);
      $i = $p2+2;
    }
    return $code;
  }
  function getPHPFuncBody($compiler,$objectdef,$default_table,$tables) {
    return $this->getPHP($compiler,$objectdef,$default_table,$tables);
  }
}

class ArrayExpr extends Expr {
  private $values;
  function __construct($values,$line,$fname) {
    $this->values = $values;
    $this->line = $line;
    $this->fname = $fname;
  }
  function getPHP($compiler,$objectdef,$default_table,$tables) {
    $php = "";
    $nl = count($this->values) > 1 ? "\n      " : "";
    $nllast = count($this->values) > 1 ? "\n    " : "";
    foreach( $this->values as $value ) {
      if( $php ) $php .= ",$nl";
      $php .= $value->getPHP($compiler,$objectdef,$default_table,$tables);
    }
    return "array($nl" . $php . "$nllast)";
  }
}

class MacroExpr extends Expr {
  private $macro;
  function __construct($macro,$line,$fname) {
    $this->macro = $macro;
    $this->line = $line;
    $this->fname = $fname;
  }
  function getPHP($compiler,$objectdef,$default_table,$tables) {
    if( $this->macro == "name" ) {
      $expr = new LiteralExpr("'{$objectdef->name}'",$this->line,$this->fname);
      return $expr->getPHP($compiler,$objectdef,$default_table,$tables);
    }
    if( $this->macro == "table" ) {
      $expr = new ClassExpr($default_table,null,$this->line,$this->fname);
      return $expr->getPHP($compiler,$objectdef,$default_table,$tables);
    }
    if( $this->macro == "columns" ) {
      $columns = array();
      if( $objectdef->columns ) foreach( $objectdef->columns as $col ) {
        $columns[] = $col;
      }
      $expr = new ArrayExpr($columns,$this->line,$this->fname);
      return $expr->getPHP($compiler,$objectdef,$default_table,$tables);
    }
    $compiler->errMsg("Undefined macro '" . $this->macro . "'",$this->line,$this->fname);
  }
}

class MacroFuncExpr extends Expr {
  private $macro;
  function __construct($macro,$line,$fname) {
    $this->macro = $macro;
    $this->line = $line;
    $this->fname = $fname;
  }
  function getPHP($compiler,$objectdef,$default_table,$tables) {
    if( !$compiler->finalCodeGenPhase() ) {
      return "static::" . $this->macro . "()";
    } else {
      # eval the function
      $classname = $objectdef->classname;
      $funcname = $this->macro;
      $val = $classname::$funcname();
      $val = getPHPCode($val);
      return $val;
    }
  }
}

class ClassExpr extends Expr {
  private $lhs;
  private $rhs;
  function __construct($lhs,$rhs,$line,$fname) {
    $this->lhs = $lhs;
    $this->rhs = $rhs;
    $this->line = $line;
    $this->fname = $fname;
  }
  function getPHP($compiler,$objectdef,$default_table,$tables) {
    if( !$this->rhs ) {
      foreach( $tables as $table => $tabledef ) {
        if( $table == $this->lhs ) {
          return $tabledef->classname . "::class";
        }
      }
      if( $default_table ) {
        return $default_table . "_" . $this->lhs . "::class";
      }
      return $this->lhs . "::class";
    }
    return $this->lhs . "_" . $this->rhs . "::class";
  }
}

class ObjectDef {
  public $name;
  public $type; # "table" or "view" or "column"
  public $classname; # php classname (e.g. tablename_colname)
  public $attrs;
  public $columns;
  public $src_linenum;
  public $src_fname;
}

class Attribute {
  public $name;
  public $args;
  public $value;
  public $eval = false;
  public $at_undefined = false;
}

abstract class DBViewParser {
  protected $lex = null;
  function __construct($lex) {
    $this->lex = $lex;
  }

  abstract function genColumn($table,$attrname,$attrs);
  abstract function genTable($table,$tabledef);
  abstract function genPHP($code);
  abstract function genIncludePHP($fname);
  abstract function finalCodeGenPhase();

  function errMsg($str,$line=null,$fname=null) {
    $this->lex->errMsg($str,$line,$fname);
  }

  function strTokToStr($str) {
    $result = "";

    for($i=1; $i<strlen($str)-1; $i++) {
      $ch = substr($str,$i,1);
      if( $ch == "\\" ) continue;
      $result .= $ch;
    }
    return $result;
  }

  function parse() {
    $lex = $this->lex;
    $token = $lex->nextToken();
    while( $token->type != EOF_TOK ) {
      if( $token->type == NEWLINE_TOK ) {
        $token = $lex->nextToken();
        continue;
      }
      if( $token->type == INDENT_TOK ) {
        $token = $lex->nextToken();
        if( $token->type == NEWLINE_TOK ) {
          $token = $lex->nextToken();
          continue;
        }
        if( $token->type == EOF_TOK ) continue;
        $this->errMsg("expecting object definition");
      }
      $def_src_linenum = $this->lex->linenum;
      $def_src_fname = $this->lex->fname;
      $gen_code = true;
      if( $token->type == AT_TOK ) {
        $at_compiletime = false;
        $at_runtime = false;
        $token = $lex->nextToken();
        if( $token->type == NAME_TOK && $token->value == "compiletime" ) {
          $token = $lex->nextToken();
          $at_compiletime = true;
        }
        else if( $token->type == NAME_TOK && $token->value == "runtime" ) {
          $token = $lex->nextToken();
          $at_runtime = true;
        }
        else {
          $this->errMsg("expecting compiletime or runtime after @");
        }
        $gen_code = (!$at_compiletime && !$at_runtime) ||
                    ($at_compiletime && !$this->finalCodeGenPhase()) ||
                    ($at_runtime && $this->finalCodeGenPhase());
      }

      if( $token->type != NAME_TOK ) {
        $this->errMsg("expecting definition");
      }
      if( $token->value == "php" ) {
        $token = $lex->nextToken();
        if( $token->type != CODE_TOK ) {
          $this->errMsg("expecting php code");
        }
        if( $gen_code ) {
          $this->genPHP($token->value);
        }
        $token = $lex->nextToken();
        continue;
      }
      if( $token->value == "require_php" ) {
        $token = $lex->nextToken();
        if( $token->type != STRING_TOK ) {
          $this->errMsg("expecting filename as string");
        }
        $fname = $this->strTokToStr($token->value);
        $token = $lex->nextToken();
        if( $token->type != NEWLINE_TOK ) {
          $this->errMsg("expecting new line");
        }
        if( $gen_code ) {
          $this->genIncludePHP($fname);
        }
        $token = $lex->nextToken();
        continue;
      }
      if( $token->value == "require" ) {
        $token = $lex->nextToken();
        if( $token->type != STRING_TOK ) {
          $this->errMsg("expecting filename as string");
        }
        $fname = $this->strTokToStr($token->value);
        $token = $lex->nextToken();
        if( $token->type != NEWLINE_TOK ) {
          $this->errMsg("expecting new line");
        }
        if( $gen_code ) {
          $lex->include($fname);
        }
        $token = $lex->nextToken();
        continue;
      }
      $def_type = "column";
      if( $token->value == "table" || $token->value == "view" || $token->value == "column" ) {
        $def_type = $token->value;
        $token = $lex->nextToken();
      }
      if( $token->type != NAME_TOK && $token->type != GLOB_TOK ) {
        $this->errMsg("expecting name of object to define");
      }
      $tablename = $token->value;
      $colname = "";
      $token = $lex->nextToken();
      if( $def_type == "column" ) {
        if( $token->type != DOT_TOK ) {
          $this->errMsg("malformed object definition '$tablename'");
        }
        $token = $lex->nextToken();
        if( $token->type != NAME_TOK && $token->type != GLOB_TOK ) {
          $this->errMsg("expected column name after $tablename");
        }
        $colname = $token->value;
        $token = $lex->nextToken();
      }
      if( $token->type != NEWLINE_TOK ) {
        $this->errMsg("expected newline after object definition");
      }
      $token = $lex->nextToken();
      $attrs = array();
      while( $token->type == INDENT_TOK || $token->type == NEWLINE_TOK ) {
        if( $token->type == NEWLINE_TOK ) {
          $token = $lex->nextToken();
          continue;
        }
        $token = $lex->nextToken();
        if( $token->type == NEWLINE_TOK ) {
          $token = $lex->nextToken();
          continue;
        }
        if( $token->type == EOF_TOK ) {
          continue;
        }
        $gen_attr = true;
        $at_undefined = false;
        while( $token->type == AT_TOK ) {
          $at_compiletime = false;
          $at_runtime = false;
          $token = $lex->nextToken();
          if( $token->type == NAME_TOK && $token->value == "compiletime" ) {
            $token = $lex->nextToken();
            $at_compiletime = true;
          }
          else if( $token->type == NAME_TOK && $token->value == "runtime" ) {
            $token = $lex->nextToken();
            $at_runtime = true;
          }
          else if( $token->type == NAME_TOK && $token->value == "undefined" ) {
            $token = $lex->nextToken();
            $at_undefined = true;
          }
          else {
            $this->errMsg("expecting compiletime or runtime after @");
          }
          $gen_attr = (!$at_compiletime && !$at_runtime) ||
                      ($at_compiletime && !$this->finalCodeGenPhase()) ||
                      ($at_runtime && $this->finalCodeGenPhase());
        }

        if( $token->type != NAME_TOK ) {
          $this->errMsg("expecting attribute name");
        }
        $attr = new Attribute;
        $attr->name = $token->value;
        $attr->at_undefined = $at_undefined;
        $token = $lex->nextToken();
        if( $attr->name == "join" ) {
          if( $token->type != NAME_TOK ) {
            $this->errMsg("expecting table name after join");
          }
          $attr->name .= "_" . $token->value;
          $token = $lex->nextToken();
        }
        if( $token->type == ARGS_TOK ) {
          $attr->args = $token->value;
        }
        else if( $token->type != COLON_TOK ) {
          $this->errMsg("expecting colon or ()'s after attribute name " . $attr->name);
        }
        $token = $lex->nextToken();
        if( $token->type == NAME_TOK && $token->value == "eval" ) {
          $attr->eval = true;
          $token = $lex->nextToken();
        }
        $attr->value = $this->parseExpr();
        if( $gen_attr ) {
          $attrs[$attr->name] = $attr;
        }
        $token = $lex->curtoken;
        if( $token->type == NEWLINE_TOK ) {
          $token = $lex->nextToken();
        } else if( $token->type != EOF_TOK ) {
          $this->errMsg("expecting newline");
        }
      }

      $objdef = new ObjectDef;
      $objdef->type = $def_type;
      $objdef->src_fname = $def_src_fname;
      $objdef->src_linenum = $def_src_linenum;
      $objdef->attrs = $attrs;
      $objdef->columns = array();

      if( $def_type == "table" || $def_type == "view" ) {
        $objdef->name = $tablename;
        $prefix = $objdef->type . "_";
        if( strncmp($objdef->name,$prefix,strlen($prefix))==0 ) {
          # Do not prepend the prefix if it is already in the object name.
          # This allows a view to be called view_tablename to avoid a
          # name conflict with tablename, without the PHP class ending up
          # with the ugly name view_view_tablename.
          $prefix = "";
        }
        $objdef->classname = $prefix . $tablename;

        if( $objdef->name == "table" || $objdef->name == "view" ) {
          $this->errMsg("Cannot name a {$objdef->type} the reserved word '{$objdef->name}'",$objdef->src_linenum,$objdef->src_fname);
        }

        if( $gen_code ) {
          $this->genTable($tablename,$objdef);
        }
      } else {
        $objdef->name = $colname;
        $objdef->classname = $tablename . "_" . $colname;

        if( $gen_code ) {
          $this->genColumn($tablename,$colname,$objdef);
        }
      }
    }
  }
  function parseExpr() {
    $token = $this->lex->curtoken;
    if( $token->type == STRING_TOK || $token->type == NUM_TOK ) {
      $expr = new LiteralExpr($token->value,$this->lex->linenum,$this->lex->fname);
      $this->lex->nextToken();
      return $expr;
    }
    if( $token->type == CODE_TOK ) {
      $expr = new CodeExpr($token->value,$this->lex->linenum,$this->lex->fname);
      $this->lex->nextToken();
      return $expr;
    }
    if( $token->type == NAME_TOK ) {
      $rhs = null;
      $lhs = $token->value;
      $token = $this->lex->nextToken();
      if( $token->type == DOT_TOK ) {
        $token = $this->lex->nextToken();
        if( $token->type != NAME_TOK ) {
          $this->lex->errMsg("Expecting name after dot");
        }
        $rhs = $token->value;
        $this->lex->nextToken();
      }
      return new ClassExpr($lhs,$rhs,$this->lex->linenum,$this->lex->fname);
    }
    if( $token->type == MACRO_TOK ) {
      $expr = new MacroExpr($token->value,$this->lex->linenum,$this->lex->fname);
      $this->lex->nextToken();
      return $expr;
    }
    if( $token->type == MACRO_FUNC_TOK ) {
      $expr = new MacroFuncExpr($token->value,$this->lex->linenum,$this->lex->fname);
      $this->lex->nextToken();
      return $expr;
    }
    if( $token->type == LBRACKET_TOK ) {
      $token = $this->lex->nextToken();
      $values = array();
      while( $token->type != RBRACKET_TOK ) {
        while( $token->type == COMMA_TOK || $token->type == NEWLINE_TOK || $token->type == INDENT_TOK ) {
          $token = $this->lex->nextToken();
        }
        if( $token->type == RBRACKET_TOK ) break;
        $value = $this->parseExpr();
        $values[] = $value;
        $token = $this->lex->curtoken;
        if( $token->type != COMMA_TOK ) break;
        $token = $this->lex->nextToken();
      }
      while( $token->type == NEWLINE_TOK || $token->type == INDENT_TOK ) {
        $token = $this->lex->nextToken();
      }
      if( $token->type != RBRACKET_TOK ) {
        $this->lex->errMsg("expecting ]");
      }
      $this->lex->nextToken();
      return new ArrayExpr($values,$this->lex->linenum,$this->lex->fname);
    }
    $this->lex->errMsg("expecting a value");
  }
}

class DBViewGen extends DBViewParser  {
  private $outfname;
  private $OUT = null;
  private $defaults;
  private $pass;
  private $tables;
  private $undefined_attrs;

  function __construct($lex,$outfname) {
    parent::__construct($lex);
    $this->undefined_attrs = array();
    $this->clear();
    $this->outfname = $outfname;
  }

  function clear() {
    $this->defaults = array();
    $this->tables = array();
  }

  function parseAndGen() {
    $pass1_fname = $this->outfname . "1";
    $this->OUT = fopen($pass1_fname,"w");
    $header = "<?php\n\n# This file was automatically generated by dbview from {$this->lex->fname}.\n\n";
    fwrite($this->OUT,$header);
    $this->pass = 1;
    $this->parse();
    $this->genFinal();
    fclose($this->OUT);

    # load the code that we just generated, so it can be called in pass 2 for code blocks flagged with "eval"
    require $pass1_fname;

    $this->checkUndefinedAttrs();

    $pass2_fname = $this->outfname . "2";
    $this->OUT = fopen($pass2_fname,"w");
    fwrite($this->OUT,$header);
    $this->lex->rewind();
    $this->clear();
    $this->pass = 2;
    $this->parse();
    $this->genFinal();
    fclose($this->OUT);

    rename($pass2_fname,$this->outfname);
    unlink($pass1_fname);
  }

  function checkUndefinedAttrs() {
    foreach( $this->undefined_attrs as $u ) {
      $attr = $u[0];
      $objdef = $u[1];

      $attrname = $attr->name;
      $classname = $objdef->classname;
      $errmsg = $classname::$attrname();

      if( !is_string($errmsg) || !$errmsg ) {
        $errmsg = "required attriute '$attrname' is undefined";
      }

      $this->errMsg($errmsg,$objdef->src_linenum,$objdef->src_fname);
    }
  }

  # this function is called by DBViewParser when it has parsed a table definition
  function genTable($table,$tabledef) {
    if( strchr($table,"*") !== false ) {
      $this->defaults[] = array($tabledef->type,$table,$tabledef->attrs);
      return;
    }
    if( array_key_exists($table,$this->tables) ) {
      $conflict = $this->tables[$table];
      $suggestion = "";
      if( ($tabledef->type == "view" || $conflict->type == "view") && $tabledef->type != $conflict->type ) {
        $suggestion = "; the view could be named view_{$table} to avoid the conflict";
      }
      $this->errMsg("declaration of {$tabledef->type} $table conflicts with earlier declaration of a {$conflict->type} by the same name{$suggestion}",$tabledef->src_linenum,$tabledef->src_fname);
    }
    $this->tables[$table] = $tabledef;
  }

  # this is called at the end of parsing, once the contents of the table are known
  function finalGenTable($table,$tabledef) {
    $classname = $tabledef->classname;

    fwrite($this->OUT,"class {$classname} {\n");
    $default_args = $this->genDefaultAttrs($tabledef,$table,"",$tabledef->attrs,$this->defaults);
    foreach( $tabledef->attrs as $attrname => $attr ) {
      $this_default_args = array_key_exists($attrname,$default_args) ? $default_args[$attrname] : "";
      $this->genAttr($attr,$this_default_args,$tabledef,null);
    }
    fwrite($this->OUT,"}\n");
  }

  # this is called at the end of parsing
  function genFinal() {
    foreach( $this->tables as $table => $tabledef ) {
      $this->finalGenTable($table,$tabledef);
    }
  }

  function registerColumn($tablename,$colname,$class_name) {
    foreach( $this->tables as $table => $tabledef ) {
      if( $table == $tablename ) {
        $tabledef->columns[$class_name] = new ClassExpr($tablename,$colname,$this->lex->linenum,$this->lex->fname);
      }
    }
  }

  function globmatch($pattern,$subject) {
    $pattern = str_replace("*",".*",$pattern);
    $pattern = "/^" . $pattern . "$/";
    return preg_match($pattern,$subject);
  }

  function classglobmatch($pattern,$classname) {
    $pattern_parts = explode(".",$pattern);
    $class_parts = explode(".",$classname);
    for($i=0;$i<count($pattern_parts);$i++) {
      $p = $pattern_parts[$i];
      if( $i >= count($class_parts) ) return false;
      $c = $class_parts[$i];
      if( !$this->globmatch($p,$c) ) return false;
    }
    return true;
  }

  function genDefaultAttrs($objectdef,$table,$colname,&$attrs,&$default_attrs) {
    $default_args = array();
    $attrs_to_emit = array();
    $classname = $table;
    if( $colname ) $classname .= "." . $colname;

    # go through from first to last to determine the default args
    for($i=0;$i<count($default_attrs);$i++) {
      $this_type = $default_attrs[$i][0];
      $pattern = $default_attrs[$i][1];
      $defaults = $default_attrs[$i][2];

      if( $this_type != $objectdef->type ) continue;
      if( !$this->classglobmatch($pattern,$classname) ) {
        continue;
      }

      foreach( $defaults as $attrname => $attr ) {
        # latter definitions will overwrite earlier ones
        $attrs_to_emit[$attrname] = $attr;
        if( $attr->args ) $default_args[$attrname] = $attr->args;
      }
    }
    foreach( $attrs_to_emit as $attrname => $attr ) {
      if( array_key_exists($attrname,$attrs) ) continue;
      $this_default_args = array_key_exists($attrname,$default_args) ? $default_args[$attrname] : "";
      $this->genAttr($attr,$this_default_args,$objectdef,$table);
    }
    return $default_args;
  }

  function genPHP($code) {
    if( substr($code,0,1) == "{" && substr($code,strlen($code)-1) == "}" ) {
      $code = substr($code,1,strlen($code)-2);
    }
    fwrite($this->OUT,"\n");
    fwrite($this->OUT,$code);
    fwrite($this->OUT,"\n");
  }

  function finalCodeGenPhase() {
    return $this->pass > 1;
  }

  function genIncludePHP($fname) {
    $in_compiler_dir = __DIR__ . DIRECTORY_SEPARATOR . $fname;
    if( file_exists($in_compiler_dir) ) {
      $this->genPHP("require_once '" . $in_compiler_dir . "';");
    }
    else {
      $this->genPHP("require_once '" . $fname . "';");
    }
  }

  # this function is called by DBViewParser when it has parsed a column definition
  function genColumn($table,$colname,$objdef) {
    if( strchr($table,"*") !== false || strchr($colname,"*") !== false ) {
      $this->defaults[] = array("column",$table . "." . $colname,$objdef->attrs);
      return;
    }

    if( !array_key_exists($table,$this->tables) ) {
      $this->errMsg("No table named '$table' has been defined.",$objdef->src_linenum,$objdef->src_fname);
    }

    $this->registerColumn($table,$colname,$objdef->classname);

    fwrite($this->OUT,"class {$objdef->classname} {\n");

    $default_args = $this->genDefaultAttrs($objdef,$table,$colname,$objdef->attrs,$this->defaults);
    fwrite($this->OUT,"\n");
    foreach( $objdef->attrs as $attrname => $attr ) {
      $this_default_args = array_key_exists($attrname,$default_args) ? $default_args[$attrname] : "";
      $this->genAttr($attr,$this_default_args,$objdef,$table);
    }
    fwrite($this->OUT,"}\n");
  }

  function genAttr($attr,$default_args,$objectdef,$default_table) {
    if( $attr->at_undefined ) {
      $this->undefined_attrs[] = array($attr,$objectdef);
    }

    fwrite($this->OUT,"  static function {$attr->name}");
    if( $attr->args ) {
      fwrite($this->OUT,$attr->args . " ");
    }
    else if( $default_args ) {
      fwrite($this->OUT,$default_args . " ");
    }
    else {
      fwrite($this->OUT,"() ");
    }

    $value = $attr->value;
    if( $this->finalCodeGenPhase() && $attr->eval ) {
      try {
        $attrname = $attr->name;
        $classname = $objectdef->classname;
        $val = $classname::$attrname();
        $val = getPHPCode($val);
        $value = new LiteralExpr($val,$attr->value->getSrcLine(),$attr->value->getSrcFile());
      } catch( EvalAtRuntime $e ) {
        # skip evaluation at compile-time
      }
    }

    fwrite($this->OUT,$value->getPHPFuncBody($this,$objectdef,$default_table,$this->tables));
    fwrite($this->OUT,"\n");
  }
}

function getPHPCode($val) {
  if( is_array($val) ) {
    $preserve_keys = false;
    $i=0;
    foreach( $val as $key => $v ) {
      if( $key != $i++ ) {
        $preserve_keys = true;
        break;
      }
    }
    if( !$preserve_keys ) {
      $result = "";
      foreach( $val as $v ) {
        if( $result ) $result .= ",\n  ";
        $result .= getPHPCode($v);
      }
      $nl = strlen($result)>1 ? "\n  " : "";
      $nllast = strlen($result)>1 ? "\n" : "";
      $result = "array($nl" . $result . "$nllast)";
      return $result;
    }
  }
  return var_export($val,true);
}

function DBViewCompile($sourcefname,$outfname) {
  $lex = new Lex();
  $lex->include($sourcefname);
  $parser = new DBViewGen($lex,$outfname);
  $parser->parseAndGen();
}
