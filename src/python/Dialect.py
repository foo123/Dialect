##
#
#   Dialect Cross-Platform SQL Builder for PHP, Python, Node/JS, ActionScript
#   https://github.com/foo123/Dialect
# 
#   Abstract the construction of SQL queries
#   Support multiple DB vendors
#   Intuitive and Flexible API
#
##

#
#
#_Requirements:__
#
# Support multiple DB vendors (eg. MySQL, Postgre, Oracle, SQL Server )
# Easily extended to new DBs ( preferably through a config setting )
# Flexible and Intuitive API
# Light-weight ( one class/file per implementation if possible )
# Speed
#
#


#
#
# Main Dialect Classes
#
#

Dialect = root.Dialect = {};

Dialect.Table = function(table, id, quote) {
    var _cnt = 0;
    
    var _id = 0;
    var _table = null;
    var _alias = null;
    var _asAlias = null;
    var _quote = '';
    
    this.useAlias = function( bool ) {
        bool = undef === bool ? true : !!bool;
        
        if ( bool )
        {
            _alias = _quote + _table + "_talias_" + _id + _quote;
            _asAlias = _quote + _table + _quote + " AS " + _quote + _alias + _quote;
        }
        else
        {
            _alias = _quote + _table + _quote;
            _asAlias = _quote + _table + _quote;
        }
        return this;
    };
    
    this.table = function() {
        return _table;
    };
    
    this.alias = function() {
        return _alias;
    };
    
    this.asAlias = function() {
        return _asAlias;
    };
    
    _table = trim(table);
    _id = id ? id : ++_cnt;
    _quote = quote || '';
    this.useAlias( false );
};

Dialect.Field = function( field, table, quote) {
    var _cnt = 0;
    
    var _id = 0;
    var _field = null;
    var _alias = null;
    var _asAlias = null;
    var _table = null;
    var _quote = '';
    
    this.useAlias = function( bool ) {
        bool = undef === bool ? true : !!bool;
        
        var table = '';
        
        if ( _table )
        {
            table = _table.alias() + ".";
        }
        
        if ( bool )
        {
            _alias = _quote + _field + "_falias_" + _id + _quote;
            _asAlias = table + _quote + _field + _quote + " AS " + _quote + _alias + _quote;
        }
        else
        {
            _alias = _quote + _field + _quote;
            _asAlias = table + _quote + _field + _quote;
        }
        return this;
    };
    
    this.setTable = function( table ) {
        if ( !(table instanceof Dialect.Table) )  table = new Dialect.Table( table );
        _table = table;
        return this;
    };
    
    this.field = function() {
        return _field;
    };
    
    this.alias = function() {
        return _alias;
    };
    
    this.asAlias = function() {
        return _asAlias;
    };
    
    this.table = function() {
        return _table;
    };
    
    this.parseField = function( f )  {
        
        var tmp = f.split('.', 2);
        var parts = {};
        
        if ( tmp[1] )
        {
            parts[ 'table' ] = tmp[0];
            parts[ 'field' ] = tmp[1];
        }
        else
        {   
            parts[ 'field' ] = tmp[0];
        }
        return parts;
    };
    
    var parts = this.parseField( field );
    
    _field = parts[ 'field' ];
    _quote = quote || '';
    _id = ++_cnt;
    
    if ( table ) this.setTable( table );
    
    else if ( parts[ 'table' ] )  this.setTable( parts[ 'table' ] );
        
    this.useAlias( false );
};

Dialect.Expression = function(_config) {

    var config = null;
    var e = [];
    var rel_ops = ['AND', 'OR'];
    var ops = {'=':1, '>':1, '<':1, '>=':1, '<=':1, '<>':1, 'LIKE':1, 'NOT_LIKE':1, 'BETWEEN':2, 'IN':100, 'NOT_IN':100};
    
    this.toString = function()  {
        return this.get();
    };
    
    this.reset = function() {
        e = [];
        return this;
    };
    
    this.get = function() {
        return e.join(' ');
    };
    
    this.expr = function() {
        var _args = Array.prototype.slice.call(arguments)
        var _argslen = _args.length;
        
        if ( _argslen > 1 && is_array( _args[0] ) )
        {
            _args = _args[0];
            _argslen = _args.length;
        }
        
        if (_argslen < 3 ) return this;
        
        var field = _args[0];
        var op = _args[1];
        var args = _args[2];
        var q = ( _args[3] ) ? _args[3] : '';
        
        // process operator
        var opc = op.toUpperCase().replace('/\s+/', '_');
        
        // nothing to do
        if ( !ops[opc] )  return this;
        
        op = opc.replace('_', ' ');
        var expr = field.alias() + " " + op + " ";
        
        //args = array_values((array)$args);
        switch( ops[opc] )
        {
            case 100:
                expr += '(' + q+args.join("{$q},{$q}")+q + ')';
                break;
            case 2:
                expr += "({$q}{$args[0]}{$q}, {$q}{$args[1]}{$q})";
                break;
            case 1:
            default:
                expr += q+args[0]+q;
                break;
        }
        
        e,push( "({$expr})" );
        
        return this;
    };
    
    config = _config;
    this.reset();
};

