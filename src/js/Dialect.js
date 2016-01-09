/**
*   Dialect, 
*   a simple and flexible Cross-Platform SQL Builder for PHP, Python, Node/XPCOM/JS, ActionScript
* 
*   @version: 0.5.1
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
    Tpl, GrammTpl, Ref, Dialect
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
function defaults( data, def, overwrite )
{
    overwrite = true === overwrite;
    for (var k in def)
    {
        if ( !def[HAS](k) ) continue;
        if ( overwrite || !data[HAS](k) )
            data[ k ] = def[ k ];
    }
    return data;
}
/*function filter( data, filt, positive )
{
    var filtered, i, l, field;
    if ( false !== positive )
    {
        filtered = { };
        for (i=0,l=filt.length; i<l; i++)
        {
            field = filt[i];
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
            if ( 0 > filt.indexOf( field ) ) 
                filtered[field] = data[field];
        }
        return filtered;
    }
}*/
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
            argslen = args.length, i, t, s, out = ''
        ;
        for(i=0; i<l; i++)
        {
            t = tpl[ i ];
            if ( 1 === t[ 0 ] )
            {
                out += t[ 1 ];
            }
            else
            {
                s = t[ 1 ];
                if ( (+s === s) && s < 0 ) s = argslen+s;
                out += args[ s ];
            }
        }
        return out;
    }
};

