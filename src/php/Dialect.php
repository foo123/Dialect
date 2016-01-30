<?php
/**
*   Dialect, 
*   a simple and flexible Cross-Platform SQL Builder for PHP, Python, Node/XPCOM/JS, ActionScript
* 
*   @version: 0.5.3
*   https://github.com/foo123/Dialect
*
*   Abstract the construction of SQL queries
*   Support multiple DB vendors
*   Intuitive and Flexible API
**/
if ( !class_exists('Dialect') )
{
class DialectTpl
{    
    public static function multisplit($tpl, $reps, $as_array=false)
    {
        $a = array( array(1, $tpl) );
        foreach ((array)$reps as $r=>$s)
        {
            $c = array( ); 
            $sr = $as_array ? $s : $r;
            $s = array(0, $s);
            foreach ($a as $ai)
            {
                if (1 === $ai[ 0 ])
                {
                    $b = explode($sr, $ai[ 1 ]);
                    $bl = count($b);
                    $c[] = array(1, $b[0]);
                    if ($bl > 1)
                    {
                        for ($j=0; $j<$bl-1; $j++)
                        {
                            $c[] = $s;
                            $c[] = array(1, $b[$j+1]);
                        }
                    }
                }        
                else
                {
                    $c[] = $ai;
                }
            }
            $a = $c;
        }
        return $a;
    }

    public static function multisplit_re( $tpl, $re ) 
    {
        $a = array(); 
        $i = 0; 
        while ( preg_match($re, $tpl, $m, PREG_OFFSET_CAPTURE, $i) ) 
        {
            $a[] = array(1, substr($tpl, $i, $m[0][1]-$i));
            $a[] = array(0, isset($m[1]) ? $m[1][0] : $m[0][0]);
            $i = $m[0][1] + strlen($m[0][0]);
        }
        $a[] = array(1, substr($tpl, $i));
        return $a;
    }
    
    public static function arg($key=null, $argslen=null)
    {
        $out = '$args';
        
        if ($key)
        {
            if (is_string($key))
                $key = !empty($key) ? explode('.', $key) : array();
            else 
                $key = array($key);
            $givenArgsLen = (bool)(null !=$argslen && is_string($argslen));
            
            foreach ($key as $k)
            {
                $kn = is_string($k) ? intval($k,10) : $k;
                if (!is_nan($kn))
                {
                    if ($kn < 0) $k = ($givenArgsLen ? $argslen : 'count('.$out.')') . ('-'.(-$kn));
                    
                    $out .= '[' . $k . ']';
                }
                else
                {
                    $out .= '["' . $k . '"]';
                }
            }
        }        
        return $out;
    }

    public static function compile($tpl, $raw=false)
    {
        static $NEWLINE = '/\\n\\r|\\r\\n|\\n|\\r/'; 
        static $SQUOTE = "/'/";
        
        if (true === $raw)
        {
            $out = 'return (';
            foreach ($tpl as $tpli)
            {
                $notIsSub = $tpli[ 0 ];
                $s = $tpli[ 1 ];
                $out .= $notIsSub ? $s : self::arg($s);
            }
            $out .= ');';
        }    
        else
        {
            $out = '$argslen=count($args); return (';
            foreach ($tpl as $tpli)
            {
                $notIsSub = $tpli[ 0 ];
                $s = $tpli[ 1 ];
                if ($notIsSub) $out .= "'" . preg_replace($NEWLINE, "' + \"\\n\" + '", preg_replace($SQUOTE, "\\'", $s)) . "'";
                else $out .= " . strval(" . self::arg($s,'$argslen') . ") . ";
            }
            $out .= ');';
        }
        return create_function('$args', $out);
    }

    
    public static $defaultArgs = '/\\$(-?[0-9]+)/';
    
    public $id = null;
    public $tpl = null;
    private $_renderer = null;
    
    public function __construct($tpl='', $replacements=null, $compiled=false)
    {
        $this->id = null;
        $this->_renderer = null;
        if ( !$replacements || empty($replacements) ) $replacements = self::$defaultArgs;
        $this->tpl = is_string($replacements)
                ? self::multisplit_re( $tpl, $replacements)
                : self::multisplit( $tpl, (array)$replacements);
        if (true === $compiled) $this->_renderer = self::compile( $this->tpl );
    }

    public function __destruct()
    {
        $this->dispose();
    }
    
    public function dispose()
    {
        $this->id = null;
        $this->tpl = null;
        $this->_renderer = null;
        return $this;
    }
    
    public function render($args=null)
    {
        if (!$args) $args = array();
        
        if ($this->_renderer) 
        {
            $f = $this->_renderer;
            return $f( $args );
        }
        
        $out = ''; $argslen = count($args);
        foreach($this->tpl as $t)
        {
            if ( 1 === $t[ 0 ] )
            {
                $out .= $t[ 1 ];
            }
            else
            {
                $s = $t[ 1 ];
                if ( is_int($s) && 0 > $s ) $s += $argslen;
                $out .= $args[ $s ];
            }
        }
        return $out;
    }
}    
class DialectGrammTpl
{    
    public static function multisplit( $tpl, $delims )
    {
        $IDL = $delims[0]; $IDR = $delims[1]; $OBL = $delims[2]; $OBR = $delims[3];
        $lenIDL = strlen($IDL); $lenIDR = strlen($IDR); $lenOBL = strlen($OBL); $lenOBR = strlen($OBR);
        $OPT = '?'; $OPTR = '*'; $NEG = '!'; $DEF = '|'; $REPL = '{'; $REPR = '}';
        $default_value = null; $negative = 0; $optional = 0; $start_i = 0; $end_i = 0;
        $l = strlen($tpl);
        $i = 0; $a = array(array(), null, 0, 0, 0, 0); $stack = array(); $s = '';
        while( $i < $l )
        {
            if ( $IDL === substr($tpl,$i,$lenIDL) )
            {
                $i += $lenIDL;
                if ( strlen($s) ) $a[0][] = array(0, $s);
                $s = '';
            }
            elseif ( $IDR === substr($tpl,$i,$lenIDR) )
            {
                $i += $lenIDR;
                // argument
                $argument = $s; $s = '';
                $p = strpos($argument, $DEF);
                if ( false !== $p )
                {
                    $default_value = substr($argument, $p+1);
                    $argument = substr($argument, 0, $p);
                }
                else
                {
                    $default_value = null;
                }
                $c = $argument[0];
                if ( $OPT === $c || $OPTR === $c )
                {
                    $optional = 1;
                    if ( $OPTR === $c )
                    {
                        $start_i = 1;
                        $end_i = -1;
                    }
                    else
                    {
                        $start_i = 0;
                        $end_i = 0;
                    }
                    $argument = substr($argument,1);
                    if ( $NEG === $argument[0] )
                    {
                        $negative = 1;
                        $argument = substr($argument,1);
                    }
                    else
                    {
                        $negative = 0;
                    }
                }
                elseif ( $REPL === $c )
                {
                    $s = ''; $j = 1; $jl = strlen($argument);
                    while ( $j < $jl && $REPR !== $argument[$j] ) $s .= $argument[$j++];
                    $argument = substr($argument, $j+1);
                    $s = explode(',', $s);
                    if ( count($s) > 1 )
                    {
                        $start_i = trim($s[0]);
                        $start_i = strlen($start_i) ? intval($start_i,10) : 0;
                        if ( is_nan($start_i) ) $start_i = 0;
                        $end_i = trim($s[1]);
                        $end_i = strlen($end_i) ? intval($end_i,10) : -1;
                        if ( is_nan($end_i) ) $end_i = 0;
                        $optional = 1;
                    }
                    else
                    {
                        $start_i = trim($s[0]);
                        $start_i = strlen($start_i) ? intval($start_i,10) : 0;
                        if ( is_nan($start_i) ) $start_i = 0;
                        $end_i = $start_i;
                        $optional = 0;
                    }
                    $s = '';
                    $negative = 0;
                }
                else
                {
                    $optional = 0;
                    $negative = 0;
                    $start_i = 0;
                    $end_i = 0;
                }
                if ( $negative && null === $default_value ) $default_value = '';
                
                if ( $optional && !$a[2] )
                {
                    $a[1] = $argument;
                    $a[2] = $optional;
                    $a[3] = $negative;
                    $a[4] = $start_i;
                    $a[5] = $end_i;
                }
                elseif ( !$optional && (null === $a[1]) )
                {
                    $a[1] = $argument;
                    $a[2] = 0;
                    $a[3] = $negative;
                    $a[4] = $start_i;
                    $a[5] = $end_i;
                }
                $a[0][] = array(1, $argument, $default_value, $optional, $negative, $start_i, $end_i);
            }
            elseif ( $OBL === substr($tpl,$i,$lenOBL) )
            {
                $i += $lenOBL;
                // optional block
                if ( strlen($s) ) $a[0][] = array(0, $s);
                $s = '';
                $stack[] = $a;
                $a = array(array(), null, 0, 0, 0, 0);
            }
            elseif ( $OBR === substr($tpl,$i,$lenOBR) )
            {
                $i += $lenOBR;
                $b = $a; $a = array_pop($stack);
                if ( strlen($s) ) $b[0][] = array(0, $s);
                $s = '';
                $a[0][] = array(-1, $b[1], $b[2], $b[3], $b[4], $b[5], $b[0]);
            }
            else
            {
                $s .= $tpl[$i++];
            }
        }
        if ( strlen($s) ) $a[0][] = array(0, $s);
        return $a[0];
    }
    
    public static $defaultDelims = array('<','>','[',']'/*,'?','*','!','|','{','}'*/);
    
    public $id = null;
    public $tpl = null;
    
    public function __construct($tpl='', $delims=null)
    {
        $this->id = null;
        if ( empty($delims) ) $delims = self::$defaultDelims;
        $this->tpl = self::multisplit( $tpl, $delims );
    }

    public function __destruct()
    {
        $this->dispose();
    }
    
    public function dispose()
    {
        $this->id = null;
        $this->tpl = null;
        return $this;
    }
    
    public function render($args=null)
    {
        if ( null === $args ) $args = array();
        
        $tpl = $this->tpl; $l = count($tpl); $stack = array();
        $rarg = null; $ri = 0; $out = '';
        $i = 0;
        while ( $i < $l || !empty($stack) )
        {
            if ( $i >= $l )
            {
                $p = array_pop($stack);
                $tpl = $p[0]; $i = $p[1]; $l = $p[2];
                $rarg = $p[3]; $ri = $p[4];
                continue;
            }
            
            $t = $tpl[ $i ]; $tt = $t[ 0 ]; $s = $t[ 1 ];
            if ( -1 === $tt )
            {
                // optional block
                if ( (0 === $t[ 3 ] && isset($args[$s])) ||
                    (1 === $t[ 3 ] && !isset($args[$s]))
                )
                {
                    if ( 1 === $t[ 3 ] )
                    {
                        $stack[] = array($tpl, $i+1, $l, $rarg, $ri);
                        $tpl = $t[ 6 ]; $i = 0; $l = count($tpl);
                        $rarg = null; $ri = 0;
                        continue;
                    }
                    else
                    {
                        $arr = is_array( $args[$s] );
                        if ( $arr && ($t[4] !== $t[5]) && count($args[$s]) > $t[ 4 ] )
                        {
                            $rs = $t[ 4 ];
                            $re = -1 === $t[ 5 ] ? count($args[$s])-1 : min($t[ 5 ], count($args[$s])-1);
                            if ( $re >= $rs )
                            {
                                $stack[] = array($tpl, $i+1, $l, $rarg, $ri);
                                $tpl = $t[ 6 ]; $i = 0; $l = count($tpl);
                                $rarg = $s;
                                for($ri=$re; $ri>$rs; $ri--) $stack[] = array($tpl, 0, $l, $rarg, $ri);
                                $ri = $rs;
                                continue;
                            }
                        }
                        else if ( !$arr && ($t[4] === $t[5]) )
                        {
                            $stack[] = array($tpl, $i+1, $l, $rarg, $ri);
                            $tpl = $t[ 6 ]; $i = 0; $l = count($tpl);
                            $rarg = $s; $ri = 0;
                            continue;
                        }
                    }
                }
            }
            else if ( 1 === $tt )
            {
                //TODO: handle nested/structured/deep arguments
                // default value if missing
                $out .= !isset($args[$s]) && null !== $t[ 2 ]
                    ? $t[ 2 ]
                    : (is_array($args[ $s ])
                    ? ($s === $rarg
                    ? $args[$s][$t[5]===$t[6]?$t[5]:$ri]
                    : $args[$s][$t[5]])
                    : $args[$s])
                ;
            }
            else /*if ( 0 === $tt )*/
            {
                $out .= $s;
            }
            $i++;
            /*if ( $i >= $l && !empty($stack) )
            {
                $p = array_pop($stack);
                $tpl = $p[0]; $i = $p[1]; $l = $p[2];
                $rarg = $p[3]; $ri = $p[4];
            }*/
        }
        return $out;
    }
}    

class DialectRef
{
    public static function parse( $r, $d ) 
    {
        $r = explode( ' AS ', trim( $r ) );
        $col = explode( '.', $r[ 0 ] );
        $l = count($col);
        if ( 3 <= $l )
        {
            $dtb = trim($col[ 0 ]);
            $tbl = trim($col[ 1 ]);
            $col = trim($col[ 2 ]);
        }
        elseif ( 2 === $l )
        {
            $dtb = null;
            $tbl = trim($col[ 0 ]);
            $col = trim($col[ 1 ]);
        }
        else
        {
            $dtb = null; $tbl = null;
            $col = trim($col[ 0 ]);
        }
        $col_q = $d->quote_name( $col );
        if ( null !== $dtb )
        {
            $dtb_q = $d->quote_name( $dtb );
            $tbl_q = $d->quote_name( $tbl );
            $tbl_col = $dtb . '.' . $tbl . '.' . $col;
            $tbl_col_q = $dtb_q . '.' . $tbl_q . '.' . $col_q;
        }
        elseif ( null !== $tbl )
        {
            $dtb_q = null;
            $tbl_q = $d->quote_name( $tbl );
            $tbl_col = $tbl . '.' . $col;
            $tbl_col_q = $tbl_q . '.' . $col_q;
        }
        else
        {
            $dtb_q = null; $tbl_q = null;
            $tbl_col = $col;
            $tbl_col_q = $col_q;
        }
        if ( count($r) < 2 )
        {
            $alias = $tbl_col;
            $alias_q = $tbl_col_q;
            $tbl_col_alias = $tbl_col;
            $tbl_col_alias_q = $tbl_col_q;
        }
        else
        {
            $alias = trim( $r[1] );
            $alias_q = $d->quote_name( $alias );
            $tbl_col_alias = $tbl_col . ' AS ' . $alias;
            $tbl_col_alias_q = $tbl_col_q . ' AS ' . $alias_q;
        }
        return new self( $col, $col_q, $tbl, $tbl_q, $dtb, $dtb_q, $alias, $alias_q, 
                    $tbl_col, $tbl_col_q, $tbl_col_alias, $tbl_col_alias_q );
    }
    
    public $col = null;
    public $col_q = null;
    public $tbl = null;
    public $tbl_q = null;
    public $dtb = null;
    public $dtb_q = null;
    public $alias = null;
    public $alias_q = null;
    public $tbl_col = null;
    public $tbl_col_q = null;
    public $tbl_col_alias = null;
    public $tbl_col_alias_q = null;
    
    public function __construct( $col, $col_q, $tbl, $tbl_q, $dtb, $dtb_q, $alias, $alias_q, 
                                $tbl_col, $tbl_col_q, $tbl_col_alias, $tbl_col_alias_q ) 
    {
        $this->col = $col;
        $this->col_q = $col_q;
        $this->tbl = $tbl;
        $this->tbl_q = $tbl_q;
        $this->dtb = $dtb;
        $this->dtb_q = $dtb_q;
        $this->alias = $alias;
        $this->alias_q = $alias_q;
        $this->tbl_col = $tbl_col;
        $this->tbl_col_q = $tbl_col_q;
        $this->tbl_col_alias = $tbl_col_alias;
        $this->tbl_col_alias_q = $tbl_col_alias_q;
    }
    
    public function __destruct()
    {
        $this->dispose( );
    }
    
    public function dispose( ) 
    {
        $this->col = null;
        $this->col_q = null;
        $this->tbl = null;
        $this->tbl_q = null;
        $this->dtb = null;
        $this->dtb_q = null;
        $this->alias = null;
        $this->alias_q = null;
        $this->tbl_col = null;
        $this->tbl_col_q = null;
        $this->tbl_col_alias = null;
        $this->tbl_col_alias_q = null;
        return $this;
    }
}
 
class Dialect
{
    const VERSION = "0.5.3";
    const TPL_RE = '/\\$\\(([^\\)]+)\\)/';
    
    public static $dialects = array(
    'mysql'            => array(
    // https://dev.mysql.com/doc/refman/5.0/en/select.html
    // https://dev.mysql.com/doc/refman/5.0/en/join.html
    // https://dev.mysql.com/doc/refman/5.5/en/expressions.html
    // https://dev.mysql.com/doc/refman/5.0/en/insert.html
    // https://dev.mysql.com/doc/refman/5.0/en/update.html
    // https://dev.mysql.com/doc/refman/5.0/en/delete.html
    // http://dev.mysql.com/doc/refman/5.7/en/create-table.html
    // http://dev.mysql.com/doc/refman/5.7/en/drop-table.html
    // http://dev.mysql.com/doc/refman/5.7/en/alter-table.html
     'quotes'        => array( array("'","'","\\'","\\'"), array('`','`'), array('','') )
    ,'clauses'       => array(
     'create'       => "CREATE TABLE IF NOT EXISTS <create_table>\n(<create_defs>)[<?create_opts>]"
    ,'alter'        => "ALTER TABLE <alter_table>\n<alter_defs>[<?alter_opts>]"
    ,'drop'         => "DROP TABLE IF EXISTS <drop_tables>[,<*drop_tables>]"
    ,'select'       => "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>[\n<*join_clauses>]][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING (<?having_conditions_required>) [AND (<?having_conditions>)]][<?!having_conditions_required>[\nHAVING <?having_conditions>]][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]"
    ,'insert'       => "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
    ,'update'       => "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]"
    ,'delete'       => "DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]"
        )
    )
    ,'postgres'          => array(
    // http://www.postgresql.org/docs/
    // http://www.postgresql.org/docs/9.1/static/sql-createtable.html
    // http://www.postgresql.org/docs/9.1/static/sql-droptable.html
    // http://www.postgresql.org/docs/9.1/static/sql-altertable.html
    // http://www.postgresql.org/docs/8.2/static/sql-syntax-lexical.html
     'quotes'        => array( array("E'","'","''","''"), array('"','"'), array('','') )
    ,'clauses'       => array(
     'create'       => "CREATE TABLE IF NOT EXISTS <create_table>\n(<create_defs>)[<?create_opts>]"
    ,'alter'        => "ALTER TABLE <alter_table>\n<alter_defs>[<?alter_opts>]"
    ,'drop'         => "DROP TABLE IF EXISTS <drop_tables>[,<*drop_tables>]"
    ,'select'       => "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>[\n<*join_clauses>]][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING (<?having_conditions_required>) [AND (<?having_conditions>)]][<?!having_conditions_required>[\nHAVING <?having_conditions>]][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
    ,'insert'       => "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
    ,'update'       => "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
    ,'delete'       => "DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
        )
    )
    ,'sqlserver'        => array(
    // https://msdn.microsoft.com/en-us/library/ms189499.aspx
    // https://msdn.microsoft.com/en-us/library/ms174335.aspx
    // https://msdn.microsoft.com/en-us/library/ms177523.aspx
    // https://msdn.microsoft.com/en-us/library/ms189835.aspx
    // https://msdn.microsoft.com/en-us/library/ms179859.aspx
    // https://msdn.microsoft.com/en-us/library/ms188385%28v=sql.110%29.aspx
    // https://msdn.microsoft.com/en-us/library/ms174979.aspx
    // https://msdn.microsoft.com/en-us/library/ms173790.aspx
    // https://msdn.microsoft.com/en-us/library/cc879314.aspx
    // http://stackoverflow.com/questions/603724/how-to-implement-limit-with-microsoft-sql-server
    // http://stackoverflow.com/questions/971964/limit-10-20-in-sql-server
     'quotes'        => array( array("'","'","''","''"), array('[',']'), array(''," ESCAPE '\\'") )
    ,'clauses'       => array(
     'create'       => "CREATE TABLE IF NOT EXISTS <create_table>\n(<create_defs>)[<?create_opts>]"
    ,'alter'        => "ALTER TABLE <alter_table>\n<alter_defs>[<?alter_opts>]"
    ,'drop'         => "DROP TABLE IF EXISTS <drop_tables>[,<*drop_tables>]"
    ,'select'       => "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>[\n<*join_clauses>]][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING (<?having_conditions_required>) [AND (<?having_conditions>)]][<?!having_conditions_required>[\nHAVING <?having_conditions>]][\nORDER BY <?order_conditions>[,<*order_conditions>][\nOFFSET <offset|0> ROWS FETCH NEXT <?count> ROWS ONLY]][<?!order_conditions>[\nORDER BY 1\nOFFSET <offset|0> ROWS FETCH NEXT <?count> ROWS ONLY]]"
    ,'insert'       => "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
    ,'update'       => "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]][\nORDER BY <?order_conditions>[,<*order_conditions>]]"
    ,'delete'       => "DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]][\nORDER BY <?order_conditions>[,<*order_conditions>]]"
        )
    )
    ,'sqlite'           => array(
        // https://www.sqlite.org/lang_createtable.html
        // https://www.sqlite.org/lang_select.html
        // https://www.sqlite.org/lang_insert.html
        // https://www.sqlite.org/lang_update.html
        // https://www.sqlite.org/lang_delete.html
        // https://www.sqlite.org/lang_expr.html
        // https://www.sqlite.org/lang_keywords.html
         'quotes'       => array( array("'","'","''","''"), array('"','"'), array(''," ESCAPE '\\'") )
        ,'clauses'      => array(
         'create'       => "CREATE TABLE IF NOT EXISTS <create_table>\n(<create_defs>)[<?create_opts>]"
        ,'alter'        => "ALTER TABLE <alter_table>\n<alter_defs>[<?alter_opts>]"
        ,'drop'         => "DROP TABLE IF EXISTS <drop_tables>[,<*drop_tables>]"
        ,'select'       => "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>[\n<*join_clauses>]][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING (<?having_conditions_required>) [AND (<?having_conditions>)]][<?!having_conditions_required>[\nHAVING <?having_conditions>]][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
        ,'insert'       => "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
        ,'update'       => "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]]"
        ,'delete'       => "[<?!order_conditions>[<?!count>DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]]]][DELETE \nFROM <from_tables>[,<*from_tables>] WHERE rowid IN (\nSELECT rowid FROM <from_tables>[,<*from_tables>][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]]\nORDER BY <?order_conditions>[,<*order_conditions>][\nLIMIT <?count> OFFSET <offset|0>]\n)][<?!order_conditions>[DELETE \nFROM <from_tables>[,<*from_tables>] WHERE rowid IN (\nSELECT rowid FROM <from_tables>[,<*from_tables>][\nWHERE (<?where_conditions_required>) [AND (<?where_conditions>)]][<?!where_conditions_required>[\nWHERE <?where_conditions>]]\nLIMIT <?count> OFFSET <offset|0>\n)]]"
        )
    )
    );
    
    private $clau = null;
    private $clus = null;
    private $vews = null;
    private $tpls = null;
    private $tbls = null;
    private $cols = null;
   
    public $db = null;
    public $escdb = null;
    public $p = null;
    
    public $type = null;
    public $clauses = null;
    public $q = null;
    public $qn = null;
    public $e = null;
    
    public function __construct( $type='mysql' )
    {
        if ( empty($type) || empty(self::$dialects[ $type ]) || empty(self::$dialects[ $type ][ 'clauses' ]) )
        {
            throw new InvalidArgumentException('Dialect: SQL dialect does not exist for "'.$type.'"');
        }
        
        $this->clau = null;
        $this->clus = null;
        $this->tbls = null;
        $this->cols = null;
        $this->vews = array( );
        $this->tpls = array( );
        
        $this->db = null;
        $this->escdb = null;
        $this->p = '';
        
        $this->type = $type;
        $this->clauses =& self::$dialects[ $this->type ][ 'clauses' ];
        $this->q = self::$dialects[ $this->type ][ 'quotes' ][ 0 ];
        $this->qn = self::$dialects[ $this->type ][ 'quotes' ][ 1 ];
        $this->e = isset(self::$dialects[ $this->type ][ 'quotes' ][ 2 ]) ? self::$dialects[ $this->type ][ 'quotes' ][ 2 ] : array('','');
    }
    
    public function dispose( )
    {
        $this->clau = null;
        $this->clus = null;
        $this->tbls = null;
        $this->cols = null;
        $this->vews = null;
        $this->tpls = null;
        
        $this->db = null;
        $this->escdb = null;
        $this->p = null;
        
        $this->type = null;
        $this->clauses = null;
        $this->q = null;
        $this->qn = null;
        $this->e = null;
        return $this;
    }
    
    public function __destruct( )
    {
        $this->dispose( );
    }
    
	public function __toString()
	{
        $sql = $this->sql( );
        return !$sql||empty($sql) ? '' : $sql;
    }
    
    public function driver( $db=null )
    {
        if ( func_num_args() > 0 )
        {
            $this->db = $db ? $db : null;
            return $this;
        }
        return $this->db;
    }
    
    public function escape( $escdb=null )
    {
        if ( func_num_args() > 0 )
        {
            $this->escdb = $escdb && is_callable($escdb) ? $escdb : null;
            return $this;
        }
        return $this->escdb;
    }
    
    public function prefix( $prefix='' )
    {
        if ( func_num_args() > 0 )
        {
            $this->p = $prefix ? $prefix : '';
            return $this;
        }
        return $this->p;
    }
    
    public function reset( $clause )
    {
        if ( empty($clause) || !isset($this->clauses[ $clause ]) )
        {
            throw new InvalidArgumentException('Dialect: SQL clause "'.$clause.'" does not exist for dialect "'.$this->type.'"');
        }
        $this->clus = array( );
        $this->tbls = array( );
        $this->cols = array( );
        $this->clau = $clause;
        
        if ( !($this->clauses[ $this->clau ] instanceof DialectGrammTpl) )
            $this->clauses[ $this->clau ] = new DialectGrammTpl( $this->clauses[ $this->clau ] );
        return $this;
    }
    
    public function clear( )
    {
        $this->clau = null;
        $this->clus = null;
        $this->tbls = null;
        $this->cols = null;
        return $this;
    }
    
    public function sql( )
    {
        $query = null;
        if ( $this->clau && isset($this->clauses[ $this->clau ]) )
            $query = $this->clauses[ $this->clau ]->render( $this->clus );
        $this->clear( );
        return $query;
    }
    
    public function prepare( $query, $args=array(), $left=null, $right=null )
    {
        if ( $query && !empty($args) )
        {
            // custom delimiters
            $left = $left ? preg_quote($left, '/') : '%';
            $right = $right ? preg_quote($right, '/') : '%';
            
            // custom prepared parameter format
            $pattern = '/' . $left . '([rlfds]:)?([0-9a-zA-Z_]+)' . $right . '/';
            $prepared = '';
            while ( preg_match($pattern, $query, $m, PREG_OFFSET_CAPTURE) )
            {
                $pos = $m[0][1];
                $len = strlen($m[0][0]);
                $param = $m[2][0];
                if ( isset($args[$param]) )
                {
                    $type = isset($m[1])&&$m[1]&&strlen($m[1][0]) ? substr($m[1][0],0,-1) : "s";
                    switch($type)
                    {
                        case 'r': 
                            // raw param
                            if ( is_array($args[$param]) )
                            {
                                $param = implode(',', $args[$param]);
                            }
                            else
                            {
                                $param = $args[$param];
                            }
                            break;
                        
                        case 'l': 
                            // like param
                            $param = $this->like( $args[$param] ); 
                            break;
                        
                        case 'f': 
                            if ( is_array($args[$param]) )
                            {
                                // array of references, e.g fields
                                $tmp = (array)$args[$param];
                                $param = DialectRef::parse( $tmp[0], $this )->tbl_col_alias_q;
                                for ($i=1,$l=count($tmp); $i<$l; $i++) $param .= ','.DialectRef::parse( $tmp[$i], $this )->tbl_col_alias_q;
                            }
                            else
                            {
                                // reference, e.g field
                                $param = DialectRef::parse( $args[$param], $this )->tbl_col_alias_q;
                            }
                            break;
                        
                        case 'd':
                            if ( is_array($args[$param]) )
                            {
                                // array of integers param
                                $param = implode( ',', $this->intval( (array)$args[$param] ) );
                            }
                            else
                            {
                                // integer
                                $param = $this->intval( $args[$param] );
                            }
                            break;
                        
                        case 's': 
                        default:
                            if ( is_array($args[$param]) )
                            {
                                // array of strings param
                                $param = implode( ',', $this->quote( (array)$args[$param] ) );
                            }
                            else
                            {
                                // string param
                                $param = $this->quote( $args[$param] );
                            }
                            break;
                    }
                    $prepared .= substr($query, 0, $pos) . $param;
                }
                else
                {
                    $prepared .= substr($query, 0, $pos) . $this->quote('');
                }
                $query = substr($query, $pos+$len );
            }
            if ( strlen($query) ) $prepared .= $query;
            return $prepared;
        }
        return $query;
    }
    
    public function make_view( $view ) 
    {
        if ( !empty($view) && $this->clau )
        {
            $this->vews[ $view ] = (object)array(
                'clau'=>$this->clau, 
                'clus'=>$this->clus,
                'tbls'=>$this->tbls,
                'cols'=>$this->cols
            );
            // make existing where / having conditions required
            if ( isset($this->vews[ $view ]->clus[ 'where_conditions' ]) )
            {
                if ( !empty($this->vews[ $view ]->clus[ 'where_conditions' ]) )
                    $this->vews[ $view ]->clus[ 'where_conditions_required' ] = $this->vews[ $view ]->clus[ 'where_conditions' ];
                unset($this->vews[ $view ]->clus[ 'where_conditions' ]);
            }
            if ( isset($this->vews[ $view ]->clus[ 'having_conditions' ]) )
            {
                if ( !empty($this->vews[ $view ]->clus[ 'having_conditions' ]) )
                    $this->vews[ $view ]->clus[ 'having_conditions_required' ] = $this->vews[ $view ]->clus[ 'having_conditions' ];
                unset($this->vews[ $view ]->clus[ 'having_conditions' ]);
            }
            $this->clear( );
        }
        return $this;
    }
    
    public function clear_view( $view ) 
    {
        if ( !empty($view) && isset($this->vews[$view]) )
        {
           unset( $this->vews[ $view ] );
        }
        return $this;
    }
    
    public function prepare_tpl( $tpl /*, $query=null, $left=null, $right=null*/ ) 
    {
        if ( !empty($tpl) )
        {
            $args = func_get_args(); 
            $argslen = count($args);
            
            if ( 1 === $argslen )
            {
                $query = null;
                $left = null;
                $right = null;
                $use_internal_query = true;
            }
            elseif ( 2 === $argslen )
            {
                $query = $args[ 1 ];
                $left = null;
                $right = null;
                $use_internal_query = false;
            }
            else if ( 3 === $argslen )
            {
                $query = null;
                $left = $args[ 1 ];
                $right = $args[ 2 ];
                $use_internal_query = true;
            }
            else/* if ( 3 < argslen )*/
            {
                $query = $args[ 1 ];
                $left = $args[ 2 ];
                $right = $args[ 3 ];
                $use_internal_query = false;
            }
            
            // custom delimiters
            $left = $left ? preg_quote($left, '/') : '%';
            $right = $right ? preg_quote($right, '/') : '%';
            // custom prepared parameter format
            $pattern = '/' . $left . '(([rlfds]:)?[0-9a-zA-Z_]+)' . $right . '/';
            
            if ( $use_internal_query )
            {
                $sql = new DialectTpl( $this->sql( ), $pattern );
                //$this->clear( );
            }
            else
            {
                $sql = new DialectTpl( $query, $pattern );
            }
            
            $types = array();
            // extract parameter types
            foreach ($sql->tpl as &$tpli)
            {
                if ( !$tpli[ 0 ] )
                {
                    $k = explode( ':', $tpli[ 1 ] );
                    if ( isset($k[1]) )
                    {
                        $types[ $k[1] ] = $k[0];
                        $tpli[ 1 ] = $k[1];
                    }
                    else
                    {
                        $types[ $k[0] ] = "s";
                        $tpli[ 1 ] = $k[0];
                    }
                }
            }
            $this->tpls[ $tpl ] = (object)array(
                'sql'=>$sql, 
                'types'=>$types
            );
        }
        return $this;
    }
    
    public function prepared( $tpl, $args )
    {
        if ( !empty($tpl) && isset($this->tpls[$tpl]) )
        {
            $sql = $this->tpls[$tpl]->sql;
            $types = $this->tpls[$tpl]->types;
            $params = array( );
            foreach((array)$args as $k=>$v)
            {
                $type = isset($types[$k]) ? $types[$k] : "s";
                switch($type)
                {
                    case 'r': 
                        // raw param
                        if ( is_array($v) )
                        {
                            $params[$k] = implode(',', $v);
                        }
                        else
                        {
                            $params[$k] = $v;
                        }
                        break;
                    
                    case 'l': 
                        // like param
                        $params[$k] = $this->like( $v ); 
                        break;
                    
                    case 'f': 
                        if ( is_array($v) )
                        {
                            // array of references, e.g fields
                            $tmp = (array)$v;
                            $params[$k] = DialectRef::parse( $tmp[0], $this )->tbl_col_alias_q;
                            for ($i=1,$l=count($tmp); $i<$l; $i++) $params[$k] .= ','.DialectRef::parse( $tmp[$i], $this )->tbl_col_alias_q;
                        }
                        else
                        {
                            // reference, e.g field
                            $params[$k] = DialectRef::parse( $v, $this )->tbl_col_alias_q;
                        }
                        break;
                    
                    case 'd':
                        if ( is_array($v) )
                        {
                            // array of integers param
                            $params[$k] = implode( ',', $this->intval( (array)$v ) );
                        }
                        else
                        {
                            // integer
                            $params[$k] = $this->intval( $v );
                        }
                        break;
                    
                    case 's': 
                    default:
                        if ( is_array($v) )
                        {
                            // array of strings param
                            $params[$k] = implode( ',', $this->quote( (array)$v ) );
                        }
                        else
                        {
                            // string param
                            $params[$k] = $this->quote( $v );
                        }
                        break;
                }
            }
            return $sql->render( $params );
        }
        return '';
    }
    
    public function clear_tpl( $tpl ) 
    {
        if ( !empty($tpl) && isset($this->tpls[$tpl]) )
        {
           $this->tpls[ $tpl ]->sql->dispose( );
           unset( $this->tpls[ $tpl ] );
        }
        return $this;
    }
    
    public function create( $table, $defs, $opts=null, $format=true, $create_clause='create' )
    {
        $this->reset($create_clause);
        if ( false !== $format )
        {
            $table = $this->refs( $table, $this->tbls );
            $table = $table[0]->tbl_col_q;
        }
        $this->clus['create_table'] = $table;
        if ( !empty($this->clus['create_defs']) )
            $defs = $this->clus['create_defs'] . ',' . $defs;
        $this->clus['create_defs'] = $defs;
        if ( !empty($opts) )
        {
            if ( !empty($this->clus['create_opts']) )
                $opts = $this->clus['create_opts'] . ',' . $opts;
            $this->clus['create_opts'] = $opts;
        }
        return $this;
    }
    
    public function alter( $table, $defs, $opts=null, $format=true, $alter_clause='alter' )
    {
        $this->reset($alter_clause);
        if ( false !== $format )
        {
            $table = $this->refs( $table, $this->tbls );
            $table = $table[0]->tbl_col_q;
        }
        $this->clus['alter_table'] = $table;
        if ( !empty($this->clus['alter_defs']) )
            $defs = $this->clus['alter_defs'] . ',' . $defs;
        $this->clus['alter_defs'] = $defs;
        if ( !empty($opts) )
        {
            if ( !empty($this->clus['alter_opts']) )
                $opts = $this->clus['alter_opts'] . ',' . $opts;
            $this->clus['alter_opts'] = $opts;
        }
        return $this;
    }
    
    public function drop( $tables, $format=true, $drop_clause='drop' )
    {
        $this->reset($drop_clause);
        if ( empty($tables) || '*' === $tables ) 
        {
            $tables = '*';
        }
        else
        {            
            if ( false !== $format )
            {
                $tbls = $this->refs( $tables, $this->tbls );
                $tables = $tbls[ 0 ]->tbl_col_q;
                for($i=1,$l=count($tbls); $i<$l; $i++) $tables .= ',' . $tbls[ $i ]->tbl_col_q;
            }
            else
            {
                $tables = implode(',', (array)$tables);
            }
        }
        if ( !empty($this->clus['drop_tables']) )
            $tables = $this->clus['drop_tables'] . ',' . $tables;
        $this->clus['drop_tables'] = $tables;
        return $this;
    }
    
    public function select( $cols='*', $format=true, $select_clause='select' )
    {
        $this->reset($select_clause);
        if ( !$cols || empty($cols) || '*' === $cols ) 
        {
            $columns = '*';
        }
        else
        {
            if ( false !== $format )
            {
                $cols = $this->refs( $cols, $this->cols );
                $columns = $cols[ 0 ]->tbl_col_alias_q;
                for($i=1,$l=count($cols); $i<$l; $i++) $columns .= ',' . $cols[ $i ]->tbl_col_alias_q;
            }
            else
            {
                $columns = implode( ',', (array)$cols );
            }
        }
        if ( !empty($this->clus['select_columns']) )
            $columns = $this->clus['select_columns'] . ',' . $columns;
        $this->clus['select_columns'] = $columns;
        return $this;
    }
    
    public function insert( $tbls, $cols, $format=true, $insert_clause='insert' )
    {
        $this->reset($insert_clause);
        $view = is_array( $tbls ) ? $tbls[0] : $tbls;
        if ( isset($this->vews[ $view ]) && $this->clau === $this->vews[ $view ]->clau )
        {
            // using custom 'soft' view
            $view = $this->vews[ $view ];
            $this->clus = self::defaults( $this->clus, $view->clus, true );
            $this->tbls = self::defaults( array(), $view->tbls, true );
            $this->cols = self::defaults( array(), $view->cols, true );
        }
        else
        {
            if ( false !== $format )
            {
                $tbls = $this->refs( $tbls, $this->tbls );
                $cols = $this->refs( $cols, $this->cols );
                $tables = $tbls[ 0 ]->tbl_col_alias_q;
                $columns = $cols[ 0 ]->tbl_col_q;
                for($i=1,$l=count($tbls); $i<$l; $i++) $tables .= ',' . $tbls[ $i ]->tbl_col_alias_q;
                for($i=1,$l=count($cols); $i<$l; $i++) $columns .= ',' . $cols[ $i ]->tbl_col_q;
            }
            else
            {
                $tables = implode( ',', (array)$tbls );
                $columns = implode( ',', (array)$cols );
            }
            if ( !empty($this->clus['insert_tables']) )
                $tables = $this->clus['insert_tables'] . ',' . $tables;
            if ( !empty($this->clus['insert_columns']) )
                $columns = $this->clus['insert_columns'] . ',' . $columns;
            $this->clus['insert_tables'] = $tables;
            $this->clus['insert_columns'] = $columns;
        }
        return $this;
    }
    
    public function values( $values )
    {
        if ( empty($values) ) return $this;
        // array of arrays
        if ( !isset($values[0]) || !is_array($values[0]) ) $values = array($values);
        $count = count($values);
        $insert_values = array();
        for ($i=0; $i<$count; $i++)
        {
            if ( !empty($values[$i]) )
            {
                $vals = array();
                foreach ((array)$values[$i] as $val)
                {
                    if ( is_array($val) )
                    {
                        if ( isset($val['integer']) )
                        {
                            $vals[] = $this->intval( $val['integer'] );
                        }
                        elseif ( isset($val['raw']) )
                        {
                            $vals[] = $val['raw'];
                        }
                        elseif ( isset($val['string']) )
                        {
                            $vals[] = $this->quote( $val['string'] );
                        }
                    }
                    else
                    {
                        $vals[] = is_int($val) ? $val : $this->quote( $val );
                    }
                }
                $insert_values[] = '('.implode(',', $vals).')';
            }
        }
        $insert_values = implode(',', $insert_values);
        if ( !empty($this->clus['values_values']) )
            $insert_values = $this->clus['values_values'] . ',' . $insert_values;
        $this->clus['values_values'] = $insert_values;
        return $this;
    }
    
    public function update( $tbls, $format=true, $update_clause='update' )
    {
        $this->reset($update_clause);
        $view = is_array( $tbls ) ? $tbls[0] : $tbls;
        if ( isset($this->vews[ $view ]) && $this->clau === $this->vews[ $view ]->clau )
        {
            // using custom 'soft' view
            $view = $this->vews[ $view ];
            $this->clus = self::defaults( $this->clus, $view->clus, true );
            $this->tbls = self::defaults( array(), $view->tbls, true );
            $this->cols = self::defaults( array(), $view->cols, true );
        }
        else
        {
            if ( false !== $format )
            {
                $tbls = $this->refs( $tbls, $this->tbls );
                $tables = $tbls[ 0 ]->tbl_col_alias_q;
                for($i=1,$l=count($tbls); $i<$l; $i++) $tables .= ',' . $tbls[ $i ]->tbl_col_alias_q;
            }
            else
            {
                $tables = implode( ',', (array)$tbls );
            }
            if ( !empty($this->clus['update_tables']) )
                $tables = $this->clus['update_tables'] . ',' . $tables;
            $this->clus['update_tables'] = $tables;
        }
        return $this;
    }
    
    public function set( $fields_values )
    {
        if ( empty($fields_values) ) return $this;
        $set_values = array();
        foreach ($fields_values as $f=>$value)
        {
            $field = $this->refs( $f, $this->cols );
            $field = $field[0]->tbl_col_q;
            if ( is_array($value) )
            {
                if ( isset($value['integer']) )
                {
                    $set_values[] = "$field = " . $this->intval($value['integer']);
                }
                elseif ( isset($value['raw']) )
                {
                    $set_values[] = "$field = {$value['raw']}";
                }
                elseif ( isset($value['string']) )
                {
                    $set_values[] = "$field = " . $this->quote($value['string']);
                }
                elseif ( isset($value['increment']) )
                {
                    $set_values[] = "$field = $field + " . $this->intval($value['increment']);
                }
                elseif ( isset($value['decrement']) )
                {
                    $set_values[] = "$field = $field - " . $this->intval($value['decrement']);
                }
            }
            else
            {
                $set_values[] = "$field = " . (is_int($value) ? $value : $this->quote($value));
            }
        }
        $set_values = implode(',', $set_values);
        if ( !empty($this->clus['set_values']) )
            $set_values = $this->clus['set_values'] . ',' . $set_values;
        $this->clus['set_values'] = $set_values;
        return $this;
    }
    
    public function del( $delete_clause='delete' )
    {
        $this->reset($delete_clause);
        return $this;
    }
    
    public function from( $tbls, $format=true )
    {
        if ( empty($tbls) ) return $this;
        $view = is_array( $tbls ) ? $tbls[0] : $tbls;
        if ( isset($this->vews[ $view ]) && $this->clau === $this->vews[ $view ]->clau )
        {
            // using custom 'soft' view
            $view = $this->vews[ $view ];
            $this->clus = self::defaults( $this->clus, $view->clus, true );
            $this->tbls = self::defaults( array(), $view->tbls, true );
            $this->cols = self::defaults( array(), $view->cols, true );
        }
        else
        {
            if ( false !== $format )
            {
                $tbls = $this->refs( $tbls, $this->tbls );
                $tables = $tbls[ 0 ]->tbl_col_alias_q;
                for($i=1,$l=count($tbls); $i<$l; $i++) $tables .= ',' . $tbls[ $i ]->tbl_col_alias_q;
            }
            else
            {
                $tables = implode( ',', (array)$tbls );
            }
            if ( !empty($this->clus['from_tables']) )
                $tables = $this->clus['from_tables'] . ',' . $tables;
            $this->clus['from_tables'] = $tables;
        }
        return $this;
    }
    
    public function join( $table, $on_cond=null, $join_type=null )
    {
        $table = $this->refs( $table, $this->tbls );
        $table = $table[0]->tbl_col_alias_q;
        if ( empty($on_cond) )
        {
            $join_clause = $table;
        }
        else
        {
            if ( is_string( $on_cond ) )
            {
                $on_cond = $this->refs( explode('=',$on_cond), $this->cols );
                $on_cond = '(' . $on_cond[0]->tbl_col_q . '=' . $on_cond[1]->tbl_col_q . ')';
            }
            else
            {
                foreach ($on_cond as $field=>$cond)
                {
                    if ( !is_array($cond) ) 
                        $on_cond[$field] = array('eq'=>$cond,'type'=>'identifier');
                }
                $on_cond = $this->conditions( $on_cond );
            }
            $join_clause = "$table ON $on_cond";
        }
        $join_clause = (empty($join_type) ? "JOIN " : (strtoupper($join_type) . " JOIN ")) . $join_clause;
        if ( !empty($this->clus['join_clauses']) )
            $join_clause = $this->clus['join_clauses'] . "\n" . $join_clause;
        $this->clus['join_clauses'] = $join_clause;
        return $this;
    }
    
    public function where( $conditions, $boolean_connective="AND" )
    {
        if ( empty($conditions) ) return $this;
        $boolean_connective = strtoupper($boolean_connective);
        if ( "OR" !== $boolean_connective ) $boolean_connective = "AND";
        $conditions = $this->conditions( $conditions, false );
        if ( !empty($this->clus['where_conditions']) )
            $conditions = $this->clus['where_conditions'] . " ".$boolean_connective." " . $conditions;
        $this->clus['where_conditions'] = $conditions;
        return $this;
    }
    
    public function group( $col, $dir="asc" )
    {
        $dir = strtoupper($dir);
        if ( "DESC" !== $dir ) $dir = "ASC";
        $column = $this->refs( $col, $this->cols );
        $group_condition = $column[0]->alias_q . " " . $dir;
        if ( !empty($this->clus['group_conditions']) )
            $group_condition = $this->clus['group_conditions'] . ',' . $group_condition;
        $this->clus['group_conditions'] = $group_condition;
        return $this;
    }
    
    public function having( $conditions, $boolean_connective="AND" )
    {
        if ( empty($conditions) ) return $this;
        $boolean_connective = strtoupper($boolean_connective);
        if ( "OR" !== $boolean_connective ) $boolean_connective = "AND";
        $conditions = $this->conditions( $conditions, false );
        if ( !empty($this->clus['having_conditions']) )
            $conditions = $this->clus['having_conditions'] . " ".$boolean_connective." " . $conditions;
        $this->clus['having_conditions'] = $conditions;
        return $this;
    }
    
    public function order( $col, $dir="asc" )
    {
        $dir = strtoupper($dir);
        if ( "DESC" !== $dir ) $dir = "ASC";
        $column = $this->refs( $col, $this->cols );
        $order_condition = $column[0]->alias_q . " " . $dir;
        if ( !empty($this->clus['order_conditions']) )
            $order_condition = $this->clus['order_conditions'] . ',' . $order_condition;
        $this->clus['order_conditions'] = $order_condition;
        return $this;
    }
    
    public function limit( $count, $offset=0 )
    {
        $this->clus['count'] = intval($count,10);
        $this->clus['offset'] = intval($offset,10);
        return $this;
    }
    
    public function page( $page, $perpage )
    {
        $page = intval($page,10); $perpage = intval($perpage,10);
        return $this->limit( $perpage, $page*$perpage );
    }
    
    public function join_conditions( $join, &$conditions )
    {
        $j = 0;
        foreach ($conditions as $f=>$cond)
        {
            $ref = DialectRef::parse( $f, $this );
            $field = $ref->col;
            if ( !isset($join[$field]) ) continue;
            $main_table = $join[$field]['table'];
            $main_id = $join[$field]['id'];
            $join_table = $join[$field]['join'];
            $join_id = $join[$field]['join_id'];
            
            $j++; $join_alias = "{$join_table}{$j}";
            
            $where = array( );
            if ( isset($join[$field]['key']) && $field !== $join[$field]['key'] )
            {
                $join_key = $join[$field]['key'];
                $where["{$join_alias}.{$join_key}"] = $field;
            }
            else
            {
                $join_key = $field;
            }
            if ( isset($join[$field]['value']) )
            {
                $join_value = $join[$field]['value'];
                $where["{$join_alias}.{$join_value}"] = $cond;
            }
            else
            {
                $join_value = $join_key;
                $where["{$join_alias}.{$join_value}"] = $cond;
            }
            $this->join(
                "{$join_table} AS {$join_alias}", 
                "{$main_table}.{$main_id}={$join_alias}.{$join_id}", 
                "inner"
            )->where( $where );
            
            unset( $conditions[$f] );
        }
        return $this;
    }
    
    public function refs( $refs, &$lookup ) 
    {
        $rs = (array)$refs;
        $refs = array( );
        foreach ($rs as $r)
        {
            $r = explode( ',', $r );
            foreach ($r as $ref)
            {
                $ref = DialectRef::parse( $ref, $this );
                if ( !isset($lookup[$ref->alias]) ) 
                {
                    $lookup[$ref->alias] = $ref;
                    if ( $ref->tbl_col !== $ref->alias && !isset($lookup[ $ref->tbl_col ]) ) $lookup[ $ref->tbl_col ] = $ref;
                }
                else
                {                    
                    $ref = $lookup[ $ref->alias ];
                }
                $refs[] = $ref;
            }
        }
        return $refs;
    }
    
    public function conditions( $conditions, $can_use_alias=false )
    {
        if ( empty($conditions) ) return '';
        if ( is_string($conditions) ) return $conditions;
        
        $condquery = '';
        $conds = array();
        $fmt = true === $can_use_alias ? 'alias_q' : 'tbl_col_q';
        
        foreach ($conditions as $f=>$value)
        {
            $field = $this->refs( $f, $this->cols );
            $field = $field[0]->{$fmt};
            
            if ( is_array( $value ) )
            {
                $type = isset($value['type']) ? $value['type'] : 'string';
                
                if ( isset($value['multi_like']) )
                {
                    $conds[] = $this->multi_like($field, $value['multi_like']);
                }
                elseif ( isset($value['like']) )
                {
                    $conds[] = "$field LIKE " . ('raw' === $type ? $value['like'] : $this->like($value['like']));
                }
                elseif ( isset($value['not_like']) )
                {
                    $conds[] = "$field NOT LIKE " . ('raw' === $type ? $value['not_like'] : $this->like($value['not_like']));
                }
                elseif ( isset($value['in']) )
                {
                    $v = (array) $value['in'];
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || is_int($v[0]) )
                    {
                        $v = $this->intval( $v );
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = "$field IN (" . implode(',', $v) . ")";
                }
                elseif ( isset($value['not_in']) )
                {
                    $v = (array) $value['not_in'];
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || is_int($v[0]) )
                    {
                        $v = $this->intval( $v );
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = "$field NOT IN (" . implode(',', $v) . ")";
                }
                elseif ( isset($value['between']) )
                {
                    $v = (array) $value['between'];
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || (is_int($v[0]) && is_int($v[1])) )
                    {
                        $v = $this->intval( $v );
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = "$field BETWEEN {$v[0]} AND {$v[1]}";
                }
                elseif ( isset($value['not_between']) )
                {
                    $v = (array) $value['not_between'];
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || (is_int($v[0]) && is_int($v[1])) )
                    {
                        $v = $this->intval( $v );
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = "$field < {$v[0]} OR $field > {$v[1]}";
                }
                elseif ( isset($value['gt']) || isset($value['gte']) )
                {
                    $op = isset($value['gt']) ? "gt" : "gte";
                    $v = $value[ $op ];
                    
                    if ( 'raw' === type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || is_int($v) )
                    {
                        $v = $this->intval( $v );
                    }
                    elseif ( 'identifier' === $type || 'field' === $type )
                    {
                        $v = $this->refs( $v, $this->cols );
                        $v = $v[0]->{$fmt};
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = $field . ('gt'===$op ? " > " : " >= ") . $v;
                }
                elseif ( isset($value['lt']) || isset($value['lte']) )
                {
                    $op = isset($value['lt']) ? "lt" : "lte";
                    $v = $value[ $op ];
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || is_int($v) )
                    {
                        $v = $this->intval( $v );
                    }
                    elseif ( 'identifier' === $type || 'field' === $type )
                    {
                        $v = $this->refs( $v, $this->cols );
                        $v = $v[0]->{$fmt};
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = $field . ('lt'===$op ? " < " : " <= ") . $v;
                }
                elseif ( isset($value['not_equal']) || isset($value['not_eq']) )
                {
                    $op = isset($value['not_eq']) ? "not_eq" : "not_equal";
                    $v = $value[ $op ];
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || is_int($v) )
                    {
                        $v = $this->intval( $v );
                    }
                    elseif ( 'identifier' === $type || 'field' === $type )
                    {
                        $v = $this->refs( $v, $this->cols );
                        $v = $v[0]->{$fmt};
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = "$field <> $v";
                }
                elseif ( isset($value['equal']) || isset($value['eq']) )
                {
                    $op = isset($value['eq']) ? "eq" : "equal";
                    $v = $value[ $op ];
                    
                    if ( 'raw' === $type )
                    {
                        // raw, do nothing
                    }
                    elseif ( 'integer' === $type || is_int($v) )
                    {
                        $v = $this->intval( $v );
                    }
                    elseif ( 'identifier' === $type || 'field' === $type )
                    {
                        $v = $this->refs( $v, $this->cols );
                        $v = $v[0]->{$fmt};
                    }
                    else
                    {
                        $v = $this->quote( $v );
                    }
                    $conds[] = "$field = $v";
                }
            }
            else
            {
                $conds[] = "$field = " . (is_int($value) ? $value : $this->quote($value));
            }
        }
        
        if ( !empty($conds) ) $condquery = '(' . implode(') AND (', $conds) . ')';
        return $condquery;
    }
    
    public function tbl( $table )
    {
        return is_array( $table ) ? array_map(array($this, 'tbl'), (array)$table): $this->p.$table;
    }
    
    public function intval( $v )
    {
        return is_array( $v ) ? array_map( array($this, 'intval'), $v ) : intval( $v, 10 );
    }
    
    public function quote_name( $f )
    {
        return is_array( $f )
        ? array_map( array($this, 'quote_name'), $f )
        : ('*' === $f ? $f : $this->qn[0] . $f . $this->qn[1]);
    }
    
    public function quote( $v )
    {
        return is_array( $v )
        ? array_map( array($this, 'quote'), $v )
        : ($this->q[0] . $this->esc( $v ) . $this->q[1]);
    }
    
    public function like( $v )
    {
        if ( is_array( $v ) ) return array_map( array($this, 'like'), $v );
        $q = $this->q;
        $e = $this->escdb ? array('','') : $this->e;
        return $e[0] . $q[0] . '%' . $this->esc_like( $this->esc( $v ) ) . '%' . $q[1] . $e[1];
    }
    
    public function multi_like( $f, $v, $trimmed=true )
    {
        $trimmed = false !== $trimmed;
        $like = "$f LIKE ";
        $ORs = explode(',', $v);
        if ( $trimmed ) $ORs = array_filter(array_map('trim', $ORs), 'strlen');
        foreach($ORs as &$OR)
        {
            $ANDs = explode('+', $OR);
            if ( $trimmed ) $ANDs = array_filter(array_map('trim', $ANDs), 'strlen');
            foreach($ANDs as &$AND) $AND = $like . $this->like($AND);
            $OR = '(' . implode(' AND ', $ANDs) . ')';
        }
        return implode(' OR ', $ORs);
    }
    
    public function esc( $v )
    {
        if ( $this->escdb )
        {
            return is_array( $v )
            ? array_map( $this->escdb, $v )
            : call_user_func( $this->escdb, $v );
        }
        elseif ( is_array( $v ) )
        {
            return array_map( array($this, 'esc'), $v );
        }
        else
        {
            // simple ecsaping using addslashes
            // '"\ and NUL (the NULL byte).
            $chars = '\\' . chr(0); $esc = '\\';
            $q =& $this->q; $v = strval($v); $ve = '';
            for($i=0,$l=strlen($v); $i<$l; $i++)
            {
                $c = $v[$i];
                if ( $q[0] === $c ) $ve .= $q[2];
                elseif ( $q[1] === $c ) $ve .= $q[3];
                else $ve .= self::addslashes( $c, $chars, $esc );
            }
            return $ve;
        }
    }
    
    public function esc_like( $v )
    {
        if ( is_array( $v ) ) return array_map( array($this, 'esc_like'), $v );
        $chars = '_%'; $esc = '\\';
        return self::addslashes( $v, $chars, $esc );
    }
    
    public static function defaults( $data, $defau=array(), $overwrite=false )
    {
        $overwrite = true === $overwrite;
        foreach((array)$defau as $k=>$v)
        {
            if ( $overwrite || !isset($data[$k]) )
                $data[ $k ] = $v;
        }
        return $data;
    }
    
    /*public static function filter( $data, $filt, $positive=true )
    {
        if ( $positive )
        {
            $filtered = array( );
            foreach((array)$filt as $field)
            {
                if ( isset($data[$field]) ) 
                    $filtered[$field] = $data[$field];
            }
            return $filtered;
        }
        else
        {
            $filtered = array( );
            foreach($data as $field=>$v)
            {
                if ( !in_array($field, $filt) ) 
                    $filtered[$field] = $v;
            }
            return $filtered;
        }
    }*/
    
    public static function addslashes( $s, $chars=null, $esc='\\' )
    {
        $s2 = '';
        if ( null === $chars ) $chars = '\\"\'' . chr(0);
        for ($i=0,$l=strlen($s); $i<$l; $i++)
        {
            $c = $s[ $i ];
            $s2 .= false === strpos($chars, $c) ? $c : (0 === ord($c) ? '\\0' : ($esc.$c));
        }
        return $s2;
    }
}
}