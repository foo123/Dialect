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
!function( root, name, factory ) {
"use strict";
var m;
if ( ('undefined'!==typeof Components)&&('object'===typeof Components.classes)&&('object'===typeof Components.classesByID)&&Components.utils&&('function'===typeof Components.utils['import']) ) /* XPCOM */
    (root.EXPORTED_SYMBOLS = [ name ]) && (root[ name ] = factory.call( root ));
else if ( ('object'===typeof module)&&module.exports ) /* CommonJS */
    module.exports = factory.call( root );
else if ( ('function'===typeof(define))&&define.amd&&('function'===typeof(require))&&('function'===typeof(require.specified))&&require.specified(name) ) /* AMD */
    define(name,['require','exports','module'],function( ){return factory.call( root );});
else if ( !(name in root) ) /* Browser/WebWorker/.. */
    (root[ name ] = (m=factory.call( root )))&&('function'===typeof(define))&&define.amd&&define(function( ){return m;} );
}(  /* current root */          this, 
    /* module name */           "Dialect",
    /* module factory */        function( exports, undef ) {
"use strict";

var PROTO = 'prototype', HAS = 'hasOwnProperty', 
    Keys = Object.keys, toString = Object[PROTO].toString,
    CHAR = 'charAt', CHARCODE = 'charCodeAt',
    escaped_re = /[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, trim_re = /^\s+|\s+$/g,
    trim = String[PROTO].trim
        ? function( s ){ return s.trim(); }
        : function( s ){ return s.replace(trim_re, ''); },
    NULL_CHAR = String.fromCharCode( 0 ),
    Tpl, Ref, Dialect
;
function F( a, c )
{
    return new Function(a, c);
}
function RE( r, f )
{
    return new RegExp(r, f||'');
}
function esc_re( s )
{
    return s.replace(escaped_re, "\\$&");
}
function is_callable( o )
{
    return "function" === typeof o;
}
function is_string( o )
{
    return "string" === typeof o;
}
function is_array( o )
{
    return o instanceof Array || '[object Array]' === toString.call(o);
}
function is_obj( o )
{
    return o instanceof Object || '[object Object]' === toString.call(o);
}
function is_string_or_array( o )
{
    var to_string = toString.call(o);
    return (o instanceof Array || o instanceof String || '[object Array]' === to_string || '[object String]' === to_string); 
}
function empty( o )
{
    if ( !o ) return true;
    var to_string = toString.call(o);
    if ( (o instanceof Array || o instanceof String || '[object Array]' === to_string || '[object String]' === to_string) && !o.length ) return true;
    if ( (o instanceof Object || '[object Array]' === to_string) && !Keys(o).length ) return true;
    return false;
}
function int( n )
{
    return parseInt(n||0, 10)||0;
}
function is_int( mixed_var )
{
    return mixed_var === +mixed_var && isFinite(mixed_var) && !(mixed_var % 1);
}
function array( o )
{
    return is_array( o ) ? o : [o];
}
function addslashes( s, chars, esc )
{
    var s2 = '', i, l, c;
    if ( 3 > arguments.length ) esc = '\\';
    if ( 2 > arguments.length ) chars = '\\"\'' + NULL_CHAR;
    for (i=0,l=s.length; i<l; i++)
    {
        c = s[CHAR]( i );
        s2 += -1 === chars.indexOf( c ) ? c : (0 === c[CHARCODE](0) ? '\\0' : (esc+c));
    }
    return s2;
}
function fmap( x, F )
{
    var l = x.length;
    if ( !l ) return [];
    var i, k, r = l&15, q = r&1, Fx=new Array(l);
    if ( q ) Fx[0] = F(x[0]);
    for (i=q; i<r; i+=2)
    { 
        k = i;
        Fx[i  ] = F(x[k  ]);
        Fx[i+1] = F(x[k+1]);
    }
    for (i=r; i<l; i+=16)
    {
        k = i;
        Fx[i  ] = F(x[k  ]);
        Fx[i+1] = F(x[k+1]);
        Fx[i+2] = F(x[k+2]);
        Fx[i+3] = F(x[k+3]);
        Fx[i+4] = F(x[k+4]);
        Fx[i+5] = F(x[k+5]);
        Fx[i+6] = F(x[k+6]);
        Fx[i+7] = F(x[k+7]);
        Fx[i+8] = F(x[k+8]);
        Fx[i+9] = F(x[k+9]);
        Fx[i+10] = F(x[k+10]);
        Fx[i+11] = F(x[k+11]);
        Fx[i+12] = F(x[k+12]);
        Fx[i+13] = F(x[k+13]);
        Fx[i+14] = F(x[k+14]);
        Fx[i+15] = F(x[k+15]);
    }
    return Fx;
}
function ffilter( x, F )
{
    var l = x.length;
    if ( !l ) return [];
    var i, k, r = l&15, q = r&1, Fx=[];
    if ( q && F(x[0]) ) Fx.push(x[0]);
    for (i=q; i<r; i+=2)
    { 
        k = i;
        if ( F(x[  k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
    }
    for (i=r; i<l; i+=16)
    {
        k = i;
        if ( F(x[  k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
        if ( F(x[++k]) ) Fx.push(x[k]);
    }
    return Fx;
}

Tpl = function Tpl( tpl, replacements, compiled ) {
    var self = this;
    if ( !(self instanceof Tpl) ) return new Tpl(tpl, replacements, compiled);
    self.id = null;
    self._renderer = null;
    replacements = replacements || Tpl.defaultArgs;
    self.tpl = replacements instanceof RegExp 
        ? Tpl.multisplit_re(tpl||'', replacements) 
        : Tpl.multisplit( tpl||'', replacements );
    if ( true === compiled ) self._renderer = Tpl.compile( self.tpl );
    self.fixRenderer( );
};
Tpl.defaultArgs = /\$(-?[0-9]+)/g;
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
    re = re.global ? re : new RegExp(re.source, re.ignoreCase?"gi":"g"); /* make sure global flag is added */
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

Ref = function( col, col_q, tbl, tbl_q, alias, alias_q, 
                tbl_col, tbl_col_q, tbl_col_alias, tbl_col_alias_q ) {
    var self = this;
    self.col = col;
    self.col_q = col_q;
    self.tbl = tbl;
    self.tbl_q = tbl_q;
    self.alias = alias;
    self.alias_q = alias_q;
    self.tbl_col = tbl_col;
    self.tbl_col_q = tbl_col_q;
    self.tbl_col_alias = tbl_col_alias;
    self.tbl_col_alias_q = tbl_col_alias_q;
};
Ref.parse = function( r, d ) {
    var col, col_q, tbl, tbl_q, alias, alias_q, 
        tbl_col, tbl_col_q, tbl_col_alias, tbl_col_alias_q;
    r = trim( r ).split(' AS ');
    col = r[ 0 ].split( '.' );
    tbl = col.length < 2 ? null : trim(col[ 0 ]);
    col = tbl ? trim(col[ 1 ]) : trim(col[ 0 ]);
    col_q = d.quote_name( col );
    if ( tbl )
    {
        tbl_q = d.quote_name( tbl );
        tbl_col = tbl + '.' + col;
        tbl_col_q = tbl_q + '.' + col_q;
    }
    else
    {
        tbl_q = null;
        tbl_col = col;
        tbl_col_q = col_q;
    }
    if ( r.length < 2 )
    {
        alias = tbl_col;
        alias_q = tbl_col_q;
        tbl_col_alias = tbl_col;
        tbl_col_alias_q = tbl_col_q;
    }
    else
    {
        alias = trim( r[1] );
        alias_q = d.quote_name( alias );
        tbl_col_alias = tbl_col + ' AS ' + alias;
        tbl_col_alias_q = tbl_col_q + ' AS ' + alias_q;
    }
    return new Ref( col, col_q, tbl, tbl_q, alias, alias_q, 
                tbl_col, tbl_col_q, tbl_col_alias, tbl_col_alias_q );
};
Ref[PROTO] = {
     constructor: Ref
    
    ,col: null
    ,col_q: null
    ,tbl: null
    ,tbl_q: null
    ,alias: null
    ,alias_q: null
    ,tbl_col: null
    ,tbl_col_q: null
    ,tbl_col_alias: null
    ,tbl_col_alias_q: null
    
    ,dispose: function( ) {
        var self = this;
        self.col = null;
        self.col_q = null;
        self.tbl = null;
        self.tbl_q = null;
        self.alias = null;
        self.alias_q = null;
        self.tbl_col = null;
        self.tbl_col_q = null;
        self.tbl_col_alias = null;
        self.tbl_col_alias_q = null;
        return self;
    }
};

var dialect = {
 'mysql'            : {
     // https://dev.mysql.com/doc/refman/5.0/en/select.html
     // https://dev.mysql.com/doc/refman/5.0/en/join.html
     // https://dev.mysql.com/doc/refman/5.5/en/expressions.html
     // https://dev.mysql.com/doc/refman/5.0/en/insert.html
     // https://dev.mysql.com/doc/refman/5.0/en/update.html
     // https://dev.mysql.com/doc/refman/5.0/en/delete.html
     'quote'        : [ ["'","'","\\'","\\'"], ['`','`'], ['',''] ]
    ,'clauses'      : {
     'select'  : ['select','from','join','where','group','having','order','limit']
    ,'insert'  : ['insert','values']
    ,'update'  : ['update','set','where','order','limit']
    ,'delete'  : ['delete','from','where','order','limit']
    }
    ,'tpl'        : {
     'select'   : 'SELECT $(select_columns)'
    ,'insert'   : 'INSERT INTO $(insert_tables) ($(insert_columns))'
    ,'update'   : 'UPDATE $(update_tables)'
    ,'delete'   : 'DELETE '
    ,'values'   : 'VALUES $(values_values)'
    ,'set'      : 'SET $(set_values)'
    ,'from'     : 'FROM $(from_tables)'
    ,'join'     : '$(join_clauses)'
    ,'where'    : 'WHERE $(where_conditions)'
    ,'group'    : 'GROUP BY $(group_conditions)'
    ,'having'   : 'HAVING $(having_conditions)'
    ,'order'    : 'ORDER BY $(order_conditions)'
    ,'limit'    : 'LIMIT $(offset),$(count)'
    }
}
,'postgre'          : {
     // http://www.postgresql.org/docs/
     // http://www.postgresql.org/docs/8.2/static/sql-syntax-lexical.html
     'quote'        : [ ["E'","'","''","''"], ['"','"'], ['',''] ]
    ,'clauses'      : {
     'select'  : ['select','from','join','where','group','having','order','limit']
    ,'insert'  : ['insert','values']
    ,'update'  : ['update','set','where','order','limit']
    ,'delete'  : ['delete','from','where','order','limit']
    }
    ,'tpl'        : {
     'select'   : 'SELECT $(select_columns)'
    ,'insert'   : 'INSERT INTO $(insert_tables) ($(insert_columns))'
    ,'update'   : 'UPDATE $(update_tables)'
    ,'delete'   : 'DELETE '
    ,'values'   : 'VALUES $(values_values)'
    ,'set'      : 'SET $(set_values)'
    ,'from'     : 'FROM $(from_tables)'
    ,'join'     : '$(join_clauses)'
    ,'where'    : 'WHERE $(where_conditions)'
    ,'group'    : 'GROUP BY $(group_conditions)'
    ,'having'   : 'HAVING $(having_conditions)'
    ,'order'    : 'ORDER BY $(order_conditions)'
    ,'limit'    : 'LIMIT $(count) OFFSET $(offset)'
    }
}
,'sqlserver'        : {
     // https://msdn.microsoft.com/en-us/library/ms189499.aspx
     // https://msdn.microsoft.com/en-us/library/ms174335.aspx
     // https://msdn.microsoft.com/en-us/library/ms177523.aspx
     // https://msdn.microsoft.com/en-us/library/ms189835.aspx
     // https://msdn.microsoft.com/en-us/library/ms179859.aspx
     // http://stackoverflow.com/questions/603724/how-to-implement-limit-with-microsoft-sql-server
     'quote'        : [ ["'","'","''","''"], ['[',']'], [''," ESCAPE '\\'"] ]
    ,'clauses'      : {
     'select'  : ['select','from','join','where','group','having','order']
    ,'insert'  : ['insert','values']
    ,'update'  : ['update','set','where','order']
    ,'delete'  : ['delete','from','where','order']
    }
    ,'tpl'        : {
     'select'   : 'SELECT $(select_columns)'
    ,'insert'   : 'INSERT INTO $(insert_tables) ($(insert_columns))'
    ,'update'   : 'UPDATE $(update_tables)'
    ,'delete'   : 'DELETE '
    ,'values'   : 'VALUES $(values_values)'
    ,'set'      : 'SET $(set_values)'
    ,'from'     : 'FROM $(from_tables)'
    ,'join'     : '$(join_clauses)'
    ,'where'    : 'WHERE $(where_conditions)'
    ,'group'    : 'GROUP BY $(group_conditions)'
    ,'having'   : 'HAVING $(having_conditions)'
    ,'order'    : 'ORDER BY $(order_conditions)'
    }
}
};

Dialect = function Dialect( type ) {
    var self = this;
    if ( !(self instanceof Dialect) ) return new Dialect( type );
    
    self.clau = null;
    self.clus = null;
    self.tbls = null;
    self.cols = null;
    self.vews = { };
    self.tpls = { };
    
    self.db = null;
    self.escdb = null;
    self.p = '';
    
    self.type = type || 'mysql';
    self.clauses = Dialect.dialect[ self.type ][ 'clauses' ];
    self.tpl = Dialect.dialect[ self.type ][ 'tpl' ];
    self.q  = Dialect.dialect[ self.type ][ 'quote' ][ 0 ];
    self.qn = Dialect.dialect[ self.type ][ 'quote' ][ 1 ];
    self.e  = Dialect.dialect[ self.type ][ 'quote' ][ 2 ] || ['',''];
};
Dialect.VERSION = "0.4.0";
Dialect.TPL_RE = /\$\(([^\)]+)\)/g;
Dialect.dialect = dialect;
Dialect.Tpl = Tpl;
Dialect.Ref = Ref;
Dialect[PROTO] = {
    constructor: Dialect
    
    ,clau: null
    ,clus: null
    ,tbls: null
    ,cols: null
    ,vews: null
    ,tpls: null
    
    ,db: null
    ,escdb: null
    ,p: null
    
    ,type: null
    ,clauses: null
    ,tpl: null
    ,q: null
    ,qn: null
    ,e: null
    
    ,dispose: function( ) {
        var self = this;
        self.clau = null;
        self.clus = null;
        self.tbls = null;
        self.cols = null;
        self.vews = null;
        self.tpls = null;
        
        self.db = null;
        self.escdb = null;
        self.p = null;
        
        self.type = null;
        self.clauses = null;
        self.tpl = null;
        self.q = null;
        self.qn = null;
        self.e = null;
        return self;
    }
    
	,toString: function( ) {
        return this.sql( ) || '';
    }
    
    ,driver: function( db ) {
        var self = this;
        if ( arguments.length )
        {
            self.db = db ? db : null;
            return self;
        }
        return self.db;
    }
    
    ,escape: function( escdb ) {
        var self = this;
        if ( arguments.length )
        {
            self.escdb = escdb && is_callable( escdb ) ? escdb : null;
            return self;
        }
        return self.escdb;
    }
    
    ,prefix: function( prefix ) {
        var self = this;
        if ( arguments.length )
        {
            self.p = prefix && prefix.length ? prefix : '';
            return self;
        }
        return self.p;
    }
    
    ,reset: function( clause ) {
        var self = this, i, l, clauses, c;
        self.clau = clause;
        self.clus = { };
        self.tbls = { };
        self.cols = { };
        clauses = self.clauses[ self.clau ];
        for (i=0,l=clauses.length; i<l; i++)
        {
            clause = clauses[ i ];
            if ( self.tpl[HAS](clause) && !(self.tpl[ clause ] instanceof Tpl) )
                self.tpl[ clause ] = new Tpl( self.tpl[ clause ], Dialect.TPL_RE );
        }
        return self;
    }
    
    ,clear: function( ) {
        var self = this;
        self.clau = null;
        self.clus = null;
        self.tbls = null;
        self.cols = null;
        return self;
    }
    
    ,sql: function( ) {
        var self = this, query = null, i, l, clause, clauses,
            sqlserver_limit = null, order_by;
        if ( self.clau && self.clus && self.clauses[HAS]( self.clau ) )
        {
            query = "";
            if ( 'sqlserver' === self.type && 'select' === self.clau && self.clus[ HAS ]( 'limit' ) )
            {
                sqlserver_limit = self.clus[ 'limit' ];
                delete self.clus[ 'limit' ];
                if ( self.clus.order )
                {
                    order_by = self.tpl[ 'order' ].render( self.clus[ 'order' ] );
                    delete self.clus[ 'order' ];
                }
                else
                {
                    order_by = 'ORDER BY (SELECT 1)';
                }
                self.clus[ 'select' ].select_columns = 'ROW_NUMBER() OVER ('+order_by+') AS __row__,'+self.clus[ 'select' ].select_columns;
            }
            clauses = self.clauses[ self.clau ];
            for (i=0,l=clauses.length; i<l; i++)
            {
                clause = clauses[ i ];
                if ( self.clus[ HAS ]( clause ) )
                    query += (query.length ? "\n" : "") + self.tpl[ clause ].render( self.clus[ clause ] );
            }
            if ( sqlserver_limit )
            {
                query = "SELECT * FROM(\n"+query+"\n) AS __a__ WHERE __row__ BETWEEN "+(sqlserver_limit.offset+1)+" AND "+(sqlserver_limit.offset+sqlserver_limit.count);
            }
        }
        self.clear( );
        return query;
    }
    
    ,prepare: function( query, args, left, right ) {
        var self = this, pattern, offset, m, pos, len, i, l, tmp, param, type, prepared;
        if ( query && args )
        {
            // custom delimiters
            left = left ? esc_re( left ) : '%';
            right = right ? esc_re( right ) : '%';
            
            // custom prepared parameter format
            pattern = RE(left + '([rlfds]:)?([0-9a-zA-Z_]+)' + right);
            prepared = '';
            while ( query.length && (m = query.match( pattern )) )
            {
                pos = m.index;
                len = m[0].length;
                param = m[2];
                if ( args[HAS](param) )
                {
                    type = m[1] ? m[1].slice(0,-1) : "s";
                    switch( type )
                    {
                        case 'r': 
                            // raw param
                            if ( is_array(args[param]) )
                            {
                                param = args[param].join(',');
                            }
                            else
                            {
                                param = args[param];
                            }
                            break;
                        
                        case 'l': 
                            // like param
                            param = self.like( args[param] ); 
                            break;
                            
                        case 'f': 
                            if ( is_array(args[param]) )
                            {
                                // array of references, e.g fields
                                tmp = array( args[param] );
                                param = Ref.parse( tmp[0], self ).tbl_col_alias_q;
                                for (i=1,l=tmp.length; i<l; i++) param += ','+Ref.parse( tmp[i], self ).tbl_col_alias_q;
                            }
                            else
                            {
                                // reference, e.g field
                                param = Ref.parse( args[param], self ).tbl_col_alias_q;
                            }
                            break;
                            
                        case 'd': 
                            if ( is_array(args[param]) )
                            {
                                // array of integers param
                                param = self.intval( array(args[param]) ).join(',');
                            }
                            else
                            {
                                // integer param
                                param = self.intval( args[param] );
                            }
                            break;
                            
                        case 's': 
                        default:
                            if ( is_array(args[param]) )
                            {
                                // array of strings param
                                param = self.quote( array(args[param]) ).join(',');
                            }
                            else
                            {
                                // string param
                                param = self.quote( args[param] );
                            }
                            break;
                    }
                    prepared += query.slice(0, pos) + param;
                }
                else
                {
                    prepared += query.slice(0, pos) + self.quote('');
                }
                query = query.slice( pos+len );
            }
            if ( query.length ) prepared += query;
            return prepared;
        }
        return query;
    }
    
    ,make_view: function( view ) {
        var self = this;
        if ( view && self.clau )
        {
            self.vews[ view ] = {
                clau:self.clau, 
                clus:self.clus, 
                tbls:self.tbls, 
                cols:self.cols
            };
            self.clear( );
        }
        return self;
    }
    
    ,clear_view: function( view ) {
        var self = this;
        if ( view && self.vews[HAS](view) )
        {
            delete self.vews[ view ];
        }
        return self;
    }
    
    ,prepare_tpl: function( tpl /*, query, left, right*/ ) {
        var self = this, pattern, sql, types, i, l, tpli, k, 
            args, argslen, query, left, right, use_internal_query;
        if ( !empty(tpl) )
        {
            args = arguments; 
            argslen = args.length;
            
            if ( 1 === argslen )
            {
                query = null;
                left = null;
                right = null;
                use_internal_query = true;
            }
            else if ( 2 === argslen )
            {
                query = args[ 1 ];
                left = null;
                right = null;
                use_internal_query = false;
            }
            else if ( 3 === argslen )
            {
                query = null;
                left = args[ 1 ];
                right = args[ 2 ];
                use_internal_query = true;
            }
            else/* if ( 3 < argslen )*/
            {
                query = args[ 1 ];
                left = args[ 2 ];
                right = args[ 3 ];
                use_internal_query = false;
            }
            
            // custom delimiters
            left = left ? esc_re( left ) : '%';
            right = right ? esc_re( right ) : '%';
            // custom prepared parameter format
            pattern = RE(left + '(([rlfds]:)?[0-9a-zA-Z_]+)' + right);
            
            if ( use_internal_query )
            {
                sql = new Tpl( self.sql( ), pattern );
                self.clear( );
            }
            else
            {
                sql = new Tpl( query, pattern );
            }
            
            types = {};
            // extract parameter types
            for(i=0,l=sql.tpl.length; i<l; i++)
            {
                tpli = sql.tpl[ i ];
                if ( !tpli[ 0 ] )
                {
                    k = tpli[ 1 ].split(':');
                    if ( k.length > 1 )
                    {
                        types[ k[1] ] = k[0];
                        sql.tpl[ i ][ 1 ] = k[1];
                    }
                    else
                    {
                        types[ k[0] ] = "s";
                        sql.tpl[ i ][ 1 ] = k[0];
                    }
                }
            }
            self.tpls[ tpl ] = {
                'sql':sql, 
                'types':types
            };
        }
        return self;
    }
    
    ,prepared: function( tpl, args ) {
        var self = this, sql, types, type, params, k, v, tmp, i, l;
        if ( !empty(tpl) && self.tpls[HAS](tpl) )
        {
            sql = self.tpls[tpl].sql;
            types = self.tpls[tpl].types;
            params = { };
            for(k in args)
            {
                if ( !args[HAS](k) ) continue;
                v = args[k];
                type = types[HAS](k) ? types[k] : "s";
                switch(type)
                {
                    case 'r': 
                        // raw param
                        if ( is_array(v) )
                        {
                            params[k] = v.join(',');
                        }
                        else
                        {
                            params[k] = v;
                        }
                        break;
                    
                    case 'l': 
                        // like param
                        params[k] = self.like( v ); 
                        break;
                    
                    case 'f': 
                        if ( is_array(v) )
                        {
                            // array of references, e.g fields
                            tmp = array(v);
                            params[k] = Ref.parse( tmp[0], self ).tbl_col_alias_q;
                            for (i=1,l=tmp.length; i<l; i++) params[k] += ','+Ref.parse( tmp[i], self ).tbl_col_alias_q;
                        }
                        else
                        {
                            // reference, e.g field
                            params[k] = DialectRef.parse( v, self ).tbl_col_alias_q;
                        }
                        break;
                    
                    case 'd':
                        if ( is_array(v) )
                        {
                            // array of integers param
                            params[k] = self.intval( array(v) ).join(',');
                        }
                        else
                        {
                            // integer
                            params[k] = self.intval( v );
                        }
                        break;
                    
                    case 's': 
                    default:
                        if ( is_array(v) )
                        {
                            // array of strings param
                            params[k] = self.quote( array(v) ).join(',');
                        }
                        else
                        {
                            // string param
                            params[k] = self.quote( v );
                        }
                        break;
                }
            }
            return sql.render( params );
        }
        return '';
    }
    
    ,clear_tpl: function( tpl ) {
        var self = this;
        if ( !empty(tpl) && self.tpls[HAS](tpl) )
        {
           self.tpls[ tpl ].sql.dispose( );
           delete self.tpls[ tpl ];
        }
        return self;
    }
    
    ,select: function( cols, format ) {
        var self = this, i, l, columns;
        self.reset('select');
        if ( !cols || !cols.length || '*' === cols ) 
        {
            columns = '*';
        }
        else
        {            
            if ( false !== format )
            {
                cols = self.refs( cols, self.cols );
                columns = cols[ 0 ].tbl_col_alias_q;
                for(i=1,l=cols.length; i<l; i++) columns += ',' + cols[ i ].tbl_col_alias_q;
            }
            else
            {
                columns = array( cols ).join(',');
            }
        }
        if ( self.clus.select && self.clus.select.select_columns.length > 0 )
            columns = self.clus.select.select_columns + ',' + columns;
        self.clus.select = {select_columns: columns};
        return self;
    }
    
    ,insert: function( tbls, cols, format ) {
        var self = this, i, l, view, tables, columns;
        self.reset('insert');
        view = is_array( tbls ) ? tbls[0] : tbls;
        if ( self.vews[HAS]( view ) && self.clau === self.vews[ view ].clau )
        {
            // using custom 'soft' view
            view = self.vews[ view ];
            self.clus = self.defaults( self.clus, view.clus, true );
            self.tbls = self.defaults( {}, view.tbls, true );
            self.cols = self.defaults( {}, view.cols, true );
        }
        else
        {
            if ( false !== format )
            {
                tbls = self.refs( tbls, self.tbls );
                cols = self.refs( cols, self.cols );
                tables = tbls[ 0 ].tbl_col_alias_q;
                columns = cols[ 0 ].tbl_col_q;
                for(i=1,l=tbls.length; i<l; i++) tables += ',' + tbls[ i ].tbl_col_alias_q;
                for(i=1,l=cols.length; i<l; i++) columns += ',' + cols[ i ].tbl_col_q;
            }
            else
            {
                tables = array( tbls ).join(',');
                columns = array( cols ).join(',');
            }
            if ( self.clus.insert )
            {
                if ( self.clus.insert.insert_tables.length > 0 )
                    tables = self.clus.insert.insert_tables + ',' + tables;
                if ( self.clus.insert.insert_columns.length > 0 )
                    columns = self.clus.insert.insert_columns + ',' + columns;
            }
            self.clus.insert = { insert_tables:tables, insert_columns:columns };
        }
        return self;
    }
    
    ,values: function( values ) {
        var self = this, count, insert_values, vals, i, val, j, l, vs;
        if ( empty(values) ) return self;
        // array of arrays
        if ( undef === values[0] || !is_array(values[0]) ) values = [values];
        count = values.length;
        insert_values = [];
        for (i=0; i<count; i++)
        {
            vs = array( values[i] );
            if ( vs.length )
            {
                vals = [];
                for (j=0,l=vs.length; j<l; j++)
                {
                    val = vs[j];
                    if ( is_obj(val) )
                    {
                        if ( val[HAS]('integer') )
                        {
                            vals.push( self.intval( val['integer'] ) );
                        }
                        else if ( val[HAS]('raw') )
                        {
                            vals.push( val['raw'] );
                        }
                        else if ( val[HAS]('string') )
                        {
                            vals.push( self.quote( val['string'] ) );
                        }
                    }
                    else
                    {
                        vals.push( is_int(val) ? val : self.quote( val ) );
                    }
                }
                insert_values.push( '(' + vals.join(',') + ')' );
            }
        }
        insert_values = insert_values.join(',');
        if ( self.clus.values && self.clus.values.values_values > 0 )
            insert_values = self.clus.values.values_values + ',' + insert_values;
        self.clus.values = { values_values:insert_values };
        return self;
    }
    
    ,update: function( tbls, format ) {
        var self = this, i, l, view, tables;
        self.reset('update');
        view = is_array( tbls ) ? tbls[0] : tbls;
        if ( self.vews[HAS]( view ) && self.clau === self.vews[ view ].clau )
        {
            // using custom 'soft' view
            view = self.vews[ view ];
            self.clus = self.defaults( self.clus, view.clus, true );
            self.tbls = self.defaults( {}, view.tbls, true );
            self.cols = self.defaults( {}, view.cols, true );
        }
        else
        {
            if ( false !== format )
            {
                tbls = self.refs( tbls, self.tbls );
                tables = tbls[ 0 ].tbl_col_alias_q;
                for(i=1,l=tbls.length; i<l; i++) tables += ',' + tbls[ i ].tbl_col_alias_q;
            }
            else
            {
                tables = array( tbls ).join(',');
            }
            if ( self.clus.update && self.clus.update.update_tables > 0 )
                tables = self.clus.update.update_tables + ',' + tables;
            self.clus.update = { update_tables:tables };
        }
        return self;
    }
    
    ,set: function( fields_values ) {
        var self = this, set_values, f, field, value, COLS;
        if ( empty(fields_values) ) return self;
        set_values = [];
        COLS = self.cols;
        for (f in fields_values)
        {
            if ( !fields_values[HAS](f) ) continue;
            field = self.refs( f, COLS )[0].tbl_col_q;
            value = fields_values[f];
            
            if ( is_obj(value) )
            {
                if ( value[HAS]('integer') )
                {
                    set_values.push( field + " = " + self.intval(value['integer']) );
                }
                else if ( value[HAS]('raw') )
                {
                    set_values.push( field + " = " + value['raw'] );
                }
                else if ( value[HAS]('string') )
                {
                    set_values.push( field + " = " + self.quote(value['string']) );
                }
                else if ( value[HAS]('increment') )
                {
                    set_values.push( field + " = " + field + " + " + self.intval(value['increment']) );
                }
                else if ( value[HAS]('decrement') )
                {
                    set_values.push( field + " = " + field + " - " + self.intval(value['increment']) );
                }
            }
            else
            {
                set_values.push( field + " = " + (is_int(value) ? value : self.quote(value)) );
            }
        }
        set_values = set_values.join(',');
        if ( self.clus.set && self.clus.set.set_values > 0 )
            set_values = self.clus.set.set_values + ',' + set_values;
        self.clus.set = { set_values:set_values };
        return self;
    }
    
    ,del: function( ) {
        var self = this;
        self.reset('delete');
        self.clus['delete'] = {};
        return self;
    }
    
    ,from: function( tbls, format ) {
        var self = this, i, l, view, tables;
        if ( empty(tbls) ) return self;
        view = is_array( tbls ) ? tbls[0] : tbls;
        if ( self.vews[HAS]( view ) && self.clau === self.vews[ view ].clau )
        {
            // using custom 'soft' view
            view = self.vews[ view ];
            self.clus = self.defaults( self.clus, view.clus, true );
            self.tbls = self.defaults( {}, view.tbls, true );
            self.cols = self.defaults( {}, view.cols, true );
        }
        else
        {
            if ( false !== format )
            {
                tbls = self.refs( tbls, self.tbls );
                tables = tbls[ 0 ].tbl_col_alias_q;
                for(i=1,l=tbls.length; i<l; i++) tables += ',' + tbls[ i ].tbl_col_alias_q;
            }
            else
            {
                tables = array( tbls ).join(',');
            }
            if ( self.clus.from && self.clus.from.from_tables.length > 0 )
                tables = self.clus.from.from_tables + ',' + tables;
            self.clus.from = { from_tables:tables };
        }
        return self;
    }
    
    ,join: function( table, on_cond, join_type ) {
        var self = this, join_clause, field, cond;
        table = self.refs( table, self.tbls )[0].tbl_col_alias_q;
        if ( empty(on_cond) )
        {
            join_clause = table;
        }
        else
        {
            if ( is_string(on_cond) )
            {
                on_cond = self.refs( on_cond.split('='), self.cols );
                on_cond = '(' + on_cond[0].tbl_col_q + '=' + on_cond[1].tbl_col_q + ')';
            }
            else
            {
                for (field in on_cond)
                {
                    if ( !on_cond[HAS](field) ) continue;
                    cond = on_cond[ field ];
                    if ( !is_obj(cond) ) on_cond[field] = {'eq':cond,'type':'identifier'};
                }
                on_cond = self.conditions( on_cond, false );
            }
            join_clause = table + " ON " + on_cond;
        }
        join_clause = (empty(join_type) ? "JOIN " : (join_type.toUpperCase() + " JOIN ")) + join_clause;
        if ( self.clus.join && self.clus.join.join_clauses.length > 0 )
            join_clause = self.clus.join.join_clauses + "\n" + join_clause;
        self.clus.join = { join_clauses:join_clause };
        return self;
    }
    
    ,where: function( conditions, boolean_connective ) {
        var self = this;
        if ( empty(conditions) ) return self;
        boolean_connective = boolean_connective ? boolean_connective.toUpperCase() : "AND";
        if ( "OR" !== boolean_connective ) boolean_connective = "AND";
        conditions = self.conditions( conditions, false );
        if ( self.clus.where && self.clus.where.where_conditions.length > 0 )
            conditions = self.clus.where.where_conditions + " "+boolean_connective+" " + conditions;
        self.clus.where = { where_conditions:conditions };
        return self;
    }
    
    ,group: function( col, dir ) {
        var self = this, group_condition;
        dir = dir ? dir.toUpperCase() : "ASC";
        if ( "DESC" !== dir ) dir = "ASC";
        group_condition = self.refs( col, self.cols )[0].alias_q + " " + dir;
        if ( self.clus.group && self.clus.group.group_conditions.length > 0 )
            group_condition = self.clus.group.group_conditions + ',' + group_condition;
        self.clus.group = { group_conditions:group_condition };
        return self;
    }
    
    ,having: function( conditions, boolean_connective ) {
        var self = this;
        if ( empty(conditions) ) return self;
        boolean_connective = boolean_connective ? boolean_connective.toUpperCase() : "AND";
        if ( "OR" !== boolean_connective ) boolean_connective = "AND";
        conditions = self.conditions( conditions, true );
        if ( self.clus.having && self.clus.having.having_conditions.length > 0 )
            conditions = self.clus.having.having_conditions + " "+boolean_connective+" " + conditions;
        self.clus.having = { having_conditions:conditions };
        return self;
    }
    
    ,order: function( col, dir ) {
        var self = this, order_condition;
        dir = dir ? dir.toUpperCase() : "ASC";
        if ( "DESC" !== dir ) dir = "ASC";
        order_condition = self.refs( col, self.cols )[0].alias_q + " " + dir;
        if ( self.clus.order && self.clus.order.order_conditions.length > 0 )
            order_condition = self.clus.order.order_conditions + ',' + order_condition;
        self.clus.order = { order_conditions:order_condition };
        return self;
    }
    
    ,limit: function( count, offset ) {
        var self = this;
        self.clus.limit = { offset:int(offset), count:int(count) };
        return self;
    }
    
    ,page: function( page, perpage ) {
        var self = this;
        page = int(page); perpage = int(perpage);
        return self.limit( perpage, page*perpage );
    }
    
    ,refs: function( refs, lookup ) {
        var self = this, i, l, j, m, r, rs, ref;
        rs = array( refs );
        refs = [ ];
        for (i=0,l=rs.length; i<l; i++)
        {
            r = rs[ i ].split(',');
            for (j=0,m=r.length; j<m; j++)
            {
                ref = Ref.parse( r[ j ], self );
                if ( !lookup[HAS](ref.tbl_col) ) 
                {
                    lookup[ ref.tbl_col ] = ref;
                    if ( ref.tbl_col !== ref.alias ) lookup[ ref.alias ] = ref;
                }
                else
                {                    
                    ref = lookup[ ref.tbl_col ];
                }
                refs.push( ref );
            }
        }
        return refs;
    }
    
    ,join_conditions: function( join, conditions ) {
        var self = this, j = 0, f, ref, field, cond, where,
            main_table, main_id, join_table, join_id, join_alias,
            join_key, join_value;
        for ( f in conditions )
        {
            if ( !conditions[HAS](f) ) continue;
            
            ref = Ref.parse( f, self );
            field = ref.col;
            if ( !join[HAS]( field ) ) continue;
            cond = conditions[ f ];
            main_table = join[field].table;
            main_id = join[field].id;
            join_table = join[field].join;
            join_id = join[field].join_id;
            
            j++; join_alias = join_table+j;
            
            where = { };
            if ( join[field][HAS]('key') && field !== join[field].key )
            {
                join_key = join[field].key;
                where[join_alias+'.'+join_key] = field;
            }
            else
            {
                join_key = field;
            }
            if ( join[field][HAS]('value') )
            {
                join_value = join[field].value;
                where[join_alias+'.'+join_value] = cond;
            }
            else
            {
                join_value = join_key;
                where[join_alias+'.'+join_value] = cond;
            }
            self.join(
                join_table+" AS "+join_alias, 
                main_table+'.'+main_id+'='+join_alias+'.'+join_id, 
                "inner"
            ).where( where );
            
            delete conditions[f];
        }
        return self;
    }
    
    ,conditions: function( conditions, can_use_alias ) {
        var self = this, condquery, conds, f, field, value, fmt, op, type, v, COLS;
        if ( empty(conditions) ) return '';
        if ( is_string(conditions) ) return conditions;
        
        condquery = '';
        conds = [];
        COLS = self.cols;
        fmt = true === can_use_alias ? 'alias_q' : 'tbl_col_q';
        
        for (f in conditions)
        {
            if ( !conditions[HAS](f) ) continue;
            
            field = self.refs( f, COLS )[0][ fmt ];
            value = conditions[ f ];
            
            if ( is_obj( value ) )
            {
                type = value[HAS]('type') ? value.type : 'string';
                
                if ( value[HAS]('multi_like') )
                {
                    conds.push( self.multi_like(field, value.multi_like) );
                }
                else if ( value[HAS]('like') )
                {
                    conds.push( field + " LIKE " + ('raw' === type ? value.like : self.like(value.like)) );
                }
                else if ( value[HAS]('not_like') )
                {
                    conds.push( field + " NOT LIKE " + ('raw' === type ? value.not_like : self.like(value.not_like)) );
                }
                else if ( value[HAS]('in') )
                {
                    v = array( value['in'] );
                    
                    if ( 'raw' === type )
                    {
                        // raw, do nothing
                    }
                    else if ( 'integer' === type || is_int(v[0]) )
                    {
                        v = self.intval( v );
                    }
                    else
                    {
                        v = self.quote( v );
                    }
                    conds.push( field + " IN (" + v.join(',') + ")" );
                }
                else if ( value[HAS]('not_in') )
                {
                    v = array( value['not_in'] );
                    
                    if ( 'raw' === type )
                    {
                        // raw, do nothing
                    }
                    else if ( 'integer' === type || is_int(v[0]) )
                    {
                        v = self.intval( v );
                    }
                    else
                    {
                        v = self.quote( v );
                    }
                    conds.push( field + " NOT IN (" + v.join(',') + ")" );
                }
                else if ( value[HAS]('between') )
                {
                    v = array( value.between );
                    
                    if ( 'raw' === type )
                    {
                        // raw, do nothing
                    }
                    else if ( 'integer' === type || (is_int(v[0]) && is_int(v[1])) )
                    {
                        v = self.intval( v );
                    }
                    else
                    {
                        v = self.quote( v );
                    }
                    conds.push( field + " BETWEEN " + v[0] + " AND " + v[1] );
                }
                else if ( value[HAS]('not_between') )
                {
                    v = array( value.not_between );
                    
                    if ( 'raw' === type )
                    {
                        // raw, do nothing
                    }
                    else if ( 'integer' === type || (is_int(v[0]) && is_int(v[1])) )
                    {
                        v = self.intval( v );
                    }
                    else
                    {
                        v = self.quote( v );
                    }
                    conds.push( field + " < " + v[0] + " OR " + field + " > " + v[1] );
                }
                else if ( value[HAS]('gt') || value[HAS]('gte') )
                {
                    op = value[HAS]('gt') ? "gt" : "gte";
                    v = value[ op ];
                    
                    if ( 'raw' === type )
                    {
                        // raw, do nothing
                    }
                    else if ( 'integer' === type || is_int(v) )
                    {
                        v = self.intval( v );
                    }
                    else if ( 'identifier' === type || 'field' === type )
                    {
                        v = self.refs( v, COLS )[0][ fmt ];
                    }
                    else
                    {
                        v = self.quote( v );
                    }
                    conds.push( field + ('gt'===op ? " > " : " >= ") + v );
                }
                else if ( value[HAS]('lt') || value[HAS]('lte') )
                {
                    op = value[HAS]('lt') ? "lt" : "lte";
                    v = value[ op ];
                    
                    if ( 'raw' === type )
                    {
                        // raw, do nothing
                    }
                    else if ( 'integer' === type || is_int(v) )
                    {
                        v = self.intval( v );
                    }
                    else if ( 'identifier' === type || 'field' === type )
                    {
                        v = self.refs( v, COLS )[0][ fmt ];
                    }
                    else
                    {
                        v = self.quote( v );
                    }
                    conds.push( field + ('lt'===op ? " < " : " <= ") + v );
                }
                else if ( value[HAS]('not_equal') || value[HAS]('not_eq') )
                {
                    op = value[HAS]('not_eq') ? "not_eq" : "not_equal";
                    v = value[ op ];
                    
                    if ( 'raw' === type )
                    {
                        // raw, do nothing
                    }
                    else if ( 'integer' === type || is_int(v) )
                    {
                        v = self.intval( v );
                    }
                    else if ( 'identifier' === type || 'field' === type )
                    {
                        v = self.refs( v, COLS )[0][ fmt ];
                    }
                    else
                    {
                        v = self.quote( v );
                    }
                    conds.push( field + " <> " + v );
                }
                else if ( value[HAS]('equal') || value[HAS]('eq') )
                {
                    op = value[HAS]('eq') ? "eq" : "equal";
                    v = value[ op ];
                    
                    if ( 'raw' === type )
                    {
                        // raw, do nothing
                    }
                    else if ( 'integer' === type || is_int(v) )
                    {
                        v = self.intval( v );
                    }
                    else if ( 'identifier' === type || 'field' === type )
                    {
                        v = self.refs( v, COLS )[0][ fmt ];
                    }
                    else
                    {
                        v = self.quote( v );
                    }
                    conds.push( field + " = " + v );
                }
            }
            else
            {
                conds.push( field + " = " + (is_int(value) ? value : self.quote(value)) );
            }
        }
        
        if ( conds.length ) condquery = '(' + conds.join(') AND (') + ')';
        return condquery;
    }
    
    ,defaults: function( data, defaults, overwrite ) {
        var k, v;
        overwrite = true === overwrite;
        for (k in defaults)
        {
            if ( !defaults[HAS](k) ) continue;
            v = defaults[ k ];
            if ( overwrite || !data[HAS](k) )
                data[ k ] = v;
        }
        return data;
    }
    
    ,filter: function( data, filter, positive ) {
        var filtered, i, l, field;
        positive = false !== positive;
        if ( positive )
        {
            filtered = { };
            for (i=0,l=filter.length; i<l; i++)
            {
                field = filter[i];
                if ( data[HAS](field) ) 
                    filtered[field] = data[field];
            }
            return filtered;
        }
        else
        {
            filtered = { };
            for (field in data)
            {
                if ( !data[HAS](field) ) continue;
                if ( 0 > filter.indexOf( field ) ) 
                    filtered[field] = data[field];
            }
            return filtered;
        }
    }
    
    ,tbl: function( table ) {
        var self = this, prefix = self.p;
        return is_array( table )
        ? fmap(table, function( table ){ return prefix+table; })
        : prefix+table
        ;
    }
    
    ,intval: function( v ) {
        var self = this;
        return is_array( v ) ? fmap(v, int) : int( v );
    }
    
    ,quote_name: function( f ) {
        var self = this, qn = self.qn;
        return is_array( f )
        ? fmap(f, function( f ){ return '*' === f ? f : qn[0] + f + qn[1]; })
        : ('*' === f ? f : qn[0] + f + qn[1])
        ;
    }
    
    ,quote: function( v ) {
        var self = this, q = self.q;
        return is_array( v )
        ? fmap(v, function( v ){ return q[0] + self.esc( v ) + q[1]; })
        : q[0] + self.esc( v ) + q[1]
        ;
    }
    
    ,like: function( v ) {
        var self = this, q = self.q, e = self.escdb ? ['',''] : self.e;
        return is_array( v )
        ? fmap(v, function( v ){ return e[0] + q[0] + '%' + self.esc_like( self.esc( v ) ) + '%' + q[1] + e[1]; })
        : e[0] + q[0] + '%' + self.esc_like( self.esc( v ) ) + '%' + q[1] + e[1]
        ;
    }
    
    ,multi_like: function( f, v, trimmed ) {
        var self = this, like, ORs, ANDs, i, l, j, m;
        trimmed = false !== trimmed;
        like = f + " LIKE ";
        ORs = v.split(',');
        if ( trimmed ) ORs = ffilter( fmap( ORs, trim ), Boolean );
        for (i=0,l=ORs.length; i<l; i++)
        {
            ANDs = ORs[i].split('+');
            if ( trimmed ) ANDs = ffilter( fmap( ANDs, trim ), Boolean );
            for (j=0,m=ANDs.length; j<m; j++) ANDs[j] = like + self.like( ANDs[j] );
            ORs[i] = '(' + ANDs.join(' AND ') + ')';
        }
        return ORs.join(' OR ');
    }
    
    ,esc: function( v ) {
        var self = this, chars, esc, i, l, ve, c, q = self.q;
        if ( self.escdb ) 
        {
            return is_array( v ) ? fmap(v, self.escdb) : self.escdb( v );
        }
        else if ( is_array( v ) )
        {
            return fmap(v, function( v ){ return self.esc( v ); });
        }
        else
        {
            // simple ecsaping using addslashes
            // '"\ and NUL (the NULL byte).
            chars = '"\'\\' + NULL_CHAR; esc = '\\';
            v = String(v); ve = '';
            for(i=0,l=v.length; i<l; i++)
            {
                c = v.charAt(i);
                if ( q[0] === c ) ve += q[2];
                else if ( q[1] === c ) ve += q[3];
                else ve += addslashes( c, chars, esc );
            }
            return ve;
        }
    }
    
    ,esc_like: function( v ) {
        var self = this, chars = '_%', esc = '\\';
        return is_array( v )
        ? fmap(v, function( v ){ return addslashes( v, chars, esc ); })
        : addslashes( v, chars, esc )
        ;
    }
};

// export it
return Dialect;
});
