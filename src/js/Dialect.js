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
!function( root, name, factory ) {
    "use strict";
    
    //
    // export the module, umd-style (no other dependencies)
    var isCommonJS = ("object" === typeof(module)) && module.exports, 
        isAMD = ("function" === typeof(define)) && define.amd, m;
    
    // CommonJS, node, etc..
    if ( isCommonJS ) 
        module.exports = (module.$deps = module.$deps || {})[ name ] = module.$deps[ name ] || (factory.call( root, {NODE:module} ) || 1);
    
    // AMD, requireJS, etc..
    else if ( isAMD && ("function" === typeof(require)) && ("function" === typeof(require.specified)) && require.specified(name) ) 
        define( name, ['require', 'exports', 'module'], function( require, exports, module ){ return factory.call( root, {AMD:module} ); } );
    
    // browser, web worker, etc.. + AMD, other loaders
    else if ( !(name in root) ) 
        (root[ name ] = (m=factory.call( root, {} ) || 1)) && isAMD && define( name, [], function( ){ return m; } );


}(  /* current root */          this, 
    /* module name */           "Dialect",
    /* module factory */        function( exports, undef ) {
"use strict";
var __version__ = "0.1", 
    PROTO = 'prototype', HAS = 'hasOwnProperty', 
    // https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String/Trim
    trim = String[PROTO].trim 
            ? function( s ){ return s.trim( ); } 
            : function( s ){ return s.replace(/^\s+|\s+$/g, ''); }, 
    Tpl, Table, Field, Expression, SQL, Dialect = {VERSION: __version__};

    
/*

__Requirements:__

* Support multiple DB vendors (eg. MySQL, Postgre, Oracle, SQL Server )
* Easily extended to new DBs ( preferably through a config setting )
* Flexible and Intuitive API
* Light-weight ( one class/file per implementation if possible )
* Speed

*/


/**
*
*  Main Dialect Classes
*
**/

/**
*
*  Dialect Template, Dialect.Tpl
*
**/
Dialect.Tpl = Tpl = function Tpl( tpl, replacements, compiled ) {
    var self = this;
    if ( !(self instanceof Tpl) ) return new Tpl(tpl, replacements, compiled);
    self.id = null;
    self._renderer = null;
    self.tpl = Tpl.multisplit( tpl||'', replacements||Tpl.defaultArgs );
    if ( true === compiled ) self._renderer = Tpl.compile( self.tpl );
    self.fixRenderer( );
};
Tpl.defaultArgs = {'$-5':-5,'$-4':-4,'$-3':-3,'$-2':-2,'$-1':-1,'$0':0,'$1':1,'$2':2,'$3':3,'$4':4,'$5':5};
Tpl.multisplit = function multisplit( tpl, reps, as_array ) {
    var r, sr, s, i, j, a, b, c, al, bl;
    as_array = !!as_array;
    a = [ [1, tpl] ];
    for ( r in reps )
    {
        if ( reps.hasOwnProperty( r ) )
        {
            c = [ ]; sr = as_array ? reps[ r ] : r; s = [0, reps[ r ]];
            for (i=0,al=a.length; i<al; i++)
            {
                if ( 1 === a[ i ][ 0 ] )
                {
                    b = a[ i ][ 1 ].split( sr ); bl = b.length;
                    c.push( [1, b[0]] );
                    if ( bl > 1 )
                    {
                        for (j=0; j<bl-1; j++)
                        {
                            c.push( s );
                            c.push( [1, b[j+1]] );
                        }
                    }
                }
                else
                {
                    c.push( a[ i ] );
                }
            }
            a = c;
        }
    }
    return a;
};
Tpl.multisplit_re = function multisplit_re( tpl, re ) {
    var a = [ ], i = 0, m;
    while ( m = re.exec( tpl ) )
    {
        a.push([1, tpl.slice(i, re.lastIndex - m[0].length)]);
        a.push([0, m[1] ? m[1] : m[0]]);
        i = re.lastIndex;
    }
    a.push([1, tpl.slice(i)]);
    return a;
};
Tpl.arg = function( key, argslen ) { 
    var i, k, kn, kl, givenArgsLen, out = 'args';
    
    if ( arguments.length && null != key )
    {
        if ( key.substr ) 
            key = key.length ? key.split('.') : [];
        else 
            key = [key];
        kl = key.length;
        givenArgsLen = !!(argslen && argslen.substr);
        
        for (i=0; i<kl; i++)
        {
            k = key[ i ]; kn = +k;
            if ( !isNaN(kn) ) 
            {
                if ( kn < 0 ) k = givenArgsLen ? (argslen+(-kn)) : (out+'.length-'+(-kn));
                out += '[' + k + ']';
            }
            else
            {
                out += '["' + k + '"]';
            }
        }
    }
    return out; 
};
Tpl.compile = function( tpl, raw ) {
    var l = tpl.length, 
        i, notIsSub, s, out;
    
    if ( true === raw )
    {
        out = '"use strict"; return (';
        for (i=0; i<l; i++)
        {
            notIsSub = tpl[ i ][ 0 ]; s = tpl[ i ][ 1 ];
            out += notIsSub ? s : Tpl.arg(s);
        }
        out += ');';
    }
    else
    {
        out = '"use strict"; var argslen=args.length; return (';
        for (i=0; i<l; i++)
        {
            notIsSub = tpl[ i ][ 0 ]; s = tpl[ i ][ 1 ];
            if ( notIsSub ) out += "'" + s.replace(SQUOTE, "\\'").replace(NEWLINE, "' + \"\\n\" + '") + "'";
            else out += " + String(" + Tpl.arg(s,"argslen") + ") + ";
        }
        out += ');';
    }
    return F('args', out);
};
Tpl[PROTO] = {
    constructor: Tpl
    
    ,id: null
    ,tpl: null
    ,_renderer: null
    
    ,dispose: function( ) {
        this.id = null;
        this.tpl = null;
        this._renderer = null;
        return this;
    }
    ,fixRenderer: function( ) {
        if ( 'function' === typeof this._renderer )
            this.render = this._renderer;
        else
            this.render = this.constructor[PROTO].render;
        return this;
    }
    ,render: function( args ) {
        args = args || [ ];
        //if ( this._renderer ) return this._renderer( args );
        var tpl = this.tpl, l = tpl.length, 
            argslen = args.length, 
            i, notIsSub, s, out = ''
        ;
        for (i=0; i<l; i++)
        {
            notIsSub = tpl[ i ][ 0 ]; s = tpl[ i ][ 1 ];
            out += (notIsSub ? s : (!s.substr && s < 0 ? args[ argslen+s ] : args[ s ]));
        }
        return out;
    }
};


/**
*
*  Dialect Table, Dialect.Table
*
**/
Dialect.Table = Table = function Table(table, id, quote) {
    var self = this, _cnt = 0, _id = 0, _table = null, _alias = null, _asAlias = null, _quote = '';
    
    self.useAlias = function( bool ) {
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
        return self;
    };
    
    self.table = function() {
        return _table;
    };
    
    self.alias = function() {
        return _alias;
    };
    
    self.asAlias = function() {
        return _asAlias;
    };
    
    _table = trim(table);
    _id = id ? id : ++_cnt;
    _quote = quote || '';
    self.useAlias( false );
};


/**
*
*  Dialect Field, Dialect.Field
*
**/
Dialect.Field = Field = function Field( field, table, quote) {
    var self = this, _cnt = 0, _id = 0, _field = null, _alias = null, _asAlias = null, _table = null, _quote = '';
    
    self.useAlias = function( bool ) {
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
        return self;
    };
    
    self.setTable = function( table ) {
        if ( !(table instanceof Dialect.Table) )  table = new Dialect.Table( table );
        _table = table;
        return self;
    };
    
    self.field = function() {
        return _field;
    };
    
    self.alias = function() {
        return _alias;
    };
    
    self.asAlias = function() {
        return _asAlias;
    };
    
    self.table = function() {
        return _table;
    };
    
    self.parseField = function( f )  {
        
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
    
    var parts = self.parseField( field );
    
    _field = parts[ 'field' ];
    _quote = quote || '';
    _id = ++_cnt;
    
    if ( table ) self.setTable( table );
    else if ( parts[ 'table' ] )  self.setTable( parts[ 'table' ] );
        
    self.useAlias( false );
};


/**
*
*  Dialect Expression, Dialect.Expression
*
**/
Dialect.Expression = Expression = function Expression(_config) {
    var self = this, config = null, e = [], rel_ops = ['AND', 'OR'],
        ops = {'=':1, '>':1, '<':1, '>=':1, '<=':1, '<>':1, 'LIKE':1, 'NOT_LIKE':1, 'BETWEEN':2, 'IN':100, 'NOT_IN':100};
    
    self.toString = function()  {
        return self.get();
    };
    
    self.reset = function() {
        e = [];
        return self;
    };
    
    self.get = function() {
        return e.join(' ');
    };
    
    self.expr = function() {
        var _args = Array[PROTO].slice.call(arguments)
        var _argslen = _args.length;
        
        if ( _argslen > 1 && is_array( _args[0] ) )
        {
            _args = _args[0];
            _argslen = _args.length;
        }
        
        if (_argslen < 3 ) return self;
        
        var field = _args[0];
        var op = _args[1];
        var args = _args[2];
        var q = ( _args[3] ) ? _args[3] : '';
        
        // process operator
        var opc = op.toUpperCase().replace('/\s+/', '_');
        
        // nothing to do
        if ( !ops[opc] )  return self;
        
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
        
        return self;
    };
    
    config = _config;
    self.reset();
};


/**
*
*  Dialect SQL, Dialect.SQL
*
**/
Dialect.SQL = SQL = function SQL( vendor ) {
    var self = this;
    if ( !(self instanceof SQL) ) return new SQL(vendor);
    self._vendor = vendor.toLowerCase();
    self._q = vendors[ self._vendor ]['quote'];
    self._vendor_clauses = vendors[ self._vendor ]['clauses'];
    self._vendor_tpls = vendors[ self._vendor ]['tpls'];
    self.reset( ).sanitize( true );
};    
SQL[PROTO] = {
    constructor: SQL
    
    ,_vendor: null
    ,_vendor_clauses: null
    ,_vendor_tpls: null
    ,_quote: ''
    ,_q: ''
    ,_sanitize: false
    ,_fields: null
    ,_tables: null
    ,_whereExpr: null
    ,_havingExpr: null
    ,_conditionsW: null
    ,_conditionsH: null
    ,_clauses: null
    
    ,dispose: function( ) {
        var self = this;
        self._vendor = null;
        self._vendor_clauses = null;
        self._vendor_tpls = null;
        self._quote = null;
        self._q = null;
        self._sanitize = null;
        self._fields = null;
        self._tables = null;
        self._whereExpr = null;
        self._havingExpr = null;
        self._conditionsW = null;
        self._conditionsH = null;
        self._clauses = null;
        return self;
    }
    ,reset: function( ) {
        var self = this;
        self._q = '';
        self._fields = [];
        self._tables = [];
        self._whereExpr = null;
        self._havingExpr = null;
        self._conditionsW = [];
        self._conditionsH = [];
        self._clauses = [];
        for (var type in self._vendor_clauses)
        {
            if ( self._vendor_clauses[HAS](type) )
            {
                var clauses = self._vendor_clauses[type];
                self._clauses[ $type ] = '';
                for (var clause in clauses) if (clauses[HAS](clause) ) self._clauses[ clause ] = '';
            }
        }
        return self;
    }
    // return a table reference
    ,table: function( table ) {
        var self = this;
        if ( !table || table instanceof Dialect.Table ) return table;
        
        if ( !self._tables[HAS]( table ) )
            self._tables[ table ] = new Dialect.Table(table, ++_tableCnt, self._quote);
        
        return self._tables[ table ];
    }
    // return a field reference
    ,field: function( field, table ) {
        var self = this;
        if ( null === field || field instanceof Dialect.Field ) return field;
        
        if ( !self._fields[HAS]( field ) )
        {
            var f = new Dialect.Field(field, table||null);
            self._fields[ field ] = f;
            
            var t = f->table();
            if ( t && !self._tables[HAS]( t.table() ) )
                self._tables[ t.table() ] = t;
        }
        return self._fields[ field ];
    }
    // return an expression reference
    ,expression: function( field, op, args, q ) {   
        var expr = new Dialect.Expression();
        expr->expr(field, op, args, q||'');
        return expr;
    }
    // try to sanitize if possible
    ,sanitize: function( bool ) {
        var self = this;
        self._sanitize = !!bool;
        return self;
    }
    ,buildQuery: function( part/*=null*/ ) {
        var self = this, parts;
        // allow to get partial query back
        if (!part)
            parts = array_flip( self._default_clauses[ self._type ] );
        else
            parts = array_flip( part );
            
        var parts2 = parts; // clone
        
        var sql = '';
        var type = self._type;
        
        if ( 'SELECT'==type )
        {
            sql += 'SELECT ';
            
            var i = 0;
            for (self._fields as field)
            {
                if (i)  sql += ", ";
                sql += field.asAlias();
                i++;
            }
            sql += " ";
        }    
        
        else if ( 'INSERT'==type )
        {
            // TODO
            sql += 'INSERT ';
        }    
        
        else if ( 'UPDATE'==type )
        {
            // TODO
            sql += 'UPDATE ';
        }    
        
        else if ( 'DELETE'==type )
        {
            // TODO
            sql += 'DELETE ';
        }
        
        else if ( 'ALTER'==type )
        {
            // TODO
            sql += 'ALTER TABLE ';
        }
        
        sql += "\n" +  array_values(
                        array_intersect_key( 
                            array_merge( parts, self._clauses )
                        , parts2)
                    ).filter(Boolean).join("\n");
        return sql;
    }
    ,query: function( q/*=false*/ ) {
        var self = this;
        self._q = q ? q : '';
        return self;
    }
    ,sql: function( part ) {
        var self = this;
        // build the query here
        if (!self._q) self._q = self.buildQuery( part );
        return self._q;
    }
    // return the sql as string, if this object is cast as a string ;)
    ,toString: function( ) {
        return this.sql();
    }
    ,insert: function(){
        $this->reset();
        $this->_type = 'INSERT';
        return $this;
    }
    ,update: function(){
        $this->reset();
        $this->_type = 'UPDATE';
        return $this;
    }
    ,del: function() {
        $this->reset();
        $this->_type = 'DELETE';
        return $this;
    }
    ,alter: function(){
        $this->reset();
        $this->_type = 'ALTER';
        return $this;
    }
    ,select: function( $fields = array() ) {
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
    ,from: function( $tables ){
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
    ,join: function($on, $jointable=null, $type='INNER'){
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
    ,where: function($rel, $expr){
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
    ,groupBy: function($by, $ord='ASC'){
        $by = $this->field( $by )->alias();
        $ord = strtoupper($ord);
        
        if (!in_array($ord, array('ASC', 'DESC')))   
            $ord = 'ASC';
            
        $this->_clauses['GROUP'] = "GROUP BY {$by} {$ord}";
        
        return $this;
    }
    ,having: function($rel, $expr){
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
    ,orderBy: function($by, $ord='ASC'){
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
    ,limit: function($count, $offset=0){
        // perform some sanitization
        $offset = intval($offset);
        $count = intval($count);
        $this->_clauses['LIMIT'] = "LIMIT {$offset}, {$count}";
        
        return $this;
    }
    // sanitized using intval
    ,paged: function($per_page, $page=1){
        // perform some sanitization
        $page = intval($page);
        if ($page < 1)
            $page = 1;
        $per_page = intval($per_page);
        
        return $this->limit($per_page, ($page-1)*$per_page);
    }
};

// export it
return Dialect;
});