GrammTpl = function GrammTpl( tpl, delims ) {
    var self = this;
    if ( !(self instanceof GrammTpl) ) return new GrammTpl(tpl, delims);
    self.id = null;
    self.tpl = GrammTpl.multisplit( tpl||'', delims||GrammTpl.defaultDelims );
};
GrammTpl.defaultDelims = ['<','>','[',']','?','*','!','|','{','}'];
GrammTpl.multisplit = function multisplit( tpl, delims ) {
    var IDL = delims[0], IDR = delims[1], OBL = delims[2], OBR = delims[3],
        OPT = delims[4], OPTR = delims[5], NEG = delims[6], DEF = delims[7],
        REPL = delims[8], REPR = delims[9],
        default_value = null, negative = 0, optional = 0, start_i, end_i,
        argument, p, stack, c, a, b, s, l = tpl.length, i, j, jl;
    i = 0; a = [[], null, 0, 0, 0, 0]; stack = []; s = '';
    while( i < l )
    {
        c = tpl[CHAR](i++);
        if ( IDL === c )
        {
            if ( s.length ) a[0].push([0, s]);
            s = '';
        }
        else if ( IDR === c )
        {
            // argument
            argument = s; s = '';
            if ( -1 < (p=argument.indexOf(DEF)) )
            {
                default_value = argument.slice( p+1 );
                argument = argument.slice( 0, p );
            }
            else
            {
                default_value = null;
            }
            c = argument[CHAR](0);
            if ( OPT === c || OPTR === c )
            {
                optional = 1;
                if ( OPTR === c )
                {
                    start_i = 1;
                    end_i = -1;
                }
                else
                {
                    start_i = 0;
                    end_i = 0;
                }
                argument = argument.slice(1);
                if ( NEG === argument[CHAR](0) )
                {
                    negative = 1;
                    argument = argument.slice(1);
                }
                else
                {
                    negative = 0;
                }
            }
            else if ( REPL === c )
            {
                s = ''; j = 1; jl = argument.length;
                while ( j < jl && REPR !== argument[CHAR](j) ) s += argument[CHAR](j++);
                argument = argument.slice( j+1 );
                s = s.split(',');
                if ( s.length > 1 )
                {
                    start_i = trim(s[0]);
                    start_i = start_i.length ? parseInt(start_i,10)||0 : 0;
                    end_i = trim(s[1]);
                    end_i = end_i.length ? parseInt(end_i,10)||0 : -1;
                    optional = 1;
                }
                else
                {
                    start_i = trim(s[0]);
                    start_i = start_i.length ? parseInt(start_i,10)||0 : 0;
                    end_i = start_i;
                    optional = 0;
                }
                s = '';
                negative = 0;
            }
            else
            {
                optional = 0;
                negative = 0;
                start_i = 0;
                end_i = 0;
            }
            if ( negative && null === default_value ) default_value = '';
            
            if ( optional && !a[2] )
            {
                a[1] = argument;
                a[2] = optional;
                a[3] = negative;
                a[4] = start_i;
                a[5] = end_i;
            }
            else if ( !optional && (null === a[1]) )
            {
                a[1] = argument;
                a[2] = 0;
                a[3] = negative;
                a[4] = start_i;
                a[5] = end_i;
            }
            a[0].push([1, argument, default_value, optional, negative, start_i, end_i]);
        }
        else if ( OBL === c )
        {
            // optional block
            if ( s.length ) a[0].push([0, s]);
            s = '';
            stack.push(a);
            a = [[], null, 0, 0, 0, 0];
        }
        else if ( OBR === c )
        {
            b = a; a = stack.pop( );
            if ( s.length ) b[0].push([0, s]);
            s = '';
            a[0].push([-1, b[1], b[2], b[3], b[4], b[5], b[0]]);
        }
        else
        {
            s += c;
        }
    }
    if ( s.length ) a[0].push([0, s]);
    return a[0];
};
GrammTpl[PROTO] = {
    constructor: GrammTpl
    
    ,id: null
    ,tpl: null
    
    ,dispose: function( ) {
        this.id = null;
        this.tpl = null;
        return this;
    }
    ,render: function( args ) {
        args = args || { };
        var tpl = this.tpl, l = tpl.length, stack = [], p, arr, MIN = Math.min,
            i, t, tt, s, rarg = null, ri = 0, rs, re, out = ''
        ;
        i = 0;
        while ( i < l || stack.length )
        {
            if ( i >= l )
            {
                p = stack.pop( );
                tpl = p[0]; i = p[1]; l = p[2];
                rarg = p[3]||null; ri = p[4]||0;
                continue;
            }
            
            t = tpl[ i ]; tt = t[ 0 ]; s = t[ 1 ];
            if ( -1 === tt )
            {
                // optional block
                if ( (0 === t[ 3 ] && args[HAS](s)) ||
                    (1 === t[ 3 ] && !args[HAS](s))
                )
                {
                    if ( 1 === t[ 3 ] )
                    {
                        stack.push([tpl, i+1, l, rarg, ri]);
                        tpl = t[ 6 ]; i = 0; l = tpl.length;
                        rarg = null; ri = 0;
                        continue;
                    }
                    else
                    {
                        arr = is_array( args[s] );
                        if ( arr && (t[4] !== t[5]) && args[s].length > t[ 4 ] )
                        {
                            rs = t[ 4 ];
                            re = -1 === t[ 5 ] ? args[s].length-1 : MIN(t[ 5 ], args[s].length-1);
                            if ( re >= rs )
                            {
                                stack.push([tpl, i+1, l, rarg, ri]);
                                tpl = t[ 6 ]; i = 0; l = tpl.length;
                                rarg = s;
                                for(ri=re; ri>rs; ri--) stack.push([tpl, 0, l, rarg, ri]);
                                ri = rs;
                                continue;
                            }
                        }
                        else if ( !arr && (t[4] === t[5]) )
                        {
                            stack.push([tpl, i+1, l, rarg, ri]);
                            tpl = t[ 6 ]; i = 0; l = tpl.length;
                            rarg = s; ri = 0;
                            continue;
                        }
                    }
                }
            }
            else if ( 1 === tt )
            {
                //TODO: handle nested/structured/deep arguments
                // default value if missing
                out += !args[HAS](s) && null !== t[ 2 ]
                    ? t[ 2 ]
                    : (is_array(args[ s ])
                    ? (s === rarg
                    ? args[s][t[5]===t[6]?t[5]:ri]
                    : args[s][t[5]])
                    : args[s])
                ;
            }
            else /*if ( 0 === tt )*/
            {
                out += s;
            }
            i++;
            /*if ( i >= l && stack.length )
            {
                p = stack.pop( );
                tpl = p[0]; i = p[1]; l = p[2];
                rarg = p[3]||null; ri = p[4]||0;
            }*/
        }
        return out;
    }
};

