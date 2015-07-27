##
#   Dialect, 
#   a simple and flexible Cross-Platform SQL Builder for PHP, Python, Node/JS, ActionScript
# 
#   @version: 0.1
#   https://github.com/foo123/Dialect
#
#   Abstract the construction of SQL queries
#   Support multiple DB vendors
#   Intuitive and Flexible API
##
import re, math

NEWLINE = re.compile(r'\n\r|\r\n|\n|\r') 
SQUOTE = re.compile(r"'")
T_REGEXP = type(NEWLINE)

# static
CNT = 0

def createFunction( args, sourceCode, additional_symbols=dict() ):
    # http://code.activestate.com/recipes/550804-create-a-restricted-python-function-from-a-string/
    
    global CNT
    CNT += 1
    funcName = 'dialect_dyna_func_' + str(CNT)
    
    # The list of symbols that are included by default in the generated
    # function's environment
    SAFE_SYMBOLS = [
        "list", "dict", "enumerate", "tuple", "set", "long", "float", "object",
        "bool", "callable", "True", "False", "dir",
        "frozenset", "getattr", "hasattr", "abs", "cmp", "complex",
        "divmod", "id", "pow", "round", "slice", "vars",
        "hash", "hex", "int", "isinstance", "issubclass", "len",
        "map", "filter", "max", "min", "oct", "chr", "ord", "range",
        "reduce", "repr", "str", "type", "zip", "xrange", "None",
        "Exception", "KeyboardInterrupt"
    ]
    
    # Also add the standard exceptions
    __bi = __builtins__
    if type(__bi) is not dict:
        __bi = __bi.__dict__
    for k in __bi:
        if k.endswith("Error") or k.endswith("Warning"):
            SAFE_SYMBOLS.append(k)
    del __bi
    
    # Include the sourcecode as the code of a function funcName:
    s = "def " + funcName + "(%s):\n" % args
    s += sourceCode # this should be already properly padded

    # Byte-compilation (optional)
    byteCode = compile(s, "<string>", 'exec')  

    # Setup the local and global dictionaries of the execution
    # environment for __TheFunction__
    bis   = dict() # builtins
    globs = dict()
    locs  = dict()

    # Setup a standard-compatible python environment
    bis["locals"]  = lambda: locs
    bis["globals"] = lambda: globs
    globs["__builtins__"] = bis
    globs["__name__"] = "SUBENV"
    globs["__doc__"] = sourceCode

    # Determine how the __builtins__ dictionary should be accessed
    if type(__builtins__) is dict:
        bi_dict = __builtins__
    else:
        bi_dict = __builtins__.__dict__

    # Include the safe symbols
    for k in SAFE_SYMBOLS:
        
        # try from current locals
        try:
          locs[k] = locals()[k]
          continue
        except KeyError:
          pass
        
        # Try from globals
        try:
          globs[k] = globals()[k]
          continue
        except KeyError:
          pass
        
        # Try from builtins
        try:
          bis[k] = bi_dict[k]
        except KeyError:
          # Symbol not available anywhere: silently ignored
          pass

    # Include the symbols added by the caller, in the globals dictionary
    globs.update(additional_symbols)

    # Finally execute the Function statement:
    eval(byteCode, globs, locs)
    
    # As a result, the function is defined as the item funcName
    # in the locals dictionary
    fct = locs[funcName]
    # Attach the function to the globals so that it can be recursive
    del locs[funcName]
    globs[funcName] = fct
    
    # Attach the actual source code to the docstring
    fct.__doc__ = sourceCode
    
    # return the compiled function object
    return fct


