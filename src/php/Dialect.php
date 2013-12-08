<?php
/*
*
*    Dialect SQL Builder, abstract the construction of SQL queries able to support portable DB applications, easy and flexible query construction
*
*/

/*
    Requirements:
        lighweight, if possible just one file (with possible configurations per DB vendor), IN PROGRESS
        flexible/extendable/generic, ability to support multiple DB vendors and dialects/flavors  IN PROGRESS
        able to construct valid sql even if order given is random, ALMOST DONE
        automatically create aliases for tables when (auto)joining  DONE
        automatically build nested where clauses with necessary parentheses
        automatically sanitize inputs  DONE PARTIALLY
        automatically recognize joins and join conditions
        ability to parse a sql string into a structure for further processing EXPERIMENTAL, DONE PARTIALLY
        the final api syntax should be simple and chainable ALMOST DONE
        
        eg:
        
        $query = $this
                    ->select('*')
                    ->from('posts')
                    ->join(array(
                        array('post.field1','meta.field2'), // this will become a join clause
                        array('post.field2','meta.field3'), // this will become a join clause with an automatic alias
                    ))
                    ->where(array(
                        array('post.field1','op','val1'),
                        array('meta.field2','op','val2'),
                    ))
                    ->order('post.field3', 'asc')
                    ->limit(0, 100)
                    ->sql();
*/


/**
**
**  Main Dialect Classes
**
**/

