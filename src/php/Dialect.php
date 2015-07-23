<?php
/**
*   Dialect, 
*   a simple and flexible Cross-Platform SQL Builder for PHP, Python, Node/JS, ActionScript
* 
*   @version: 0.1
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
 
class Dialect
{
    const VERSION = "0.1";
    const TPL_RE = '/\\$\\(([^\\)]+)\\)/';
    
    public static $dialect = array(
     
     'mysql'            => array(
        
         'quote'        => array( "'", '`' )
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
         'select'   => 'SELECT $(fields)'
        ,'insert'   => 'INSERT INTO $(tables) ($(fields))'
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
        ,'where_'   => '$(where) AND $(conditions)'
        ,'group'    => 'GROUP BY $(field) $(dir)'
        ,'group_'   => '$(group),$(field) $(dir)'
        ,'having'   => 'HAVING $(conditions)'
        ,'having_'  => '$(having) AND $(conditions)'
        ,'order'    => 'ORDER BY $(field) $(dir)'
        ,'order_'   => '$(order),$(field) $(dir)'
        ,'limit'    => 'LIMIT $(offset),$(count)'
        
        ,'year'     => 'YEAR($(field))'
        ,'month'    => 'MONTH($(field))'
        ,'day'      => 'DAY($(field))'
        ,'hour'     => 'HOUR($(field))'
        ,'minute'   => 'MINUTE($(field))'
        ,'second'   => 'SECOND($(field))'
        )
    )
    /*
    ,'postgre'          => array(
         'quote'        => array( "'", '`' )
        ,'clauses'      => array(
        // http://www.postgresql.org/docs/
         'select'  => array('select','from','join','where','group','having','order','limit')
        ,'insert'  => array('insert','values')
        ,'update'  => array('update','set','where','order','limit')
        ,'delete'  => array('delete','from','where','order','limit')
        )
        ,'tpl'        => array(
         'select'   => 'SELECT $(fields)'
        ,'insert'   => 'INSERT INTO $(tables) ($(fields))'
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
        ,'where_'   => '$(where) AND $(conditions)'
        ,'group'    => 'GROUP BY $(field) $(dir)'
        ,'group_'   => '$(group),$(field) $(dir)'
        ,'having'   => 'HAVING $(conditions)'
        ,'having_'  => '$(having) AND $(conditions)'
        ,'order'    => 'ORDER BY $(field) $(dir)'
        ,'order_'   => '$(order),$(field) $(dir)'
        ,'limit'    => 'LIMIT $(count) OFFSET $(offset)'
        
        ,'year'     => 'EXTRACT (YEAR FROM $(field))'
        ,'month'    => 'EXTRACT (MONTH FROM $(field))'
        ,'day'      => 'EXTRACT (DAY FROM $(field))'
        ,'hour'     => 'EXTRACT (HOUR FROM $(field))'
        ,'minute'   => 'EXTRACT (MINUTE FROM $(field))'
        ,'second'   => 'EXTRACT (SECOND FROM $(field))'
        )
    )
    */
    );
    
    public static function Tpl( $tpl, $reps=null, $compiled=false )
    {
        if ( $tpl instanceof DialectTpl ) return $tpl;
        return new DialectTpl( $tpl, $reps, $compiled );
    }
    
    private $clause = null;
    private $state = null;
    private $_views = null;
    public $clauses = null;
    public $tpl = null;
    public $db = null;
    public $prefix = null;
    public $escdb = null;
    public $q = null;
    public $qn = null;
    
    public function __construct( $type='mysql' )
    {
        $this->db = null;
        $this->prefix = '';
        $this->escdb = null;
        $this->clause = null;
        $this->state = null;
        
        $this->clauses =& self::$dialect[ $type ][ 'clauses' ];
        $this->tpl =& self::$dialect[ $type ][ 'tpl' ];
        $this->q = self::$dialect[ $type ][ 'quote' ][ 0 ];
        $this->qn = self::$dialect[ $type ][ 'quote' ][ 1 ];
        
        $this->_views = array( );
    }
    
    public function dispose( )
    {
        $this->db = null;
        $this->prefix = null;
        $this->escdb = null;
        $this->clause = null;
        $this->state = null;
        $this->clauses = null;
        $this->tpl = null;
        $this->q = null;
        $this->qn = null;
        $this->_views = null;
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
        $this->db = $db ? $db : null;
        return $this;
    }
    
    public function table_prefix( $prefix='' )
    {
        $this->prefix = $prefix ? $prefix : '';
        return $this;
    }
    
    public function escape( $escdb=null )
    {
        $this->escdb = $escdb && is_callable($escdb) ? $escdb : null;
        return $this;
    }
    
    public function reset( $clause )
    {
        $this->clause = $clause;
        $this->state = array( );
        
        foreach($this->clauses[ $this->clause ] as $clause)
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
        $this->clause = null;
        $this->state = null;
        return $this;
    }
    
    public function sql( )
    {
        $query = null;
        if ( $this->clause && !empty($this->state) && isset($this->clauses[ $this->clause ]) )
        {
            $query = array( );
            foreach($this->clauses[ $this->clause ] as $clause)
            {
                if ( isset($this->state[ $clause ]) )
                    $query[] = $this->state[ $clause ];
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
            $pattern = '/' . $left . '(ad|as|af|f|l|r|d|s):([0-9a-zA-Z_]+)' . $right . '/';
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
                        // array of references, e.g fields
                        case 'af': $param = implode( ',', $this->ref( (array)$args[$param] ) ); break;
                        // array of integers param
                        case 'ad': $param = '(' . implode( ',', $this->intval( (array)$args[$param] ) ) . ')'; break;
                        // array of strings param
                        case 'as': $param = '(' . implode( ',', $this->quote( (array)$args[$param] ) ) . ')'; break;
                        // reference, e.g field
                        case 'f': $param = $this->ref( $args[$param] ); break;
                        // like param
                        case 'l': $param = $this->like( $args[$param] ); break;
                        // raw param
                        case 'r': $param = $args[$param]; break;
                        // integer param
                        case 'd': $param = $this->intval( $args[$param] ); break;
                        // string param
                        case 's': default: $param = $this->quote( $args[$param] ); break;
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
        if ( !empty($view) && $this->clause )
        {
            $this->_views[ $view ] = (object)array('clause'=>$this->clause, 'state'=>$this->state);
            $this->clear( );
        }
        return $this;
    }
    
    public function clear_view( $view ) 
    {
        if ( !empty($view) && isset($this->_views[$view]) )
        {
           unset( $this->_views[ $view ] );
        }
        return $this;
    }
    
    public function select( $fields='*', $format=true )
    {
        $this->reset('select');
        $format = false !== $format;
        if ( !$fields || empty($fields) || '*' === $fields ) $fields = $this->quote_name('*');
        else if ( $format ) $fields = implode( ',', $this->ref((array)$fields) );
        else $fields = implode( ',', (array)$fields );
        $this->state['select'] = $this->tpl['select']->render( array( 'fields'=>$fields ) );
        return $this;
    }
    
    public function insert( $tables, $fields, $format=true )
    {
        $this->reset('insert');
        $format = false !== $format;
        $maybe_view = is_array( $tables ) ? $tables[0] : $tables;
        if ( isset($this->_views[ $maybe_view ]) && $this->clause === $this->_views[ $maybe_view ]->clause )
        {
            // using custom 'soft' view
            $this->state = $this->defaults( $this->state, $this->_views[ $maybe_view ]->state, true );
        }
        else
        {
            if ( $format )
            {
                $tables = implode(',', $this->ref((array)$tables));
                $fields = implode(',', $this->ref((array)$fields));
            }
            else
            {
                $tables = implode(',', (array)$tables);
                $fields = implode(',', (array)$fields);
            }
            $this->state['insert'] = $this->tpl['insert']->render( array( 'tables'=>$tables, 'fields'=>$fields ) );
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
                        elseif ( isset($val['string']) )
                        {
                            $vals[] = $this->quote( $val['string'] );
                        }
                        elseif ( isset($val['prepared']) )
                        {
                            $vals[] = $val['prepared'];
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
        if ( isset($this->state['values']) ) $this->state['values'] = $this->tpl['values_']->render( array( 'values'=>$this->state['values'], 'values_values'=>$insert_values ) );
        else $this->state['values'] = $this->tpl['values']->render( array( 'values_values'=>$insert_values ) );
        return $this;
    }
    
    public function update( $tables, $format=true )
    {
        $this->reset('update');
        $format = false !== $format;
        $maybe_view = is_array( $tables ) ? $tables[0] : $tables;
        if ( isset($this->_views[ $maybe_view ]) && $this->clause === $this->_views[ $maybe_view ]->clause )
        {
            // using custom 'soft' view
            $this->state = $this->defaults( $this->state, $this->_views[ $maybe_view ]->state, true );
        }
        else
        {
            if ( $format ) $tables = implode(',', $this->ref((array)$tables));
            else $tables = implode(',', (array)$tables);
            $this->state['update'] = $this->tpl['update']->render( array( 'tables'=>$tables ) );
        }
        return $this;
    }
    
    public function set( $fields_values )
    {
        if ( empty($fields_values) ) return $this;
        $set_values = array();
        foreach ($fields_values as $field=>$value)
        {
            $field = $this->ref( $field );
            if ( is_array($value) )
            {
                if ( isset($value['integer']) )
                {
                    $set_values[] = "$field = " . $this->intval($value['integer']);
                }
                elseif ( isset($value['string']) )
                {
                    $set_values[] = "$field = " . $this->quote($value['string']);
                }
                elseif ( isset($value['prepared']) )
                {
                    $set_values[] = "$field = {$value['prepared']}";
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
        if ( isset($this->state['set']) ) $this->state['set'] = $this->tpl['set_']->render( array( 'set'=>$this->state['set'], 'set_values'=>$set_values ) );
        else $this->state['set'] = $this->tpl['set']->render( array( 'set_values'=>$set_values ) );
        return $this;
    }
    
    public function del( )
    {
        $this->reset('delete');
        $this->state['delete'] = $this->tpl['delete']->render( array() );
        return $this;
    }
    
    public function from( $tables, $format=true )
    {
        if ( empty($tables) ) return $this;
        $format = false !== $format;
        $maybe_view = is_array( $tables ) ? $tables[0] : $tables;
        if ( isset($this->_views[ $maybe_view ]) && $this->clause === $this->_views[ $maybe_view ]->clause )
        {
            // using custom 'soft' view
            $this->state = $this->defaults( $this->state, $this->_views[ $maybe_view ]->state, true );
        }
        else
        {
            if ( $format ) $tables = implode(',', $this->ref((array)$tables));
            else $tables = implode(',', (array)$tables);
            if ( isset($this->state['from']) ) $this->state['from'] = $this->tpl['from_']->render( array( 'from'=>$this->state['from'], 'tables'=>$tables ) );
            else $this->state['from'] = $this->tpl['from']->render( array( 'tables'=>$tables ) );
        }
        return $this;
    }
    
    public function join( $table, $on_cond=null, $join_type=null )
    {
        $table = $this->ref( $table );
        if ( empty($on_cond) )
        {
            $join_clause = $table;
        }
        else
        {
            if ( is_string( $on_cond ) )
            {
                $on_cond = '(' . implode( '=', $this->ref( explode( '=', $on_cond ) ) ) . ')';
            }
            else
            {
                foreach ($on_cond as $field=>$cond)
                {
                    if ( !is_array($cond) ) $on_cond[$field] = array('eq'=>$cond,'type'=>'field');
                }
                $on_cond = $this->conditions( $on_cond );
            }
            $join_clause = "$table ON $on_cond";
        }
        $join_type = empty($join_type) ? "" : (strtoupper($join_type) . " ");
        if ( isset($this->state['join']) ) $this->state['join'] = $this->tpl['join_']->render( array( 'join'=>$this->state['join'], 'join_clause'=>$join_clause, 'join_type'=>$join_type ) );
        else $this->state['join'] = $this->tpl['join']->render( array( 'join_clause'=>$join_clause, 'join_type'=>$join_type ) );
        return $this;
    }
    
    public function where( $conditions )
    {
        if ( empty($conditions) ) return $this;
        $conditions = $this->conditions( $conditions );
        if ( isset($this->state['where']) ) $this->state['where'] = $this->tpl['where_']->render( array( 'where'=>$this->state['where'], 'conditions'=>$conditions ) );
        else $this->state['where'] = $this->tpl['where']->render( array( 'conditions'=>$conditions ) );
        return $this;
    }
    
    public function group( $field, $dir="asc" )
    {
        $dir = strtoupper($dir);
        if ( "DESC" !== $dir ) $dir = "ASC";
        $field = $this->ref( $field );
        if ( isset($this->state['group']) ) $this->state['group'] = $this->tpl['group_']->render( array( 'group'=>$this->state['group'], 'field'=>$field, 'dir'=>$dir ) );
        else $this->state['group'] = $this->tpl['group']->render( array( 'field'=>$field, 'dir'=>$dir ) );
        return $this;
    }
    
    public function having( $conditions )
    {
        if ( empty($conditions) ) return $this;
        $conditions = $this->conditions( $conditions );
        if ( isset($this->state['having']) ) $this->state['having'] = $this->tpl['having_']->render( array( 'having'=>$this->state['having'], 'conditions'=>$conditions ) );
        else $this->state['having'] = $this->tpl['having']->render( array( 'conditions'=>$conditions ) );
        return $this;
    }
    
    public function order( $field, $dir="asc" )
    {
        $dir = strtoupper($dir);
        if ( "DESC" !== $dir ) $dir = "ASC";
        $field = $this->ref( $field );
        if ( isset($this->state['order']) ) $this->state['order'] = $this->tpl['order_']->render( array( 'order'=>$this->state['order'], 'field'=>$field, 'dir'=>$dir ) );
        else $this->state['order'] = $this->tpl['order']->render( array( 'field'=>$field, 'dir'=>$dir ) );
        return $this;
    }
    
    public function limit( $count, $offset=0 )
    {
        $count = intval($count,10); $offset = intval($offset,10);
        $this->state['limit'] = $this->tpl['limit']->render( array( 'offset'=>$offset, 'count'=>$count ) );
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
        foreach ($conditions as $field=>$cond)
        {
            $field_raw = $this->fld( $field );
            if ( isset($join[$field_raw]) )
            {
                $main_table = $join[$field_raw]['table'];
                $main_id = $join[$field_raw]['id'];
                $join_table = $join[$field_raw]['join'];
                $join_id = $join[$field_raw]['join_id'];
                
                $j++; $join_alias = "{$join_table}{$j}";
                
                $where = array( );
                if ( isset($join[$field_raw]['key']) && $field_raw !== $join[$field_raw]['key'] )
                {
                    $join_key = $join[$field_raw]['key'];
                    $where["{$join_alias}.{$join_key}"] = $field_raw;
                }
                else
                {
                    $join_key = $field_raw;
                }
                if ( isset($join[$field_raw]['value']) )
                {
                    $join_value = $join[$field_raw]['value'];
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
                
                unset( $conditions[$field] );
           }
        }
        return $this;
    }
    
    public function conditions( $conditions )
    {
        if ( empty($conditions) ) return '';
        if ( is_string($conditions) ) return $conditions;
        
        $condquery = '';
        $conds = array();
        
        foreach ($conditions as $field=>$value)
        {
            $field = $this->ref( $field );
            
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
                    elseif ( 'field' === $type )
                    {
                        $v = $this->ref( $v );
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
                    elseif ( 'field' === $type )
                    {
                        $v = $this->ref( $v );
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
                    elseif ( 'field' === $type )
                    {
                        $v = $this->ref( $v );
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
                    elseif ( 'field' === $type )
                    {
                        $v = $this->ref( $v );
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
            foreach((array)$filter as $field)
            {
                if ( isset($data[$field]) ) 
                    unset($data[$field]);
            }
            return $data;
        }
    }
    
    public function tbl( $table )
    {
        if ( is_array($table) ) return array_map(array($this, 'tbl'), (array)$table);
        return $this->prefix.$table;
    }
    
    public function fld( $field )
    {
        if ( is_array($field) ) return array_map(array($this, 'fld'), (array)$field);
        $field = explode('.', $field);
        return end($field);
    }
    
    public function ref( $refs )
    {
        if ( is_array($refs) ) return array_map(array($this, 'ref'), (array)$refs);
        $refs = array_map( 'trim', explode( ',', $refs ) );
        foreach ($refs as $i=>$ref)
        {
            $ref = array_map( 'trim', explode( 'AS', $ref ) );
            foreach ($ref as $j=>$r)
            {
                $ref[$j] = implode( '.', $this->quote_name( explode( '.', $r ) ) );
            }
            $refs[$i] = implode( ' AS ', $ref );
        }
        return implode( ',', $refs );
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
        return $this->qn . $f . $this->qn;
    }
    
    public function quote( $v )
    {
        if ( is_array( $v ) )
            return array_map( array($this, 'quote'), $v );
        return $this->q . $this->esc($v) . $this->q;
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
            return call_user_func( $this->escdb, $v );
        else
            // simple ecsaping using addslashes
            // '"\ and NUL (the NULL byte).
            return addslashes( $v );
    }
    
    public function esc_like( $v )
    {
        if ( is_array( $v ) )
            return array_map( array($this, 'esc_like'), $v );
        return addcslashes( $v, '_%\\' );
    }
    
    public function like( $v )
    {
        if ( is_array( $v ) )
            return array_map( array($this, 'like'), $v );
        return $this->q . '%' . $this->esc( $this->esc_like( $v ) ) . '%' . $this->q;
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
    
    public function year( $field )
    {
        if ( !($this->tpl['year'] instanceof DialectTpl) ) $this->tpl['year'] = new DialectTpl( $this->tpl['year'], self::TPL_RE );
        return $this->tpl['year']->render( array( 'field'=>$field ) );
    }
    
    public function month( $field )
    {
        if ( !($this->tpl['month'] instanceof DialectTpl) ) $this->tpl['month'] = new DialectTpl( $this->tpl['month'], self::TPL_RE );
        return $this->tpl['month']->render( array( 'field'=>$field ) );
    }
    
    public function day( $field )
    {
        if ( !($this->tpl['day'] instanceof DialectTpl) ) $this->tpl['day'] = new DialectTpl( $this->tpl['day'], self::TPL_RE );
        return $this->tpl['day']->render( array( 'field'=>$field ) );
    }
    
    public function hour( $field )
    {
        if ( !($this->tpl['hour'] instanceof DialectTpl) ) $this->tpl['hour'] = new DialectTpl( $this->tpl['hour'], self::TPL_RE );
        return $this->tpl['hour']->render( array( 'field'=>$field ) );
    }
    
    public function minute( $field )
    {
        if ( !($this->tpl['minute'] instanceof DialectTpl) ) $this->tpl['minute'] = new DialectTpl( $this->tpl['minute'], self::TPL_RE );
        return $this->tpl['minute']->render( array( 'field'=>$field ) );
    }
    
    public function second( $field )
    {
        if ( !($this->tpl['second'] instanceof DialectTpl) ) $this->tpl['second'] = new DialectTpl( $this->tpl['second'], self::TPL_RE );
        return $this->tpl['second']->render( array( 'field'=>$field ) );
    }
}
}