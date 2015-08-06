<?php
/**
*   Dialect, 
*   a simple and flexible Cross-Platform SQL Builder for PHP, Python, Node/JS, ActionScript
* 
*   @version: 0.2.1
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
        
        $out = '';
        
        foreach ($this->tpl as $tpli)
        {
            $notIsSub = $tpli[ 0 ];
            $s = $tpli[ 1 ];
            $out .= $notIsSub ? $s : strval($args[ $s ]);
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
    const VERSION = "0.2.1";
    const TPL_RE = '/\\$\\(([^\\)]+)\\)/';
    
    public static $dialect = array(
    'mysql'            => array(
         'quote'        => array( "'", '`', '' )
        ,'clauses'      => array(
         // https://dev.mysql.com/doc/refman/5.0/en/select.html, https://dev.mysql.com/doc/refman/5.0/en/join.html, https://dev.mysql.com/doc/refman/5.5/en/expressions.html
         'select'  => array('select','from','join','where','group','having','order','limit')
         // https://dev.mysql.com/doc/refman/5.0/en/insert.html
        ,'insert'  => array('insert','values')
         // https://dev.mysql.com/doc/refman/5.0/en/update.html
        ,'update'  => array('update','set','where','order','limit')
         // https://dev.mysql.com/doc/refman/5.0/en/delete.html
        ,'delete'  => array('delete','from','where','order','limit')
        )
        ,'tpl'        => array(
         'select'   => 'SELECT $(columns)'
        ,'insert'   => 'INSERT INTO $(tables) ($(columns))'
        ,'update'   => 'UPDATE $(tables)'
        ,'delete'   => 'DELETE '
        ,'values'   => 'VALUES $(values_values)'
        ,'values_'  => '$(values),$(values_values)'
        ,'set'      => 'SET $(set_values)'
        ,'set_'     => '$(set),$(set_values)'
        ,'from'     => 'FROM $(tables)'
        ,'from_'    => '$(from),$(tables)'
        ,'join'     => '$(join_type)JOIN $(join_clause)'
        ,'join_'    => "\$(join)\n\$(join_type)JOIN \$(join_clause)"
        ,'where'    => 'WHERE $(conditions)'
        ,'where_'   => '$(where) $(boolean_connective) $(conditions)'
        ,'group'    => 'GROUP BY $(column) $(dir)'
        ,'group_'   => '$(group),$(column) $(dir)'
        ,'having'   => 'HAVING $(conditions)'
        ,'having_'  => '$(having) $(boolean_connective) $(conditions)'
        ,'order'    => 'ORDER BY $(column) $(dir)'
        ,'order_'   => '$(order),$(column) $(dir)'
        ,'limit'    => 'LIMIT $(offset),$(count)'

        ,'year'     => 'YEAR($(column))'
        ,'month'    => 'MONTH($(column))'
        ,'day'      => 'DAY($(column))'
        ,'hour'     => 'HOUR($(column))'
        ,'minute'   => 'MINUTE($(column))'
        ,'second'   => 'SECOND($(column))'
        )
    )
    ,'postgre'          => array(
         'quote'        => array( '`', '"', 'E' )
        ,'clauses'      => array(
         // http://www.postgresql.org/docs/
         'select'  => array('select','from','join','where','group','having','order','limit')
        ,'insert'  => array('insert','values')
        ,'update'  => array('update','set','where','order','limit')
        ,'delete'  => array('delete','from','where','order','limit')
        )
        ,'tpl'        => array(
         'select'   => 'SELECT $(columns)'
        ,'insert'   => 'INSERT INTO $(tables) ($(columns))'
        ,'update'   => 'UPDATE $(tables)'
        ,'delete'   => 'DELETE '
        ,'values'   => 'VALUES $(values_values)'
        ,'values_'  => '$(values),$(values_values)'
        ,'set'      => 'SET $(set_values)'
        ,'set_'     => '$(set),$(set_values)'
        ,'from'     => 'FROM $(tables)'
        ,'from_'    => '$(from),$(tables)'
        ,'join'     => '$(join_type)JOIN $(join_clause)'
        ,'join_'    => "\$(join)\n\$(join_type)JOIN \$(join_clause)"
        ,'where'    => 'WHERE $(conditions)'
        ,'where_'   => '$(where) $(boolean_connective) $(conditions)'
        ,'group'    => 'GROUP BY $(column) $(dir)'
        ,'group_'   => '$(group),$(column) $(dir)'
        ,'having'   => 'HAVING $(conditions)'
        ,'having_'  => '$(having) $(boolean_connective) $(conditions)'
        ,'order'    => 'ORDER BY $(column) $(dir)'
        ,'order_'   => '$(order),$(column) $(dir)'
        ,'limit'    => 'LIMIT $(count) OFFSET $(offset)'

        ,'year'     => 'EXTRACT (YEAR FROM $(column))'
        ,'month'    => 'EXTRACT (MONTH FROM $(column))'
        ,'day'      => 'EXTRACT (DAY FROM $(column))'
        ,'hour'     => 'EXTRACT (HOUR FROM $(column))'
        ,'minute'   => 'EXTRACT (MINUTE FROM $(column))'
        ,'second'   => 'EXTRACT (SECOND FROM $(column))'
        )
    )
    );
    
    private $clau = null;
    private $clus = null;
    private $vews = null;
    private $tbls = null;
    private $cols = null;
   
    public $db = null;
    public $escdb = null;
    public $p = null;
    
    public $clauses = null;
    public $tpl = null;
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
        
        $this->db = null;
        $this->escdb = null;
        $this->p = '';
        
        $this->clauses =& self::$dialect[ $type ][ 'clauses' ];
        $this->tpl =& self::$dialect[ $type ][ 'tpl' ];
        $this->q = self::$dialect[ $type ][ 'quote' ][ 0 ];
        $this->qn = self::$dialect[ $type ][ 'quote' ][ 1 ];
        $this->e = isset(self::$dialect[ $type ][ 'quote' ][ 2 ]) ? self::$dialect[ $type ][ 'quote' ][ 2 ] : '';
    }
    
    public function dispose( )
    {
        $this->clau = null;
        $this->clus = null;
        $this->tbls = null;
        $this->cols = null;
        $this->vews = null;
        
        $this->db = null;
        $this->escdb = null;
        $this->p = null;
        
        $this->clauses = null;
        $this->tpl = null;
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
        $this->clau = $clause;
        $this->clus = array( );
        $this->tbls = array( );
        $this->cols = array( );
        
        foreach($this->clauses[ $this->clau ] as $clause)
        {
            if ( isset($this->tpl[ $clause ]) && !($this->tpl[ $clause ] instanceof DialectTpl) )
                $this->tpl[ $clause ] = new DialectTpl( $this->tpl[ $clause ], self::TPL_RE );
            
            // continuation clause if exists, ..
            $c = "{$clause}_";
            if ( isset($this->tpl[ $c ]) && !($this->tpl[ $c ] instanceof DialectTpl) )
                $this->tpl[ $c ] = new DialectTpl( $this->tpl[ $c ], self::TPL_RE );
        }
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
        if ( $this->clau && !empty($this->clus) && isset($this->clauses[ $this->clau ]) )
        {
            $query = array( );
            foreach($this->clauses[ $this->clau ] as $clause)
            {
                if ( isset($this->clus[ $clause ]) )
                    $query[] = $this->clus[ $clause ];
            }
            $query = implode("\n", $query);
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
            $pattern = '/' . $left . '([rlfds]):([0-9a-zA-Z_]+)' . $right . '/';
            $prepared = '';
            while ( preg_match($pattern, $query, $m, PREG_OFFSET_CAPTURE) )
            {
                $pos = $m[0][1];
                $len = strlen($m[0][0]);
                $param = $m[2][0];
                if ( isset($args[$param]) )
                {
                    $type = $m[1][0];
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
        $this->clus['select'] = $this->tpl['select']->render( array( 'columns'=>$columns ) );
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
            $this->clus = $this->defaults( $this->clus, $view->clus, true );
            $this->tbls = $this->defaults( array(), $view->tbls, true );
            $this->cols = $this->defaults( array(), $view->cols, true );
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
            $this->clus['insert'] = $this->tpl['insert']->render( array( 'tables'=>$tables, 'columns'=>$columns ) );
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
        if ( isset($this->clus['values']) ) $this->clus['values'] = $this->tpl['values_']->render( array( 'values'=>$this->clus['values'], 'values_values'=>$insert_values ) );
        else $this->clus['values'] = $this->tpl['values']->render( array( 'values_values'=>$insert_values ) );
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
            $this->clus = $this->defaults( $this->clus, $view->clus, true );
            $this->tbls = $this->defaults( array(), $view->tbls, true );
            $this->cols = $this->defaults( array(), $view->cols, true );
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
            $this->clus['update'] = $this->tpl['update']->render( array( 'tables'=>$tables ) );
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
        if ( isset($this->clus['set']) ) $this->clus['set'] = $this->tpl['set_']->render( array( 'set'=>$this->clus['set'], 'set_values'=>$set_values ) );
        else $this->clus['set'] = $this->tpl['set']->render( array( 'set_values'=>$set_values ) );
        return $this;
    }
    
    public function del( )
    {
        $this->reset('delete');
        $this->clus['delete'] = $this->tpl['delete']->render( array() );
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
            $this->clus = $this->defaults( $this->clus, $view->clus, true );
            $this->tbls = $this->defaults( array(), $view->tbls, true );
            $this->cols = $this->defaults( array(), $view->cols, true );
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
            if ( isset($this->clus['from']) ) $this->clus['from'] = $this->tpl['from_']->render( array( 'from'=>$this->clus['from'], 'tables'=>$tables ) );
            else $this->clus['from'] = $this->tpl['from']->render( array( 'tables'=>$tables ) );
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
        $join_type = empty($join_type) ? "" : (strtoupper($join_type) . " ");
        if ( isset($this->clus['join']) ) $this->clus['join'] = $this->tpl['join_']->render( array( 'join'=>$this->clus['join'], 'join_clause'=>$join_clause, 'join_type'=>$join_type ) );
        else $this->clus['join'] = $this->tpl['join']->render( array( 'join_clause'=>$join_clause, 'join_type'=>$join_type ) );
        return $this;
    }
    
    public function where( $conditions, $boolean_connective="AND" )
    {
        if ( empty($conditions) ) return $this;
        $boolean_connective = strtoupper($boolean_connective);
        if ( "OR" !== $boolean_connective ) $boolean_connective = "AND";
        $conditions = $this->conditions( $conditions, false );
        if ( isset($this->clus['where']) ) $this->clus['where'] = $this->tpl['where_']->render( array( 'where'=>$this->clus['where'], 'boolean_connective'=>$boolean_connective, 'conditions'=>$conditions ) );
        else $this->clus['where'] = $this->tpl['where']->render( array( 'boolean_connective'=>$boolean_connective, 'conditions'=>$conditions ) );
        return $this;
    }
    
    public function group( $col, $dir="asc" )
    {
        $dir = strtoupper($dir);
        if ( "DESC" !== $dir ) $dir = "ASC";
        $column = $this->refs( $col, $this->cols );
        $column = $column[0]->alias_q;
        if ( isset($this->clus['group']) ) $this->clus['group'] = $this->tpl['group_']->render( array( 'group'=>$this->clus['group'], 'column'=>$column, 'dir'=>$dir ) );
        else $this->clus['group'] = $this->tpl['group']->render( array( 'column'=>$column, 'dir'=>$dir ) );
        return $this;
    }
    
    public function having( $conditions, $boolean_connective="AND" )
    {
        if ( empty($conditions) ) return $this;
        $boolean_connective = strtoupper($boolean_connective);
        if ( "OR" !== $boolean_connective ) $boolean_connective = "AND";
        $conditions = $this->conditions( $conditions, true );
        if ( isset($this->clus['having']) ) $this->clus['having'] = $this->tpl['having_']->render( array( 'having'=>$this->clus['having'], 'boolean_connective'=>$boolean_connective, 'conditions'=>$conditions ) );
        else $this->clus['having'] = $this->tpl['having']->render( array( 'boolean_connective'=>$boolean_connective, 'conditions'=>$conditions ) );
        return $this;
    }
    
    public function order( $col, $dir="asc" )
    {
        $dir = strtoupper($dir);
        if ( "DESC" !== $dir ) $dir = "ASC";
        $column = $this->refs( $col, $this->cols );
        $column = $column[0]->alias_q;
        if ( isset($this->clus['order']) ) $this->clus['order'] = $this->tpl['order_']->render( array( 'order'=>$this->clus['order'], 'column'=>$column, 'dir'=>$dir ) );
        else $this->clus['order'] = $this->tpl['order']->render( array( 'column'=>$column, 'dir'=>$dir ) );
        return $this;
    }
    
    public function limit( $count, $offset=0 )
    {
        $count = intval($count,10); $offset = intval($offset,10);
        $this->clus['limit'] = $this->tpl['limit']->render( array( 'offset'=>$offset, 'count'=>$count ) );
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
    
    public function defaults( $data, $defaults=array(), $overwrite=false )
    {
        $overwrite = true === $overwrite;
        foreach((array)$defaults as $k=>$v)
        {
            if ( $overwrite || !isset($data[$k]) )
                $data[ $k ] = $v;
        }
        return $data;
    }
    
    public function filter( $data, $filter, $positive=true )
    {
        if ( $positive )
        {
            $filtered = array( );
            foreach((array)$filter as $field)
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
                if ( !in_array($field, $filter) ) 
                    $filtered[$field] = $v;
            }
            return $filtered;
        }
    }
    
    public function tbl( $table )
    {
        if ( is_array($table) ) return array_map(array($this, 'tbl'), (array)$table);
        return $this->p.$table;
    }
    
    public function intval( $v )
    {
        if ( is_array( $v ) )
            return array_map( array($this, 'intval'), $v );
        return intval( $v, 10 );
    }
    
    public function quote_name( $f )
    {
        if ( is_array( $f ) )
            return array_map( array($this, 'quote_name'), $f );
        return '*' === $f ? $f : $this->qn . $f . $this->qn;
    }
    
    public function quote( $v )
    {
        if ( is_array( $v ) )
            return array_map( array($this, 'quote'), $v );
        $q = $this->q;
        $e = $this->escdb ? '' : $this->e;
        return $e . $q . $this->esc($v) . $q;
    }
    
    public function esc( $v )
    {
        if ( is_array( $v ) )
        {
            if ( $this->escdb ) 
                return array_map( $this->escdb, $v );
            else
                return array_map( array($this, 'esc'), $v );
        }
        if ( $this->escdb ) 
        {
            return call_user_func( $this->escdb, $v );
        }
        else
        {
            // simple ecsaping using addslashes
            // '"\ and NUL (the NULL byte).
            $chars = $this->q . '"\'\\' . chr(0); 
            $esc = '\\';
            return self::addslashes( $v, $chars, $esc );
        }
    }
    
    public function esc_like( $v )
    {
        if ( is_array( $v ) )
            return array_map( array($this, 'esc_like'), $v );
        $chars = '_%'; $esc = '\\';
        return self::addslashes( $v, $chars, $esc );
    }
    
    public function like( $v )
    {
        if ( is_array( $v ) )
            return array_map( array($this, 'like'), $v );
        $q = $this->q;
        $e = $this->escdb ? '' : $this->e;
        return $e . $q . '%' . $this->esc_like( $this->esc( $v ) ) . '%' . $q;
    }
    
    public function multi_like( $f, $v, $doTrim=true )
    {
        $like = "$f LIKE ";
        $ORs = explode(',', $v);
        if ( $doTrim ) $ORs = array_filter(array_map('trim', $ORs), 'strlen');
        foreach($ORs as &$OR)
        {
            $ANDs = explode('+', $OR);
            if ( $doTrim ) $ANDs = array_filter(array_map('trim', $ANDs), 'strlen');
            foreach($ANDs as &$AND)
            {
                $AND = $like . $this->like($AND);
            }
            $OR = '(' . implode(' AND ', $ANDs) . ')';
        }
        return implode(' OR ', $ORs);
    }
    
    public function year( $column )
    {
        if ( !($this->tpl['year'] instanceof DialectTpl) ) $this->tpl['year'] = new DialectTpl( $this->tpl['year'], self::TPL_RE );
        return $this->tpl['year']->render( array( 'column'=>$column ) );
    }
    
    public function month( $column )
    {
        if ( !($this->tpl['month'] instanceof DialectTpl) ) $this->tpl['month'] = new DialectTpl( $this->tpl['month'], self::TPL_RE );
        return $this->tpl['month']->render( array( 'column'=>$column ) );
    }
    
    public function day( $column )
    {
        if ( !($this->tpl['day'] instanceof DialectTpl) ) $this->tpl['day'] = new DialectTpl( $this->tpl['day'], self::TPL_RE );
        return $this->tpl['day']->render( array( 'column'=>$column ) );
    }
    
    public function hour( $column )
    {
        if ( !($this->tpl['hour'] instanceof DialectTpl) ) $this->tpl['hour'] = new DialectTpl( $this->tpl['hour'], self::TPL_RE );
        return $this->tpl['hour']->render( array( 'column'=>$column ) );
    }
    
    public function minute( $column )
    {
        if ( !($this->tpl['minute'] instanceof DialectTpl) ) $this->tpl['minute'] = new DialectTpl( $this->tpl['minute'], self::TPL_RE );
        return $this->tpl['minute']->render( array( 'column'=>$column ) );
    }
    
    public function second( $column )
    {
        if ( !($this->tpl['second'] instanceof DialectTpl) ) $this->tpl['second'] = new DialectTpl( $this->tpl['second'], self::TPL_RE );
        return $this->tpl['second']->render( array( 'column'=>$column ) );
    }
    
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