if ( !class_exists('Dialect') )
{

class DialectTable
{
    private static $_cnt = 0;
    
    private $_id = 0;
    private $_table = null;
    private $_alias = null;
    private $_asAlias = null;
    
    public function __construct( $table )
    {
        $this->_table = $table;
        $this->_id = self::$_cnt++;
        $this->useAlias( false );
    }
    
    public function useAlias( $bool = true )
    {
        if ( $bool )
        {
            $this->_alias = $this->_table . "_talias_" . $this->_id;
            $this->_asAlias = $this->_table . " AS " . $this->_alias;
        }
        else
        {
            $this->_alias = $this->_table;
            $this->_asAlias = $this->_table;
        }
        return $this;
    }
    
    public function table()
    {
        return $this->_table;
    }
    
    public function alias()
    {
        return $this->_alias;
    }
    
    public function asAlias()
    {
        return $this->_asAlias;
    }
}

class DialectField
{
    private static $_cnt = 0;
    
    private $_id = 0;
    private $_field = null;
    private $_alias = null;
    private $_asAlias = null;
    private $_table = null;
    
    public function __construct( $field, $table=null )
    {
        $parts = $this->parseField( $field );
        
        $this->_field = $parts[ 'field' ];
        $this->_id = ++self::$_cnt;
        
        if ( $table )
            $this->setTable( $table );
        
        else if ( isset( $parts[ 'table' ] ) )
            $this->setTable( $parts[ 'table' ] );
            
        $this->useAlias( false );
    }
    
    public function useAlias( $bool = true )
    {
        $table = '';
        
        if ( $this->_table )
        {
            $table = $this->_table->table(). ".";
        }
        
        if ( $bool )
        {
            $this->_alias = $this->_field . "_falias_" . $this->_id;
            $this->_asAlias = $table . $this->_field . " AS " . $this->_alias;
        }
        else
        {
            $this->_alias = $this->_field;
            $this->_asAlias = $table . $this->_field;
        }
        return $this;
    }
    
    public function setTable( $table )
    {
        if ( !($table instanceof DialectTable) )
            $table = new DialectTable( $table );
        $this->_table = $table;
        return $this;
    }
    
    public function field()
    {
        return $this->_field;
    }
    
    public function alias()
    {
        return $this->_alias;
    }
    
    public function asAlias()
    {
        return $this->_asAlias;
    }
    
    public function table()
    {
        return $this->_table;
    }
    
    public function parseField( $f )
    {
        //preg_match('~([^\.]+?)\.?([^\.]*?)~', $str, $m);
        
        print_r($f);
        $tmp = explode('.', $f, 2);
        
        $parts = array();
        
        if ( isset( $tmp[1] ) )
        {
            $parts[ 'table' ] = $tmp[0];
            $parts[ 'field' ] = $tmp[1];
        }
        else
        {   
            $parts[ 'field' ] = $tmp[0];
        }
        return $parts;
    }
}

class DialectExpression
{
    private $config = null;
    private $e = array();
    private $rel_ops = array('AND', 'OR');
    private $ops = array('='=>1, '>'=>1, '<'=>1, '>='=>1, '<='=>1, '<>'=>1, 'LIKE'=>1, 'NOT_LIKE'=>1, 'BETWEEN'=>2, 'IN'=>100, 'NOT_IN'=>100);
    
    public function __construct( $config=null )
    {
        $this->config = $config;
        $this->reset();
    }
    
    public function __toString()
    {
        return $this->get();
    }
    
    public function reset()
    {
        $this->e = array();
        return $this;
    }
    
    public function get()
    {
        return implode(' ', $this->e);
    }
    
    public function expr()
    {
        $_args = func_get_args();
        $_argslen = count($_args);
        
        if ( $_argslen > 1 && is_array( $_args[0] ) )
        {
            $_args = $_args[0];
            $_argslen = count($_args);
        }
        
        if ($_argslen < 3 ) return $this;
        
        $field = $_args[0];
        $op = $_args[1];
        $args = $_args[2];
        $q = ( isset($_args[3]) ) ? $_args[3] : '';
        
        // process operator
        $opc = preg_replace('/\s+/', '_', trim(strtoupper($op)));
        
        // nothing to do
        if ( !isset($this->ops[$opc]) )  return $this;
        
        $op = str_replace('_', ' ', $opc);
        $expr = $field->alias() . " " . $op . " ";
        
        $args = array_values((array)$args);
        switch( $this->ops[$opc] )
        {
            case 100:
                $expr .= '(' . $q.implode("{$q},{$q}", $args).$q . ')';
                break;
            case 2:
                $expr .= "({$q}{$args[0]}{$q}, {$q}{$args[1]}{$q})";
                break;
            case 1:
            default:
                $expr .= $q.$args[0].$q;
                break;
        }
        
        $this->e[] = "({$expr})";
        
        return $this;
    }
}

class Dialect
{
    private static $isInited = false;
    private static $vendors = null;
    private static $query_types = null;
    
    private $_vendor = null;
    private $_vendor_clauses = null;
    private $_vendor_tpls = null;
    private $_ops = null;
    private $_rel_ops = null;
    
    private $_type = null;
    private $_sanitize = true;
    private $_q = '';
    private $_whereExpr = null;
    private $_havingExpr = null;
    private $_tables = null;
    private $_fields = null;
    private $_conditionsW = null;
    private $_conditionsH = null;
    private $_clauses = null;
    
    public static init()
    {
        if ( self::$isInited ) return;
        
        // http://www.php.net/manual/en/refs.database.abstract.php
        // http://www.php.net/manual/en/intro.pdo.php
        self::$vendors = array(
            
            'mysqli'=>array(
                // http://php.net/manual/en/function.mysql-real-escape-string.php
                // http://www.php.net/manual/en/mysqli.real-escape-string.php
                //'escape'    =>  'mysqli_real_escape_string ( mysqli $link , string $escapestr )',
                'quote'     =>  '`',
                'concat'    =>  '',
                'clauses'   =>  array(
                    'SELECT' => array('FROM', 'JOIN', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT'),
                    'INSERT' => array('INTO', 'VALUES'),
                    'UPDATE' => array('SET', 'WHERE'),
                    'DELETE' => array('FROM', 'WHERE'),
                    'ALTER' => array()
                ),
                'ops'       =>  array('='=>1, '>'=>1, '<'=>1, '>='=>1, '<='=>1, '<>'=>1, 'LIKE'=>1, 'NOT_LIKE'=>1, 'BETWEEN'=>2, 'IN'=>100, 'NOT_IN'=>100),
                'rel_ops'   =>  array('AND', 'OR'),
                'tpls'      =>  array(
                    'INSERT'    => 'INSERT __{{ALIASED_FIELDS}}__ ',
                    'UPDATE'    => 'UPDATE __{{ALIASED_TABLES}}__ ',
                    'DELETE'    => 'DELETE ',
                    'ALTER'     => 'ALTER TABLE __{{ALIASED_TABLES}}__ ',
                    'INTO'      => 'INTO __{{ALIASED_TABLES}}__ ',
                    'VALUES'    => 'VALUES __{{VALUES}}__ ',
                    'SET'       => 'SET ',
                    'SELECT'    => 'SELECT __{{ALIASED_FIELDS}}__ ',
                    
                    'FROM'      => 'FROM __{{ALIASED_TABLES}}__ ',
                    'JOIN'      => '__{{JOIN_TYPE}}__ __{{ALIASED_TABLE}}__ __{{ON_CONDITION}}__ ',
                    'WHERE'     => 'WHERE __{{WHERE_EXPRESSION}}__',
                    'GROUP'     => 'GROUP BY __{{GROUP_FIELDS}}__',
                    'HAVING'    => 'HAVING __{{HAVING_EXPRESSION}}__',
                    'ORDER'     => 'ORDER BY __{{ORDER_FIELDS}}__',
                    'LIMIT'     => 'LIMIT __{{OFFSET}}__, __{{COUNT}}__',
                    
                    'CURRENT'   =>  'CURRENT_TIMESTAMP()',
                    'NULLDATE'  =>  '0000-00-00 00:00:00',
                    'YEAR'      =>  'YEAR(__{{DATE}}__)',
                    'MONTH'     =>  'MONTH(__{{DATE}}__)',
                    'DAY'       =>  'DAY(__{{DATE}}__)',
                    'HOUR'      =>  'HOUR(__{{DATE}}__)',
                    'MINUTE'    =>  'MINUTE(__{{DATE}}__)',
                    'SECOND'    =>  'SECOND(__{{DATE}}__)'
                )
            ),
            
            'postgre'=>array(
                // http://www.php.net/manual/en/function.pg-escape-string.php
                //'escape'    =>  'pg_escape_string ([ resource $connection ], string $data )',
                'quote'     =>  '"',
                'concat'    =>  '||',
                'clauses'   =>  array(
                    'SELECT' => array('FROM', 'JOIN', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT'),
                    'INSERT' => array('INTO', 'VALUES'),
                    'UPDATE' => array('SET', 'WHERE'),
                    'DELETE' => array('FROM', 'WHERE'),
                    'ALTER' => array()
                ),
                'ops'       =>  array('='=>1, '>'=>1, '<'=>1, '>='=>1, '<='=>1, '<>'=>1, 'LIKE'=>1, 'NOT_LIKE'=>1, 'BETWEEN'=>2, 'IN'=>100, 'NOT_IN'=>100),
                'rel_ops'   =>  array('AND', 'OR'),
                'tpls'      =>  array(
                    'INSERT'    => 'INSERT __{{ALIASED_FIELDS}}__ ',
                    'UPDATE'    => 'UPDATE __{{ALIASED_TABLES}}__ ',
                    'DELETE'    => 'DELETE ',
                    'ALTER'     => 'ALTER TABLE __{{ALIASED_TABLES}}__ ',
                    'INTO'      => 'INTO __{{ALIASED_TABLES}}__ ',
                    'VALUES'    => 'VALUES __{{VALUES}}__ ',
                    'SET'       => 'SET ',
                    'SELECT'    => 'SELECT __{{ALIASED_FIELDS}}__ ',
                    
                    'FROM'      => 'FROM __{{ALIASED_TABLES}}__ ',
                    'JOIN'      => '__{{JOIN_TYPE}}__ __{{ALIASED_TABLE}}__ __{{ON_CONDITION}}__ ',
                    'WHERE'     => 'WHERE __{{WHERE_EXPRESSION}}__',
                    'GROUP'     => 'GROUP BY __{{GROUP_FIELDS}}__',
                    'HAVING'    => 'HAVING __{{HAVING_EXPRESSION}}__',
                    'ORDER'     => 'ORDER BY __{{ORDER_FIELDS}}__',
                    'LIMIT'     => 'LIMIT __{{OFFSET}}__, __{{COUNT}}__',
                    
                    'CURRENT'   =>  'NOW()',
                    'NULLDATE'  =>  '1970-01-01 00:00:00',
                    'YEAR'      =>  'EXTRACT (YEAR FROM __{{DATE}}__)',
                    'MONTH'     =>  'EXTRACT (MONTH FROM __{{DATE}}__)',
                    'DAY'       =>  'EXTRACT (DAY FROM __{{DATE}}__)',
                    'HOUR'      =>  'EXTRACT (HOUR FROM __{{DATE}}__)',
                    'MINUTE'    =>  'EXTRACT (MINUTE FROM __{{DATE}}__)',
                    'SECOND'    =>  'EXTRACT (SECOND FROM __{{DATE}}__)'
                )
            ),
            
            'sql_server'=>array(
                // http://www.php.net/manual/en/function.pg-escape-string.php
                //'escape'    =>  'pg_escape_string ([ resource $connection ], string $data )',
                'quote'     =>  '"',
                'concat'    =>  '||',
                'clauses'   =>  array(
                    'SELECT' => array('FROM', 'JOIN', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT'),
                    'INSERT' => array('INTO', 'VALUES'),
                    'UPDATE' => array('SET', 'WHERE'),
                    'DELETE' => array('FROM', 'WHERE'),
                    'ALTER' => array()
                ),
                'ops'       =>  array('='=>1, '>'=>1, '<'=>1, '>='=>1, '<='=>1, '<>'=>1, 'LIKE'=>1, 'NOT_LIKE'=>1, 'BETWEEN'=>2, 'IN'=>100, 'NOT_IN'=>100),
                'rel_ops'   =>  array('AND', 'OR'),
                'tpls'      =>  array(
                    'INSERT'    => 'INSERT __{{ALIASED_FIELDS}}__ ',
                    'UPDATE'    => 'UPDATE __{{ALIASED_TABLES}}__ ',
                    'DELETE'    => 'DELETE ',
                    'ALTER'     => 'ALTER TABLE __{{ALIASED_TABLES}}__ ',
                    'INTO'      => 'INTO __{{ALIASED_TABLES}}__ ',
                    'VALUES'    => 'VALUES __{{VALUES}}__ ',
                    'SET'       => 'SET ',
                    'SELECT'    => 'SELECT __{{ALIASED_FIELDS}}__ ',
                    
                    'FROM'      => 'FROM __{{ALIASED_TABLES}}__ ',
                    'JOIN'      => '__{{JOIN_TYPE}}__ __{{ALIASED_TABLE}}__ __{{ON_CONDITION}}__ ',
                    'WHERE'     => 'WHERE __{{WHERE_EXPRESSION}}__',
                    'GROUP'     => 'GROUP BY __{{GROUP_FIELDS}}__',
                    'HAVING'    => 'HAVING __{{HAVING_EXPRESSION}}__',
                    'ORDER'     => 'ORDER BY __{{ORDER_FIELDS}}__',
                    'LIMIT'     => 'LIMIT __{{OFFSET}}__, __{{COUNT}}__',
                    
                    'CURRENT'   =>  'NOW()',
                    'NULLDATE'  =>  '1970-01-01 00:00:00',
                    'YEAR'      =>  'EXTRACT (YEAR FROM __{{DATE}}__)',
                    'MONTH'     =>  'EXTRACT (MONTH FROM __{{DATE}}__)',
                    'DAY'       =>  'EXTRACT (DAY FROM __{{DATE}}__)',
                    'HOUR'      =>  'EXTRACT (HOUR FROM __{{DATE}}__)',
                    'MINUTE'    =>  'EXTRACT (MINUTE FROM __{{DATE}}__)',
                    'SECOND'    =>  'EXTRACT (SECOND FROM __{{DATE}}__)'
                )
            )
        );
        self::$vendors['mysql'] = self::$vendors['mysqli'];
        
        self::$query_types = array('SELECT', 'INSERT', 'UPDATE', 'DELETE', 'ALTER');
        
        self::$isInited = true;
    }
    
    // static builder method
    public static function create( $vendor='mysql' )
    {
        return new self( $vendor );
    }
    
    public function __construct( $vendor='mysql' )
    {
        $this->_vendor = strtolower($vendor);
        $this->_vendor_clauses = self::$vendors[ $this->_vendor ]['clauses'];
        $this->_vendor_tpls = self::$vendors[ $this->_vendor ]['tpls'];
        $this->reset( )->sanitize( true );
    }
    
    public function reset()
    {
        $this->_q = '';
        $this->_fields = array();
        $this->_tables = array();
        $this->_whereExpr = null;
        $this->_havingExpr = null;
        $this->_conditionsW = array();
        $this->_conditionsH = array();
        $this->_clauses = array();
        
        foreach ($this->_vendor_clauses as $type => $clauses)
        {
            $this->_clauses[ $type ] = '';
            foreach ($clauses as $clause)
                $this->_clauses[ $clause ] = '';
        }
        
        return $this;
    }
    
    // return the sql as string, if this object is cast as a string ;)
    public function __toString()
    {
        return $this->sql();
    }
    
    // return a table reference
    public function table( $table )
    {
        if ( null === $table ) return $table;
        
        elseif ( $table instanceof DialectTable ) return $table;
        
        if ( !isset( $this->_tables[ $table ] ) )
            $this->_tables[ $table ] = new DialectTable($table, ++self::$_tableCnt);
        
        return $this->_tables[ $table ];
    }
    
    // return a field reference
    public function field( $field, $table=null )
    {
        if ( null === $field ) return $field;
        
        else if ( $field instanceof DialectField ) return $field;
        
        if ( !isset( $this->_fields[ $field ] ) )
        {
            $f = new DialectField($field, $table);
            $this->_fields[ $field ] = $f;
            
            $t = $f->table();
            if ( $t && !isset( $this->_tables[ $t->table() ]) )
                $this->_tables[ $t->table() ] = $t;
        }
        return $this->_fields[ $field ];
    }
    
    // return an expression reference
    public function expression( $field, $op, $args, $q='' )
    {   
        $expr = new DialectExpression();
        $expr->expr($field, $op, $args, $q);
        return $expr;
    }
    
    // try to sanitize if possible
    public function sanitize($bool)
    {
        $this->_sanitize = (bool)$bool;
        return $this;
    }
    
    // simple prepare using sprintf
    public function prepare( $sql, $params=null )
    {
        if ( !$params && is_array($sql) )
        {
            $params = $sql;
            $sql = $this->sql();
        }
        return vsprintf( $sql, $params );
    }
    
    protected function buildQuery( $part=null )
    {
        // allow to get partial query back
        if (!$part)
            $parts = array_flip( $this->_default_clauses[ $this->_type ] );
        else
            $parts = array_flip( (array)$part );
            
        $parts2 = $parts; // clone
        
        $sql = '';
        $type = $this->_type;
        
        if ('SELECT'==$type)
        {
            $sql .= 'SELECT ';
            
            $i = 0;
            foreach ($this->_fields as $field)
            {
                if ($i)  $sql .= ", ";
                $sql .= $field->asAlias();
                $i++;
            }
            $sql .= " ";
        }    
        
        elseif ('INSERT'==$type)
        {
            // TODO
            $sql .= 'INSERT ';
        }    
        
        elseif ('UPDATE'==$type)
        {
            // TODO
            $sql .= 'UPDATE ';
        }    
        
        elseif ('DELETE'==$type)
        {
            // TODO
            $sql .= 'DELETE ';
        }
        
        elseif ('ALTER'==$type)
        {
            // TODO
            $sql .= 'ALTET TABLE ';
        }
        
        $sql .= "\n" . implode(   "\n",  
                            array_filter(
                                array_values(
                                    array_intersect_key( 
                                        array_merge( $parts, $this->_clauses )
                                    , $parts2)
                            ), 'strlen')
                        );
        return $sql;
    }
    
    // just placeholder here
    public function escape( $val )
    {
        return $val;
    }
    
    public function query( $q=false )
    {
        $this->_q = ($q) ? $q : '';
        return $this;
    }
    
    public function sql( $part=false )
    {
        // build the query here
        if (empty($this->_q))  $this->_q = $this->buildQuery( $part );
        return $this->_q;
    }
    
    public function insert()
    {
        $this->reset();
        $this->_type = 'INSERT';
        return $this;
    }
    
    public function update()
    {
        $this->reset();
        $this->_type = 'UPDATE';
        return $this;
    }
    
    public function delete()
    {
        $this->reset();
        $this->_type = 'DELETE';
        return $this;
    }
    
    public function alter()
    {
        $this->reset();
        $this->_type = 'ALTER';
        return $this;
    }
    
    public function select( $fields = array() )
    {
        $this->reset();
        $this->_type = 'SELECT';
        
        $fields = array_values( (array)$fields );
        
        // select all by default
        if ( empty($fields) ) $fields = array( '*' => '*' );
        
        $this->_fields = $fields;
        
        foreach ($fields as $field)
        {
            // transform to dialect fields
            $this->field( $field );
        }
        
        return $this;
    }
    
    public function from( $tables )
    {
        $tables = array_values( (array)$tables );
        
        $this->_tables = $tables;
        
        foreach ($tables as $table)
        {
            // transform to dialect tables
            $this->table($table);
        }
        
        $from = 'FROM ';
        
        $i = 0;
        foreach ($this->_tables as $table)
        {
            if ($i) $from .= ", ";
            $from .= $table->asAlias();
            $i++;
        }
        
        $this->_clauses['FROM'] = $from;
            
        return $this;
    }
    
    // partially sanitized using white-list
    public function join($on, $jointable=null, $type='INNER')
    {
        $on = array_values( (array)$on );
        $type = strtoupper( $type );
        $jointable = $this->table( $jointable );
        
        foreach ($on as $i => $field)
        {
            $field = $this->field( $field );
            $ftable = $field->table();
            $alias = ($jointable) ? $jointable->asAlias() : $ftable->asAlias();
            $on[$i] = $field->alias();
        }
        $on = implode(' = ', $on);
        
        if ( !empty($this->_clauses['JOIN']) )
            $this->_clauses['JOIN'] .= " ";
            
        switch ( $type )
        {
            case 'LEFT':
                $this->_clauses['JOIN'] .= "LEFT JOIN {$alias} ON {$on}";
                break;
            case 'RIGHT':
                $this->_clauses['JOIN'] .= "RIGHT JOIN {$alias} ON {$on}";
                break;
            case 'OUTER':
                $this->_clauses['JOIN'] .= "OUTER JOIN {$alias} ON {$on}";
                break;
            case 'INNER':
            default:
                $this->_clauses['JOIN'] .= "INNER JOIN {$alias} ON {$on}";
                break;
        }
        return $this;
    }
    
    public function where($rel, $expr)
    {
        $args = func_get_args();
        array_shift( $args );
        $argslen = count($args);
        
        if ( $argslen > 1 )
        {
            $args[0] = $this->table( $args[0] );
            
            // expression given
            $this->_whereExpr = new DialectExpression();
            
            $this->_whereExpr->expr( $args );
            
            $expr = $this->_whereExpr->get();
        }
        
        $rel = strtoupper( $rel );
        if ( !in_array($rel, $this->_rel_ops)) $rel = 'AND';
        
        $this->_conditionsW[] = array($expr, $rel);
        $this->_clauses['WHERE'] = 'WHERE ';
        
        foreach ($this->_conditionsW as $i=>$e)
        {
            $this->_clauses['WHERE'] .= ($i) ? $e[1] . ' ' . $e[0] . ' ' : $e[0] . ' ';
        }
        return $this;
    }
    
    // partially sanitized using white-list
    public function groupBy($by, $ord='ASC')
    {
        $by = $this->field( $by )->alias();
        $ord = strtoupper($ord);
        
        if (!in_array($ord, array('ASC', 'DESC')))   
            $ord = 'ASC';
            
        $this->_clauses['GROUP'] = "GROUP BY {$by} {$ord}";
        
        return $this;
    }
    
    public function having($rel, $expr)
    {
        $args = func_get_args();
        array_shift( $args );
        $argslen = count($args);
        
        if ( $argslen > 1 )
        {
            $args[0] = $this->table( $args[0] );
            
            // expression given
            $this->_havingExpr = new DialectExpression();
            
            $this->_havingExpr->expr( $args );
            
            $expr = $this->_havingExpr->get();
        }
        
        $rel = strtoupper( $rel );
        if ( !in_array($rel, $this->_rel_ops)) $rel = 'AND';
        
        $this->_conditionsH[] = array($expr, $rel);
        $this->_clauses['HAVING'] = 'HAVING ';
        
        foreach ($this->_conditionsH as $i=>$e)
        {
            $this->_clauses['HAVING'] .= ($i) ? $e[1] . ' ' . $e[0] . ' ' : $e[0] . ' ';
        }
        return $this;
    }
    
    // partially sanitized using white-list
    public function orderBy($by, $ord='ASC')
    {
        $add_comma = true;
        
        if (empty($this->_clauses['ORDER']))
        {
            $this->_clauses['ORDER'] = "ORDER BY";
            $add_comma = false;
        }
        
        $ord = strtoupper($ord);
        if (!in_array($ord, array('ASC', 'DESC')))   
            $ord = 'ASC';
            
        if ($add_comma)
            $this->_clauses['ORDER'] .= ',';
            
        $by = $this->field( $by )->alias();
        
        $this->_clauses['ORDER'] .= " {$by} {$ord}";
        
        return $this;
    }
    
    // sanitized using intval
    public function limit($count, $offset=0)
    {
        // perform some sanitization
        $offset = intval($offset);
        $count = intval($count);
        $this->_clauses['LIMIT'] = "LIMIT {$offset}, {$count}";
        
        return $this;
    }
    
    // sanitized using intval
    public function paged($per_page, $page=1)
    {
        // perform some sanitization
        $page = intval($page);
        if ($page < 1)
            $page = 1;
        $per_page = intval($per_page);
        
        return $this->limit($per_page, ($page-1)*$per_page);
    }
}

// init 
Dialect::init();
}