class Tpl:
    
    def multisplit(tpl, reps, as_array=False):
        a = [ [1, tpl] ]
        reps = enumerate(reps) if as_array else reps.items()
        for r,s in reps:
        
            c = [ ] 
            sr = s if as_array else r
            s = [0, s]
            for ai in a:
            
                if 1 == ai[ 0 ]:
                
                    b = ai[ 1 ].split( sr )
                    bl = len(b)
                    c.append( [1, b[0]] )
                    if bl > 1:
                        for bj in b[1:]:
                        
                            c.append( s )
                            c.append( [1, bj] )
                        
                else:
                
                    c.append( ai )
                
            
            a = c
        return a

    def multisplit_re( tpl, rex ):
        a = [ ]
        i = 0
        m = rex.search(tpl, i)
        while m:
            a.append([1, tpl[i:m.start()]])
            try:
                mg = m.group(1)
            except:
                mg = m.group(0)
            is_numeric = False
            try:
                mn = int(mg,10)
                is_numeric = False if math.isnan(mn) else True
            except ValueError:
                is_numeric = False
            a.append([0, mn if is_numeric else mg])
            i = m.end()
            m = rex.search(tpl, i)
        a.append([1, tpl[i:]])
        return a
    
    def arg(key=None, argslen=None):
        out = 'args'
        
        if None != key:
        
            if isinstance(key,str):
                key = key.split('.') if len(key) else []
            else: 
                key = [key]
            #givenArgsLen = bool(None !=argslen and isinstance(argslen,str))
            
            for k in key:
                is_numeric = False
                try:
                    kn = int(k,10) if isinstance(k,str) else k
                    is_numeric = False if math.isnan(kn) else True
                except ValueError:
                    is_numeric = False
                if is_numeric:
                    out += '[' + str(kn) + ']';
                else:
                    out += '["' + str(k) + '"]';
                
        return out

    def compile(tpl, raw=False):
        global NEWLINE
        global SQUOTE
        
        if True == raw:
        
            out = 'return ('
            for tpli in tpl:
            
                notIsSub = tpli[ 0 ] 
                s = tpli[ 1 ]
                out += s if notIsSub else Tpl.arg(s)
            
            out += ')'
            
        else:
        
            out = 'return ('
            for tpli in tpl:
            
                notIsSub = tpli[ 0 ]
                s = tpli[ 1 ]
                if notIsSub: out += "'" + re.sub(NEWLINE, "' + \"\\n\" + '", re.sub(SQUOTE, "\\'", s)) + "'"
                else: out += " + str(" + Tpl.arg(s,"argslen") + ") + "
            
            out += ')'
        
        return createFunction('args', "    " + out)

    
    defaultArgs = re.compile(r'\$(-?[0-9]+)')
    
    def __init__(self, tpl='', replacements=None, compiled=False):
        global T_REGEXP
        
        self.id = None
        self.tpl = None
        self._renderer = None
        
        if not replacements: replacements = Tpl.defaultArgs
        self.tpl = Tpl.multisplit_re( tpl, replacements ) if isinstance(replacements, T_REGEXP) else Tpl.multisplit( tpl, replacements )
        if compiled is True: self._renderer = Tpl.compile( self.tpl )

    def __del__(self):
        self.dispose()
        
    def dispose(self):
        self.id = None
        self.tpl = None
        self._renderer = None
        return self
    
    def render(self, args=None):
        if None == args: args = [ ]
        
        if self._renderer: return self._renderer( args )
        
        out = ''
        
        for tpli in self.tpl:
        
            notIsSub = tpli[ 0 ] 
            s = tpli[ 1 ]
            out += s if notIsSub else str(args[ s ])
        
        return out