Ref = function( col, col_q, tbl, tbl_q, dtb, dtb_q, alias, alias_q, 
                tbl_col, tbl_col_q, tbl_col_alias, tbl_col_alias_q ) {
    var self = this;
    self.col = col;
    self.col_q = col_q;
    self.tbl = tbl;
    self.tbl_q = tbl_q;
    self.dtb = dtb;
    self.dtb_q = dtb_q;
    self.alias = alias;
    self.alias_q = alias_q;
    self.tbl_col = tbl_col;
    self.tbl_col_q = tbl_col_q;
    self.tbl_col_alias = tbl_col_alias;
    self.tbl_col_alias_q = tbl_col_alias_q;
};
Ref.parse = function( r, d ) {
    var col, col_q, tbl, tbl_q, dtb, dtb_q, alias, alias_q, 
        tbl_col, tbl_col_q, tbl_col_alias, tbl_col_alias_q;
    r = trim( r ).split(' AS ');
    col = r[ 0 ].split( '.' );
    if ( 3 <= col.length )
    {
        dtb = trim(col[ 0 ]);
        tbl = trim(col[ 1 ]);
        col = trim(col[ 2 ]);
    }
    else if ( 2 === col.length )
    {
        dtb = null;
        tbl = trim(col[ 0 ]);
        col = trim(col[ 1 ]);
    }
    else
    {
        dtb = null; tbl = null;
        col = trim(col[ 0 ]);
    }
    col_q = d.quote_name( col );
    if ( null !== dtb )
    {
        dtb_q = d.quote_name( dtb );
        tbl_q = d.quote_name( tbl );
        tbl_col = dtb + '.' + tbl + '.' + col;
        tbl_col_q = dtb_q + '.' + tbl_q + '.' + col_q;
    }
    else if ( null !== tbl )
    {
        dtb_q = null;
        tbl_q = d.quote_name( tbl );
        tbl_col = tbl + '.' + col;
        tbl_col_q = tbl_q + '.' + col_q;
    }
    else
    {
        dtb_q = null; tbl_q = null;
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
    return new Ref( col, col_q, tbl, tbl_q, dtb, dtb_q, alias, alias_q, 
                tbl_col, tbl_col_q, tbl_col_alias, tbl_col_alias_q );
};
Ref[PROTO] = {
     constructor: Ref
    
    ,col: null
    ,col_q: null
    ,tbl: null
    ,tbl_q: null
    ,dtb: null
    ,dtb_q: null
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
        self.dtb = null;
        self.dtb_q = null;
        self.alias = null;
        self.alias_q = null;
        self.tbl_col = null;
        self.tbl_col_q = null;
        self.tbl_col_alias = null;
        self.tbl_col_alias_q = null;
        return self;
    }
};

