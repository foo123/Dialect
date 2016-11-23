/**
*   Dialect, 
*   a simple and flexible Cross-Platform SQL Builder for PHP, Python, Node/XPCOM/JS, ActionScript
* 
*   @version: 0.8.1
*   https://github.com/foo123/Dialect
*
*   Abstract the construction of SQL queries
*   Support multiple DB vendors
*   Intuitive and Flexible API
**/
!function( root, name, factory ){
"use strict";
if ( ('undefined'!==typeof Components)&&('object'===typeof Components.classes)&&('object'===typeof Components.classesByID)&&Components.utils&&('function'===typeof Components.utils['import']) ) /* XPCOM */
    (root.$deps = root.$deps||{}) && (root.EXPORTED_SYMBOLS = [name]) && (root[name] = root.$deps[name] = factory.call(root));
else if ( ('object'===typeof module)&&module.exports ) /* CommonJS */
    (module.$deps = module.$deps||{}) && (module.exports = module.$deps[name] = factory.call(root));
else if ( ('undefined'!==typeof System)&&('function'===typeof System.register)&&('function'===typeof System['import']) ) /* ES6 module */
    System.register(name,[],function($__export){$__export(name, factory.call(root));});
else if ( ('function'===typeof define)&&define.amd&&('function'===typeof require)&&('function'===typeof require.specified)&&require.specified(name) /*&& !require.defined(name)*/ ) /* AMD */
    define(name,['module'],function(module){factory.moduleUri = module.uri; return factory.call(root);});
else if ( !(name in root) ) /* Browser/WebWorker/.. */
    (root[name] = factory.call(root)||1)&&('function'===typeof(define))&&define.amd&&define(function(){return root[name];} );
}(  /* current root */          this, 
    /* module name */           "Dialect",
    /* module factory */        function ModuleFactory__Dialect( undef ){
"use strict";

var PROTO = 'prototype', HAS = 'hasOwnProperty', 
    Keys = Object.keys, toString = Object[PROTO].toString,
    CHAR = 'charAt', CHARCODE = 'charCodeAt',
    escaped_re = /[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, trim_re = /^\s+|\s+$/g,
    trim = String[PROTO].trim
        ? function( s ){ return s.trim(); }
        : function( s ){ return s.replace(trim_re, ''); },
    NULL_CHAR = String.fromCharCode( 0 ), GUID = 0;

function guid( )
{
    return ''+new Date().getTime()+'--'+(++GUID);
}

// https://github.com/foo123/StringTemplate
function StringTemplate( tpl, replacements, compiled )
{
    var self = this;
    if ( !(self instanceof StringTemplate) ) return new StringTemplate(tpl, replacements, compiled);
    self.id = null;
    self.tpl = null;
    self._renderer = null;
    self._args = [tpl||'',replacements || StringTemplate.defaultArgs,compiled];
    self._parsed = false;
}
StringTemplate.VERSION = '1.0.0';
StringTemplate.defaultArgs = /\$(-?[0-9]+)/g;
StringTemplate.guid = guid;
StringTemplate.multisplit = function multisplit( tpl, reps, as_array ) {
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
StringTemplate.multisplit_re = function multisplit_re( tpl, re ) {
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
StringTemplate.arg = function( key, argslen ) { 
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
StringTemplate.compile = function( tpl, raw ) {
    var l = tpl.length, 
        i, notIsSub, s, out;
    
    if ( true === raw )
    {
        out = '"use strict"; return (';
        for (i=0; i<l; i++)
        {
            notIsSub = tpl[ i ][ 0 ]; s = tpl[ i ][ 1 ];
            out += notIsSub ? s : StringTemplate.arg(s);
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
            else out += " + String(" + StringTemplate.arg(s,"argslen") + ") + ";
        }
        out += ');';
    }
    return new Function('args', out);
};
StringTemplate[PROTO] = {
    constructor: StringTemplate
    
    ,id: null
    ,tpl: null
    ,_parsed: false
    ,_args: null
    ,_renderer: null
    
    ,dispose: function( ) {
        var self = this;
        self.id = null;
        self.tpl = null;
        self._parsed = null;
        self._args = null;
        self._renderer = null;
        return self;
    }
    ,fixRenderer: function( ) {
        var self = this;
        self.render = 'function' === typeof self._renderer ? self._renderer : self.constructor[PROTO].render;
        return self;
    }
    ,parse: function( ) {
        var self = this;
        if ( false === self._parsed )
        {
            // lazy init
            self._parsed = true;
            var tpl = self._args[0], replacements = self._args[1], compiled = self._args[2];
            self._args = null;
            self.tpl = replacements instanceof RegExp 
                ? StringTemplate.multisplit_re(tpl, replacements) 
                : StringTemplate.multisplit( tpl, replacements );
            if ( true === compiled )
            {
                self._renderer = StringTemplate.compile( self.tpl );
                self.fixRenderer( );
            }
        }
        return self;
    }
    ,render: function( args ) {
        var self = this;
        args = args || [ ];
        if ( false === self._parsed )
        {
            // lazy init
            self.parse( );
            if ( self._renderer ) return self._renderer( args );
        }
        //if ( self._renderer ) return self._renderer( args );
        var tpl = self.tpl, l = tpl.length,
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
                if ( (+s === s) && (s < 0) ) s = argslen+s;
                out += args[ s ];
            }
        }
        return out;
    }
};

// https://github.com/foo123/GrammarTemplate
function walk( obj, keys, keys_alt, obj_alt )
{
    var o, l, i, k, found = 0;
    if ( keys )
    {
        o = obj;
        l = keys.length;
        i = 0;
        found = 1;
        while( i < l )
        {
            k = keys[i++];
            if ( (null != o) && (null != o[k]) )
            {
                o = o[k];
            }
            else
            {
                found = 0;
                break;
            }
        }
    }
    if ( !found && keys_alt )
    {
        o = obj;
        l = keys_alt.length;
        i = 0;
        found = 1;
        while( i < l )
        {
            k = keys_alt[i++];
            if ( (null != o) && (null != o[k]) )
            {
                o = o[k];
            }
            else
            {
                found = 0;
                break;
            }
        }
    }
    if ( !found && (null != obj_alt) && (obj_alt !== obj) )
    {
        if ( keys )
        {
            o = obj_alt;
            l = keys.length;
            i = 0;
            found = 1;
            while( i < l )
            {
                k = keys[i++];
                if ( (null != o) && (null != o[k]) )
                {
                    o = o[k];
                }
                else
                {
                    found = 0;
                    break;
                }
            }
        }
        if ( !found && keys_alt )
        {
            o = obj_alt;
            l = keys_alt.length;
            i = 0;
            found = 1;
            while( i < l )
            {
                k = keys_alt[i++];
                if ( (null != o) && (null != o[k]) )
                {
                    o = o[k];
                }
                else
                {
                    found = 0;
                    break;
                }
            }
        }
    }
    return found ? o : null;
}
function StackEntry( stack, value )
{
    this.prev = stack || null;
    this.value = value || null;
}
function TplEntry( node, tpl )
{
    if ( tpl ) tpl.next = this;
    this.node = node || null;
    this.prev = tpl || null;
    this.next = null;
}

function multisplit( tpl, delims, postop )
{
    var IDL = delims[0], IDR = delims[1],
        OBL = delims[2], OBR = delims[3], TPL = delims[4],
        lenIDL = IDL.length, lenIDR = IDR.length,
        lenOBL = OBL.length, lenOBR = OBR.length, lenTPL = TPL.length,
        ESC = '\\', OPT = '?', OPTR = '*', NEG = '!', DEF = '|',
        REPL = '{', REPR = '}', DOT = '.', REF = ':',
        default_value = null, negative = 0, optional = 0, nested, not_escd, start_i, end_i, template,
        argument, p, stack, c, a, b, s, l = tpl.length, i, j, jl, escaped, ch,
        subtpl, arg_tpl, cur_tpl, start_tpl, cur_arg, opt_args,
        roottpl, block, cur_block, prev_arg, prev_opt_args;
    
    postop = true === postop;
    a = new TplEntry({type: 0, val: ''});
    cur_arg = {
        type    : 1,
        name    : null,
        key     : null,
        stpl    : null,
        dval    : null,
        opt     : 0,
        neg     : 0,
        start   : 0,
        end     : 0
    };
    roottpl = a; block = null;
    opt_args = null; subtpl = {}; cur_tpl = null; arg_tpl = {}; start_tpl = null;
    stack = null; s = ''; escaped = false;
    
    i = 0;
    while( i < l )
    {
        ch = tpl[CHAR](i);
        if ( ESC === ch )
        {
            escaped = !escaped;
            i += 1;
        }
        
        if ( IDL === tpl.substr(i,lenIDL) )
        {
            i += lenIDL;
            
            if ( escaped )
            {
                s += IDL;
                escaped = false;
                continue;
            }
            
            if ( s.length )
            {
                if ( 0 === a.node.type ) a.node.val += s;
                else a = new TplEntry({type: 0, val: s}, a);
            }
            s = '';
        }
        else if ( IDR === tpl.substr(i,lenIDR) )
        {
            i += lenIDR;
            
            if ( escaped )
            {
                s += IDR;
                escaped = false;
                continue;
            }
            
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
            not_escd = true;
            if ( postop )
            {
                c = tpl.substr(i,2);
                if ( (ESC+OPT === c) || (ESC+OPTR === c) || (ESC+REPL === c) )
                {
                    not_escd = false;
                    i += 1;
                    c = '';
                }
                else
                {
                    c = i < l ? tpl[CHAR](i) : '';
                }
            }
            else
            {
                c = argument[CHAR](0);
            }
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
                if ( postop )
                {
                    i += 1;
                    if ( (i < l) && (NEG === tpl[CHAR](i)) )
                    {
                        negative = 1;
                        i += 1;
                    }
                    else
                    {
                        negative = 0;
                    }
                }
                else
                {
                    if ( NEG === argument[CHAR](1) )
                    {
                        negative = 1;
                        argument = argument.slice(2);
                    }
                    else
                    {
                        negative = 0;
                        argument = argument.slice(1);
                    }
                }
            }
            else if ( REPL === c )
            {
                if ( postop )
                {
                    s = ''; j = i+1; jl = l;
                    while ( (j < jl) && (REPR !== tpl[CHAR](j)) ) s += tpl[CHAR](j++);
                    i = j;
                }
                else
                {
                    s = ''; j = 1; jl = argument.length;
                    while ( (j < jl) && (REPR !== argument[CHAR](j)) ) s += argument[CHAR](j++);
                    argument = argument.slice( j+1 );
                }
                s = s.split(',');
                if ( s.length > 1 )
                {
                    start_i = trim(s[0]);
                    start_i = start_i.length ? (+start_i)|0 /*parseInt(start_i,10)||0*/ : 0;
                    end_i = trim(s[1]);
                    end_i = end_i.length ? (+end_i)|0 /*parseInt(end_i,10)||0*/ : -1;
                    optional = 1;
                }
                else
                {
                    start_i = trim(s[0]);
                    start_i = start_i.length ? (+start_i)|0 /*parseInt(start_i,10)||0*/ : 0;
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
            if ( negative && (null === default_value) ) default_value = '';
            
            template = -1 < argument.indexOf(REF) ? argument.split(REF) : [argument,null];
            argument = template[0]; template = template[1];
            nested = -1 < argument.indexOf(DOT) ? argument.split(DOT) : null;
            
            if ( cur_tpl && !arg_tpl[cur_tpl] ) arg_tpl[cur_tpl] = {};
            
            if ( not_escd && (TPL+OBL === tpl.substr(i,lenTPL+lenOBL)) )
            {
                // template definition
                i += lenTPL;
                template = template&&template.length ? template : 'grtpl--'+guid( );
                start_tpl = template;
                if ( cur_tpl && argument.length)
                    arg_tpl[cur_tpl][argument] = template;
            }
            
            if ( !argument.length ) continue; // template definition only
            
            if ( (null==template) && cur_tpl && arg_tpl[cur_tpl] && arg_tpl[cur_tpl][argument] )
                template = arg_tpl[cur_tpl][argument];
            
            if ( optional && !cur_arg.opt )
            {
                cur_arg.name = argument;
                cur_arg.key = nested;
                cur_arg.stpl = template;
                cur_arg.dval = default_value;
                cur_arg.opt = optional;
                cur_arg.neg = negative;
                cur_arg.start = start_i;
                cur_arg.end = end_i;
                // handle multiple optional arguments for same optional block
                opt_args = new StackEntry(null, [argument,nested,negative,start_i,end_i]);
            }
            else if ( optional )
            {
                // handle multiple optional arguments for same optional block
                opt_args = new StackEntry(opt_args, [argument,nested,negative,start_i,end_i]);
            }
            else if ( !optional && (null === cur_arg.name) )
            {
                cur_arg.name = argument;
                cur_arg.key = nested;
                cur_arg.stpl = template;
                cur_arg.dval = default_value;
                cur_arg.opt = 0;
                cur_arg.neg = negative;
                cur_arg.start = start_i;
                cur_arg.end = end_i;
                // handle multiple optional arguments for same optional block
                opt_args = new StackEntry(null, [argument,nested,negative,start_i,end_i]);
            }
            a = new TplEntry({
                type    : 1,
                name    : argument,
                key     : nested,
                stpl    : template,
                dval    : default_value,
                opt     : optional,
                start   : start_i,
                end     : end_i
            }, a);
        }
        else if ( OBL === tpl.substr(i,lenOBL) )
        {
            i += lenOBL;
            
            if ( escaped )
            {
                s += OBL;
                escaped = false;
                continue;
            }
            
            // optional block
            if ( s.length )
            {
                if ( 0 === a.node.type ) a.node.val += s;
                else a = new TplEntry({type: 0, val: s}, a);
            }
            s = '';
            stack = new StackEntry(stack, [a, block, cur_arg, opt_args, cur_tpl, start_tpl]);
            if ( start_tpl ) cur_tpl = start_tpl;
            start_tpl = null;
            cur_arg = {
                type    : 1,
                name    : null,
                key     : null,
                stpl    : null,
                dval    : null,
                opt     : 0,
                neg     : 0,
                start   : 0,
                end     : 0
            };
            opt_args = null;
            a = new TplEntry({type: 0, val: ''});
            block = a;
        }
        else if ( OBR === tpl.substr(i,lenOBR) )
        {
            i += lenOBR;
            
            if ( escaped )
            {
                s += OBR;
                escaped = false;
                continue;
            }
            
            b = a;
            cur_block = block;
            prev_arg = cur_arg;
            prev_opt_args = opt_args;
            if ( stack )
            {
                a = stack.value[0];
                block = stack.value[1];
                cur_arg = stack.value[2];
                opt_args = stack.value[3];
                cur_tpl = stack.value[4];
                start_tpl = stack.value[5];
                stack = stack.prev;
            }
            else
            {
                a = null;
            }
            if ( s.length )
            {
                if ( 0 === b.node.type ) b.node.val += s;
                else b = new TplEntry({type: 0, val: s}, b);
            }
            s = '';
            if ( start_tpl )
            {
                subtpl[start_tpl] = new TplEntry({
                    type    : 2,
                    name    : prev_arg.name,
                    key     : prev_arg.key,
                    start   : 0/*cur_arg.start*/,
                    end     : 0/*cur_arg.end*/,
                    opt_args: null/*opt_args*/,
                    tpl     : cur_block
                });
                start_tpl = null;
            }
            else
            {
                a = new TplEntry({
                    type    : -1,
                    name    : prev_arg.name,
                    key     : prev_arg.key,
                    start   : prev_arg.start,
                    end     : prev_arg.end,
                    opt_args: prev_opt_args,
                    tpl     : cur_block
                }, a);
            }
        }
        else
        {
            if ( ESC === ch ) s += ch;
            else s += tpl[CHAR](i++);
        }
    }
    if ( s.length )
    {
        if ( 0 === a.node.type ) a.node.val += s;
        else a = new TplEntry({type: 0, val: s}, a);
    }
    return [roottpl, subtpl];
}

function optional_block( args, block, SUB, FN, index, orig_args )
{
    var opt_vars, opt_v, opt_arg, arr, rs, re, ri, len, block_arg = null, out = '';
    
    if ( -1 === block.type )
    {
        // optional block, check if optional variables can be rendered
        opt_vars = block.opt_args; if ( !opt_vars ) return '';
        while( opt_vars )
        {
            opt_v = opt_vars.value;
            opt_arg = walk( args, opt_v[1], [String(opt_v[0])], orig_args );
            if ( (null === block_arg) && (block.name === opt_v[0]) ) block_arg = opt_arg;
            
            if ( (0 === opt_v[2] && null == opt_arg) ||
                (1 === opt_v[2] && null != opt_arg)
            )
                return '';
            opt_vars = opt_vars.prev;
        }
    }
    else
    {
        block_arg = walk( args, block.key, [String(block.name)], orig_args );
    }
    
    arr = is_array( block_arg ); len = arr ? block_arg.length : -1;
    if ( arr && (len > block.start) )
    {
        for(rs=block.start,re=(-1===block.end?len-1:Math.min(block.end,len-1)),ri=rs; ri<=re; ri++)
            out += main( args, block.tpl, SUB, FN, ri, orig_args );
    }
    else if ( !arr && (block.start === block.end) )
    {
        out = main( args, block.tpl, SUB, FN, null, orig_args );
    }
    return out;
}
function non_terminal( args, symbol, SUB, FN, index, orig_args )
{
    var opt_arg, tpl_args, tpl, out = '', fn;
    if ( symbol.stpl && ((SUB && SUB[symbol.stpl]) || (FN && FN[symbol.stpl]) || (GrammarTemplate.fnGlobal[symbol.stpl])) )
    {
        // using custom function or sub-template
        opt_arg = walk( args, symbol.key, [String(symbol.name)], orig_args );
        
        if ( SUB && SUB[symbol.stpl] )
        {
            // sub-template
            if ( (null != index/* || null != symbol.start*/) && (0 !== index || !symbol.opt) && is_array(opt_arg) )
            {
                opt_arg = opt_arg[/*null != index ?*/ index /*: symbol.start*/];
            }
            if ( (null == opt_arg) && (null !== symbol.dval) )
            {
                // default value if missing
                out = symbol.dval;
            }
            else
            {
                // try to associate sub-template parameters to actual input arguments
                tpl = SUB[symbol.stpl].node; tpl_args = {};
                if ( null != opt_arg )
                {
                    /*if ( opt_arg[HAS](tpl.name) && !opt_arg[HAS](symbol.name) ) tpl_args = opt_arg;
                    else tpl_args[tpl.name] = opt_arg;*/
                    if ( is_array(opt_arg) ) tpl_args[tpl.name] = opt_arg;
                    else tpl_args = opt_arg;
                }
                out = optional_block( tpl_args, tpl, SUB, FN, null, null == orig_args ? args : orig_args );
            }
        }
        else //if ( fn )
        {
            // custom function
            fn = FN && FN[symbol.stpl] ? FN[symbol.stpl] : (GrammarTemplate.fnGlobal[symbol.stpl] ? GrammarTemplate.fnGlobal[symbol.stpl] : null);
            
            if ( is_array(opt_arg) )
            {
                index = null != index ? index : symbol.start;
                opt_arg = index < opt_arg.length ? opt_arg[index] : null;
            }
            
            opt_arg = "function" === typeof fn ? fn(opt_arg, index, args, orig_args, symbol) : String(fn);
            
            out = (null == opt_arg) && (null !== symbol.dval) ? symbol.dval : String(opt_arg);
        }
    }
    else if ( symbol.opt && (null !== symbol.dval) )
    {
        // boolean optional argument
        out = symbol.dval;
    }
    else
    {
        // plain symbol argument
        opt_arg = walk( args, symbol.key, [String(symbol.name)], orig_args );
        
        // default value if missing
        if ( is_array(opt_arg) )
        {
            index = null != index ? index : symbol.start;
            opt_arg = index < opt_arg.length ? opt_arg[index] : null;
        }
        out = (null == opt_arg) && (null !== symbol.dval) ? symbol.dval : String(opt_arg);
    }
    return out;
}
function main( args, tpl, SUB, FN, index, orig_args )
{
    var tt, out = '';
    while ( tpl )
    {
        tt = tpl.node.type;
        out += (-1 === tt
            ? optional_block( args, tpl.node, SUB, FN, index, orig_args ) /* optional code-block */
            : (1 === tt
            ? non_terminal( args, tpl.node, SUB, FN, /*0 === index ? (tpl.node.opt&&tpl.node.stpl?null:index) : */index, orig_args ) /* non-terminal */
            : tpl.node.val /* terminal */
        ));
        tpl = tpl.next;
    }
    return out;
}


function GrammarTemplate( tpl, delims, postop )
{
    var self = this;
    if ( !(self instanceof GrammarTemplate) ) return new GrammarTemplate(tpl, delims, postop);
    self.id = null;
    self.tpl = null;
    self.fn = {};
    // lazy init
    self._args = [tpl||'', delims||GrammarTemplate.defaultDelims, postop||false];
};
GrammarTemplate.VERSION = '2.1.0';
GrammarTemplate.defaultDelims = ['<','>','[',']',':='/*,'?','*','!','|','{','}'*/];
GrammarTemplate.fnGlobal = {};
GrammarTemplate.guid = guid;
GrammarTemplate.multisplit = multisplit;
GrammarTemplate.main = main;
GrammarTemplate[PROTO] = {
    constructor: GrammarTemplate
    
    ,id: null
    ,tpl: null
    ,fn: null
    ,_args: null
    
    ,dispose: function( ) {
        var self = this;
        self.id = null;
        self.tpl = null;
        self.fn = null;
        self._args = null;
        return self;
    }
    ,parse: function( ) {
        var self = this;
        if ( (null === self.tpl) && (null !== self._args) )
        {
            // lazy init
            self.tpl = GrammarTemplate.multisplit( self._args[0], self._args[1], self._args[2] );
            self._args = null;
        }
        return self;
    }
    ,render: function( args ) {
        var self = this;
        // lazy init
        if ( null === self.tpl ) self.parse( );
        return GrammarTemplate.main( null==args ? {} : args, self.tpl[0], self.tpl[1], self.fn );
    }
};

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
function defaults( data, def, overwrite, array_copy )
{
    overwrite = true === overwrite;
    array_copy = true === array_copy;
    for (var k in def)
    {
        if ( !def[HAS](k) ) continue;
        if ( overwrite || !data[HAS](k) )
            data[ k ] = array_copy && def[ k ].slice ? def[ k ].slice( ) : def[ k ];
    }
    return data;
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
function map_join( arr, prop, sep )
{
    var joined = '', i, l;
    if ( arr && arr.length )
    {
        sep = null == sep ? ',' : sep;
        joined = arr[0][prop];
        for(i=1,l=arr.length; i<l; i++) joined += sep + arr[i][prop];
    }
    return joined;
}

function Ref( _col, col, _tbl, tbl, _dtb, dtb, _alias, alias, _qual, qual, _func )
{
    var self = this;
    self._col = _col;
    self.col = col;
    self._tbl = _tbl;
    self.tbl = tbl;
    self._dtb = _dtb;
    self.dtb = dtb;
    self._alias = _alias;
    self._qualified =_qual;
    self.qualified = qual;
    self.full = self.qualified;
    self._func = null == _func ? [] : _func;
    if ( self._func.length )
    {
        for(var f=0,fl=self._func.length; f<fl; f++) self.full = self._func[f]+'('+self.full+')';
    }
    if ( null != self._alias )
    {
        self.alias = alias;
        self.aliased = self.full + ' AS ' + self.alias;
    }
    else
    {
        self.alias = self.full;
        self.aliased = self.full;
    }
}
var Ref_spc_re = /\s/, Ref_num_re = /[0-9]/, Ref_alf_re = /[a-z_]/i;
Ref.parse = function( r, d ) {
    // should handle field formats like:
    // [ F1(..Fn( ] [[dtb.]tbl.]col [ )..) ] [ AS alias ]
    // and extract alias, dtb, tbl, col identifiers (if present)
    // and also extract F1,..,Fn function identifiers (if present)
    var i, l, stacks, stack, ids, funcs, keywords2 = ['AS'],
        s, err, err_pos, err_type, paren, quote, ch, keyword,
        col, col_q, tbl, tbl_q, dtb, dtb_q, alias, alias_q,
        tbl_col, tbl_col_q
    ;
    r = trim( r ); l = r.length; i = 0;
    stacks = [[]]; stack = stacks[0];
    ids = []; funcs = [];
    // 0 = SEP, 1 = ID, 2 = FUNC, 5 = Keyword, 10 = *, 100 = Subtree
    s = ''; err = null; paren = 0; quote = null;
    while ( i < l )
    {
        ch = r.charAt(i++);
        
        if ( '"' === ch || '`' === ch || '\'' === ch || '[' === ch || ']' === ch )
        {
            // sql quote
            if ( !quote )
            {
                if ( s.length || (']' === ch) )
                {
                    err = ['invalid',i];
                    break;
                }
                quote = '[' === ch ? ']' : ch;
                continue;
            }
            else if ( quote === ch )
            {
                if ( s.length )
                {
                    stack.unshift([1, s]);
                    ids.unshift(s);
                    s = '';
                }
                else
                {
                    err = ['invalid',i];
                    break;
                }
                quote = null;
                continue;
            }
            else if ( quote )
            {
                s += ch;
                continue;
            }
        }
        
        if ( quote )
        {
            // part of sql-quoted value
            s += ch;
            continue;
        }
        
        if ( '*' === ch )
        {
            // placeholder
            if ( s.length )
            {
                err = ['invalid',i];
                break;
            }
            stack.unshift([10, '*']);
            ids.unshift(10);
        }
        
        else if ( '.' === ch )
        {
            // separator
            if ( s.length )
            {
                stack.unshift([1, s]);
                ids.unshift(s);
                s = '';
            }
            if ( !stack.length || 1 !== stack[0][0] )
            {
                // error, mismatched separator
                err = ['invalid',i];
                break;
            }
            stack.unshift([0, '.']);
            ids.unshift(0);
        }
        
        else if ( '(' === ch )
        {
            // left paren
            paren++;
            if ( s.length )
            {
                // identifier is function
                stack.unshift([2, s]);
                funcs.unshift(s);
                s = '';
            }
            if ( !stack.length || (2 !== stack[0][0] && 1 !== stack[0][0]) )
            {
                err = ['invalid',i];
                break;
            }
            if ( 1 === stack[0][0] )
            {
                stack[0][0] = 2;
                funcs.unshift(ids.shift());
            }
            stacks.unshift([]);
            stack = stacks[0];
        }
        
        else if ( ')' === ch )
        {
            // right paren
            paren--;
            if ( s.length )
            {
                keyword = -1 < keywords2.indexOf(s.toUpperCase());
                stack.unshift([keyword ? 5 : 1, s]);
                ids.unshift(keyword ? 5 : s);
                s = '';
            }
            if ( stacks.length < 2 )
            {
                err = ['invalid',i];
                break;
            }
            // reduce
            stacks[1].unshift([100, stacks.shift()]);
            stack = stacks[0];
        }
        
        else if ( Ref_spc_re.test(ch) )
        {
            // space separator
            if ( s.length )
            {
                keyword = -1 < keywords2.indexOf(s.toUpperCase());
                stack.unshift([keyword ? 5 : 1, s]);
                ids.unshift(keyword ? 5 : s);
                s = '';
            }
            continue;
        }
        
        else if ( Ref_num_re.test(ch) )
        {
            if ( !s.length )
            {
                err = ['invalid',i];
                break;
            }
            // identifier
            s += ch;
        }
        
        else if ( Ref_alf_re.test(ch) )
        {
            // identifier
            s += ch;
        }
        
        else
        {
            err = ['invalid',i];
            break;
        }
    }
    if ( s.length )
    {
        stack.unshift([1, s]);
        ids.unshift(s);
        s = '';
    }
    if ( !err && paren ) err = ['paren', l];
    if ( !err && quote ) err = ['quote', l];
    if ( !err && 1 !== stacks.length ) err = ['invalid', l];
    if ( err )
    {
        err_pos = err[1]-1; err_type = err[0];
        if ( 'paren' == err_type )
        {
            // error, mismatched parentheses
            throw new TypeError('Dialect: Mismatched parentheses "'+r+'" at position '+err_pos+'.');
        }
        else if ( 'quote' == err_type )
        {
            // error, mismatched quotes
            throw new TypeError('Dialect: Mismatched quotes "'+r+'" at position '+err_pos+'.');
        }
        else// if ( 'invalid' == err_type )
        {
            // error, invalid character
            throw new TypeError('Dialect: Invalid character "'+r+'" at position '+err_pos+'.');
        }
    }
    alias = null; alias_q = '';
    if ( (ids.length >= 3) && (5 === ids[1]) && ('string' === typeof ids[0]) )
    {
        alias = ids.shift();
        alias_q = d.quote_name( alias );
        ids.shift();
    }
    col = null; col_q = '';
    if ( ids.length && ('string' === typeof ids[0] || 10 === ids[0]) )
    {
        if ( 10 === ids[0] )
        {
            ids.shift();
            col = col_q = '*';
        }
        else
        {
            col = ids.shift();
            col_q = d.quote_name( col );
        }
    }
    tbl = null; tbl_q = '';
    if ( (ids.length >= 2) && (0 === ids[0]) && ('string' === typeof ids[1]) )
    {
        ids.shift();
        tbl = ids.shift();
        tbl_q = d.quote_name( tbl );
    }
    dtb = null; dtb_q = '';
    if ( (ids.length >= 2) && (0 === ids[0]) && ('string' === typeof ids[1]) )
    {
        ids.shift();
        dtb = ids.shift();
        dtb_q = d.quote_name( dtb );
    }
    tbl_col = (dtb ? dtb+'.' : '') + (tbl ? tbl+'.' : '') + (col ? col : '');
    tbl_col_q = (dtb ? dtb_q+'.' : '') + (tbl ? tbl_q+'.' : '') + (col ? col_q : '');
    return new Ref(col, col_q, tbl, tbl_q, dtb, dtb_q, alias, alias_q, tbl_col, tbl_col_q, funcs);
};
Ref[PROTO] = {
     constructor: Ref
    
    ,_func: null
    ,_col: null
    ,col: null
    ,_tbl: null
    ,tbl: null
    ,_dtb: null
    ,dtb: null
    ,_alias: null
    ,alias: null
    ,_qualified: null
    ,qualified: null
    ,full: null
    ,aliased: null
    
    ,cloned: function( alias, alias_q, func ) {
        var self = this;
        if ( !arguments.length )
        {
            alias = self._alias;
            alias_q = self.alias;
        }
        else
        {
            alias_q = alias_q || alias;
        }
        if ( null == func )
        {
            func = self._func;
        }
        return new Ref( self._col, self.col, self._tbl, self.tbl, self._dtb, self.dtb, alias, alias_q, 
                    self._qualified, self.qualified, func );
    }
    
    ,dispose: function( ) {
        var self = this;
        self._func = null;
        self._col = null;
        self.col = null;
        self._tbl = null;
        self.tbl = null;
        self._dtb = null;
        self.dtb = null;
        self._alias = null;
        self.alias = null;
        self._qualified = null;
        self.qualified = null;
        self.full = null;
        self.aliased = null;
        return self;
    }
};

var dialects = {
 "mysql"            : {
     "quotes"       : [ ["'","'","\\'","\\'"], ["`","`"], ["",""] ]
    
    ,"functions"    : {
     "strpos"       : ["POSITION(",2," IN ",1,")"]
    ,"strlen"       : ["LENGTH(",1,")"]
    ,"strlower"     : ["LCASE(",1,")"]
    ,"strupper"     : ["UCASE(",1,")"]
    ,"trim"         : ["TRIM(",1,")"]
    ,"quote"        : ["QUOTE(",1,")"]
    ,"random"       : ["RAND()"]
    ,"now"          : ["NOW()"]
    }
    
    ,"clauses"      : "[<?transact_clause|>START TRANSACTION  <type|>;\n<statements>;[\n<*statements>;]\n[<?rollback|>ROLLBACK;][<?!rollback>COMMIT;]][<?create_clause|>[<?view|>CREATE VIEW <create_table> [(\n<?columns>[,\n<*columns>]\n)] AS <query>][<?!view>CREATE[ <?temporary|>TEMPORARY] TABLE[ <?ifnotexists|>IF NOT EXISTS] <create_table> [(\n<?columns>:=[<col:COL>:=[[[CONSTRAINT <?constraint> ]UNIQUE KEY <name|> <type|> (<?uniquekey>[,<*uniquekey>])][[CONSTRAINT <?constraint> ]PRIMARY KEY <type|> (<?primarykey>)][[<?!index>KEY][<?index|>INDEX] <name|> <type|> (<?key>[,<*key>])][CHECK (<?check>)][<?column> <type>[ <?!isnull><?isnotnull|>NOT NULL][ <?!isnotnull><?isnull|>NULL][ DEFAULT <?default_value>][ <?auto_increment|>AUTO_INCREMENT][ <?!primary><?unique|>UNIQUE KEY][ <?!unique><?primary|>PRIMARY KEY][ COMMENT '<?comment>'][ COLUMN_FORMAT <?format>][ STORAGE <?storage>]]][,\n<*col:COL>]]\n)][ <?options>:=[<opt:OPT>:=[[ENGINE=<?engine>][AUTO_INCREMENT=<?auto_increment>][CHARACTER SET=<?charset>][COLLATE=<?collation>]][, <*opt:OPT>]]][\nAS <?query>]]][<?alter_clause|>ALTER [<?view|>VIEW][<?!view>TABLE] <alter_table>\n<columns>[ <?options>]][<?drop_clause|>DROP [<?view|>VIEW][<?!view>[<?temporary|>TEMPORARY ]TABLE][ <?ifexists|>IF EXISTS] <drop_tables>[,<*drop_tables>]][<?select_clause|>SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>:=[<join:JOIN>:=[[<?type> ]JOIN <table>[ ON <?cond>]][\n<*join:JOIN>]]][\nWHERE <?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING <?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]][<?insert_clause|>INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]][<?update_clause|>UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]][<?delete_clause|>DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]]"
}


,"postgres"         : {
     "quotes"       : [ ["E'","'","''","''"], ["\"","\""], ["",""] ]
    
    ,"functions"    : {
     "strpos"       : ["position(",2," in ",1,")"]
    ,"strlen"       : ["length(",1,")"]
    ,"strlower"     : ["lower(",1,")"]
    ,"strupper"     : ["upper(",1,")"]
    ,"trim"         : ["trim(",1,")"]
    ,"quote"        : ["quote(",1,")"]
    ,"random"       : ["random()"]
    ,"now"          : ["now()"]
    }
    
    ,"clauses"      : "[<?transact_clause|>START TRANSACTION  <type|>;\n<statements>;[\n<*statements>;]\n[<?rollback|>ROLLBACK;][<?!rollback>COMMIT;]][<?create_clause|>[<?view|>CREATE[ <?temporary|>TEMPORARY] VIEW <create_table> [(\n<?columns>[,\n<*columns>]\n)] AS <query>][<?!view>CREATE[ <?temporary|>TEMPORARY] TABLE[ <?ifnotexists|>IF NOT EXISTS] <create_table> [(\n<?columns>:=[<col:COL>:=[[<?column> <type>[ COLLATE <?collation>][ CONSTRAINT <?constraint>][ <?!isnull><?isnotnull|>NOT NULL][ <?!isnotnull><?isnull|>NULL][ DEFAULT <?default_value>][ CHECK (<?check>)][ <?unique|>UNIQUE][ <?primary|>PRIMARY KEY]]][,\n<*col:COL>]]\n)]]][<?alter_clause|>ALTER [<?view|>VIEW][<?!view>TABLE] <alter_table>\n<columns>[ <?options>]][<?drop_clause|>DROP [<?view|>VIEW][<?!view>TABLE][ <?ifexists|>IF EXISTS] <drop_tables>[,<*drop_tables>]][<?select_clause|>SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>:=[<join:JOIN>:=[[<?type> ]JOIN <table>[ ON <?cond>]][\n<*join:JOIN>]]][\nWHERE <?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING <?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]][<?insert_clause|>INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]][<?update_clause|>UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]][<?delete_clause|>DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]]"
}


,"sqlserver"        : {
     "quotes"       : [ ["'","'","''","''"], ["[","]"], [""," ESCAPE '\\'"] ]
    
    ,"functions"    : {
     "strpos"       : ["CHARINDEX(",2,",",1,")"]
    ,"strlen"       : ["LEN(",1,")"]
    ,"strlower"     : ["LOWER(",1,")"]
    ,"strupper"     : ["UPPER(",1,")"]
    ,"trim"         : ["LTRIM(RTRIM(",1,"))"]
    ,"quote"        : ["QUOTENAME(",1,",\"'\")"]
    ,"random"       : ["RAND()"]
    ,"now"          : ["CURRENT_TIMESTAMP"]
    }
    
    ,"clauses"      : "[<?transact_clause|>BEGIN TRANSACTION  <type|>;\n<statements>;[\n<*statements>;]\n[<?rollback|>ROLLBACK;][<?!rollback>COMMIT;]][<?create_clause|>[<?view|>CREATE[ <?temporary|>TEMPORARY] VIEW[ <?ifnotexists|>IF NOT EXISTS] <create_table> [(\n<?columns>[,\n<*columns>]\n)] AS <query>][<?!view>[<?ifnotexists|>IF NOT EXISTS (SELECT * FROM sysobjects WHERE name=<create_table> AND xtype='U')\n]CREATE TABLE <create_table> [<?!query>(\n<columns>:=[<col:COL>:=[[[CONSTRAINT <?constraint> ]<?column> <type|>[ <?isnotnull|>NOT NULL][ [CONSTRAINT <?constraint> ]DEFAULT <?default_value>][ CHECK (<?check>)][ <?!primary><?unique|>UNIQUE][ <?!unique><?primary|>PRIMARY KEY[ COLLATE <?collation>]]]][,\n<*col:COL>]]\n)][<?ifnotexists|>\nGO]]][<?alter_clause|>ALTER [<?view|>VIEW][<?!view>TABLE] <alter_table>\n<columns>[ <?options>]][<?drop_clause|>DROP [<?view|>VIEW][<?!view>TABLE][ <?ifexists|>IF EXISTS] <drop_tables>[,<*drop_tables>]][<?select_clause|>SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>:=[<join:JOIN>:=[[<?type> ]JOIN <table>[ ON <?cond>]][\n<*join:JOIN>]]][\nWHERE <?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING <?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>][\nOFFSET <offset|0> ROWS FETCH NEXT <?count> ROWS ONLY]][<?!order_conditions>[\nORDER BY 1\nOFFSET <offset|0> ROWS FETCH NEXT <?count> ROWS ONLY]]][<?insert_clause|>INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]][<?update_clause|>UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]]][<?delete_clause|>DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE <?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]]]"
}


,"sqlite"           : {
     "quotes"       : [ ["'","'","''","''"], ["\"","\""], [""," ESCAPE '\\'"] ]
    
    ,"functions"    : {
     "strpos"       : ["instr(",2,",",1,")"]
    ,"strlen"       : ["length(",1,")"]
    ,"strlower"     : ["lower(",1,")"]
    ,"strupper"     : ["upper(",1,")"]
    ,"trim"         : ["trim(",1,")"]
    ,"quote"        : ["quote(",1,")"]
    ,"random"       : ["random()"]
    ,"now"          : ["datetime('now')"]
    }
    
    ,"clauses"      : "[<?transact_clause|>BEGIN <type|> TRANSACTION;\n<statements>;[\n<*statements>;]\n[<?rollback|>ROLLBACK;][<?!rollback>COMMIT;]][<?create_clause|>[<?view|>CREATE[ <?temporary|>TEMPORARY] VIEW[ <?ifnotexists|>IF NOT EXISTS] <create_table> [(\n<?columns>[,\n<*columns>]\n)] AS <query>][<?!view>CREATE[ <?temporary|>TEMPORARY] TABLE[ <?ifnotexists|>IF NOT EXISTS] <create_table> [<?!query>(\n<columns>:=[<col:COL>:=[[[CONSTRAINT <?constraint> ]<?column> <type|>[ <?isnotnull|>NOT NULL][ DEFAULT <?default_value>][ CHECK (<?check>)][ <?!primary><?unique|>UNIQUE][ <?!unique><?primary|>PRIMARY KEY[ <?auto_increment|>AUTOINCREMENT][ COLLATE <?collation>]]]][,\n<*col:COL>]]\n)[ <?without_rowid|>WITHOUT ROWID]][AS <?query>]]][<?alter_clause|>ALTER [<?view|>VIEW][<?!view>TABLE] <alter_table>\n<columns>[ <?options>]][<?drop_clause|>DROP [<?view|>VIEW][<?!view>TABLE][ <?ifexists|>IF EXISTS] <drop_tables>][<?select_clause|>SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>:=[<join:JOIN>:=[[<?type> ]JOIN <table>[ ON <?cond>]][\n<*join:JOIN>]]][\nWHERE <?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING <?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]][<?insert_clause|>INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]][<?update_clause|>UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE <?where_conditions>]][<?delete_clause|>[<?!order_conditions><?!count>DELETE FROM <from_tables> [, <*from_tables>][\nWHERE <?where_conditions>]][DELETE FROM <from_tables> [, <*from_tables>] WHERE rowid IN (\nSELECT rowid FROM <from_tables> [, <*from_tables>][\nWHERE <?where_conditions>]\nORDER BY <?order_conditions> [, <*order_conditions>][\nLIMIT <?count> OFFSET <offset|0>]\n)][<?!order_conditions>DELETE FROM <from_tables> [, <*from_tables>] WHERE rowid IN (\nSELECT rowid FROM <from_tables> [, <*from_tables>][\nWHERE <?where_conditions>]\nLIMIT <?count> OFFSET <offset|0>\n)]]"
}
};

function Dialect( type )
{
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
}
Dialect.VERSION = "0.8.1";
//Dialect.TPL_RE = /\$\(([^\)]+)\)/g;
Dialect.dialects = dialects;
Dialect.StringTemplate = StringTemplate;
Dialect.GrammarTemplate = GrammarTemplate;
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
        /*if ( !clause || !self.clauses[HAS](clause) )
        {
            throw new TypeError('Dialect: SQL clause "'+clause+'" does not exist for dialect "'+self.type+'"');
        }*/
        self.clus = { };
        self.tbls = { };
        self.cols = { };
        self.clau = clause;
        if ( !(self.clauses/*[ self.clau ]*/ instanceof Dialect.GrammarTemplate) )
            self.clauses/*[ self.clau ]*/ = new Dialect.GrammarTemplate( self.clauses/*[ self.clau ]*/ );
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
    
    ,subquery: function( ) {
        var self = this, sub;
        sub = new Dialect( self.type );
        sub.driver( self.driver() ).escape( self.escape() ).prefix( self.prefix() );
        sub.vews = self.vews;
        return sub;
    }
    
    ,sql: function( ) {
        var self = this, query = null;
        if ( self.clau /*&& self.clauses[HAS]( self.clau )*/ )
        {
            if ( self.clus[HAS]('select_columns') )
                self.clus['select_columns'] = map_join( self.clus['select_columns'], 'aliased' );
            if ( self.clus[HAS]('from_tables') )
                self.clus['from_tables'] = map_join( self.clus['from_tables'], 'aliased' );
            if ( self.clus[HAS]('insert_tables') )
                self.clus['insert_tables'] = map_join( self.clus['insert_tables'], 'aliased' );
            if ( self.clus[HAS]('insert_columns') )
                self.clus['insert_columns'] = map_join( self.clus['insert_columns'], 'full' );
            if ( self.clus[HAS]('update_tables') )
                self.clus['update_tables'] = map_join( self.clus['update_tables'], 'aliased' );
            if ( self.clus[HAS]('create_table') )
                self.clus['create_table'] = map_join( self.clus['create_table'], 'full' );
            if ( self.clus[HAS]('alter_table') )
                self.clus['alter_table'] = map_join( self.clus['alter_table'], 'full' );
            if ( self.clus[HAS]('drop_tables') )
                self.clus['drop_tables'] = map_join( self.clus['drop_tables'], 'full' );
            if ( self.clus[HAS]('where_conditions_required') /*&& !!self.clus['where_conditions_required']*/ )
            {
                self.clus['where_conditions'] = self.clus[HAS]('where_conditions') ? ('('+self.clus['where_conditions_required']+') AND ('+self.clus['where_conditions']+')') : self.clus['where_conditions_required'];
                delete self.clus['where_conditions_required'];
            }
            if ( self.clus[HAS]('having_conditions_required') /*&& !!self.clus['having_conditions_required']*/ )
            {
                self.clus['having_conditions'] = self.clus[HAS]('having_conditions') ? ('('+self.clus['having_conditions_required']+') AND ('+self.clus['having_conditions']+')') : self.clus['having_conditions_required'];
                delete self.clus['having_conditions_required'];
            }
            self.clus[self.clau+'_clause'] = 1;
            query = self.clauses/*[ self.clau ]*/.render( self.clus ) || "";
        }
        self.clear( );
        return query;
    }
    
    ,createView: function( view ) {
        var self = this;
        if ( view && self.clau )
        {
            self.vews[ view ] = {
                clau:self.clau, 
                clus:self.clus, 
                tbls:self.tbls, 
                cols:self.cols
            };
            // make existing where / having conditions required
            if ( self.vews[ view ].clus[HAS]('where_conditions') )
            {
                if ( !!self.vews[ view ].clus.where_conditions )
                    self.vews[ view ].clus.where_conditions_required = self.vews[ view ].clus.where_conditions;
                delete self.vews[ view ].clus.where_conditions;
            }
            if ( self.vews[ view ].clus[HAS]('having_conditions') )
            {
                if ( !!self.vews[ view ].clus.having_conditions )
                    self.vews[ view ].clus.having_conditions_required = self.vews[ view ].clus.having_conditions;
                delete self.vews[ view ].clus.having_conditions;
            }
            self.clear( );
        }
        return self;
    }
    
    ,useView: function( view ) {
        // using custom 'soft' view
        var self = this, selected_columns, select_columns;
        
        selected_columns = self.clus['select_columns'];
            
        view = self.vews[ view ];
        self.clus = defaults( self.clus, view.clus, true, true );
        self.tbls = defaults( {}, view.tbls, true );
        self.cols = defaults( {}, view.cols, true );
        
        // handle name resolution and recursive re-aliasing in views
        if ( !!selected_columns )
        {
            selected_columns = self.refs( selected_columns, self.cols, true );
            select_columns = [];
            for(var i=0,l=selected_columns.length; i<l; i++)
            {
                if ( '*' === selected_columns[i].full )
                    select_columns = select_columns.concat( self.clus['select_columns'] );
                else
                    select_columns.push( selected_columns[i] );
            }
            self.clus['select_columns'] = select_columns;
        }
        return self;
    }
    
    ,dropView: function( view ) {
        var self = this;
        if ( view && self.vews[HAS](view) )
        {
            delete self.vews[ view ];
        }
        return self;
    }
    
    ,prepareTpl: function( tpl /*, query, left, right*/ ) {
        var self = this, pattern, sql, 
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
                sql = new Dialect.StringTemplate( self.sql( ), pattern );
                //self.clear( );
            }
            else
            {
                sql = new Dialect.StringTemplate( query, pattern );
            }
            
            self.tpls[ tpl ] = {
                'sql':sql, 
                'types':null
            };
        }
        return self;
    }
    
    ,prepared: function( tpl, args ) {
        var self = this, sql, types, type, params, k, v, tmp, i, l, tpli, k;
        if ( !empty(tpl) && self.tpls[HAS](tpl) )
        {
            sql = self.tpls[tpl].sql;
            types = self.tpls[tpl].types;
            if ( null === types )
            {
                // lazy init
                sql.parse( );
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
                self.tpls[tpl].types = types;
            }
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
                            params[k] = Ref.parse( tmp[0], self ).aliased;
                            for (i=1,l=tmp.length; i<l; i++) params[k] += ','+Ref.parse( tmp[i], self ).aliased;
                        }
                        else
                        {
                            // reference, e.g field
                            params[k] = Ref.parse( v, self ).aliased;
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
                                param = Ref.parse( tmp[0], self ).aliased;
                                for (i=1,l=tmp.length; i<l; i++) param += ','+Ref.parse( tmp[i], self ).aliased;
                            }
                            else
                            {
                                // reference, e.g field
                                param = Ref.parse( args[param], self ).aliased;
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
    
    ,dropTpl: function( tpl ) {
        var self = this;
        if ( !empty(tpl) && self.tpls[HAS](tpl) )
        {
           self.tpls[ tpl ].sql.dispose( );
           delete self.tpls[ tpl ];
        }
        return self;
    }
    
    ,Transaction: function( options, transact_clause ) {
        var self = this, statements;
        transact_clause = transact_clause || 'transact';
        if ( self.clau !== transact_clause ) self.reset(transact_clause);
        options = options || {};
        self.clus.type = options.type || null;
        self.clus.rollback = options.rollback ? 1 : null;
        if ( !empty(options.statements) )
        {
            statements = array(statements);
            if ( !self.clus.statements ) self.clus.statements = statements;
            else self.clus.statements = self.clus.statements.concat(statements);
        }
        return self;
    }
    
    ,Create: function( table, options, create_clause ) {
        var self = this, cols, opts;
        create_clause = create_clause || 'create';
        if ( self.clau !== create_clause ) self.reset(create_clause);
        options = options || {ifnotexists:1};
        self.clus.create_table = self.refs( table, self.tbls );
        self.clus.view = options.view ? 1 : null;
        self.clus.ifnotexists = options.ifnotexists ? 1 : null;
        self.clus.temporary = options.temporary ? 1 : null;
        self.clus.query = !empty(options.query) ? options.query : null;
        if ( !empty(options.columns) )
        {
            cols = array( options.columns );
            if ( !self.clus.columns ) self.clus.columns = cols;
            else self.clus.columns = self.clus.columns.concat(cols);
        }
        if ( !empty(options.table) )
        {
            opts = array( options.table );
            if ( !self.clus.options ) self.clus.options = opts;
            else self.clus.options = self.clus.options.concat(opts);
        }
        return self;
    }
    
    ,Alter: function( table, options, alter_clause ) {
        var self = this, cols, opts;
        alter_clause = alter_clause || 'alter';
        if ( self.clau !== alter_clause ) self.reset(alter_clause);
        self.clus.alter_table = self.refs( table, self.tbls );
        options = options || {};
        self.clus.view = options.view ? 1 : null;
        if ( !empty(options.columns) )
        {
            cols = array( options.columns );
            if ( !self.clus.columns ) self.clus.columns = cols;
            else self.clus.columns = self.clus.columns.concat(cols);
        }
        if ( !empty(options.table) )
        {
            opts = array( options.table );
            if ( !self.clus.options ) self.clus.options = opts;
            else self.clus.options = self.clus.options.concat(opts);
        }
        return self;
    }
    
    ,Drop: function( tables, options, drop_clause ) {
        var self = this, view;
        drop_clause = drop_clause || 'drop';
        if ( self.clau !== drop_clause ) self.reset(drop_clause);
        view = is_array( tables ) ? tables[0] : tables;
        if ( self.vews[HAS]( view ) )
        {
            // drop custom 'soft' view
            self.dropView( view );
            return self;
        }
        tables = self.refs( null==tables ? '*' : tables, self.tbls );
        options = options || {ifexists:1};
        self.clus.view = options.view ? 1 : null;
        self.clus.ifexists = options.ifexists ? 1 : null;
        self.clus.temporary = options.temporary ? 1 : null;
        if ( !self.clus.drop_tables )
            self.clus.drop_tables = tables;
        else
            self.clus.drop_tables = self.clus.drop_tables.concat(tables);
        return self;
    }
    
    ,Select: function( columns, select_clause ) {
        var self = this;
        select_clause = select_clause || 'select';
        if ( self.clau !== select_clause ) self.reset(select_clause);
        columns = self.refs( null==columns ? '*' : columns, self.cols );
        if ( !self.clus.select_columns )
            self.clus.select_columns = columns;
        else
            self.clus.select_columns = self.clus.select_columns.concat(columns);
        return self;
    }
    
    ,Insert: function( tables, columns, insert_clause ) {
        var self = this, view;
        insert_clause = insert_clause || 'insert';
        if ( self.clau !== insert_clause ) self.reset(insert_clause);
        view = is_array( tables ) ? tables[0] : tables;
        if ( self.vews[HAS]( view ) && (self.clau === self.vews[ view ].clau) )
        {
            // using custom 'soft' view
            self.useView( view );
        }
        else
        {
            tables = self.refs( tables, self.tbls );
            columns = self.refs( columns, self.cols );
            if ( !self.clus.insert_tables )
                self.clus.insert_tables = tables;
            else
                self.clus.insert_tables = self.clus.insert_tables.concat(tables);
            if ( !self.clus.insert_columns )
                self.clus.insert_columns = columns;
            else
                self.clus.insert_columns = self.clus.insert_columns.concat(columns);
        }
        return self;
    }
    
    ,Values: function( values ) {
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
    
    ,Update: function( tables, update_clause ) {
        var self = this, view;
        update_clause = update_clause || 'update';
        if ( self.clau !== update_clause ) self.reset(update_clause);
        view = is_array( tables ) ? tables[0] : tables;
        if ( self.vews[HAS]( view ) && (self.clau === self.vews[ view ].clau) )
        {
            // using custom 'soft' view
            self.useView( view );
        }
        else
        {
            tables = self.refs( tables, self.tbls );
            if ( !self.clus.update_tables )
                self.clus.update_tables = tables;
            else
                self.clus.update_tables = self.clus.update_tables.concat(tables);
        }
        return self;
    }
    
    ,Set: function( fields_values ) {
        var self = this, set_values, set_case_value, f, field, value, COLS;
        if ( empty(fields_values) ) return self;
        set_values = [];
        COLS = self.cols;
        for (f in fields_values)
        {
            if ( !fields_values[HAS](f) ) continue;
            field = self.refs( f, COLS )[0].full;
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
                else if ( value[HAS]('case') )
                {
                    set_case_value = field + " = CASE";
                    if ( value['case'][HAS]('when') )
                    {
                        for ( case_value in value['case']['when'] )
                        {
                            if ( !value['case']['when'][HAS](case_value) ) continue;
                            set_case_value += "\nWHEN " + self.conditions(value['case']['when'][case_value],false) + " THEN " + self.quote(case_value);
                        }
                        if ( value['case'][HAS]('else') )
                            set_case_value += "\nELSE " + self.quote(value['case']['else']);
                    }
                    else
                    {
                        for ( case_value in value['case'] )
                        {
                            if ( !value['case'][HAS](case_value) ) continue;
                            set_case_value += "\nWHEN " + self.conditions(value['case'][case_value],false) + " THEN " + self.quote(case_value);
                        }
                    }
                    set_case_value += "\nEND";
                    set_values.push( set_case_value );
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
    
    ,Delete: function( delete_clause ) {
        var self = this;
        delete_clause = delete_clause || 'delete';
        if ( self.clau !== delete_clause ) self.reset(delete_clause);
        return self;
    }
    
    ,From: function( tables ) {
        var self = this, view, tables;
        if ( empty(tables) ) return self;
        view = is_array( tables ) ? tables[0] : tables;
        if ( self.vews[HAS]( view ) && (self.clau === self.vews[ view ].clau) )
        {
            // using custom 'soft' view
            self.useView( view );
        }
        else
        {
            tables = self.refs( tables, self.tbls );
            if ( !self.clus.from_tables )
                self.clus.from_tables = tables;
            else
                self.clus.from_tables = self.clus.from_tables.concat(tables);
        }
        return self;
    }
    
    ,Join: function( table, on_cond, join_type ) {
        var self = this, join_clause, field, cond;
        table = self.refs( table, self.tbls )[0].aliased;
        join_type = empty(join_type) ? null : join_type.toUpperCase();
        if ( empty(on_cond) )
        {
            join_clause = {
                table   : table,
                type    : join_type
            };
        }
        else
        {
            if ( is_string(on_cond) )
            {
                on_cond = self.refs( on_cond.split('='), self.cols );
                on_cond = '(' + on_cond[0].full + '=' + on_cond[1].full + ')';
            }
            else
            {
                for (field in on_cond)
                {
                    if ( !on_cond[HAS](field) ) continue;
                    cond = on_cond[ field ];
                    if ( !is_obj(cond) ) on_cond[field] = {'eq':cond,'type':'identifier'};
                }
                on_cond = '(' + self.conditions( on_cond, false ) + ')';
            }
            join_clause = {
                table   : table,
                type    : join_type,
                cond    : on_cond
            };
        }
        if ( !self.clus.join_clauses ) self.clus.join_clauses = [join_clause];
        else self.clus.join_clauses.push(join_clause);
        return self;
    }
    
    ,Where: function( conditions, boolean_connective ) {
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
    
    ,Group: function( col, dir ) {
        var self = this, group_condition;
        dir = dir ? dir.toUpperCase() : "ASC";
        if ( "DESC" !== dir ) dir = "ASC";
        group_condition = self.refs( col, self.cols )[0].alias + " " + dir;
        if ( !!self.clus.group_conditions )
            group_condition = self.clus.group_conditions + ',' + group_condition;
        self.clus.group_conditions = group_condition;
        return self;
    }
    
    ,Having: function( conditions, boolean_connective ) {
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
    
    ,Order: function( col, dir ) {
        var self = this, order_condition;
        dir = dir ? dir.toUpperCase() : "ASC";
        if ( "DESC" !== dir ) dir = "ASC";
        order_condition = self.refs( col, self.cols )[0].alias + " " + dir;
        if ( !!self.clus.order_conditions )
            order_condition = self.clus.order_conditions + ',' + order_condition;
        self.clus.order_conditions = order_condition;
        return self;
    }
    
    ,Limit: function( count, offset ) {
        var self = this;
        self.clus.count = int(count);
        self.clus.offset = int(offset);
        return self;
    }
    
    ,Page: function( page, perpage ) {
        var self = this;
        page = int(page); perpage = int(perpage);
        return self.Limit( perpage, page*perpage );
    }
    
    ,conditions: function( conditions, can_use_alias ) {
        var self = this, condquery, conds, f, field, value, fmt, op, type, v, COLS, cases, case_i, case_value;
        if ( empty(conditions) ) return '';
        if ( is_string(conditions) ) return conditions;
        
        condquery = '';
        conds = [];
        COLS = self.cols;
        fmt = true === can_use_alias ? 'alias' : 'full';
        
        for (f in conditions)
        {
            if ( !conditions[HAS](f) ) continue;
            
            value = conditions[ f ];
            
            if ( is_obj( value ) )
            {
                if ( value[HAS]('raw') )
                {
                    conds.push(String(value['raw']));
                    continue;
                }
                
                if ( value[HAS]('or') )
                {
                    cases = [];
                    for(var i=0,il=value['or'].length; i<il; i++)
                    {
                        case_i = value['or'][i];
                        cases.push(self.conditions(case_i, can_use_alias));
                    }
                    conds.push(cases.join(' OR '));
                    continue;
                }
                
                if ( value[HAS]('and') )
                {
                    cases = [];
                    for(var i=0,il=value['and'].length; i<il; i++)
                    {
                        case_i = value['and'][i];
                        cases.push(self.conditions(case_i, can_use_alias));
                    }
                    conds.push(cases.join(' AND '));
                    continue;
                }
                
                if ( value[HAS]('either') )
                {
                    cases = [];
                    for(var i=0,il=value['either'].length; i<il; i++)
                    {
                        case_i = {}; case_i[f] = value['either'][i];
                        cases.push(self.conditions(case_i, can_use_alias));
                    }
                    conds.push(cases.join(' OR '));
                    continue;
                }
                
                if ( value[HAS]('together') )
                {
                    cases = [];
                    for(var i=0,il=value['together'].length; i<il; i++)
                    {
                        case_i = {}; case_i[f] = value['together'][i];
                        cases.push(self.conditions(case_i, can_use_alias));
                    }
                    conds.push(cases.join(' AND '));
                    continue;
                }
                
                field = self.refs( f, COLS )[0][ fmt ];
                type = value[HAS]('type') ? value.type : 'string';
                
                if ( value[HAS]('case') )
                {
                    cases = field + " = CASE";
                    if ( value['case'][HAS]('when') )
                    {
                        for ( case_value in value['case']['when'] )
                        {
                            if ( !value['case']['when'][HAS](case_value) ) continue;
                            cases += " WHEN " + self.conditions(value['case']['when'][case_value], can_use_alias) + " THEN " + self.quote(case_value);
                        }
                        if ( value['case'][HAS]('else') )
                            cases += " ELSE " + self.quote(value['case']['else']);
                    }
                    else
                    {
                        for ( case_value in value['case'] )
                        {
                            if ( !value['case'][HAS](case_value) ) continue;
                            cases += " WHEN " + self.conditions(value['case'][case_value], can_use_alias) + " THEN " + self.quote(case_value);
                        }
                    }
                    cases += " END";
                    conds.push( cases );
                }
                else if ( value[HAS]('multi_like') )
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
                else if ( value[HAS]('contains') )
                {
                    v = String(value.contains);
                    
                    if ( 'raw' === type )
                    {
                        // raw, do nothing
                    }
                    else
                    {
                        v = self.quote( v );
                    }
                    conds.push(self.sql_function('strpos', [field,v]) + ' > 0');
                }
                else if ( value[HAS]('not_contains') )
                {
                    v = String(value.not_contains);
                    
                    if ( 'raw' === type )
                    {
                        // raw, do nothing
                    }
                    else
                    {
                        v = self.quote( v );
                    }
                    conds.push(self.sql_function('strpos', [field,v]) + ' = 0');
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
                    
                    // partial between clause
                    if ( null == v[0] )
                    {
                        // switch to lte clause
                        if ( 'raw' === type )
                        {
                            // raw, do nothing
                        }
                        else if ( 'integer' === type || is_int(v[1]) )
                        {
                            v[1] = self.intval( v[1] );
                        }
                        else
                        {
                            v[1] = self.quote( v[1] );
                        }
                        conds.push( field + " <= " + v[1] );
                    }
                    else if ( null == v[1] )
                    {
                        // switch to gte clause
                        if ( 'raw' === type )
                        {
                            // raw, do nothing
                        }
                        else if ( 'integer' === type || is_int(v[0]) )
                        {
                            v[0] = self.intval( v[0] );
                        }
                        else
                        {
                            v[0] = self.quote( v[0] );
                        }
                        conds.push( field + " >= " + v[0] );
                    }
                    else
                    {
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
                }
                else if ( value[HAS]('not_between') )
                {
                    v = array( value.not_between );
                    
                    // partial between clause
                    if ( null == v[0] )
                    {
                        // switch to gt clause
                        if ( 'raw' === type )
                        {
                            // raw, do nothing
                        }
                        else if ( 'integer' === type || is_int(v[1]) )
                        {
                            v[1] = self.intval( v[1] );
                        }
                        else
                        {
                            v[1] = self.quote( v[1] );
                        }
                        conds.push( field + " > " + v[1] );
                    }
                    else if ( null == v[1] )
                    {
                        // switch to lt clause
                        if ( 'raw' === type )
                        {
                            // raw, do nothing
                        }
                        else if ( 'integer' === type || is_int(v[0]) )
                        {
                            v[0] = self.intval( v[0] );
                        }
                        else
                        {
                            v[0] = self.quote( v[0] );
                        }
                        conds.push( field + " < " + v[0] );
                    }
                    else
                    {
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
                field = self.refs( f, COLS )[0][ fmt ];
                conds.push( field + " = " + (is_int(value) ? value : self.quote(value)) );
            }
        }
        
        if ( conds.length ) condquery = '(' + conds.join(') AND (') + ')';
        return condquery;
    }
    
    ,joinConditions: function( join, conditions ) {
        var self = this, j = 0, f, ref, field, cond, where,
            main_table, main_id, join_table, join_id, join_alias,
            join_key, join_value;
        for ( f in conditions )
        {
            if ( !conditions[HAS](f) ) continue;
            
            ref = Ref.parse( f, self );
            field = ref._col;
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
            self.Join(
                join_table+" AS "+join_alias, 
                main_table+'.'+main_id+'='+join_alias+'.'+join_id, 
                "inner"
            ).Where( where );
            
            delete conditions[f];
        }
        return self;
    }
    
    ,refs: function( refs, lookup, re_alias ) {
        var self = this;
        if ( true === re_alias )
        {
            var i, l, ref, ref2, alias, qualified, qualified_full, alias2, qualified_full2;
            for (i=0,l=refs.length; i<l; i++)
            {
                ref = refs[ i ];
                alias = ref.alias;
                qualified = ref.qualified;
                qualified_full = ref.full;
                
                if ( '*' === qualified_full ) continue;
                
                if ( !lookup[HAS]( alias ) )
                {
                    if ( lookup[HAS]( qualified_full ) )
                    {
                        ref2 = lookup[ qualified_full ];
                        alias2 = ref2.alias;
                        qualified_full2 = ref2.full;
                        
                        if ( (qualified_full2 !== qualified_full) && (alias2 !== alias) && (alias2 === qualified_full) )
                        {
                            // handle recursive aliasing
                            /*if ( (qualified_full2 !== alias2) && lookup[HAS]( alias2 ) )
                                delete lookup[ alias2 ];*/
                            
                            ref2 = ref2.cloned( ref.alias );
                            refs[i] = lookup[ alias ] = ref2;
                        }
                    }
                    else if ( lookup[HAS]( qualified ) )
                    {
                        ref2 = lookup[ qualified ];
                        if ( ref2.qualified !== qualified ) ref2 = lookup[ ref2.qualified ];
                        if ( ref.full !== ref.alias )
                            ref2 = ref2.cloned( ref.alias, null, ref._func );
                        else
                            ref2 = ref2.cloned( null, ref2.alias, ref._func );
                        refs[i] = lookup[ ref2.alias ] = ref2;
                        if ( (ref2.alias !== ref2.full) && !lookup[HAS]( ref2.full ) )
                            lookup[ ref2.full ] = ref2;
                    }
                    else
                    {
                        lookup[ alias ] = ref;
                        if ( (alias !== qualified_full) && !lookup[HAS]( qualified_full ) )
                            lookup[ qualified_full ] = ref;
                    }
                }
                else
                {
                    refs[i] = lookup[ alias ];
                }
            }
        }
        else
        {
            var i, l, j, m, r, rs, ref, alias, qualified;
            rs = array( refs );
            refs = [ ];
            for (i=0,l=rs.length; i<l; i++)
            {
                r = rs[ i ].split(',');
                for (j=0,m=r.length; j<m; j++)
                {
                    ref = Ref.parse( r[ j ], self );
                    alias = ref.alias; qualified = ref.full;
                    if ( !lookup[HAS](alias) ) 
                    {
                        lookup[ alias ] = ref;
                        if ( (qualified !== alias) && !lookup[HAS](qualified) )
                            lookup[ qualified ] = ref;
                    }
                    else
                    {                    
                        ref = lookup[ alias ];
                    }
                    refs.push( ref );
                }
            }
        }
        return refs;
    }
    
    ,tbl: function( table ) {
        var self = this;
        if ( is_array( table ) )
        {
            for(var i=0,l=table.length; i<l; i++) table[i] = self.tbl( table[i] );
            return table;
        }
        return self.p + table;
    }
    
    ,intval: function( v ) {
        var self = this;
        if ( is_array( v ) )
        {
            for(var i=0,l=v.length; i<l; i++) v[i] = self.intval( v[i] );
            return v;
        }
        return parseInt( v, 10 );
    }
    
    ,quote_name: function( v, optional ) {
        var self = this, qn = self.qn;
        optional = true === optional;
        if ( is_array( v ) )
        {
            for(var i=0,l=v.length; i<l; i++) v[i] = self.quote_name( v[i], optional );
            return v;
        }
        else if ( optional )
        {
            return (qn[0] == v.slice(0,qn[0].length) ? '' : qn[0]) + v + (qn[1] == v.slice(-qn[1].length) ? '' : qn[1]);
        }
        else
        {
            return qn[0] + v + qn[1];
        }
    }
    
    ,quote: function( v ) {
        var self = this, q = self.q;
        if ( is_array( v ) )
        {
            for(var i=0,l=v.length; i<l; i++) v[i] = self.quote( v[i] );
            return v;
        }
        return q[0] + self.esc( v ) + q[1];
    }
    
    ,like: function( v ) {
        var self = this, q, e;
        if ( is_array( v ) )
        {
            for(var i=0,l=v.length; i<l; i++) v[i] = self.like( v[i] );
            return v;
        }
        q = self.q; e = self.escdb ? ['',''] : self.e;
        return e[0] + q[0] + '%' + self.esc_like( self.esc( v ) ) + '%' + q[1] + e[1];
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
        var self = this, chars, esc, i, l, ve, c, q;
        if ( is_array( v ) )
        {
            for(i=0,l=v.length; i<l; i++) v[i] = self.esc( v[i] );
            return v;
        }
        else if ( self.escdb ) 
        {
            return self.escdb( v );
        }
        else
        {
            // simple ecsaping using addslashes
            // '"\ and NUL (the NULL byte).
            q = self.q;
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
        var self = this;
        if ( is_array( v ) )
        {
            for(var i=0,l=v.length; i<l; i++) v[i] = self.esc_like( v[i] );
            return v;
        }
        return addslashes( v, '_%', '\\' );
    }
    
    ,sql_function: function( f, args ) {
        var self = this, func, is_arg, i, l, fi, argslen;
        if ( null == Dialect.dialects[ self.type ][ 'functions' ][ f ] )
            throw new TypeError('Dialect: SQL function "'+f+'" does not exist for dialect "'+self.type+'"');
        f = Dialect.dialects[ self.type ][ 'functions' ][ f ];
        args = null != args ? array(args) : [];
        argslen = args.length;
        func = ''; is_arg = false;
        for(i=0,l=f.length; i<l; i++)
        {
            fi = f[i];
            func += is_arg ? (0<fi && argslen>=fi ? args[fi-1] : '') : fi;
            is_arg = !is_arg;
        }
        return func;
    }
};

// export it
return Dialect;
});
