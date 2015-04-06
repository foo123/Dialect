<?php
/**
*
*   Dialect Cross-Platform SQL Builder for PHP, Python, Node/JS, ActionScript
*   https://github.com/foo123/Dialect
* 
*   Abstract the construction of SQL queries
*   Support multiple DB vendors
*   Intuitive and Flexible API
*
**/

/*

__Requirements:__

* Support multiple DB vendors (eg. MySQL, Postgre, Oracle, SQL Server )
* Easily extended to new vendors ( preferably through a config setting )
* Simple, Flexible and Intuitive API
* Light-weight ( one class/file per implementation if possible )
* Speed

*/


/**
*
*  Main Dialect Classes
*
**/
if ( !class_exists('Dialect') )
{
class DialectTable
{
    private static $_cnt = 0;
    private $q = '';
    
    public $id = 0;
    public $name = null;
    public $alias = null;
    public $asAlias = null;
    public $isAliased = false;
    
    public static function parse( $t )
    {
        $parts = array( );
        $tmp = explode(' AS ', $t);
        $parts[ 'name' ] = trim($tmp[ 0 ]);
        if ( count( $tmp ) > 1 )
        {
            $parts[ 'alias' ] = trim($tmp[ 1 ]);
        }
        else
        {
            $parts[ 'alias' ] = false;
        }
        return (object)$parts;
    }
    
    public function __construct( $name, $quote='' )
    {
        $parts = is_object( $name ) ? $name : self::parse( $name );
        $this->q = $quote;
        $this->id = ++self::$_cnt;
        $this->name = $parts->name;
        $this->useAlias( $parts->alias );
    }
    
    public function useAlias( $alias = true )
    {
        if ( !$alias )
        {
            $this->alias = implode('', array($this->q, $this->name, $this->q));
            $this->asAlias = implode('', array($this->q, $this->name, $this->q));
            $this->isAliased = false;
        }
        elseif ( true === $alias )
        {
            $this->alias = implode('', array($this->q, "t_", $this->id, "_", $this->name, $this->q));
            $this->asAlias = implode('', array($this->q, $this->name, $this->q, " AS ", $this->q, $this->alias, $this->q));
            $this->isAliased = true;
        }
        elseif ( $alias )
        {
            $this->alias = implode('', array($this->q, '' . $alias, $this->q));
            $this->asAlias = implode('', array($this->q, $this->name, $this->q, " AS ", $this->q, $this->alias, $this->q));
            $this->isAliased = true;
        }
        return $this;
    }
}

class DialectField
{
    private static $_cnt = 0;
    private $q = '';
    
    public $id = 0;
    public $name = null;
    public $table = null;
    public $alias = null;
    public $asAlias = null;
    public $isAliased = false;
    
    public static function parse( $f )
    {
        $tmp = explode('.', $f);
        $parts = array( );
        
        if ( count( $tmp ) > 1 )
        {
            $parts[ 'table' ] = trim($tmp[ 0 ]);
            $parts[ 'name' ] = trim($tmp[ 1 ]);
        }
        else
        {   
            $parts[ 'table' ] = false;
            $parts[ 'name' ] = trim($tmp[ 0 ]);
        }
        $tmp = explode(' AS ', $parts[ 'field' ]);
        if ( count( $tmp ) > 1 )
        {
            $parts[ 'alias' ] = trim($tmp[ 1 ]);
            $parts[ 'name' ] = trim($tmp[ 0 ]);
        }
        else
        {
            $parts[ 'alias' ] = false;
        }
        return (object)$parts;
    }
    
    public function __construct( $name, $quote='' )
    {
        $parts = is_object( $name ) ? $name : self::parse( $name );
        $this->q = $quote;
        $this->id = ++self::$_cnt;
        $this->name = $parts->name;
        $this->table = $parts->table;
        $this->useAlias( $parts->alias );
    }
    
    public function useAlias( $alias = true )
    {
        $table = $this->table ? ($this->table . ".") : '';
        
        if ( !$alias )
        {
            $this->alias = implode('', array($this->q, $this->name, $this->q));
            $this->asAlias = implode('', array($table, $this->q, $this->name, $this->q));
            $this->isAliased = false;
        }
        elseif ( true === $alias )
        {
            $this->alias = implode('', array($this->q, "f_", $this->id, "_", $this->name, $this->q));
            $this->asAlias = implode('', array($table, $this->q, $this->name, $this->q, " AS ", $this->q, $this->alias, $this->q));
            $this->isAliased = true;
        }
        elseif ( $alias )
        {
            $this->alias = implode('', array($this->q, ''. $alias, $this->q));
            $this->asAlias = implode('', array($table, $this->q, $this->name, $this->q, " AS ", $this->q, $this->alias, $this->q));
            $this->isAliased = true;
        }
        return $this;
    }
}


class Dialect
{
    const VERSION = "0.1";
    
    private static $_vendor = null;
    
    private $_query = '';
    private $_quote = '';
    private $_sanitize = true;
    
    private $_type = null;
    private $_clauses = null;
    private $_tables = null;
    private $_join_tables = null;
    private $_fields = null;
    private $_whereExpr = null;
    private $_havingExpr = null;
    private $_conditionsW = null;
    private $_conditionsH = null;
    
    public static vendor( $config=null )
    {
        if ( !empty($config) )
        {
            self::$_vendor = (array)$config;
        }
    }
    
    // static builder method
    public static function newInstance( )
    {
        return new self( );
    }
    
    public function __construct( )
    {
        if ( self::$_vendor && isset(self::$_vendor['quote']) )
        {
            $this->_quote = strval(self::$_vendor['quote']);
        }
        $this->reset( )->sanitize( true );
    }
    
    // return the sql as string, if this object is cast as a string ;)
    public function __toString( )
    {
        return $this->sql( );
    }
    
    // try to sanitize if possible
    public function sanitize( $bool=true )
    {
        $this->_sanitize = (bool)$bool;
        return $this;
    }
    
    // just placeholder here
    public function escape( $val )
    {
        // http://www.php.net/manual/en/pdo.quote.php
        return PDO::quote( $val, PDO::PARAM_STR );
    }
    
    // simple prepare using sprintf
    public function prepare( $sql, $params=null )
    {
        // http://www.php.net/manual/en/pdo.prepare.php
        // PDO::prepare
        if ( !$params && is_array( $sql ) )
        {
            $params = $sql;
            $sql = $this->sql();
        }
        return vsprintf( $sql, $params );
    }
    
    public function reset( )
    {
        $this->_query = '';
        
        $this->_type = null;
        
        $this->_fields = array( );
        $this->_tables = array( );
        $this->_join_tables = array( );
        
        $this->_clauses = array(
             'FROM' => null
            ,'JOIN' => null
            ,'GROUP' => null
            ,'HAVING' => null
            ,'WHERE' => null
            ,'ORDER' => null
            ,'LIMIT' => null
        );
        $this->_whereExpr = null;
        $this->_havingExpr = null;
        $this->_conditionsW = array( );
        $this->_conditionsH = array( );
        
        return $this;
    }
    
    // return a table reference
    public function table( $tablename )
    {
        if ( !$tablename ) return $tablename;
        
        $isDialect = false;
        $table = $tablename;
        
        if ( $table instanceof DialectTable ) 
        {
            $isDialect = true;
            $tablename = $table->name;
        }
        if ( !isset( $this->_tables[ $tablename ] ) )
        {
            if ( !$isDialect ) $table = new DialectTable( $tablename, $this->_quote );
            $this->_tables[ $tablename ] = $table;
        }
        
        return $table;
    }
    
    // return a field reference
    public function field( $fieldname, $table=null )
    {
        if ( !$fieldname ) return $fieldname;
        
        $isDialect = false;
        $field = $fieldname;
        
        if ( $field instanceof DialectField )
        {
            $isDialect = true;
            $fieldname = $field->name;
        }
        if ( !isset( $this->_fields[ $fieldname ] ) )
        {
            if ( !$isDialect ) $field = new DialectField( $fieldname, null, $this->_quote );
            $this->_fields[ $fieldname ] = $field;
            if ( !$field->table && $table )
            {
                $field->setTable( $this->table( $table ) );
            }
            $table = $field->table;
            if ( $table && !isset( $this->_tables[ $table->name ]) )
            {
                $this->_tables[ $table->name ] = $table;
            }
        }
        
        return $field;
    }
    
    // return a table reference
    public function join_table( $table )
    {
        if ( !$table ) return $table;
        
        $alias = null;
        $isDialect = false;
        $tablename = $table;
        
        if ( $table instanceof DialectTable ) 
        {
            return $table->copy( );
        }
        else
        {
            $aliased = explode(' AS ', $tablename);
            $tablename = trim($aliased[ 0 ]);
            if ( count($aliased) > 1 )
            {
                $alias = trim($aliased[ 1 ]);
            }
            
            $table = new DialectTable( $tablename, $this->_quote );
            if ( $alias )
            {
                $table->useAlias( $alias );
            }
        }
        
        return $table;
    }
    
    public function query( $q=null )
    {
        $this->_query = $q ? $q : '';
        return $this;
    }
    
    public function sql( $part=false )
    {
        // build the query here
        if ( empty($this->_query) )  $this->_query = $this->buildQuery( $part );
        return $this->_query;
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
        else
        {
            return '';
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
    
    public function insert( )
    {
        $this->reset( );
        $this->_type = 'INSERT';
        return $this;
    }
    
    public function update( )
    {
        $this->reset( );
        $this->_type = 'UPDATE';
        return $this;
    }
    
    public function delete( )
    {
        $this->reset( );
        $this->_type = 'DELETE';
        return $this;
    }
    
    public function alter( )
    {
        $this->reset( );
        $this->_type = 'ALTER';
        return $this;
    }
    
    public function select( $fields=array() )
    {
        $this->reset( ); 
        $this->_type = 'SELECT';
        
        $fields = array_values( (array)$fields );
        
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
        
        foreach ($tables as $table)
        {
            // transform to dialect tables
            $this->table( $table );
        }
        // adjust tables in fields
        foreach ($this->_fields as $field)
        {
            if ( $field->table && isset($this->_tables[$field->table->name]) )
            {
                $field->setTable( $this->_tables[$field->table->name] );
            }
        }
        $this->_clauses['FROM'] = true;
            
        return $this;
    }
    
    // partially sanitized using white-list
    public function join( $jointable, $fields, $type='INNER' )
    {
        $type = strtoupper( $type );
        $jointable = $this->join_table( $jointable );
        
        foreach ((array)$fields as $i=>$field)
        {
            $field = $this->field( $field )->setTable( $jointable );
            $fields[ $i ] = $field->alias;
        }
        $on = implode(' = ', $fields);
        return $this;
    }
    
    public function where( $rel, $expr )
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
    public function groupBy( $by, $ord='ASC' )
    {
        $by = $this->field( $by )->alias;
        $ord = strtoupper($ord);
        
        if (!in_array($ord, array('ASC', 'DESC')))   
            $ord = 'ASC';
            
        $this->_clauses['GROUP'] = "GROUP BY {$by} {$ord}";
        
        return $this;
    }
    
    public function having( $rel, $expr )
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
    public function orderBy( $by, $ord='ASC' )
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
            
        $by = $this->field( $by )->alias;
        
        $this->_clauses['ORDER'] .= " {$by} {$ord}";
        
        return $this;
    }
    
    // sanitized using intval
    public function limit( $count, $offset=0 )
    {
        // perform some sanitization
        $offset = intval( $offset );
        $count = intval( $count );
        $this->_clauses['LIMIT'] = "LIMIT {$offset}, {$count}";
        
        return $this;
    }
    
    // sanitized using intval
    public function paged( $per_page, $page=1 )
    {
        // perform some sanitization
        $page = intval( $page );
        if ($page < 1) $page = 1;
        $per_page = intval($per_page);
        
        return $this->limit($per_page, ($page-1)*$per_page);
    }
}
}