var dialects = {
 'mysql'            : {
    // https://dev.mysql.com/doc/refman/5.0/en/select.html
    // https://dev.mysql.com/doc/refman/5.0/en/join.html
    // https://dev.mysql.com/doc/refman/5.5/en/expressions.html
    // https://dev.mysql.com/doc/refman/5.0/en/insert.html
    // https://dev.mysql.com/doc/refman/5.0/en/update.html
    // https://dev.mysql.com/doc/refman/5.0/en/delete.html
    // http://dev.mysql.com/doc/refman/5.7/en/create-table.html
    // http://dev.mysql.com/doc/refman/5.7/en/drop-table.html
    // http://dev.mysql.com/doc/refman/5.7/en/alter-table.html
     'quotes'       : [ ["'","'","\\'","\\'"], ['`','`'], ['',''] ]
    ,'clauses'      : {
     'create'       : "CREATE TABLE IF NOT EXISTS <create_table>\n(<create_defs>)[<?create_opts>]"
    ,'alter'        : "ALTER TABLE <alter_table>\n<alter_defs>[<?alter_opts>]"
    ,'drop'         : "DROP TABLE IF EXISTS <drop_tables>[,<*drop_tables>]"
    ,'select'       : "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>[\n<*join_clauses>]][\nWHERE <?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING <?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]"
    ,'insert'       : "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
    ,'update'       : "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]"
    ,'delete'       : "DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]"
    }
}
,'postgres'          : {
    // http://www.postgresql.org/docs/
    // http://www.postgresql.org/docs/9.1/static/sql-createtable.html
    // http://www.postgresql.org/docs/9.1/static/sql-droptable.html
    // http://www.postgresql.org/docs/9.1/static/sql-altertable.html
    // http://www.postgresql.org/docs/8.2/static/sql-syntax-lexical.html
     'quotes'       : [ ["E'","'","''","''"], ['"','"'], ['',''] ]
    ,'clauses'      : {
     'create'       : "CREATE TABLE IF NOT EXISTS <create_table>\n(<create_defs>)[<?create_opts>]"
    ,'alter'        : "ALTER TABLE <alter_table>\n<alter_defs>[<?alter_opts>]"
    ,'drop'         : "DROP TABLE IF EXISTS <drop_tables>[,<*drop_tables>]"
    ,'select'       : "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>[\n<*join_clauses>]][\nWHERE <?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING <?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
    ,'insert'       : "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
    ,'update'       : "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
    ,'delete'       : "DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
    }
}
,'sqlserver'        : {
    // https://msdn.microsoft.com/en-us/library/ms189499.aspx
    // https://msdn.microsoft.com/en-us/library/ms174335.aspx
    // https://msdn.microsoft.com/en-us/library/ms177523.aspx
    // https://msdn.microsoft.com/en-us/library/ms189835.aspx
    // https://msdn.microsoft.com/en-us/library/ms179859.aspx
    // https://msdn.microsoft.com/en-us/library/ms188385%28v=sql.110%29.aspx
    // https://msdn.microsoft.com/en-us/library/ms174979.aspx
    // https://msdn.microsoft.com/en-us/library/ms173790.aspx
    // https://msdn.microsoft.com/en-us/library/cc879314.aspx
    // http://stackoverflow.com/questions/603724/how-to-implement-limit-with-microsoft-sql-server
    // http://stackoverflow.com/questions/971964/limit-10-20-in-sql-server
     /*
"{?SELECT * FROM(\nSELECT {select_columns},ROW_NUMBER() OVER (ORDER BY {order_conditions|(SELECT 1)}) AS __row__\nFROM {from_tables}{?\n{join_clauses}?}(join_clauses){?\nWHERE {where_conditions}?}(where_conditions){?\nGROUP BY {group_conditions}?}(group_conditions){?\nHAVING {having_conditions}?}(having_conditions)\n) AS __query__ WHERE __query__.__row__ BETWEEN ({offset}+1) AND ({offset}+{count})?}(count){?SELECT {select_columns}\nFROM {from_tables}{?\n{join_clauses}?}(join_clauses){?\nWHERE {where_conditions}?}(where_conditions){?\nGROUP BY {group_conditions}?}(group_conditions){?\nHAVING {having_conditions}?}(having_conditions){?\nORDER BY {order_conditions}?}(order_conditions)?}(!count)"
     */
     'quotes'       : [ ["'","'","''","''"], ['[',']'], [''," ESCAPE '\\'"] ]
    ,'clauses'      : {
     'create'       : "CREATE TABLE IF NOT EXISTS <create_table>\n(<create_defs>)[<?create_opts>]"
    ,'alter'        : "ALTER TABLE <alter_table>\n<alter_defs>[<?alter_opts>]"
    ,'drop'         : "DROP TABLE IF EXISTS <drop_tables>[,<*drop_tables>]"
    ,'select'       : "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>[\n<*join_clauses>]][\nWHERE <?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING <?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>][\nOFFSET <offset|0> ROWS FETCH NEXT <?count> ROWS ONLY]][<?!order_conditions>[\nORDER BY 1\nOFFSET <offset|0> ROWS FETCH NEXT <?count> ROWS ONLY]]"
    ,'insert'       : "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
    ,'update'       : "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]]"
    ,'delete'       : "DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]]"
    }
}
,'sqlite'           : {
    // https://www.sqlite.org/lang_createtable.html
    // https://www.sqlite.org/lang_select.html
    // https://www.sqlite.org/lang_insert.html
    // https://www.sqlite.org/lang_update.html
    // https://www.sqlite.org/lang_delete.html
    // https://www.sqlite.org/lang_expr.html
    // https://www.sqlite.org/lang_keywords.html
    // http://stackoverflow.com/questions/1824490/how-do-you-enable-limit-for-delete-in-sqlite
     'quotes'       : [ ["'","'","''","''"], ['"','"'], [''," ESCAPE '\\'"] ]
    ,'clauses'      : {
     'create'       : "CREATE TABLE IF NOT EXISTS <create_table>\n(<create_defs>)[<?create_opts>]"
    ,'alter'        : "ALTER TABLE <alter_table>\n<alter_defs>[<?alter_opts>]"
    ,'drop'         : "DROP TABLE IF EXISTS <drop_tables>[,<*drop_tables>]"
    ,'select'       : "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>[\n<*join_clauses>]][\nWHERE <?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING <?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
    ,'insert'       : "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
    ,'update'       : "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE <?where_conditions>]"
    ,'delete'       : "[<?!order_conditions>[<?!count>DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE <?where_conditions>]]][DELETE \nFROM <from_tables>[,<*from_tables>] WHERE rowid IN (\nSELECT rowid FROM <from_tables>[,<*from_tables>][\nWHERE <?where_conditions>]\nORDER BY <?order_conditions>[,<*order_conditions>][\nLIMIT <?count> OFFSET <offset|0>]\n)][<?!order_conditions>[DELETE \nFROM <from_tables>[,<*from_tables>] WHERE rowid IN (\nSELECT rowid FROM <from_tables>[,<*from_tables>][\nWHERE <?where_conditions>]\nLIMIT <?count> OFFSET <offset|0>\n)]]"
    }
}
};

