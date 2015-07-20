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
    
    public static $dialect = array(
     
     'mysql'            => array(
        
         'quote'        => array( "'", '`' )
        ,'clauses'      => array(
        // https://dev.mysql.com/doc/refman/5.0/en/select.html, https://dev.mysql.com/doc/refman/5.0/en/join.html
         'select'  => array('select','from','join','where','group','having','order','limit')
        // https://dev.mysql.com/doc/refman/5.0/en/insert.html
        ,'insert'  => array('insert','values')
        // https://dev.mysql.com/doc/refman/5.0/en/update.html
        ,'update'  => array('update','set','where','order','limit')
        // https://dev.mysql.com/doc/refman/5.0/en/delete.html
        ,'delete'  => array('delete','from','where','order','limit')
        )
        ,'tpl'        => array(
         'select'   => 'SELECT $0'
        ,'insert'   => 'INSERT INTO $0 ($1)'
        ,'update'   => 'UPDATE $0'
        ,'delete'   => 'DELETE '
        ,'values'   => 'VALUES $0'
        ,'values_'  => ',$0'
        ,'set'      => 'SET $0'
        ,'set_'     => ',$0'
        ,'from'     => 'FROM $0'
        ,'from_'    => ',$0'
        ,'join'     => 'JOIN $0'
        ,'alt_join' => '$1 JOIN $0'
        ,'join_'    => "\nJOIN \$0"
        ,'alt_join_'=> "\n\$1 JOIN \$0"
        ,'where'    => 'WHERE $0'
        ,'group'    => 'GROUP BY $0'
        ,'group_'   => ',$0'
        ,'having'   => 'HAVING $0'
        ,'order'    => 'ORDER BY $0'
        ,'order_'   => ',$0'
        ,'limit'    => 'LIMIT $0,$1'
        
        ,'year'     => 'YEAR($0)'
        ,'month'    => 'MONTH($0)'
        ,'day'      => 'DAY($0)'
        ,'hour'     => 'HOUR($0)'
        ,'minute'   => 'MINUTE($0)'
        ,'second'   => 'SECOND($0)'
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
         'select'   => 'SELECT $0'
        ,'insert'   => 'INSERT INTO $0 ($1)'
        ,'update'   => 'UPDATE $0'
        ,'delete'   => 'DELETE '
        ,'values'   => 'VALUES $0'
        ,'values_'  => ',$0'
        ,'set'      => 'SET $0'
        ,'set_'     => ',$0'
        ,'from'     => 'FROM $0'
        ,'from_'    => ',$0'
        ,'join'     => 'JOIN $0'
        ,'alt_join' => '$1 JOIN $0'
        ,'join_'    => "\n" . 'JOIN $0'
        ,'alt_join_'=> "\n" . '$1 JOIN $0'
        ,'where'    => 'WHERE $0'
        ,'group'    => 'GROUP BY $0'
        ,'group_'   => ',$0'
        ,'having'   => 'HAVING $0'
        ,'order'    => 'ORDER BY $0'
        ,'order_'   => ',$0'
        ,'limit'    => 'LIMIT $1 OFFSET $0'
        
        ,'year'     => 'EXTRACT (YEAR FROM $0)'
        ,'month'    => 'EXTRACT (MONTH FROM $0)'
        ,'day'      => 'EXTRACT (DAY FROM $0)'
        ,'hour'     => 'EXTRACT (HOUR FROM $0)'
        ,'minute'   => 'EXTRACT (MINUTE FROM $0)'
        ,'second'   => 'EXTRACT (SECOND FROM $0)'
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
    public $clauses = null;
    public $tpl = null;
    public $db = null;
    public $escdb = null;
    public $q = null;
    public $qn = null;
    
    public function __construct( $type='mysql' )
    {
        $this->db = null;
        $this->escdb = null;
        $this->clause = null;
        $this->state = null;
        
        $this->clauses =& self::$dialect[ $type ][ 'clauses' ];
        $this->tpl =& self::$dialect[ $type ][ 'tpl' ];
        $this->q = self::$dialect[ $type ][ 'quote' ][ 0 ];
        $this->qn = self::$dialect[ $type ][ 'quote' ][ 1 ];
    }
    
    public function dispose( )
    {
        $this->db = null;
        $this->escdb = null;
        $this->clause = null;
        $this->state = null;
        $this->clauses = null;
        $this->tpl = null;
        $this->q = null;
        $this->qn = null;
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
    
    public function escape( $escdb=null )
    {
        $this->escdb = $escdb && is_callable($escdb) ? $escdb : null;
        return $this;
    }
    
    public function reset( $clause )
    {
        $this->state = array( );
        $this->clause = $clause;
        
        foreach($this->clauses[ $this->clause ] as $clause)
        {
            if ( isset($this->tpl[ $clause ]) && !($this->tpl[ $clause ] instanceof DialectTpl) )
                $this->tpl[ $clause ] = new DialectTpl( $this->tpl[ $clause ] );
            
            // continuation clause if exists, ..
            $c = "{$clause}_";
            if ( isset($this->tpl[ $c ]) && !($this->tpl[ $c ] instanceof DialectTpl) )
                $this->tpl[ $c ] = new DialectTpl( $this->tpl[ $c ] );
            
            // alternative clause form if exists
            $c = "alt_{$clause}";
            if ( isset($this->tpl[ $c ]) && !($this->tpl[ $c ] instanceof DialectTpl) )
            {
                $this->tpl[ $c ] = new DialectTpl( $this->tpl[ $c ] );
                
                // alternative clause form continuation if exists, ..
                $c = "{$c}_";
                if ( isset($this->tpl[ $c ]) && !($this->tpl[ $c ] instanceof DialectTpl) )
                    $this->tpl[ $c ] = new DialectTpl( $this->tpl[ $c ] );
            }
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
            $pattern = '/' . $left . '(ad|as|l|r|d|s)\\(([0-9a-zA-Z_]+)\\)' . $right . '/';
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
                        // array of integers param
                        case 'ad': $param = '(' . implode(',', $this->intval((array)$args[$param])) . ')'; break;
                        // array of strings param
                        case 'as': $param = '(' . implode(',', $this->quote((array)$args[$param])) . ')'; break;
                        // like param
                        case 'l': $param = $this->like($args[$param]); break;
                        // raw param
                        case 'r': $param = $args[$param]; break;
                        // integer param
                        case 'd': $param = $this->intval($args[$param]); break;
                        // string param
                        case 's': default: $param = $this->quote($args[$param]); break;
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
    
    public function year( $field )
    {
        $this->tpl['year'] = self::Tpl( $this->tpl['year'] );
        return $this->tpl['year']->render( array( $field ) );
    }
    
    public function month( $field )
    {
        $this->tpl['month'] = self::Tpl( $this->tpl['month'] );
        return $this->tpl['month']->render( array( $field ) );
    }
    
    public function day( $field )
    {
        $this->tpl['day'] = self::Tpl( $this->tpl['day'] );
        return $this->tpl['day']->render( array( $field ) );
    }
    
    public function hour( $field )
    {
        $this->tpl['hour'] = self::Tpl( $this->tpl['hour'] );
        return $this->tpl['hour']->render( array( $field ) );
    }
    
    public function minute( $field )
    {
        $this->tpl['minute'] = self::Tpl( $this->tpl['minute'] );
        return $this->tpl['minute']->render( array( $field ) );
    }
    
    public function second( $field )
    {
        $this->tpl['second'] = self::Tpl( $this->tpl['second'] );
        return $this->tpl['second']->render( array( $field ) );
    }
    
    public function select( $fields='*' )
    {
        $this->reset('select');
        if ( !$fields || empty($fields) ) $fields = '*';
        $this->state['select'] = $this->tpl['select']->render( array( implode(',',(array)$fields) ) );
        return $this;
    }
    
    public function insert( $table, $fields )
    {
        $this->reset('insert');
        $this->state['insert'] = $this->tpl['insert']->render( array( $table, implode(',',(array)$fields) ) );
        return $this;
    }
    
    public function values( $values )
    {
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
        if ( isset($this->state['values']) ) $this->state['values'] .= $this->tpl['values_']->render( array( $insert_values ) );
        else $this->state['values'] = $this->tpl['values']->render( array( $insert_values ) );
        return $this;
    }
    
    public function update( $tables )
    {
        $this->reset('update');
        $this->state['update'] = $this->tpl['update']->render( array( implode(',', (array)$tables) ) );
        return $this;
    }
    
    public function set( $fields_values )
    {
        $set_values = array();
        foreach ($fields_values as $field=>$value)
        {
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
        if ( isset($this->state['set']) ) $this->state['set'] .= $this->tpl['set_']->render( array( $set_values ) );
        else $this->state['set'] = $this->tpl['set']->render( array( $set_values ) );
        return $this;
    }
    
    public function del( )
    {
        $this->reset('delete');
        $this->state['delete'] = $this->tpl['delete']->render( array() );
        return $this;
    }
    
    public function from( $tables )
    {
        $tables = implode(',',(array)$tables);
        if ( isset($this->state['from']) ) $this->state['from'] .= $this->tpl['from_']->render( array( $tables ) );
        else $this->state['from'] = $this->tpl['from']->render( array( $tables ) );
        return $this;
    }
    
    public function join( $table, $cond=null, $type=null )
    {
        $join_clause = empty($cond) ? $table : "$table ON $cond";
        if ( empty($type) )
        {
            if ( isset($this->state['join']) ) $this->state['join'] .= $this->tpl['join_']->render( array( $join_clause ) );
            else $this->state['join'] = $this->tpl['join']->render( array( $join_clause ) );
        }
        else
        {
            if ( isset($this->state['join']) ) $this->state['join'] .= $this->tpl['alt_join_']->render( array( $join_clause, strtoupper($type) ) );
            else $this->state['join'] = $this->tpl['alt_join']->render( array( $join_clause, strtoupper($type) ) );
        }
        return $this;
    }
    
    public function where( $conditions )
    {
        if ( !empty($conditions) )
            $this->state['where'] = $this->tpl['where']->render( array( is_string($conditions) ? $conditions : $this->conditions( $conditions ) ) );
        return $this;
    }
    
    public function group( $field, $dir="asc" )
    {
        $dir = strtoupper($dir);
        if ( "DESC" !== $dir ) $dir = "ASC";
        $grouped = "$field $dir";
        if ( isset($this->state['group']) ) $this->state['group'] .= $this->tpl['group_']->render( array( $grouped ) );
        else $this->state['group'] = $this->tpl['group']->render( array( $grouped ) );
        return $this;
    }
    
    public function having( $conditions )
    {
        if ( !empty($conditions) )
            $this->state['having'] = $this->tpl['having']->render( array( is_string($conditions) ? $conditions : $this->conditions( $conditions ) ) );
        return $this;
    }
    
    public function order( $field, $dir="asc" )
    {
        $dir = strtoupper($dir);
        if ( "DESC" !== $dir ) $dir = "ASC";
        $ordered = "$field $dir";
        if ( isset($this->state['order']) ) $this->state['order'] .= $this->tpl['order_']->render( array( $ordered ) );
        else $this->state['order'] = $this->tpl['order']->render( array( $ordered ) );
        return $this;
    }
    
    public function limit( $count, $offset=0 )
    {
        $count = intval($count,10); $offset = intval($offset,10);
        $this->state['limit'] = $this->tpl['limit']->render( array( $offset, $count ) );
        return $this;
    }
    
    public function page( $page, $perpage )
    {
        $page = intval($page,10); $perpage = intval($perpage,10);
        return $this->limit( $perpage, $page*$perpage );
    }
    
    public function conditions( $conditions )
    {
        $condquery = '';
        if ( !empty($conditions) )
        {
            $conds = array();
            
            foreach ($conditions as $field=>$value)
            {
                if ( is_array( $value ) )
                {
                    if ( isset($value['multi-like']) )
                    {
                        // Add the search tuple to the query.
                        $conds[] = $this->multi_like($field, $value['multi-like']);
                    }
                    elseif ( isset($value['like']) )
                    {
                        // Add the search tuple to the query.
                        $conds[] = "$field LIKE " . $this->like($value['like']);
                    }
                    elseif ( isset($value['like-prepared']) )
                    {
                        // prepared dynamically
                        $conds[] = "$field LIKE {$value['like-prepared']}";
                    }
                    elseif ( isset($value['in']) )
                    {
                        if ( isset($value['type']) )
                        {
                            if ( 'integer' == $value['type'] )
                            {
                                $value['in'] = '(' . implode( ',', $this->intval( (array)$value['in'] ) ) . ')';
                            }
                            elseif ( 'string' == $value['type'] )
                            {
                                $value['in'] = '(' . implode( ',', $this->quote( (array)$value['in'] ) ) . ')';
                            }
                            elseif ( 'prepared' == $value['type'] )
                            {
                                // prepared dynamically
                            }
                            else
                            {
                                $value['in'] = '(' . implode( ',', $this->quote( (array)$value['in'] ) ) . ')';
                            }
                        }
                        else
                        {
                            $value['in'] = (array)$value['in'];
                            if ( isset($value['in'][0]) && is_int($value['in'][0]) )
                                $value['in'] = '(' . implode( ',', $this->intval( $value['in'] ) ) . ')';
                            else
                                $value['in'] = '(' . implode( ',', $this->quote( $value['in'] ) ) . ')';
                        }
                        // Add the search tuple to the query.
                        $conds[] = "$field IN {$value['in']}";
                    }
                    elseif ( isset($value['between']) )
                    {
                        if ( isset($value['type']) )
                        {
                            if ( 'integer' == $value['type'] )
                            {
                                $value['between'] = $this->intval( $value['between'] );
                            }
                            elseif ( 'string' == $value['type'] )
                            {
                                $value['between'] = $this->quote( $value['between'] );
                            }
                            elseif ( 'prepared' == $value['type'] )
                            {
                                // prepared dynamically
                            }
                            else
                            {
                                $value['between'] = $this->quote( $value['between'] );
                            }
                        }
                        else
                        {
                            if ( !is_int($value['between'][0]) || !is_int($value['between'][1]) )
                            {
                                $value['between'] = $this->quote( $value['between'] );
                            }
                        }
                        // Add the search tuple to the query.
                        $conds[] = "$field BETWEEN {$value['between'][0]} AND {$value['between'][1]}";
                    }
                    elseif ( isset($value['equal']) || 
                        isset($value['eq']) || 
                        isset($value['gt']) || 
                        isset($value['lt']) || 
                        isset($value['gte']) || 
                        isset($value['lte']) 
                    )
                    {
                        if ( isset($value['eq']) || isset($value['equal']) )
                        {
                            $op = '=';
                            $key = isset($value['equal']) ? 'equal' : 'eq';
                        }
                        elseif ( isset($value['gt']) )
                        {
                            $op = '>';
                            $key = 'gt';
                        }
                        elseif ( isset($value['gte']) )
                        {
                            $op = '>=';
                            $key = 'gte';
                        }
                        elseif ( isset($value['lte']) )
                        {
                            $op = '<=';
                            $key = 'lte';
                        }
                        elseif ( isset($value['lt']) )
                        {
                            $op = '<';
                            $key = 'lt';
                        }
                        
                        if ( isset($value['type']) )
                        {
                            if ( 'integer' == $value['type'] )
                            {
                                $value[$key] = $this->intval( $value[$key] );
                            }
                            elseif ( 'string' == $value['type'] )
                            {
                                $value[$key] = $this->quote( $value[$key] );
                            }
                            elseif ( 'prepared' == $value['type'] )
                            {
                                // prepared dynamically
                            }
                            else
                            {
                                $value[$key] = $this->quote( $value[$key] );
                            }
                        }
                        else
                        {
                            if ( !is_int($value[$key]) )
                                $value[$key] = $this->quote( $value[$key] );
                        }
                        // Add the search tuple to the query.
                        $conds[] = "$field $op {$value[$key]}";
                    }
                }
                else
                {
                    // Add the search tuple to the query.
                    $conds[] = "$field = " . (is_int($value) ? $value : $this->quote($value));
                }
            }
            
            if ( !empty($conds) ) $condquery = '(' . implode(') AND (', $conds) . ')';
        }
        return $condquery;
    }
    
    public function intval( $v )
    {
        if ( is_array( $v ) )
            return array_map( array($this, 'intval'), $v );
        else
            return intval( $v, 10 );
    }
    
    public function quote_name( $f )
    {
        if ( is_array( $f ) )
            return array_map( array($this, 'quote_name'), $f );
        else
            return $this->qn . $f . $this->qn;
    }
    
    public function quote( $v )
    {
        if ( is_array( $v ) )
            return array_map( array($this, 'quote'), $v );
        else
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
        else
        {
            if ( $this->escdb ) 
                return call_user_func( $this->escdb, $v );
            else
                // simple ecsaping using addslashes
                // '"\ and NUL (the NULL byte).
                return addslashes( $v );
        }
    }
    
    public function esc_like( $v )
    {
        if ( is_array( $v ) )
            return array_map( array($this, 'esc_like'), $v );
        else
            return addcslashes( $v, '_%\\' );
    }
    
    public function like( $v )
    {
        if ( is_array( $v ) )
            return array_map( array($this, 'like'), $v );
        else
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
}
}