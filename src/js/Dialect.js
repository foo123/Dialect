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

var PROTO = 'prototype', HAS = 'hasOwnProperty', toString = Object[PROTO].toString,
    F = function( a, c ){ return new Function(a, c); },
    RE = function( r, f ){ return new RegExp(r, f||''); },
    is_callable = function( o ){ return "function" === typeof o; },
    is_string = function( o ){ return "string" === typeof o; },
    is_array = function( o ){ return o instanceof Array || '[object Array]' === toString.call(o); },
    is_obj = function( o ){ return o instanceof Object || '[object Object]' === toString.call(o); },
    empty = function( o ){ 
        if ( !o ) return true;
        var to_string = toString.call(o);
        return (o instanceof Array || o instanceof String || '[object Array]' === to_string || '[object String]' === to_string) && !o.length;
    },
    array = function( o ){ return is_array( o ) ? o : [o]; },
    SPACE_RE = /^\s+|\s+$/g,
    trim = String[PROTO].trim
        ? function( s ){ return s.trim(); }
        : function( s ){ return s.replace(SPACE_RE, ''); },
    ESCAPED_RE = /[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g,
    esc_re = function( s ) { return s.replace(ESCAPED_RE, "\\$&"); },
    Tpl, Dialect
;

// adapted from phpjs
function addcslashes( str, charlist )
{
    var target = '', chrs = [], i = 0, j = 0, c = '',  next = '',  rangeBegin = '',
    rangeEnd = '',  chr = '',  begin = 0,  end = 0,  octalLength = 0,  postOctalPos = 0,
    cca = 0, escHexGrp = [],  encoded = '', percentHex = /%([\dA-Fa-f]+)/g;
    
    var _pad = function (n, c) {
        if ((n = n + '').length < c) {
            return new Array(++c - n.length).join('0') + n;
        }
        return n;
    };

    for (i = 0; i < charlist.length; i++) 
    {
        c = charlist.charAt(i);
        next = charlist.charAt(i + 1);
        if (c === '\\' && next && (/\d/).test(next)) 
        { 
            // Octal
            rangeBegin = charlist.slice(i + 1).match(/^\d+/)[0];
            octalLength = rangeBegin.length;
            postOctalPos = i + octalLength + 1;
            if (charlist.charAt(postOctalPos) + charlist.charAt(postOctalPos + 1) === '..') 
            { 
                // Octal begins range
                begin = rangeBegin.charCodeAt(0);
                if ((/\\\d/).test(charlist.charAt(postOctalPos + 2) + charlist.charAt(postOctalPos + 3))) 
                { 
                    // Range ends with octal
                    rangeEnd = charlist.slice(postOctalPos + 3).match(/^\d+/)[0];
                    i += 1; // Skip range end backslash
                } 
                else if (charlist.charAt(postOctalPos + 2)) 
                { 
                    // Range ends with character
                    rangeEnd = charlist.charAt(postOctalPos + 2);
                } 
                else 
                {
                    throw 'Range with no end point';
                }
                end = rangeEnd.charCodeAt(0);
                if (end > begin) 
                { 
                    // Treat as a range
                    for (j = begin; j <= end; j++) 
                        chrs.push(String.fromCharCode(j));
                } 
                else 
                { 
                    // Supposed to treat period, begin and end as individual characters only, not a range
                    chrs.push('.', rangeBegin, rangeEnd);
                }
                i += rangeEnd.length + 2; // Skip dots and range end (already skipped range end backslash if present)
            } 
            else 
            { 
                // Octal is by itself
                chr = String.fromCharCode(parseInt(rangeBegin, 8));
                chrs.push(chr);
            }
            i += octalLength; // Skip range begin
        } 
        else if (next + charlist.charAt(i + 2) === '..') 
        { 
            // Character begins range
            rangeBegin = c;
            begin = rangeBegin.charCodeAt(0);
            if ((/\\\d/).test(charlist.charAt(i + 3) + charlist.charAt(i + 4))) 
            { 
                // Range ends with octal
                rangeEnd = charlist.slice(i + 4).match(/^\d+/)[0];
                i += 1; // Skip range end backslash
            } 
            else if (charlist.charAt(i + 3)) 
            { 
                // Range ends with character
                rangeEnd = charlist.charAt(i + 3);
            } 
            else 
            {
                throw 'Range with no end point';
            }
            end = rangeEnd.charCodeAt(0);
            if (end > begin) 
            { 
                // Treat as a range
                for (j = begin; j <= end; j++)
                    chrs.push(String.fromCharCode(j));
            } 
            else 
            { 
                // Supposed to treat period, begin and end as individual characters only, not a range
                chrs.push('.', rangeBegin, rangeEnd);
            }
            i += rangeEnd.length + 2; // Skip dots and range end (already skipped range end backslash if present)
        } 
        else 
        { 
            // Character is by itself
            chrs.push(c);
        }
    }

    for (i = 0; i < str.length; i++) 
    {
        c = str.charAt(i);
        if (chrs.indexOf(c) !== -1) 
        {
            target += '\\';
            cca = c.charCodeAt(0);
            if (cca < 32 || cca > 126) 
            { 
                // Needs special escaping
                switch (c) 
                {
                    case '\n': target += 'n'; break;
                    case '\t': target += 't';  break;
                    case '\u000D': target += 'r';  break;
                    case '\u0007': target += 'a'; break;
                    case '\v': target += 'v'; break;
                    case '\b': target += 'b';  break;
                    case '\f': target += 'f'; break;
                    default:
                        //target += _pad(cca.toString(8), 3);break; // Sufficient for UTF-16
                        encoded = encodeURIComponent(c);
                        // 3-length-padded UTF-8 octets
                        if ((escHexGrp = percentHex.exec(encoded)) !== null) 
                            target += _pad(parseInt(escHexGrp[1], 16).toString(8), 3); // already added a slash above
                        while ((escHexGrp = percentHex.exec(encoded)) !== null) 
                            target += '\\' + _pad(parseInt(escHexGrp[1], 16).toString(8), 3);
                        break;
                }
            } 
            else 
            { 
                // Perform regular backslashed escaping
                target += c;
            }
        } 
        else 
        { 
            // Just add the character unescaped
            target += c;
        }
    }
    return target;
}

function addslashes( str ) 
{
    return (str + '').replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0');
}

function is_int( mixed_var ) 
{
    return mixed_var === +mixed_var && isFinite(mixed_var) && !(mixed_var % 1);
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

var dialect = {
 'mysql'            : {
    
     'quote'        : [ "'", '`' ]
    ,'clauses'      : {
    // https://dev.mysql.com/doc/refman/5.0/en/select.html, https://dev.mysql.com/doc/refman/5.0/en/join.html
     'select'  : ['select','from','join','where','group','having','order','limit']
    // https://dev.mysql.com/doc/refman/5.0/en/insert.html
    ,'insert'  : ['insert','values']
    // https://dev.mysql.com/doc/refman/5.0/en/update.html
    ,'update'  : ['update','set','where','order','limit']
    // https://dev.mysql.com/doc/refman/5.0/en/delete.html
    ,'delete'  : ['delete','from','where','order','limit']
    }
    ,'tpl'        : {
     'select'   : 'SELECT $(fields)'
    ,'insert'   : 'INSERT INTO $(tables) ($(fields))'
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
    ,'where_'   : '$(where) AND $(conditions)'
    ,'group'    : 'GROUP BY $(field) $(dir)'
    ,'group_'   : '$(group),$(field) $(dir)'
    ,'having'   : 'HAVING $(conditions)'
    ,'having_'  : '$(having) AND $(conditions)'
    ,'order'    : 'ORDER BY $(field) $(dir)'
    ,'order_'   : '$(order),$(field) $(dir)'
    ,'limit'    : 'LIMIT $(offset),$(count)'
    
    ,'year'     : 'YEAR($(field))'
    ,'month'    : 'MONTH($(field))'
    ,'day'      : 'DAY($(field))'
    ,'hour'     : 'HOUR($(field))'
    ,'minute'   : 'MINUTE($(field))'
    ,'second'   : 'SECOND($(field))'
    }
 }
/*
,'postgre'          : {
     'quote'        : [ "'", '`' ]
    ,'clauses'      : {
    // http://www.postgresql.org/docs/
     'select'  : ['select','from','join','where','group','having','order','limit']
    ,'insert'  : ['insert','values']
    ,'update'  : ['update','set','where','order','limit']
    ,'delete'  : ['delete','from','where','order','limit']
    }
    ,'tpl'        : {
     'select'   : 'SELECT $(fields)'
    ,'insert'   : 'INSERT INTO $(tables) ($(fields))'
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
    ,'where_'   : '$(where) AND $(conditions)'
    ,'group'    : 'GROUP BY $(field) $(dir)'
    ,'group_'   : '$(group),$(field) $(dir)'
    ,'having'   : 'HAVING $(conditions)'
    ,'having_'  : '$(having) AND $(conditions)'
    ,'order'    : 'ORDER BY $(field) $(dir)'
    ,'order_'   : '$(order),$(field) $(dir)'
    ,'limit'    : 'LIMIT $(count) OFFSET $(offset)'
    
    ,'year'     : 'EXTRACT (YEAR FROM $(field))'
    ,'month'    : 'EXTRACT (MONTH FROM $(field))'
    ,'day'      : 'EXTRACT (DAY FROM $(field))'
    ,'hour'     : 'EXTRACT (HOUR FROM $(field))'
    ,'minute'   : 'EXTRACT (MINUTE FROM $(field))'
    ,'second'   : 'EXTRACT (SECOND FROM $(field))'
    }
}
*/
};

Dialect = function Dialect( type ) {
    var self = this;
    if ( !(self instanceof Dialect) ) return new Dialect( type );
    
    self.db = null;
    self.prefix = '';
    self.escdb = null;
    self.clause = null;
    self.state = null;
    type = type || 'mysql';
    self.clauses = Dialect.dialect[ type ][ 'clauses' ];
    self.tpl = Dialect.dialect[ type ][ 'tpl' ];
    self.q = Dialect.dialect[ type ][ 'quote' ][ 0 ];
    self.qn = Dialect.dialect[ type ][ 'quote' ][ 1 ];
    self._views = { };
};
Dialect.VERSION = "0.1";
Dialect.TPL_RE = /\$\(([^\)]+)\)/g;
Dialect.dialect = dialect;
Dialect.Tpl = function( tpl, reps, compiled ) {
    if ( tpl instanceof Tpl ) return tpl;
    return new Tpl( tpl, reps, compiled );
};
Dialect[PROTO] = {
    constructor: Dialect
    
    ,clause: null
    ,state: null
    ,clauses: null
    ,tpl: null
    ,db: null
    ,prefix: null
    ,escdb: null
    ,q: null
    ,qn: null
    ,_views: null
    
    ,dispose: function( ) {
        var self = this;
        self.db = null;
        self.prefix = null;
        self.escdb = null;
        self.clause = null;
        self.state = null;
        self.clauses = null;
        self.tpl = null;
        self.q = null;
        self.qn = null;
        self._views = null;
        return self;
    }
    
	,toString: function( ) {
        return this.sql( ) || '';
    }
    
    ,driver: function( db ) {
        var self = this;
        self.db = db ? db : null;
        return self;
    }
    
    ,table_prefix: function( prefix ) {
        var self = this;
        self.prefix = prefix ? prefix : '';
        return self;
    }
    
    ,escape: function( escdb ) {
        var self = this;
        self.escdb = escdb && is_callable(escdb) ? escdb : null;
        return self;
    }
    
    ,reset: function( clause ) {
        var self = this, i, l, clauses, c;
        self.clause = clause;
        self.state = { };
        clauses = self.clauses[ self.clause ];
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
        self.clause = null;
        self.state = null;
        return self;
    }
    
    ,sql: function( ) {
        var self = this, query = null, i, l, clause, clauses;
        if ( self.clause && self.state && self.clauses[HAS]( self.clause ) )
        {
            query = [ ];
            clauses = self.clauses[ self.clause ];
            for (i=0,l=clauses.length; i<l; i++)
            {
                clause = clauses[ i ];
                if ( self.state[ HAS ]( clause ) )
                    query.push( self.state[ clause ] );
            }
            query = query.join("\n");
        }
        self.clear( );
        return query;
    }
    
    ,prepare: function( query, args, left, right ) {
        var self = this, pattern, offset, m, pos, len, param, type, prepared;
        if ( query && args )
        {
            // custom delimiters
            left = left ? esc_re( left ) : '%';
            right = right ? esc_re( right ) : '%';
            
            // custom prepared parameter format
            pattern = RE(left + '(ad|as|l|r|d|s):([0-9a-zA-Z_]+)' + right);
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
                        // array of integers param
                        case 'ad': param = '(' + self.intval( array(args[param]) ).join(',') + ')'; break;
                        // array of strings param
                        case 'as': param = '(' + self.quote( array(args[param]) ).join(',') + ')'; break;
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
        if ( view && self.clause )
        {
            self._views[ view ] = {clause:self.clause, state:self.state};
            self.clear( );
        }
        return self;
    }
    
    ,clear_view: function( view ) {
        var self = this;
        if ( view && self._views[HAS](view) )
        {
            delete self._views[ view ];
        }
        return self;
    }
    
    ,select: function( fields ) {
        var self = this;
        self.reset('select');
        if ( !fields || !fields.length ) fields = '*';
        self.state.select = self.tpl.select.render( { fields:array(fields).join(',') } );
        return self;
    }
    
    ,insert: function( tables, fields ) {
        var self = this;
        self.reset('insert');
        tables = array(tables).join(',');
        if ( self._views[HAS]( tables ) && self.clause === self._views[ tables ].clause )
        {
            // using custom 'soft' view
            self.state = self.defaults( self.state, self._views[ tables ].state, true );
        }
        else
        {
            self.state.insert = self.tpl.insert.render( { tables:tables, fields:array(fields).join(',') } );
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
                        else if ( val[HAS]('string') )
                        {
                            vals.push( self.quote( val['string'] ) );
                        }
                        else if ( val[HAS]('prepared') )
                        {
                            vals.push( val['prepared'] );
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
        if ( self.state.values ) self.state.values = self.tpl.values_.render( { values:self.state.values, values_values:insert_values } );
        else self.state.values = self.tpl.values.render( { values_values:insert_values } );
        return self;
    }
    
    ,update: function( tables ) {
        var self = this;
        self.reset('update');
        tables = array(tables).join(',');
        if ( self._views[HAS]( tables ) && self.clause === self._views[ tables ].clause )
        {
            // using custom 'soft' view
            self.state = self.defaults( self.state, self._views[ tables ].state, true );
        }
        else
        {
            self.state.update = self.tpl.update.render( { tables:tables } );
        }
        return self;
    }
    
    ,set: function( fields_values ) {
        var self = this, set_values, field, value;
        if ( empty(fields_values) ) return self;
        set_values = [];
        for (field in fields_values)
        {
            if ( !fields_values[HAS](field) ) continue;
            value = fields_values[field];
            if ( is_obj(value) )
            {
                if ( value[HAS]('integer') )
                {
                    set_values.push( field + " = " + self.intval(value['integer']) );
                }
                else if ( value[HAS]('string') )
                {
                    set_values.push( field + " = " + self.quote(value['string']) );
                }
                else if ( value[HAS]('prepared') )
                {
                    set_values.push( field + " = " + value['prepared'] );
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
        if ( self.state.set ) self.state.set = self.tpl.set_.render( { set:self.state.set, set_values:set_values } );
        else self.state.set = self.tpl.set.render( { set_values:set_values } );
        return self;
    }
    
    ,del: function( ) {
        var self = this;
        self.reset('delete');
        self.state['delete'] = self.tpl['delete'].render( {} );
        return self;
    }
    
    ,from: function( tables ) {
        var self = this;
        if ( empty(tables) ) return self;
        tables = array(tables).join(',');
        if ( self._views[HAS]( tables ) && self.clause === self._views[ tables ].clause )
        {
            // using custom 'soft' view
            self.state = self.defaults( self.state, self._views[ tables ].state, true );
        }
        else
        {
            if ( self.state.from ) self.state.from = self.tpl.from_.render( { from:self.state.from, tables:tables } );
            else self.state.from = self.tpl.from.render( { tables:tables } );
        }
        return self;
    }
    
    ,join: function( table, on_cond, join_type ) {
        var self = this;
        var join_clause = on_cond ? (table + " ON " + on_cond) : table;
        join_type = empty(join_type) ? "" : (join_type.toUpperCase() + " ");
        if ( self.state.join ) self.state.join = self.tpl.join_.render( { join:self.state.join, join_clause:join_clause, join_type:join_type } );
        else self.state.join = self.tpl.join.render( { join_clause:join_clause, join_type:join_type } );
        return self;
    }
    
    ,where: function( conditions ) {
        var self = this;
        if ( empty(conditions) ) return self;
        conditions = is_string(conditions) ? conditions : self.conditions( conditions );
        if ( self.state.where ) self.state.where = self.tpl.where_.render( { where:self.state.where, conditions:conditions } );
        else self.state.where = self.tpl.where.render( { conditions:conditions } );
        return self;
    }
    
    ,group: function( field, dir ) {
        var self = this;
        dir = dir ? dir.toUpperCase() : "ASC";
        if ( "DESC" !== dir ) dir = "ASC";
        if ( self.state.group ) self.state.group = self.tpl.group_.render( { group:self.state.group, field:field, dir:dir } );
        else self.state.group = self.tpl.group.render( { field:field, dir:dir } );
        return self;
    }
    
    ,having: function( conditions ) {
        var self = this;
        if ( empty(conditions) ) return self;
        conditions = is_string(conditions) ? conditions : self.conditions( conditions );
        if ( self.state.having ) self.state.having = self.tpl.having_.render( { having:self.state.having, conditions:conditions } );
        else self.state.having = self.tpl.having.render( { conditions:conditions } );
        return self;
    }
    
    ,order: function( field, dir ) {
        var self = this;
        dir = dir ? dir.toUpperCase() : "ASC";
        if ( "DESC" !== dir ) dir = "ASC";
        if ( self.state.order ) self.state.order = self.tpl.order_.render( { order:self.state.order, field:field, dir:dir } );
        else self.state.order = self.tpl.order.render( { field:field, dir:dir } );
        return self;
    }
    
    ,limit: function( count, offset ) {
        var self = this;
        count = parseInt(count,10); offset = parseInt(offset||0,10);
        self.state.limit = self.tpl.limit.render( { offset:offset, count:count } );
        return self;
    }
    
    ,page: function( page, perpage ) {
        var self = this;
        page = parseInt(page,10); perpage = parseInt(perpage,10);
        return self.limit( perpage, page*perpage );
    }
    
    ,join_conditions: function( join, conditions ) {
        var self = this, j = 0, field, cond, field_raw, where,
            main_table, main_id, join_table, join_id, join_alias,
            join_key, join_value;
        for ( field in conditions )
        {
            if ( !conditions[HAS](field) ) continue;
            
            field_raw = self.fld( field );
            if ( join[HAS](field_raw) )
            {
                cond = conditions[ field ];
                main_table = join[field_raw].table;
                main_id = join[field_raw].id;
                join_table = join[field_raw].join;
                join_id = join[field_raw].join_id;
                
                j++; join_alias = join_table+j;
                
                where = { };
                if ( join[field_raw][HAS]('key') && field_raw !== join[field_raw].key )
                {
                    join_key = join[field_raw].key;
                    where[join_alias+'.'+join_key] = field_raw;
                }
                else
                {
                    join_key = field_raw;
                }
                if ( join[field_raw][HAS]('value') )
                {
                    join_value = join[field_raw].value;
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
                
                delete conditions[field];
           }
        }
        return self;
    }
    
    ,conditions: function( conditions ) {
        var self = this, condquery = '', conds, field, value, op, type, v;
        if ( !empty(conditions) )
        {
            conds = [];
            
            for ( field in conditions)
            {
                if ( !conditions[HAS](field) ) continue;
                
                value = conditions[field];
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
        }
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
            for (i=0,l=filter.length; i<l; i++)
            {
                field = filter[i];
                if ( data[HAS](field) ) 
                    delete data[field];
            }
            return data;
        }
    }
    
    ,tbl: function( table ) {
        var self = this, prefix = self.prefix;
        if ( is_array( table ) )
            return table.map(function( table ){return prefix+table;});
        return prefix+table;
    }
    
    ,fld: function( field ) {
        var self = this;
        if ( is_array( field ) )
            return field.map(function( field ){return field.split('.').pop( );});
        return field.split('.').pop( );
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
            return f.map(function( f ){return qn + f + qn;});
        return qn + f + qn;
    }
    
    ,quote: function( v ) {
        var self = this, q = self.q;
        if ( is_array( v ) )
            return v.map(function( v ){return q + self.esc( v ) + q;});
        return q + self.esc( v ) + q;
    }
    
    ,esc: function( v ) {
        var self = this;
        if ( is_array( v ) )
        {
            if ( self.escdb ) 
                return v.map( self.escdb );
            else
                return v.map(function( v ){return addslashes( v );});
        }
        if ( self.escdb ) 
            return self.escdb( v );
        else
            // simple ecsaping using addslashes
            // '"\ and NUL (the NULL byte).
            return addslashes( v );
    }
    
    ,esc_like: function( v ) {
        var self = this;
        if ( is_array( v ) )
            return v.map(function( v ){return addcslashes( v, '_%\\' );});
        return addcslashes( v, '_%\\' );
    }
    
    ,like: function( v ) {
        var self = this, q = self.q;
        if ( is_array( v ) )
            return v.map(function( v ){return q + '%' + self.esc( self.esc_like( v ) ) + '%' + q;});
        return q + '%' + self.esc( self.esc_like( v ) ) + '%' + q;
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
            for (j=0,m=ASNDs.length; j<m; j++)
            {
                ANDs[j] = like + self.like( ANDs[j] );
            }
            ORs[i] = '(' + ANDs.join(' AND ') + ')';
        }
        return ORs.join(' OR ');
    }
    
    ,year: function( field ) {
        var self = this;
        if ( !(self.tpl.year instanceof Tpl) ) self.tpl.year = new Tpl( self.tpl.year, Dialect.TPL_RE );
        return self.tpl.year.render( { field:field } );
    }
    
    ,month: function( field ) {
        var self = this;
        if ( !(self.tpl.month instanceof Tpl) ) self.tpl.month = new Tpl( self.tpl.month, Dialect.TPL_RE );
        return self.tpl.month.render( { field:field } );
    }
    
    ,day: function( field ) {
        var self = this;
        if ( !(self.tpl.day instanceof Tpl) ) self.tpl.day = new Tpl( self.tpl.day, Dialect.TPL_RE );
        return self.tpl.day.render( { field:field } );
    }
    
    ,hour: function( field ) {
        var self = this;
        if ( !(self.tpl.hour instanceof Tpl) ) self.tpl.hour = new Tpl( self.tpl.hour, Dialect.TPL_RE );
        return self.tpl.hour.render( { field:field } );
    }
    
    ,minute: function( field ) {
        var self = this;
        if ( !(self.tpl.minute instanceof Tpl) ) self.tpl.minute = new Tpl( self.tpl.minute, Dialect.TPL_RE );
        return self.tpl.minute.render( { field:field } );
    }
    
    ,second: function( field ) {
        var self = this;
        if ( !(self.tpl.second instanceof Tpl) ) self.tpl.second = new Tpl( self.tpl.second, Dialect.TPL_RE );
        return self.tpl.second.render( { field:field } );
    }
};

// export it
return Dialect;
});
