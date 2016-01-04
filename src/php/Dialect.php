<?php
/**
*   Dialect, 
*   a simple and flexible Cross-Platform SQL Builder for PHP, Python, Node/XPCOM/JS, ActionScript
* 
*   @version: 0.4.0
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
    public static function multisplit_gram( $tpl )
    {
        $SEPL = '{'; $SEPR = '}';
        $default_value = null;
        $l = strlen($tpl);
        $i = 0; $a = array(); $stack = array(); $s = '';
        while( $i < $l )
        {
            $c = $tpl[$i++];
            if ( $SEPL === $c )
            {
                if ( strlen($s) ) $a[] = array(1, $s);
                $s = '';
                
                if ( $i < $l && '?' === $tpl[$i] )
                {
                    // optional block
                    $stack[] = $a;
                    $a = array();
                    $i += 1;
                }
                // else argument
            }
            elseif ( $SEPR === $c )
            {
                if ( !empty($stack) && '?' === $tpl[$i-2] && '(' === $tpl[$i] )
                {
                    $s = substr($s, 0, -1);
                    // optional block end
                    $p = strpos($tpl, ')', $i+1);
                    $argument = substr($tpl, $i+1, $p-$i-1);
                    $b = $a; $a = array_pop($stack);
                    $a[] = array(-1, $argument, $b);
                    $i = $p+1;
                }
                else
                {
                    // argument
                    $argument = $s;
                    $s = '';
                    $p = strpos($argument, '|');
                    if ( false !== $p )
                    {
                        $default_value = substr($argument, $p+1);
                        $argument = substr($argument, 0, $p);
                    }
                    else
                    {
                        $default_value = null;
                    }
                    $a[] = array(0, $argument, $default_value);
                }
            }
            else
            {
                $s .= $c;
            }
        }
        if ( strlen($s) ) $a[] = array(1, $s);
        return $a;
    }
    
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
        
        if ( true === $replacements )
        {
            // grammar template
            $this->tpl = self::multisplit_gram( $tpl );
        }
        else
        {
            if ( !$replacements || empty($replacements) ) $replacements = self::$defaultArgs;
            $this->tpl = is_string($replacements)
                    ? self::multisplit_re( $tpl, $replacements)
                    : self::multisplit( $tpl, (array)$replacements);
            if (true === $compiled) $this->_renderer = self::compile( $this->tpl );
        }
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
        
        $out = ''; $stack = array();
        $tpl = $this->tpl; $l = count($tpl); $i = 0;
        
        while ( $i < $l )
        {
            $t = $tpl[ $i ]; $tt = $t[ 0 ]; $s = $t[ 1 ];
            if ( 1 === $tt )
            {
                $out .= $s;
            }
            elseif ( -1 === $tt )
            {
                // optional block
                if ( isset($args[$s]) )
                {
                    $stack[] = array($tpl, $i+1, $l);
                    $tpl = $t[ 2 ];
                    $i = 0; $l = count($tpl);
                    continue;
                }
            }
            else
            {
                // default value if missing
                if ( !isset($args[$s]) )
                    $out .= isset($t[ 2 ]) ? $t[ 2 ] : 'null';
                else
                    $out .= $args[ $s ];
            }
            $i++;
            if ( $i >= $l && !empty($stack) )
            {
                $p = array_pop($stack);
                $tpl = $p[0]; $i = $p[1]; $l = $p[2];
            }
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
        $tbl = count($col) < 2 ? null : trim($col[ 0 ]);
        $col = $tbl ? trim($col[ 1 ]) : trim($col[ 0 ]);
        $col_q = $d->quote_name( $col );
        if ( $tbl )
        {
            $tbl_q = $d->quote_name( $tbl );
            $tbl_col = $tbl . '.' . $col;
            $tbl_col_q = $tbl_q . '.' . $col_q;
        }
        else
        {
            $tbl_q = null;
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
        return new self( $col, $col_q, $tbl, $tbl_q, $alias, $alias_q, 
                    $tbl_col, $tbl_col_q, $tbl_col_alias, $tbl_col_alias_q );
    }
    
    public $col = null;
    public $col_q = null;
    public $tbl = null;
    public $tbl_q = null;
    public $alias = null;
    public $alias_q = null;
    public $tbl_col = null;
    public $tbl_col_q = null;
    public $tbl_col_alias = null;
    public $tbl_col_alias_q = null;
    
    public function __construct( $col, $col_q, $tbl, $tbl_q, $alias, $alias_q, 
                                $tbl_col, $tbl_col_q, $tbl_col_alias, $tbl_col_alias_q ) 
    {
        $this->col = $col;
        $this->col_q = $col_q;
        $this->tbl = $tbl;
        $this->tbl_q = $tbl_q;
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
    const VERSION = "0.4.0";
    const TPL_RE = '/\\$\\(([^\\)]+)\\)/';
    
    public static $dialect = array(
    'mysql'            => array(
        // https://dev.mysql.com/doc/refman/5.0/en/select.html
        // https://dev.mysql.com/doc/refman/5.0/en/join.html
        // https://dev.mysql.com/doc/refman/5.5/en/expressions.html
        // https://dev.mysql.com/doc/refman/5.0/en/insert.html
        // https://dev.mysql.com/doc/refman/5.0/en/update.html
        // https://dev.mysql.com/doc/refman/5.0/en/delete.html
         'quotes'        => array( array("'","'","\\'","\\'"), array('`','`'), array('','') )
        ,'clauses'       => array(
     'select'       => "SELECT {select_columns}\nFROM {from_tables}{?\n{join_clauses}?}(join_clauses){?\nWHERE {where_conditions}?}(where_conditions){?\nGROUP BY {group_conditions}?}(group_conditions){?\nHAVING {having_conditions}?}(having_conditions){?\nORDER BY {order_conditions}?}(order_conditions){?\nLIMIT {offset|0},{count}?}(count)"
    ,'insert'       => "INSERT INTO {insert_tables} ({insert_columns})\nVALUES {values_values}"
    ,'update'       => "UPDATE {update_tables}\nSET {set_values}{?\nWHERE {where_conditions}?}(where_conditions){?\nORDER BY {order_conditions}?}(order_conditions){?\nLIMIT {offset|0},{count}?}(count)"
    ,'delete'       => "DELETE \nFROM {from_tables}{?\nWHERE {where_conditions}?}(where_conditions){?\nORDER BY {order_conditions}?}(order_conditions){?\nLIMIT {offset|0},{count}?}(count)"
        )
    )
    ,'postgre'          => array(
        // http://www.postgresql.org/docs/
        // http://www.postgresql.org/docs/8.2/static/sql-syntax-lexical.html
         'quotes'        => array( array("E'","'","''","''"), array('"','"'), array('','') )
        ,'clauses'       => array(
     'select'       => "SELECT {select_columns}\nFROM {from_tables}{?\n{join_clauses}?}(join_clauses){?\nWHERE {where_conditions}?}(where_conditions){?\nGROUP BY {group_conditions}?}(group_conditions){?\nHAVING {having_conditions}?}(having_conditions){?\nORDER BY {order_conditions}?}(order_conditions){?\nLIMIT {count} OFFSET {offset|0}?}(count)"
    ,'insert'       => "INSERT INTO {insert_tables} ({insert_columns})\nVALUES {values_values}"
    ,'update'       => "UPDATE {update_tables}\nSET {set_values}{?\nWHERE {where_conditions}?}(where_conditions){?\nORDER BY {order_conditions}?}(order_conditions){?\nLIMIT {count} OFFSET {offset|0}?}(count)"
    ,'delete'       => "DELETE \nFROM {from_tables}{?\nWHERE {where_conditions}?}(where_conditions){?\nORDER BY {order_conditions}?}(order_conditions){?\nLIMIT {count} OFFSET {offset|0}?}(count)"
        )
    )
    ,'sqlserver'          => array(
        // https://msdn.microsoft.com/en-us/library/ms189499.aspx
        // https://msdn.microsoft.com/en-us/library/ms174335.aspx
        // https://msdn.microsoft.com/en-us/library/ms177523.aspx
        // https://msdn.microsoft.com/en-us/library/ms189835.aspx
        // https://msdn.microsoft.com/en-us/library/ms179859.aspx
         'quotes'        => array( array("'","'","''","''"), array('[',']'), array(''," ESCAPE '\\'") )
        ,'clauses'       => array(
     'select'       => "SELECT {select_columns}\nFROM {from_tables}{?\n{join_clauses}?}(join_clauses){?\nWHERE {where_conditions}?}(where_conditions){?\nGROUP BY {group_conditions}?}(group_conditions){?\nHAVING {having_conditions}?}(having_conditions){?\nORDER BY {order_conditions}?}(order_conditions)"
     // http://stackoverflow.com/questions/603724/how-to-implement-limit-with-microsoft-sql-server
     ,'select_with_limit'=> "SELECT * FROM(\nSELECT {select_columns},ROW_NUMBER() OVER (ORDER BY {order_conditions|(SELECT 1)}) AS __row__\nFROM {from_tables}{?\n{join_clauses}?}(join_clauses){?\nWHERE {where_conditions}?}(where_conditions){?\nGROUP BY {group_conditions}?}(group_conditions){?\nHAVING {having_conditions}?}(having_conditions)\n) AS __query__ WHERE __query__.__row__ BETWEEN ({offset}+1) AND ({offset}+{count})"
    ,'insert'       => "INSERT INTO {insert_tables} ({insert_columns})\nVALUES {values_values}"
    ,'update'       => "UPDATE {update_tables}\nSET {set_values}{?\nWHERE {where_conditions}?}(where_conditions){?\nORDER BY {order_conditions}?}(order_conditions)"
    ,'delete'       => "DELETE \nFROM {from_tables}{?\nWHERE {where_conditions}?}(where_conditions){?\nORDER BY {order_conditions}?}(order_conditions)"
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
        $this->clauses =& self::$dialect[ $this->type ][ 'clauses' ];
        $this->q = self::$dialect[ $this->type ][ 'quotes' ][ 0 ];
        $this->qn = self::$dialect[ $this->type ][ 'quotes' ][ 1 ];
        $this->e = isset(self::$dialect[ $this->type ][ 'quotes' ][ 2 ]) ? self::$dialect[ $this->type ][ 'quotes' ][ 2 ] : array('','');
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
        $this->clus = array( );
        $this->tbls = array( );
        $this->cols = array( );
        $this->clau = $clause;
        
        if ( !($this->clauses[ $this->clau ] instanceof DialectTpl) )
            $this->clauses[ $this->clau ] = new DialectTpl( $this->clauses[ $this->clau ], true );
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
        {
            $query = "";
            if ( 'sqlserver' === $this->type && 'select' === $this->clau && isset($this->clus[ 'count' ]) )
            {
                $this->clau = 'select_with_limit';
                if ( !($this->clauses[ $this->clau ] instanceof DialectTpl) )
                    $this->clauses[ $this->clau ] = new DialectTpl( $this->clauses[ $this->clau ], true );
            }
            $query = $this->clauses[ $this->clau ]->render( $this->clus );
        }
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
    
    public function select( $cols='*', $format=true )
    {
        $this->reset('select');
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
    
    public function insert( $tbls, $cols, $format=true )
    {
        $this->reset('insert');
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
    
    public function update( $tbls, $format=true )
    {
        $this->reset('update');
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
    
    public function del( )
    {
        $this->reset('delete');
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
                if ( !isset($lookup[$ref->tbl_col]) ) 
                {
                    $lookup[$ref->tbl_col] = $ref;
                    if ( $ref->tbl_col !== $ref->alias ) $lookup[ $ref->alias ] = $ref;
                }
                else
                {                    
                    $ref = $lookup[ $ref->tbl_col ];
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