Dialect = function Dialect( type ) {
    var self = this;
    if ( !(self instanceof Dialect) ) return new Dialect( type );
    if ( !arguments.length ) type = 'mysql';
    
    if ( !type || !Dialect.dialects[ type ] || !Dialect.dialects[ type ][ 'clauses' ] )
    {
        throw new TypeError('Dialect: SQL dialect does not exist for "'+type+'"');
    }
    
    self.clau = null;
    self.clus = null;
    self.tbls = null;
    self.cols = null;
    self.vews = { };
    self.tpls = { };
    
    self.db = null;
    self.escdb = null;
    self.p = '';
    
    self.type = type;
    self.clauses = Dialect.dialects[ self.type ][ 'clauses' ];
    self.q  = Dialect.dialects[ self.type ][ 'quotes' ][ 0 ];
    self.qn = Dialect.dialects[ self.type ][ 'quotes' ][ 1 ];
    self.e  = Dialect.dialects[ self.type ][ 'quotes' ][ 2 ] || ['',''];
};
Dialect.VERSION = "0.5.1";
Dialect.TPL_RE = /\$\(([^\)]+)\)/g;
Dialect.dialects = dialects;
Dialect.GrammTpl = GrammTpl;
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
        var self = this, i, l, c;
        if ( !clause || !self.clauses[HAS](clause) )
        {
            throw new TypeError('Dialect: SQL clause "'+clause+'" does not exist for dialect "'+self.type+'"');
        }
        self.clus = { };
        self.tbls = { };
        self.cols = { };
        self.clau = clause;
        if ( !(self.clauses[ self.clau ] instanceof GrammTpl) )
            self.clauses[ self.clau ] = new GrammTpl( self.clauses[ self.clau ] );
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
        var self = this, query = null;
        if ( self.clau && self.clauses[HAS]( self.clau ) )
            query = self.clauses[ self.clau ].render( self.clus ) || "";
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
                //self.clear( );
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
                if ( 0 === tpli[ 0 ] )
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
    
    ,create: function( table, defs, opts, format, create_clause ) {
        var self = this;
        self.reset(create_clause||'create');
        if ( false !== format )
        {
            table = self.refs( table, self.tbls )[0].tbl_col_q;
        }
        self.clus.create_table = table;
        if ( !!self.clus.create_defs )
            defs = self.clus.create_defs + ',' + defs;
        self.clus.create_defs = defs;
        if ( !!opts )
        {
            if ( !!self.clus.create_opts )
                opts = self.clus.create_opts + ',' + opts;
            self.clus.create_opts = opts;
        }
        return self;
    }
    
    ,alter: function( table, defs, opts, format, alter_clause ) {
        var self = this;
        self.reset(alter_clause||'alter');
        if ( false !== format )
        {
            table = self.refs( table, self.tbls )[0].tbl_col_q;
        }
        self.clus.alter_table = table;
        if ( !!self.clus.alter_defs )
            defs = self.clus.alter_defs + ',' + defs;
        self.clus.alter_defs = defs;
        if ( !!opts )
        {
            if ( !!self.clus.alter_opts )
                opts = self.clus.alter_opts + ',' + opts;
            self.clus.alter_opts = opts;
        }
        return self;
    }
    
    ,drop: function( tables, format, drop_clause ) {
        var self = this, i, l, tbls;
        self.reset(drop_clause||'drop');
        if ( !tables || !tables.length || '*' === tables ) 
        {
            tables = '*';
        }
        else
        {            
            if ( false !== format )
            {
                tbls = self.refs( tables, self.tbls );
                tables = tbls[ 0 ].tbl_col_q;
                for(i=1,l=tbls.length; i<l; i++) tables += ',' + tbls[ i ].tbl_col_q;
            }
            else
            {
                tables = array( tables ).join(',');
            }
        }
        if ( !!self.clus.drop_tables )
            tables = self.clus.drop_tables + ',' + tables;
        self.clus.drop_tables = tables;
        return self;
    }
    
    ,select: function( cols, format, select_clause ) {
        var self = this, i, l, columns;
        self.reset(select_clause||'select');
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
        if ( !!self.clus.select_columns )
            columns = self.clus.select_columns + ',' + columns;
        self.clus.select_columns = columns;
        return self;
    }
    
    ,insert: function( tbls, cols, format, insert_clause ) {
        var self = this, i, l, view, tables, columns;
        self.reset(insert_clause||'insert');
        view = is_array( tbls ) ? tbls[0] : tbls;
        if ( self.vews[HAS]( view ) && self.clau === self.vews[ view ].clau )
        {
            // using custom 'soft' view
            view = self.vews[ view ];
            self.clus = defaults( self.clus, view.clus, true );
            self.tbls = defaults( {}, view.tbls, true );
            self.cols = defaults( {}, view.cols, true );
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
            if ( !!self.clus.insert_tables )
                tables = self.clus.insert_tables + ',' + tables;
            if ( !!self.clus.insert_columns )
                columns = self.clus.insert_columns + ',' + columns;
            self.clus.insert_tables = tables;
            self.clus.insert_columns = columns;
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
        if ( !!self.clus.values_values )
            insert_values = self.clus.values_values + ',' + insert_values;
        self.clus.values_values = insert_values;
        return self;
    }
    
    ,update: function( tbls, format, update_clause ) {
        var self = this, i, l, view, tables;
        self.reset(update_clause||'update');
        view = is_array( tbls ) ? tbls[0] : tbls;
        if ( self.vews[HAS]( view ) && self.clau === self.vews[ view ].clau )
        {
            // using custom 'soft' view
            view = self.vews[ view ];
            self.clus = defaults( self.clus, view.clus, true );
            self.tbls = defaults( {}, view.tbls, true );
            self.cols = defaults( {}, view.cols, true );
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
            if ( !!self.clus.update_tables )
                tables = self.clus.update_tables + ',' + tables;
            self.clus.update_tables = tables;
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
        if ( !!self.clus.set_values )
            set_values = self.clus.set_values + ',' + set_values;
        self.clus.set_values = set_values;
        return self;
    }
    
    ,del: function( delete_clause ) {
        var self = this;
        self.reset(delete_clause||'delete');
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
            self.clus = defaults( self.clus, view.clus, true );
            self.tbls = defaults( {}, view.tbls, true );
            self.cols = defaults( {}, view.cols, true );
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
            if ( !!self.clus.from_tables )
                tables = self.clus.from_tables + ',' + tables;
            self.clus.from_tables = tables;
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
        if ( !!self.clus.join_clauses )
            join_clause = self.clus.join_clauses + "\n" + join_clause;
        self.clus.join_clauses = join_clause;
        return self;
    }
    
    ,where: function( conditions, boolean_connective ) {
        var self = this;
        if ( empty(conditions) ) return self;
        boolean_connective = boolean_connective ? boolean_connective.toUpperCase() : "AND";
        if ( "OR" !== boolean_connective ) boolean_connective = "AND";
        conditions = self.conditions( conditions, false );
        if ( !!self.clus.where_conditions )
            conditions = self.clus.where_conditions + " "+boolean_connective+" " + conditions;
        self.clus.where_conditions = conditions;
        return self;
    }
    
    ,group: function( col, dir ) {
        var self = this, group_condition;
        dir = dir ? dir.toUpperCase() : "ASC";
        if ( "DESC" !== dir ) dir = "ASC";
        group_condition = self.refs( col, self.cols )[0].alias_q + " " + dir;
        if ( !!self.clus.group_conditions )
            group_condition = self.clus.group_conditions + ',' + group_condition;
        self.clus.group_conditions = group_condition;
        return self;
    }
    
    ,having: function( conditions, boolean_connective ) {
        var self = this;
        if ( empty(conditions) ) return self;
        boolean_connective = boolean_connective ? boolean_connective.toUpperCase() : "AND";
        if ( "OR" !== boolean_connective ) boolean_connective = "AND";
        conditions = self.conditions( conditions, true );
        if ( !!self.clus.having_conditions )
            conditions = self.clus.having_conditions + " "+boolean_connective+" " + conditions;
        self.clus.having_conditions = conditions;
        return self;
    }
    
    ,order: function( col, dir ) {
        var self = this, order_condition;
        dir = dir ? dir.toUpperCase() : "ASC";
        if ( "DESC" !== dir ) dir = "ASC";
        order_condition = self.refs( col, self.cols )[0].alias_q + " " + dir;
        if ( !!self.clus.order_conditions )
            order_condition = self.clus.order_conditions + ',' + order_condition;
        self.clus.order_conditions = order_condition;
        return self;
    }
    
    ,limit: function( count, offset ) {
        var self = this;
        self.clus.count = int(count);
        self.clus.offset = int(offset);
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
                if ( !lookup[HAS](ref.alias) ) 
                {
                    lookup[ ref.alias ] = ref;
                    if ( ref.tbl_col !== ref.alias && !lookup[HAS](ref.tbl_col) ) lookup[ ref.tbl_col ] = ref;
                }
                else
                {                    
                    ref = lookup[ ref.alias ];
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
            chars = '\\' + NULL_CHAR; esc = '\\';
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