Dialect.SQL = function() {

    const VERSION = "0.2";
    
    private static $isInited = false;
    private static $vendors = array();
    
    private $_vendor = null;
    private $_vendor_clauses = null;
    private $_quote = '';
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
    
    public static addVendor($vendor=null, $config=null)
    {
        if ( $vendor && $config )
        {
            self::$vendors[ strtolower($vendor) ] = (array)$config;
        }
    }
    
    public static init()
    {
        if ( self::$isInited ) return;
        
        // http://www.php.net/manual/en/refs.database.abstract.php
        // http://www.php.net/manual/en/intro.pdo.php
        
        self::addVendor('mysql', array(
            // http://php.net/manual/en/function.mysql-real-escape-string.php
            // http://www.php.net/manual/en/mysqli.real-escape-string.php
            //'escape'    =>  'mysqli_real_escape_string ( mysqli $link , string $escapestr )',
            
            'quote'         =>  '`',
            
            'concat'        =>  '',
            
            'operatorss'    =>  array(
                '=' => 1, 
                '>' => 1, 
                '<' => 1, 
                '>=' => 1, 
                '<=' => 1, 
                '<>' => 1, 
                'LIKE' => 1, 
                'NOT_LIKE' => 1, 
                'BETWEEN' => 2, 
                'IN' => 100, 
                'NOT_IN' => 100
            ),
            
            'rel_operatorss'   =>  array('AND', 'OR'),
            
            'datetime'        => array(
                'CURRENT'   =>  'CURRENT_TIMESTAMP()',
                'NULLDATE'  =>  '0000-00-00 00:00:00',
                'YEAR'      =>  'YEAR(__{{DATE}}__)',
                'MONTH'     =>  'MONTH(__{{DATE}}__)',
                'DAY'       =>  'DAY(__{{DATE}}__)',
                'HOUR'      =>  'HOUR(__{{DATE}}__)',
                'MINUTE'    =>  'MINUTE(__{{DATE}}__)',
                'SECOND'    =>  'SECOND(__{{DATE}}__)'
            ),
            
            'clauses'   =>  array(
                'SELECT' => <<<_CLAUSE_
SELECT __{{SELECT_EXPRS}}__
FROM __{{TABLE_REFS}}__
WHERE __{{WHERE_CONDS}}__
GROUP BY __{{GROUP_EXPRS}}__
HAVING __{{HAVING_CONDS}}__
ORDER BY __{{ORDER_EXPRS}}__
LIMIT __{{LIMITS}}__
_CLAUSE_
,
                'INSERT' => <<<_CLAUSE_
INSERT INTO __{{TABLE_REF}}__
VALUES __{{VAL_EXPRS}}__
_CLAUSE_
,
                'UPDATE' => <<<_CLAUSE_
UPDATE __{{TABLE_REFS}}__
SET __{{KEY_VAL_EXPRS}}__
WHERE __{{WHERE_CONDS}}__
_CLAUSE_
,
                'DELETE' => <<<_CLAUSE_
DELETE FROM __{{TABLE_REF}}__
WHERE __{{WHERE_CONDS}}__
ORDER BY __{{ORDER_EXPRS}}__
LIMIT __{{LIMIT_COUNT}}__
_CLAUSE_
,
                'ALTER' => <<<_CLAUSE_
ALTER [IGNORE] TABLE tbl_name
    [alter_specification [, alter_specification] ...]

alter_specification:
    table_options
  | ADD [COLUMN] col_name column_definition
        [FIRST | AFTER col_name ]
  | ADD [COLUMN] (col_name column_definition,...)
  | ADD {INDEX|KEY} [index_name]
        [index_type] (index_col_name,...) [index_type]
  | ADD [CONSTRAINT [symbol]] PRIMARY KEY
        [index_type] (index_col_name,...) [index_type]
  | ADD [CONSTRAINT [symbol]]
        UNIQUE [INDEX|KEY] [index_name]
        [index_type] (index_col_name,...) [index_type]
  | ADD [FULLTEXT|SPATIAL] [INDEX|KEY] [index_name]
        (index_col_name,...) [index_type]
  | ADD [CONSTRAINT [symbol]]
        FOREIGN KEY [index_name] (index_col_name,...)
        reference_definition
  | ALTER [COLUMN] col_name {SET DEFAULT literal | DROP DEFAULT}
  | CHANGE [COLUMN] old_col_name new_col_name column_definition
        [FIRST|AFTER col_name]
  | MODIFY [COLUMN] col_name column_definition
        [FIRST | AFTER col_name]
  | DROP [COLUMN] col_name
  | DROP PRIMARY KEY
  | DROP {INDEX|KEY} index_name
  | DROP FOREIGN KEY fk_symbol
  | DISABLE KEYS
  | ENABLE KEYS
  | RENAME [TO|AS] new_tbl_name
  | ORDER BY col_name [, col_name] ...
  | CONVERT TO CHARACTER SET charset_name [COLLATE collation_name]
  | [DEFAULT] CHARACTER SET [=] charset_name [COLLATE [=] collation_name]
  | DISCARD TABLESPACE
  | IMPORT TABLESPACE
_CLAUSE_

            )
        ));
        
        self::addVendor('postgre', array(
            // http://www.php.net/manual/en/function.pg-escape-string.php
            //'escape'    =>  'pg_escape_string ([ resource $connection ], string $data )',
            
            'quote'     =>  '"',
            
            'concat'    =>  '||',
            
            'operatorss'    =>  array(
                '=' => 1, 
                '>' => 1, 
                '<' => 1, 
                '>=' => 1, 
                '<=' => 1, 
                '<>' => 1, 
                'LIKE' => 1, 
                'NOT_LIKE' => 1, 
                'BETWEEN' => 2, 
                'IN' => 100, 
                'NOT_IN' => 100
            ),
            
            'rel_operatorss'   =>  array('AND', 'OR'),
            
            'datetime'  =>  array(
                'CURRENT'   =>  'NOW()',
                'NULLDATE'  =>  '1970-01-01 00:00:00',
                'YEAR'      =>  'EXTRACT (YEAR FROM __{{DATE}}__)',
                'MONTH'     =>  'EXTRACT (MONTH FROM __{{DATE}}__)',
                'DAY'       =>  'EXTRACT (DAY FROM __{{DATE}}__)',
                'HOUR'      =>  'EXTRACT (HOUR FROM __{{DATE}}__)',
                'MINUTE'    =>  'EXTRACT (MINUTE FROM __{{DATE}}__)',
                'SECOND'    =>  'EXTRACT (SECOND FROM __{{DATE}}__)'
            ),
            
            'clauses'   =>  array(
                'SELECT' => <<<_CLAUSE_
SELECT __{{SELECT_EXPRS}}__
FROM __{{TABLE_REFS}}__
WHERE __{{WHERE_CONDS}}__
GROUP BY __{{GROUP_EXPRS}}__
HAVING __{{HAVING_CONDS}}__
ORDER BY __{{ORDER_EXPRS}}__
LIMIT __{{LIMITS}}__
_CLAUSE_
,
                'INSERT' => <<<_CLAUSE_
INSERT INTO __{{TABLE_REF}}__
VALUES __{{VAL_EXPRS}}__
_CLAUSE_
,
                'UPDATE' => <<<_CLAUSE_
UPDATE __{{TABLE_REFS}}__
SET __{{KEY_VAL_EXPRS}}__
WHERE __{{WHERE_CONDS}}__
_CLAUSE_
,
                'DELETE' => <<<_CLAUSE_
DELETE FROM __{{TABLE_REF}}__
WHERE __{{WHERE_CONDS}}__
ORDER BY __{{ORDER_EXPRS}}__
LIMIT __{{LIMIT_COUNT}}__
_CLAUSE_
,
                'ALTER' => <<<_CLAUSE_
ALTER [IGNORE] TABLE tbl_name
    [alter_specification [, alter_specification] ...]

alter_specification:
    table_options
  | ADD [COLUMN] col_name column_definition
        [FIRST | AFTER col_name ]
  | ADD [COLUMN] (col_name column_definition,...)
  | ADD {INDEX|KEY} [index_name]
        [index_type] (index_col_name,...) [index_type]
  | ADD [CONSTRAINT [symbol]] PRIMARY KEY
        [index_type] (index_col_name,...) [index_type]
  | ADD [CONSTRAINT [symbol]]
        UNIQUE [INDEX|KEY] [index_name]
        [index_type] (index_col_name,...) [index_type]
  | ADD [FULLTEXT|SPATIAL] [INDEX|KEY] [index_name]
        (index_col_name,...) [index_type]
  | ADD [CONSTRAINT [symbol]]
        FOREIGN KEY [index_name] (index_col_name,...)
        reference_definition
  | ALTER [COLUMN] col_name {SET DEFAULT literal | DROP DEFAULT}
  | CHANGE [COLUMN] old_col_name new_col_name column_definition
        [FIRST|AFTER col_name]
  | MODIFY [COLUMN] col_name column_definition
        [FIRST | AFTER col_name]
  | DROP [COLUMN] col_name
  | DROP PRIMARY KEY
  | DROP {INDEX|KEY} index_name
  | DROP FOREIGN KEY fk_symbol
  | DISABLE KEYS
  | ENABLE KEYS
  | RENAME [TO|AS] new_tbl_name
  | ORDER BY col_name [, col_name] ...
  | CONVERT TO CHARACTER SET charset_name [COLLATE collation_name]
  | [DEFAULT] CHARACTER SET [=] charset_name [COLLATE [=] collation_name]
  | DISCARD TABLESPACE
  | IMPORT TABLESPACE
_CLAUSE_

            )
        ));
            
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
        $this->_quote = self::$vendors[ $this->_vendor ]['quote'];
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
        if ( !$table ) return $table;
        
        elseif ( $table instanceof DialectTable ) return $table;
        
        if ( !isset( $this->_tables[ $table ] ) )
            $this->_tables[ $table ] = new DialectTable($table, ++self::$_tableCnt, $this->_quote);
        
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
Dialect.init();