class Dialect:
    """
    Dialect for Python,
    https://github.com/foo123/Dialect
    """
    
    VERSION = '0.1'
    
    TPL_RE = re.compile(r'/\$\(([^\)]+)\)')
    Tpl = Tpl

    dialect = {
        'mysql'            : {
            'quote'        : [ "'", '`' ]
            ,'clauses'      : {
            # https://dev.mysql.com/doc/refman/5.0/en/select.html, https://dev.mysql.com/doc/refman/5.0/en/join.html, https://dev.mysql.com/doc/refman/5.5/en/expressions.html
            'select'  : ['select','from','join','where','group','having','order','limit']
            # https://dev.mysql.com/doc/refman/5.0/en/insert.html
            ,'insert'  : ['insert','values']
            # https://dev.mysql.com/doc/refman/5.0/en/update.html
            ,'update'  : ['update','set','where','order','limit']
            # https://dev.mysql.com/doc/refman/5.0/en/delete.html
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
            ,'where_'   : '$(where) $(boolean_connective) $(conditions)'
            ,'group'    : 'GROUP BY $(field) $(dir)'
            ,'group_'   : '$(group),$(field) $(dir)'
            ,'having'   : 'HAVING $(conditions)'
            ,'having_'  : '$(having) $(boolean_connective) $(conditions)'
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
    }
    
    def __init__( self, type='mysql' ):
        self.db = None
        self.prefix = '';
        self.escdb = None
        self.clause = None
        self.state = None
        self.clauses = Dialect.dialect[ type ][ 'clauses' ]
        self.tpl = Dialect.dialect[ type ][ 'tpl' ]
        self.q = Dialect.dialect[ type ][ 'quote' ][ 0 ]
        self.qn = Dialect.dialect[ type ][ 'quote' ][ 1 ]
        self._views = { }
    
    def __del__( self ):
        self.dispose()
        
    def dispose( self ):
        self.db = None
        self.prefix = None
        self.escdb = None
        self.clause = None
        self.state = None
        self.clauses = None
        self.tpl = None
        self.q = None
        self.qn = None
        self._views = None
        return self
    
	def __str__( self ):
        sql = self.sql( )
        return sql if sql else ''
    
    def driver( self, db ):
        self.db = db if db else None
        return self
    
    def table_prefix( self, prefix ):
        self.prefix = prefix if prefix else ''
        return self
    
    def escape( self, escdb ):
        self.escdb = escdb if escdb and callable(escdb) else None
        return self
    
    def reset( self, clause ):
        self.clause = clause
        self.state = { }
        clauses = self.clauses[ self.clause ]
        for clause in clauses:
            if (clause in self.tpl) and not isinstance(self.tpl[ clause ], Tpl):
                self.tpl[ clause ] = Tpl( self.tpl[ clause ], Dialect.TPL_RE )
            
            # continuation clause if exists, ..
            c = clause + '_';
            if (c in self.tpl) and not isinstance(self.tpl[ c ], Tpl):
                self.tpl[ c ] = new Tpl( self.tpl[ c ], Dialect.TPL_RE )
        return self
    
    def clear( self ):
        self.clause = None
        self.state = None
        return self
    
    def sql( self ):
        query = None
        if self.clause and self.state and (self.clause in self.clauses):
            query = [ ]
            clauses = self.clauses[ self.clause ]
            for clause in clauses:
                if clause in self.state:
                    query.append( self.state[ clause ] )
            query = "\n".join(query)
        self.clear( )
        return query
    
    def prepare( self, query, args, left='%', right='%' ):
        if query and args:
            # custom delimiters
            left = re.escape( left )
            right = re.escape( right )
            
            # custom prepared parameter format
            pattern = RE(left + '(ad|as|af|f|l|r|d|s):([0-9a-zA-Z_]+)' + right)
            prepared = ''
            while query.length && (m = query.match( pattern )):
                pos = m.index;
                len = m[0].length;
                param = m[2];
                if args[HAS](param):
                    type = m[1];
                    switch( type )
                    {
                        # array of references, e.g fields
                        case 'af': param = self.ref( array(args[param]) ).join(','); break;
                        # array of integers param
                        case 'ad': param = '(' + self.intval( array(args[param]) ).join(',') + ')'; break;
                        # array of strings param
                        case 'as': param = '(' + self.quote( array(args[param]) ).join(',') + ')'; break;
                        # reference, e.g field
                        case 'f': param = self.ref( args[param] ); break;
                        # like param
                        case 'l': param = self.like( args[param] ); break;
                        # raw param
                        case 'r': param = args[param]; break;
                        # integer param
                        case 'd': param = self.intval( args[param] ); break;
                        # string param
                        case 's': default: param = self.quote( args[param] ); break;
                    }
                    prepared += query.slice(0, pos) + param;
                else:
                    prepared += query.slice(0, pos) + self.quote('');
                query = query.slice( pos+len );
            if len(query): prepared += query
            return prepared

        return query
    
    def make_view( self, view ):
        if view and self.clause:
            self._views[ view ] = {'clause':self.clause, 'state':self.state}
            self.clear( )
        return self
    
    def clear_view( self, view ):
        if view and (view in self._views):
            del self._views[ view ]
        return self
    
    def select( self, fields='*', format=True ):
        self.reset('select')
        format = False is not format
        if not fields or not len(fields) or '*' == fields: fields = self.quote_name('*')
        elif format: fields = ','.join( self.ref( list( fields ) ) )
        else: fields = ','.join( list( fields ) )
        self.state['select'] = self.tpl['select'].render( { 'fields':fields } )
        return self
    
    def insert( self, tables, fields, format=True ):
        self.reset('insert');
        format = False is not format
        maybe_view = is_array( tables ) ? tables[0] : tables
        if ( self._views[HAS]( maybe_view ) && self.clause === self._views[ maybe_view ].clause )
        {
            # using custom 'soft' view
            self.state = self.defaults( self.state, self._views[ maybe_view ].state, true );
        }
        else
        {
            if ( format )
            {
                tables = self.ref( array( tables ) ).join(',');
                fields = self.ref( array( fields ) ).join(',');
            }
            else
            {
                tables = array( tables ).join(',');
                fields = array( fields ).join(',');
            }
            self.state.insert = self.tpl.insert.render( { tables:tables, fields:fields } );
        }
        return self
    
    def values( self, values ):
        var self = this, count, insert_values, vals, i, val, j, l, vs;
        if ( empty(values) ) return self;
        # array of arrays
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
        return self
    
    def update( self, tables, format=True ):
        var self = this, maybe_view;
        self.reset('update')
        format = false !== format;
        maybe_view = is_array( tables ) ? tables[0] : tables;
        if ( self._views[HAS]( maybe_view ) && self.clause === self._views[ maybe_view ].clause )
        {
            # using custom 'soft' view
            self.state = self.defaults( self.state, self._views[ maybe_view ].state, true );
        }
        else
        {
            if ( format ) tables = self.ref( array( tables ) ).join(',');
            else tables = array( tables ).join(',');
            self.state.update = self.tpl.update.render( { tables:tables } );
        }
        return self
    
    def set( self, fields_values ):
        var self = this, set_values, field, value;
        if ( empty(fields_values) ) return self;
        set_values = [];
        for (field in fields_values)
        {
            if ( !fields_values[HAS](field) ) continue;
            value = fields_values[field];
            field = self.ref( field );
            
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
        return self
    
    def delete( self ):
        self.reset('delete');
        self.state['delete'] = self.tpl['delete'].render( {} )
        return self
    
    def from( self, tables, format ):
        if ( empty(tables) ) return self;
        format = false !== format;
        maybe_view = is_array( tables ) ? tables[0] : tables;
        if ( self._views[HAS]( maybe_view ) && self.clause === self._views[ maybe_view ].clause )
        {
            // using custom 'soft' view
            self.state = self.defaults( self.state, self._views[ maybe_view ].state, true );
        }
        else
        {
            if ( format ) tables = self.ref( array( tables ) ).join(',');
            else tables = array( tables ).join(',');
            if ( self.state.from ) self.state.from = self.tpl.from_.render( { from:self.state.from, tables:tables } );
            else self.state.from = self.tpl.from.render( { tables:tables } );
        }
        return self
    
    def join( self, table, on_cond=None, join_type='' ):
        var self = this, join_clause, field, cond;
        table = self.ref( table );
        if ( empty(on_cond) )
        {
            join_clause = table;
        }
        else
        {
            if ( is_string(on_cond) )
            {
                on_cond = '(' + self.ref( on_cond.split('=') ).join( '=' ) + ')';
            }
            else
            {
                for (field in on_cond)
                {
                    if ( !on_cond[HAS](field) ) continue;
                    cond = on_cond[ field ];
                    if ( !is_obj(cond) ) on_cond[field] = {'eq':cond,'type':'field'};
                }
                on_cond = self.conditions( on_cond );
            }
            join_clause = table + " ON " + on_cond;
        }
        join_type = empty(join_type) ? "" : (join_type.toUpperCase() + " ");
        if ( self.state.join ) self.state.join = self.tpl.join_.render( { join:self.state.join, join_clause:join_clause, join_type:join_type } );
        else self.state.join = self.tpl.join.render( { join_clause:join_clause, join_type:join_type } );
        return self
    
    def where( self, conditions, boolean_connective="and" ):
        var self = this;
        if ( empty(conditions) ) return self;
        boolean_connective = boolean_connective ? boolean_connective.toUpperCase() : "AND";
        if ( "OR" !== boolean_connective ) boolean_connective = "AND";
        conditions = self.conditions( conditions );
        if ( self.state.where ) self.state.where = self.tpl.where_.render( { where:self.state.where, boolean_connective:boolean_connective, conditions:conditions } );
        else self.state.where = self.tpl.where.render( { boolean_connective:boolean_connective, conditions:conditions } );
        return self
    
    def group( self, field, dir="asc" ):
        dir = dir ? dir.toUpperCase() : "ASC";
        if ( "DESC" !== dir ) dir = "ASC";
        field = self.ref( field );
        if ( self.state.group ) self.state.group = self.tpl.group_.render( { group:self.state.group, field:field, dir:dir } );
        else self.state.group = self.tpl.group.render( { field:field, dir:dir } );
        return self
    
    def having( self, conditions, boolean_connective="and" ):
        if ( empty(conditions) ) return self;
        boolean_connective = boolean_connective ? boolean_connective.toUpperCase() : "AND";
        if ( "OR" !== boolean_connective ) boolean_connective = "AND";
        conditions = self.conditions( conditions );
        if ( self.state.having ) self.state.having = self.tpl.having_.render( { having:self.state.having, boolean_connective:boolean_connective, conditions:conditions } );
        else self.state.having = self.tpl.having.render( { boolean_connective:boolean_connective, conditions:conditions } );
        return self
    
    def order( self, field, dir="asc" ):
        dir = dir ? dir.toUpperCase() : "ASC";
        if ( "DESC" !== dir ) dir = "ASC";
        field = self.ref( field );
        if ( self.state.order ) self.state.order = self.tpl.order_.render( { order:self.state.order, field:field, dir:dir } );
        else self.state.order = self.tpl.order.render( { field:field, dir:dir } );
        return self
    
    def limit( self, count, offset=0 ):
        var self = this;
        count = parseInt(count,10); offset = parseInt(offset||0,10);
        self.state.limit = self.tpl.limit.render( { offset:offset, count:count } );
        return self
    
    def page( self, page, perpage ):
        var self = this;
        page = parseInt(page,10); perpage = parseInt(perpage,10);
        return self.limit( perpage, page*perpage )
    
    def join_conditions( self, join, conditions ):
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
        return self
    
    def conditions( self, conditions ):
        var self = this, condquery, conds, field, value, op, type, v;
        if ( empty(conditions) ) return '';
        if ( is_string(conditions) ) return conditions;
        
        condquery = '';
        conds = [];
        
        for ( field in conditions)
        {
            if ( !conditions[HAS](field) ) continue;
            
            value = conditions[field];
            field = self.ref( field );
            
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
                        # raw, do nothing
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
                        # raw, do nothing
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
                        # raw, do nothing
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
                        # raw, do nothing
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
                        # raw, do nothing
                    }
                    else if ( 'integer' === type || is_int(v) )
                    {
                        v = self.intval( v );
                    }
                    else if ( 'field' === type )
                    {
                        v = self.ref( v );
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
                        # raw, do nothing
                    }
                    else if ( 'integer' === type || is_int(v) )
                    {
                        v = self.intval( v );
                    }
                    else if ( 'field' === type )
                    {
                        v = self.ref( v );
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
                        # raw, do nothing
                    }
                    else if ( 'integer' === type || is_int(v) )
                    {
                        v = self.intval( v );
                    }
                    else if ( 'field' === type )
                    {
                        v = self.ref( v );
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
                        # raw, do nothing
                    }
                    else if ( 'integer' === type || is_int(v) )
                    {
                        v = self.intval( v );
                    }
                    else if ( 'field' === type )
                    {
                        v = self.ref( v );
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
        return condquery
    
    def defaults( self, data, defaults, overwrite=False ):
        overwrite = True is overwrite
        for (k in defaults)
        {
            if ( !defaults[HAS](k) ) continue;
            v = defaults[ k ];
            if ( overwrite || !data[HAS](k) )
                data[ k ] = v;
        }
        return data
    
    def filter( self, data, filter, positive=True ):
        var filtered, i, l, field;
        positive = False is not positive
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
    
    def tbl( self, table ):
        var self = this, prefix = self.prefix;
        if ( is_array( table ) )
            return table.map(function( table ){return prefix+table;});
        return prefix+table
    
    def fld( self, field ):
        var self = this;
        if ( is_array( field ) )
            return field.map(function( field ){return field.split('.').pop( );});
        return field.split('.').pop( )
    
    def ref( self, refs ):
        var self = this, i, l, ref, j, m;
        if ( is_array(refs) ) return refs.map(function( ref ){ return self.ref( ref ); });
        refs = refs.split( ',' ).map( trim );
        for (i=0,l=refs.length; i<l; i++)
        {
            ref = refs[ i ].split( 'AS' ).map( trim );
            for (j=0,m=ref.length; j<m; j++)
            {
                ref[ j ] = self.quote_name( ref[ j ].split( '.' ) ).join( '.' );
            }
            refs[ i ] = ref.join( ' AS ' );
        }
        return refs.join( ',' );
    
    def intval( self, v ):
        var self = this;
        if ( is_array( v ) )
            return v.map(function( v ){return parseInt( v, 10 );});
        return parseInt( v, 10 )
    
    def quote_name( self, f ):
        var self = this, qn = self.qn;
        if ( is_array( f ) )
            return f.map(function( f ){return qn + f + qn;});
        return '*' !== f ? qn + f + qn : f
    
    def quote( self, v ):
        var self = this, q = self.q;
        if ( is_array( v ) )
            return v.map(function( v ){return q + self.esc( v ) + q;});
        return q + self.esc( v ) + q
    
    def esc( self, v ):
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
            # simple ecsaping using addslashes
            # '"\ and NUL (the NULL byte).
            return addslashes( v );
    
    def esc_like( self, v ):
        var self = this;
        if ( is_array( v ) )
            return v.map(function( v ){return addcslashes( v, '_%\\' );});
        return addcslashes( v, '_%\\' )
    
    def like( self, v ):
        var self = this, q = self.q;
        if ( is_array( v ) )
            return v.map(function( v ){return q + '%' + self.esc( self.esc_like( v ) ) + '%' + q;});
        return q + '%' + self.esc( self.esc_like( v ) ) + '%' + q
    
    def multi_like( self, f, v, doTrim=True ):
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
        return ORs.join(' OR ')
    
    def year( self, field ):
        if ( !(self.tpl.year instanceof Tpl) ) self.tpl.year = new Tpl( self.tpl.year, Dialect.TPL_RE );
        return self.tpl.year.render( { field:field } )
    
    def month( self, field ):
        if ( !(self.tpl.month instanceof Tpl) ) self.tpl.month = new Tpl( self.tpl.month, Dialect.TPL_RE );
        return self.tpl.month.render( { field:field } )
    
    def day( self, field ):
        if ( !(self.tpl.day instanceof Tpl) ) self.tpl.day = new Tpl( self.tpl.day, Dialect.TPL_RE );
        return self.tpl.day.render( { field:field } )
    
    def hour( self, field ):
        if ( !(self.tpl.hour instanceof Tpl) ) self.tpl.hour = new Tpl( self.tpl.hour, Dialect.TPL_RE );
        return self.tpl.hour.render( { field:field } )
    
    def minute( self, field ):
        if ( !(self.tpl.minute instanceof Tpl) ) self.tpl.minute = new Tpl( self.tpl.minute, Dialect.TPL_RE );
        return self.tpl.minute.render( { field:field } )
    
    def second( self, field ):
        if ( !(self.tpl.second instanceof Tpl) ) self.tpl.second = new Tpl( self.tpl.second, Dialect.TPL_RE );
        return self.tpl.second.render( { field:field } )
        

__all__ = ['Dialect']

