/**
*   Dialect, 
*   a simple and flexible Cross-Platform SQL Builder for PHP, Python, Node/JS, ActionScript
* 
*   @version: 0.2
*   https://github.com/foo123/Dialect
*
*   Abstract the construction of SQL queries
*   Support multiple DB vendors
*   Intuitive and Flexible API
**/
!function( root, name, factory ) {
"use strict";

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

var PROTO = 'prototype', HAS = 'hasOwnProperty', 
    Keys = Object.keys, toString = Object[PROTO].toString,
    CHAR = 'charAt', CHARCODE = 'charCodeAt',
    F = function( a, c ){ return new Function(a, c); },
    RE = function( r, f ){ return new RegExp(r, f||''); },
    is_callable = function( o ){ return "function" === typeof o; },
    is_string = function( o ){ return "string" === typeof o; },
    is_array = function( o ){ return o instanceof Array || '[object Array]' === toString.call(o); },
    is_obj = function( o ){ return o instanceof Object || '[object Object]' === toString.call(o); },
    is_string_or_array = function( o ){ 
        var to_string = toString.call(o);
        return (o instanceof Array || o instanceof String || '[object Array]' === to_string || '[object String]' === to_string); 
    },
    empty = function( o ){ 
        if ( !o ) return true;
        var to_string = toString.call(o);
        if ( (o instanceof Array || o instanceof String || '[object Array]' === to_string || '[object String]' === to_string) && !o.length ) return true;
        if ( (o instanceof Object || '[object Array]' === to_string) && !Keys(o).length ) return true;
        return false;
    },
    is_int = function( mixed_var ) {
        return mixed_var === +mixed_var && isFinite(mixed_var) && !(mixed_var % 1);
    },
    /*clone = function( o ){ 
        var cloned = { }, k, v;
        for (k in o)
        {
            if ( !o[HAS](k) ) continue;
            v = o[k];
            if ( is_string_or_array( v ) ) cloned[k] = v.slice();
            else cloned[k] = v;
        }
        return cloned;
    },*/
    array = function( o ){ return is_array( o ) ? o : [o]; },
    space_re = /^\s+|\s+$/g,
    trim = String[PROTO].trim
        ? function( s ){ return s.trim(); }
        : function( s ){ return s.replace(space_re, ''); },
    escaped_re = /[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g,
    esc_re = function( s ) { return s.replace(escaped_re, "\\$&"); },
    NULL_CHAR = String.fromCharCode( 0 ),
    addslashes = function( s, chars, esc ) {
        var s2 = '', i, l, c;
        if ( 3 > arguments.length ) esc = '\\';
        if ( 2 > arguments.length ) chars = '\\"\'' + NULL_CHAR;
        for (i=0,l=s.length; i<l; i++)
        {
            c = s[CHAR]( i );
            s2 += -1 === chars.indexOf( c ) ? c : (0 === c[CHARCODE](0) ? '\\0' : (esc+c));
        }
        return s2;
    },
    Tpl, Ref, Dialect
;


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
     'quote'        : [ "'", '`', '' ]
    ,'clauses'      : {
     // https://dev.mysql.com/doc/refman/5.0/en/select.html, https://dev.mysql.com/doc/refman/5.0/en/join.html, https://dev.mysql.com/doc/refman/5.5/en/expressions.html
     'select'  : ['select','from','join','where','group','having','order','limit']
     // https://dev.mysql.com/doc/refman/5.0/en/insert.html
    ,'insert'  : ['insert','values']
     // https://dev.mysql.com/doc/refman/5.0/en/update.html
    ,'update'  : ['update','set','where','order','limit']
     // https://dev.mysql.com/doc/refman/5.0/en/delete.html
    ,'delete'  : ['delete','from','where','order','limit']
    }
    ,'tpl'        : {
     'select'   : 'SELECT $(columns)'
    ,'insert'   : 'INSERT INTO $(tables) ($(columns))'
    ,'update'   : 'UPDATE $(tables)'
    ,'delete'   : 'DELETE '
    ,'values'   : 'VALUES $(values_values)'
    ,'values_'  : '$(values),$(values_values)'
    ,'set'      : 'SET $(set_values)'
    ,'set_'     : '$(set),$(set_values)'
    ,'from'     : 'FROM $(tables)'
    ,'from_'    : '$(from),$(tables)'
    ,'join'     : '$(join_type)JOIN $(join_clause)'
    ,'join_'    : '$(join)' + "\n" + '$(join_type)JOIN $(join_clause)'
    ,'where'    : 'WHERE $(conditions)'
    ,'where_'   : '$(where) $(boolean_connective) $(conditions)'
    ,'group'    : 'GROUP BY $(column) $(dir)'
    ,'group_'   : '$(group),$(column) $(dir)'
    ,'having'   : 'HAVING $(conditions)'
    ,'having_'  : '$(having) $(boolean_connective) $(conditions)'
    ,'order'    : 'ORDER BY $(column) $(dir)'
    ,'order_'   : '$(order),$(column) $(dir)'
    ,'limit'    : 'LIMIT $(offset),$(count)'

    ,'year'     : 'YEAR($(column))'
    ,'month'    : 'MONTH($(column))'
    ,'day'      : 'DAY($(column))'
    ,'hour'     : 'HOUR($(column))'
    ,'minute'   : 'MINUTE($(column))'
    ,'second'   : 'SECOND($(column))'
    }
}
,'postgre'          : {
     'quote'        : [ '`', '"', 'E' ]
    ,'clauses'      : {
     // http://www.postgresql.org/docs/
     'select'  : ['select','from','join','where','group','having','order','limit']
    ,'insert'  : ['insert','values']
    ,'update'  : ['update','set','where','order','limit']
    ,'delete'  : ['delete','from','where','order','limit']
    }
    ,'tpl'        : {
     'select'   : 'SELECT $(columns)'
    ,'insert'   : 'INSERT INTO $(tables) ($(columns))'
    ,'update'   : 'UPDATE $(tables)'
    ,'delete'   : 'DELETE '
    ,'values'   : 'VALUES $(values_values)'
    ,'values_'  : '$(values),$(values_values)'
    ,'set'      : 'SET $(set_values)'
    ,'set_'     : '$(set),$(set_values)'
    ,'from'     : 'FROM $(tables)'
    ,'from_'    : '$(from),$(tables)'
    ,'join'     : '$(join_type)JOIN $(join_clause)'
    ,'join_'    : '$(join)' + "\n" + '$(join_type)JOIN $(join_clause)'
    ,'where'    : 'WHERE $(conditions)'
    ,'where_'   : '$(where) $(boolean_connective) $(conditions)'
    ,'group'    : 'GROUP BY $(column) $(dir)'
    ,'group_'   : '$(group),$(column) $(dir)'
    ,'having'   : 'HAVING $(conditions)'
    ,'having_'  : '$(having) $(boolean_connective) $(conditions)'
    ,'order'    : 'ORDER BY $(column) $(dir)'
    ,'order_'   : '$(order),$(column) $(dir)'
    ,'limit'    : 'LIMIT $(count) OFFSET $(offset)'

    ,'year'     : 'EXTRACT (YEAR FROM $(column))'
    ,'month'    : 'EXTRACT (MONTH FROM $(column))'
    ,'day'      : 'EXTRACT (DAY FROM $(column))'
    ,'hour'     : 'EXTRACT (HOUR FROM $(column))'
    ,'minute'   : 'EXTRACT (MINUTE FROM $(column))'
    ,'second'   : 'EXTRACT (SECOND FROM $(column))'
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
    
    self.db = null;
    self.escdb = null;
    self.p = '';
    
    type = type || 'mysql';
    self.clauses = Dialect.dialect[ type ][ 'clauses' ];
    self.tpl = Dialect.dialect[ type ][ 'tpl' ];
    self.q = Dialect.dialect[ type ][ 'quote' ][ 0 ];
    self.qn = Dialect.dialect[ type ][ 'quote' ][ 1 ];
    self.e = Dialect.dialect[ type ][ 'quote' ][ 2 ] || '';
};
Dialect.VERSION = "0.2";
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
    
    ,db: null
    ,escdb: null
    ,p: null
    
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
        
        self.db = null;
        self.escdb = null;
        self.p = null;
        
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
            
            // continuation clause if exists, ..
            c = clause + '_';
            if ( self.tpl[HAS](c) && !(self.tpl[ c ] instanceof Tpl) )
                self.tpl[ c ] = new Tpl( self.tpl[ c ], Dialect.TPL_RE );
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
        var self = this, query = null, i, l, clause, clauses;
        if ( self.clau && self.clus && self.clauses[HAS]( self.clau ) )
        {
            query = [ ];
            clauses = self.clauses[ self.clau ];
            for (i=0,l=clauses.length; i<l; i++)
            {
                clause = clauses[ i ];
                if ( self.clus[ HAS ]( clause ) )
                    query.push( self.clus[ clause ] );
            }
            query = query.join("\n");
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
            pattern = RE(left + '(ad|as|af|f|l|r|d|s):([0-9a-zA-Z_]+)' + right);
            prepared = '';
            while ( query.length && (m = query.match( pattern )) )
            {
                pos = m.index;
                len = m[0].length;
                param = m[2];
                if ( args[HAS](param) )
                {
                    type = m[1];
                    switch( type )
                    {
                        // array of references, e.g fields
                        case 'af': 
                            tmp = array( args[param] );
                            param = Ref.parse( tmp[0], self ).tbl_col_alias_q;
                            for (i=1,l=tmp.length; i<l; i++) param += ','+Ref.parse( tmp[i], self ).tbl_col_alias_q;
                            break;
                        // array of integers param
                        case 'ad': param = '(' + self.intval( array(args[param]) ).join(',') + ')'; break;
                        // array of strings param
                        case 'as': param = '(' + self.quote( array(args[param]) ).join(',') + ')'; break;
                        // reference, e.g field
                        case 'f': param = Ref.parse( args[param], self ).tbl_col_alias_q; break;
                        // like param
                        case 'l': param = self.like( args[param] ); break;
                        // raw param
                        case 'r': param = args[param]; break;
                        // integer param
                        case 'd': param = self.intval( args[param] ); break;
                        // string param
                        case 's': default: param = self.quote( args[param] ); break;
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
        self.clus.select = self.tpl.select.render( { columns:columns } );
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
            self.clus.insert = self.tpl.insert.render( { tables:tables, columns:columns } );
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
        if ( self.clus.values ) self.clus.values = self.tpl.values_.render( { values:self.clus.values, values_values:insert_values } );
        else self.clus.values = self.tpl.values.render( { values_values:insert_values } );
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
            self.clus.update = self.tpl.update.render( { tables:tables } );
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
        if ( self.clus.set ) self.clus.set = self.tpl.set_.render( { set:self.clus.set, set_values:set_values } );
        else self.clus.set = self.tpl.set.render( { set_values:set_values } );
        return self;
    }
    
    ,del: function( ) {
        var self = this;
        self.reset('delete');
        self.clus['delete'] = self.tpl['delete'].render( {} );
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
            if ( self.clus.from ) self.clus.from = self.tpl.from_.render( { from:self.clus.from, tables:tables } );
            else self.clus.from = self.tpl.from.render( { tables:tables } );
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
        join_type = empty(join_type) ? "" : (join_type.toUpperCase() + " ");
        if ( self.clus.join ) self.clus.join = self.tpl.join_.render( { join:self.clus.join, join_clause:join_clause, join_type:join_type } );
        else self.clus.join = self.tpl.join.render( { join_clause:join_clause, join_type:join_type } );
        return self;
    }
    
    ,where: function( conditions, boolean_connective ) {
        var self = this;
        if ( empty(conditions) ) return self;
        boolean_connective = boolean_connective ? boolean_connective.toUpperCase() : "AND";
        if ( "OR" !== boolean_connective ) boolean_connective = "AND";
        conditions = self.conditions( conditions, false );
        if ( self.clus.where ) self.clus.where = self.tpl.where_.render( { where:self.clus.where, boolean_connective:boolean_connective, conditions:conditions } );
        else self.clus.where = self.tpl.where.render( { boolean_connective:boolean_connective, conditions:conditions } );
        return self;
    }
    
    ,group: function( col, dir ) {
        var self = this, column;
        dir = dir ? dir.toUpperCase() : "ASC";
        if ( "DESC" !== dir ) dir = "ASC";
        column = self.refs( col, self.cols )[0].alias_q;
        if ( self.clus.group ) self.clus.group = self.tpl.group_.render( { group:self.clus.group, column:column, dir:dir } );
        else self.clus.group = self.tpl.group.render( { column:column, dir:dir } );
        return self;
    }
    
    ,having: function( conditions, boolean_connective ) {
        var self = this;
        if ( empty(conditions) ) return self;
        boolean_connective = boolean_connective ? boolean_connective.toUpperCase() : "AND";
        if ( "OR" !== boolean_connective ) boolean_connective = "AND";
        conditions = self.conditions( conditions, true );
        if ( self.clus.having ) self.clus.having = self.tpl.having_.render( { having:self.clus.having, boolean_connective:boolean_connective, conditions:conditions } );
        else self.clus.having = self.tpl.having.render( { boolean_connective:boolean_connective, conditions:conditions } );
        return self;
    }
    
    ,order: function( col, dir ) {
        var self = this, column;
        dir = dir ? dir.toUpperCase() : "ASC";
        if ( "DESC" !== dir ) dir = "ASC";
        column = self.refs( col, self.cols )[0].alias_q;
        if ( self.clus.order ) self.clus.order = self.tpl.order_.render( { order:self.clus.order, column:column, dir:dir } );
        else self.clus.order = self.tpl.order.render( { column:column, dir:dir } );
        return self;
    }
    
    ,limit: function( count, offset ) {
        var self = this;
        count = parseInt(count,10); offset = parseInt(offset||0,10);
        self.clus.limit = self.tpl.limit.render( { offset:offset, count:count } );
        return self;
    }
    
    ,page: function( page, perpage ) {
        var self = this;
        page = parseInt(page,10); perpage = parseInt(perpage,10);
        return self.limit( perpage, page*perpage );
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
        if ( is_array( table ) )
            return table.map(function( table ){return prefix+table;});
        return prefix+table;
    }
    
    ,intval: function( v ) {
        var self = this;
        if ( is_array( v ) )
            return v.map(function( v ){return parseInt( v, 10 );});
        return parseInt( v, 10 );
    }
    
    ,quote_name: function( f ) {
        var self = this, qn = self.qn;
        if ( is_array( f ) )
            return f.map(function( f ){return '*' === f ? f : qn + f + qn;});
        return '*' === f ? f : qn + f + qn;
    }
    
    ,quote: function( v ) {
        var self = this, q = self.q, e = self.escdb ? '' : self.e;
        if ( is_array( v ) )
            return v.map(function( v ){return e + q + self.esc( v ) + q;});
        return e + q + self.esc( v ) + q;
    }
    
    ,esc: function( v ) {
        // simple ecsaping using addslashes
        // '"\ and NUL (the NULL byte).
        var self = this, chars, esc;
        if ( is_array( v ) )
        {
            if ( self.escdb )
            {
                return v.map( self.escdb );
            }
            else
            {
                chars = self.q + '"\'\\' + NULL_CHAR; 
                esc = '\\';
                return v.map(function( v ){return addslashes( v, chars, esc );});
            }
        }
        if ( self.escdb ) 
        {
            return self.escdb( v );
        }
        else
        {
            chars = self.q + '"\'\\' + NULL_CHAR; 
            esc = '\\';
            return addslashes( v, chars, esc );
        }
    }
    
    ,esc_like: function( v ) {
        var self = this, chars = '_%', esc = '\\';
        if ( is_array( v ) )
            return v.map(function( v ){return addslashes( v, chars, esc );});
        return addslashes( v, chars, esc );
    }
    
    ,like: function( v ) {
        var self = this, q = self.q, e = self.escdb ? '' : self.e;
        if ( is_array( v ) )
            return v.map(function( v ){return e + q + '%' + self.esc_like( self.esc( v ) ) + '%' + q;});
        return e + q + '%' + self.esc_like( self.esc( v ) ) + '%' + q;
    }
    
    ,multi_like: function( f, v, doTrim ) {
        var self = this, like, ORs, ANDs, i, l, j, m;
        doTrim = false !== doTrim;
        like = f + " LIKE ";
        ORs = v.split(',');
        if ( doTrim ) ORs = ORs.map( trim ).filter( Boolean );
        for (i=0,l=ORs.length; i<l; i++)
        {
            ANDs = ORs[i].split('+');
            if ( doTrim ) ANDs = ANDs.map( trim ).filter( Boolean );
            for (j=0,m=ANDs.length; j<m; j++)
            {
                ANDs[j] = like + self.like( ANDs[j] );
            }
            ORs[i] = '(' + ANDs.join(' AND ') + ')';
        }
        return ORs.join(' OR ');
    }
    
    ,year: function( column ) {
        var self = this;
        if ( !(self.tpl.year instanceof Tpl) ) self.tpl.year = new Tpl( self.tpl.year, Dialect.TPL_RE );
        return self.tpl.year.render( { column:column } );
    }
    
    ,month: function( column ) {
        var self = this;
        if ( !(self.tpl.month instanceof Tpl) ) self.tpl.month = new Tpl( self.tpl.month, Dialect.TPL_RE );
        return self.tpl.month.render( { column:column } );
    }
    
    ,day: function( column ) {
        var self = this;
        if ( !(self.tpl.day instanceof Tpl) ) self.tpl.day = new Tpl( self.tpl.day, Dialect.TPL_RE );
        return self.tpl.day.render( { column:column } );
    }
    
    ,hour: function( column ) {
        var self = this;
        if ( !(self.tpl.hour instanceof Tpl) ) self.tpl.hour = new Tpl( self.tpl.hour, Dialect.TPL_RE );
        return self.tpl.hour.render( { column:column } );
    }
    
    ,minute: function( column ) {
        var self = this;
        if ( !(self.tpl.minute instanceof Tpl) ) self.tpl.minute = new Tpl( self.tpl.minute, Dialect.TPL_RE );
        return self.tpl.minute.render( { column:column } );
    }
    
    ,second: function( column ) {
        var self = this;
        if ( !(self.tpl.second instanceof Tpl) ) self.tpl.second = new Tpl( self.tpl.second, Dialect.TPL_RE );
        return self.tpl.second.render( { column:column } );
    }
};

// export it
return Dialect;
});
