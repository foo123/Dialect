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
import re, math, copy

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

def is_int( v ):
    return isinstance(v, int)

def is_string( v ):
    return isinstance(v, str)

def is_obj( v ):
    return isinstance(v, dict)

def is_array( v ):
    return isinstance(v, (list,tuple))
    
def array( v ):
    return v if isinstance(v, list) else [v]

def empty( v ):
    return v == None or (isinstance(v, (list,str,dict)) and 0 == len(v))

def addslashes( s ):
    esc = ["\\",'"',"'","\0"]
    s2 = ''
    for ch in s: s2 += '\\'+ch if ch in esc else ch
    return s2

def addslashes_like( s ):
    esc = ["_","%","\\"]
    s2 = ''
    for ch in s: s2 += '\\'+ch if ch in esc else ch
    return s2


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
    
    TPL_RE = re.compile(r'\$\(([^\)]+)\)')
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
                self.tpl[ c ] = Tpl( self.tpl[ c ], Dialect.TPL_RE )
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
            left = re.escape( left ) if left else '%'
            right = re.escape( right ) if right else '%'
            
            # custom prepared parameter format
            pattern = re.compile(left + '(ad|as|af|f|l|r|d|s):([0-9a-zA-Z_]+)' + right)
            prepared = ''
            m = pattern.search( query )
            while m:
                pos = m.start(0)
                le = len(m.group(0))
                param = m.group(2)
                if param in args:
                    type = m.group(1)
                    
                    # array of references, e.g fields
                    if 'af'==type: param = ','.join(self.ref( array(args[param]) ))
                    # array of integers param
                    elif 'ad'==type: param = '(' + ','.join(self.intval2str( array(args[param]) )) + ')'
                    # array of strings param
                    elif 'as'==type: param = '(' + ','.join(self.quote( array(args[param]) )) + ')'
                    # reference, e.g field
                    elif 'f'==type: param = self.ref( args[param] )
                    # like param
                    elif 'l'==type: param = self.like( args[param] )
                    # raw param
                    elif 'r'==type: param = args[param]
                    # integer param
                    elif 'd'==type: param = self.intval2str( args[param] )
                    # string param
                    #elif 's'==type: 
                    else: param = self.quote( args[param] )
                    
                    prepared += query[0:pos] + param
                else:
                    prepared += query[0:pos] + self.quote('')
                query = query[pos+le:]
                m = pattern.search( query )
            
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
        format = format is not False
        if not fields or not len(fields) or '*' == fields: fields = self.quote_name('*')
        elif format: fields = ','.join( self.ref( array( fields ) ) )
        else: fields = ','.join( array( fields ) )
        self.state['select'] = self.tpl['select'].render( { 'fields':fields } )
        return self
    
    def insert( self, tables, fields, format=True ):
        self.reset('insert');
        format = format is not False
        maybe_view = tables[0] if is_array( tables ) else tables
        if (maybe_view in self._views) and self.clause == self._views[ maybe_view ]['clause']:
            # using custom 'soft' view
            self.state = self.defaults( self.state, self._views[ maybe_view ]['state'], True )
        else:
            if format:
                tables = ','.join( self.ref( array( tables ) ) )
                fields = ','.join( self.ref( array( fields ) ) )
            else:
                tables = ','.join( array( tables ) )
                fields = ','.join( array( fields ) )
            self.state['insert'] = self.tpl['insert'].render( { 'tables':tables, 'fields':fields } )
        return self
    
    def values( self, values ):
        if empty(values): return self
        # array of arrays
        if not is_array(values) or not is_array(values[0]): values = [values]
        insert_values = []
        for vs in values:
            vs = array( vs )
            if len(vs):
                vals = []
                for val in vs:
                    if is_obj( val ):
                        if 'integer' in val:
                            vals.append( self.intval2str( val['integer'] ) )
                        elif 'raw' in val:
                            vals.append( val['raw'] )
                        elif 'string' in val:
                            vals.append( self.quote( val['string'] ) )
                    else:
                        vals.append( str(val) if is_int(val) else self.quote( val ) )
                insert_values.append( '(' + ','.join(vals) + ')' )
        insert_values = ','.join(insert_values)
        if 'values' in self.state: self.state['values'] = self.tpl['values_'].render( { 'values':self.state['values'], 'values_values':insert_values } )
        else: self.state['values'] = self.tpl['values'].render( { 'values_values':insert_values } )
        return self
    
    def update( self, tables, format=True ):
        self.reset('update')
        format = format is not False
        maybe_view = tables[0] if is_array( tables ) else tables
        if (maybe_view in self._views) and self.clause == self._views[ maybe_view ]['clause']:
            # using custom 'soft' view
            self.state = self.defaults( self.state, self._views[ maybe_view ]['state'], True )
        else:
            if format: tables = ','.join(self.ref( array( tables ) ))
            else: tables = array( tables ).join(',');
            self.state['update'] = self.tpl['update'].render( { 'tables':tables } )
        return self
    
    def set( self, fields_values ):
        if empty(fields_values): return self
        set_values = []
        for field in fields_values:
            value = fields_values[field]
            field = self.ref( field )
            
            if is_obj(value):
                if 'integer' in value:
                    set_values.append( field + " = " + self.intval2str(value['integer']) )
                elif 'raw' in value:
                    set_values.append( field + " = " + value['raw'] )
                elif 'string' in value:
                    set_values.append( field + " = " + self.quote(value['string']) )
                elif 'increment' in value:
                    set_values.append( field + " = " + field + " + " + self.intval2str(value['increment']) )
                elif 'decrement' in value:
                    set_values.append( field + " = " + field + " - " + self.intval2str(value['increment']) )
            else:
                set_values.append( field + " = " + (str(value) if is_int(value) else self.quote(value)) )
        set_values = ','.join(set_values)
        if 'set' in self.state: self.state['set'] = self.tpl['set_'].render( { 'set':self.state['set'], 'set_values':set_values } )
        else: self.state['set'] = self.tpl['set'].render( { 'set_values':set_values } )
        return self
    
    def delete( self ):
        self.reset('delete')
        self.state['delete'] = self.tpl['delete'].render( {} )
        return self
    
    def fromTbl( self, tables, format=True ):
        if empty(tables): return self
        format = format is not False
        maybe_view = tables[0] if is_array( tables ) else tables
        if (maybe_view in self._views) and self.clause == self._views[ maybe_view ]['clause']:
            # using custom 'soft' view
            self.state = self.defaults( self.state, self._views[ maybe_view ]['state'], True )
        else:
            if format: tables = ','.join(self.ref( array( tables ) ))
            else: tables = ','.join(array( tables ))
            if 'from' in self.state: self.state['from'] = self.tpl['from_'].render( { 'from':self.state['from'], 'tables':tables } )
            else: self.state['from'] = self.tpl['from'].render( { 'tables':tables } )
        return self
    
    def join( self, table, on_cond=None, join_type='' ):
        table = self.ref( table )
        if empty(on_cond):
            join_clause = table
        else:
            if is_string(on_cond):
                on_cond = '(' + '='.join(self.ref( on_cond.split('=') )) + ')'
            else:
                for field in on_cond:
                    cond = on_cond[ field ]
                    if not is_obj(cond): on_cond[field] = {'eq':cond,'type':'field'}
                on_cond = self.conditions( on_cond )
            join_clause = table + " ON " + on_cond
        join_type = "" if empty(join_type) else (join_type.upper() + " ")
        if 'join' in self.state: self.state['join'] = self.tpl['join_'].render( { 'join':self.state['join'], 'join_clause':join_clause, 'join_type':join_type } )
        else: self.state['join'] = self.tpl['join'].render( { 'join_clause':join_clause, 'join_type':join_type } )
        return self
    
    def where( self, conditions, boolean_connective="and" ):
        if empty(conditions): return self
        boolean_connective = boolean_connective.upper() if boolean_connective else "AND"
        if "OR" != boolean_connective: boolean_connective = "AND"
        conditions = self.conditions( conditions )
        if 'where' in self.state: self.state['where'] = self.tpl['where_'].render( { 'where':self.state['where'], 'boolean_connective':boolean_connective, 'conditions':conditions } )
        else: self.state['where'] = self.tpl['where'].render( { 'boolean_connective':boolean_connective, 'conditions':conditions } )
        return self
    
    def group( self, field, dir="asc" ):
        dir = dir.upper() if dir else "ASC"
        if "DESC" != dir: dir = "ASC"
        field = self.ref( field )
        if 'group' in self.state: self.state['group'] = self.tpl['group_'].render( { 'group':self.state['group'], 'field':field, 'dir':dir } )
        else: self.state['group'] = self.tpl['group'].render( { 'field':field, 'dir':dir } )
        return self
    
    def having( self, conditions, boolean_connective="and" ):
        if empty(conditions): return self
        boolean_connective = boolean_connective.upper() if boolean_connective else "AND"
        if "OR" != boolean_connective: boolean_connective = "AND"
        conditions = self.conditions( conditions )
        if 'having' in self.state: self.state['having'] = self.tpl['having_'].render( { 'having':self.state['having'], 'boolean_connective':boolean_connective, 'conditions':conditions } )
        else: self.state['having'] = self.tpl['having'].render( { 'boolean_connective':boolean_connective, 'conditions':conditions } )
        return self
    
    def order( self, field, dir="asc" ):
        dir = dir.upper() if dir else "ASC"
        if "DESC" != dir: dir = "ASC"
        field = self.ref( field )
        if 'order' in self.state: self.state['order'] = self.tpl['order_'].render( { 'order':self.state['order'], 'field':field, 'dir':dir } )
        else: self.state['order'] = self.tpl['order'].render( { 'field':field, 'dir':dir } )
        return self
    
    def limit( self, count, offset=0 ):
        count = int(count,10) if is_string(count) else count
        offset = int(offset,10) if is_string(offset) else offset
        self.state['limit'] = self.tpl['limit'].render( { 'offset':offset, 'count':count } )
        return self
    
    def page( self, page, perpage ):
        page = int(page,10) if is_string(page) else page
        perpage = int(perpage,10) if is_string(perpage) else perpage
        return self.limit( perpage, page*perpage )
    
    def join_conditions( self, join, conditions ):
        j = 0
        conditions_copied = copy.copy(conditions)
        for field in conditions_copied:
            
            field_raw = self.fld( field )
            if field_raw in join:
                cond = conditions[ field ]
                main_table = join[field_raw]['table']
                main_id = join[field_raw]['id']
                join_table = join[field_raw]['join']
                join_id = join[field_raw]['join_id']
                
                j += 1
                join_alias = join_table+str(j)
                
                where = { }
                if ('key' in join[field_raw]) and field_raw != join[field_raw]['key']:
                    join_key = join[field_raw]['key']
                    where[join_alias+'.'+join_key] = field_raw
                else:
                    join_key = field_raw
                if 'value' in join[field_raw]:
                    join_value = join[field_raw]['value']
                    where[join_alias+'.'+join_value] = cond
                else:
                    join_value = join_key
                    where[join_alias+'.'+join_value] = cond
                self.join(
                    join_table+" AS "+join_alias, 
                    main_table+'.'+main_id+'='+join_alias+'.'+join_id, 
                    "inner"
                ).where( where )
                
                del conditions[field]
        return self
    
    def conditions( self, conditions ):
        if empty(conditions): return ''
        if is_string(conditions): return conditions
        
        condquery = ''
        conds = []
        
        for field in conditions:
            
            value = conditions[field]
            field = self.ref( field )
            
            if is_obj( value ):
                type = value['type'] if 'type' in value else 'string'
                
                if 'multi_like' in value:
                    conds.append( self.multi_like(field, value['multi_like']) )
                elif 'like' in value:
                    conds.append( field + " LIKE " + (str(value['like']) if 'raw' == type else self.like(value['like'])) )
                elif 'not_like' in value:
                    conds.append( field + " NOT LIKE " + (str(value['not_like']) if 'raw' == type else self.like(value['not_like'])) )
                elif 'in' in value:
                    v = array( value['in'] )
                    
                    if 'raw' == type:
                        # raw, do nothing
                        pass
                    elif 'integer' == type or is_int(v[0]):
                        v = self.intval2str( v );
                    else:
                        v = self.quote( v )
                    conds.append( field + " IN (" + ','.join(v) + ")" )
                elif 'not_in' in value:
                    v = array( value['not_in'] )
                    
                    if 'raw' == type:
                        # raw, do nothing
                        pass
                    elif 'integer' == type or is_int(v[0]):
                        v = self.intval2str( v );
                    else:
                        v = self.quote( v )
                    conds.append( field + " NOT IN (" + ','.join(v) + ")" )
                elif 'between' in value:
                    v = array( value['between'] )
                    
                    if 'raw' == type:
                        # raw, do nothing
                        pass
                    elif 'integer' == type or (is_int(v[0]) and is_int(v[1])):
                        v = self.intval( v )
                    else:
                        v = self.quote( v )
                    conds.append( field + " BETWEEN " + str(v[0]) + " AND " + str(v[1]) )
                elif 'not_between' in value:
                    v = array( value['not_between'] )
                    
                    if 'raw' == type:
                        # raw, do nothing
                        pass
                    elif 'integer' == type or (is_int(v[0]) and is_int(v[1])):
                        v = self.intval( v )
                    else:
                        v = self.quote( v )
                    conds.append( field + " < " + str(v[0]) + " OR " + field + " > " + str(v[1]) )
                elif ('gt' in value) or ('gte' in value):
                    op = 'gt' if 'gt' in value else "gte"
                    v = value[ op ]
                    
                    if 'raw' == type:
                        # raw, do nothing
                        pass
                    elif 'integer' == type or is_int(v):
                        v = self.intval( v )
                    elif 'field' == type:
                        v = self.ref( v )
                    else:
                        v = self.quote( v )
                    conds.append( field + (" > " if 'gt'==op else " >= ") + str(v) )
                elif ('lt' in value) or ('lte' in value):
                    op = 'lt' if 'lt' in value else "lte"
                    v = value[ op ]
                    
                    if 'raw' == type:
                        # raw, do nothing
                        pass
                    elif 'integer' == type or is_int(v):
                        v = self.intval( v )
                    elif 'field' == type:
                        v = self.ref( v )
                    else:
                        v = self.quote( v )
                    conds.append( field + (" < " if 'lt'==op else " <= ") + str(v) )
                elif ('not_equal' in value) or ('not_eq' in value):
                    op = 'not_equal' if 'not_equal' in value else "not_eq"
                    v = value[ op ]
                    
                    if 'raw' == type:
                        # raw, do nothing
                        pass
                    elif 'integer' == type or is_int(v):
                        v = self.intval( v )
                    elif 'field' == type:
                        v = self.ref( v )
                    else:
                        v = self.quote( v )
                    conds.append( field + " <> " + str(v) )
                elif ('equal' in value) or ('eq' in value):
                    op = 'equal' if 'equal' in value else "eq"
                    v = value[ op ]
                    
                    if 'raw' == type:
                        # raw, do nothing
                        pass
                    elif 'integer' == type or is_int(v):
                        v = self.intval( v )
                    elif 'field' == type:
                        v = self.ref( v )
                    else:
                        v = self.quote( v )
                    conds.append( field + " = " + str(v) )
            else:
                conds.append( field + " = " + (str(value) if is_int(value) else self.quote(value)) )
        
        if len(conds): condquery = '(' + ') AND ('.join(conds) + ')'
        return condquery
    
    def defaults( self, data, defaults, overwrite=False ):
        overwrite = overwrite is True
        for k in defaults:
            v = defaults[ k ]
            if overwrite or not(k in data):
                data[ k ] = v
        return data
    
    def filter( self, data, filter, positive=True ):
        positive = positive is not False
        if positive:
            filtered = { }
            for field in filter:
                if field in data:
                    filtered[field] = data[field]
            return filtered
        else:
            filtered = { }
            for field in data:
                if field not in filter:
                    filtered[field] = data[field]
            return filtered
    
    def tbl( self, table ):
        prefix = self.prefix
        if is_array( table ):
            return list(map(lambda table: prefix+table, table))
        return prefix+table
    
    def fld( self, field ):
        if is_array( field ):
            return list(map(lambda field: field.split('.').pop( ), field))
        return field.split('.').pop( )
    
    def ref( self, refs ):
        if is_array(refs): return list(map(lambda ref: self.ref( ref ), refs))
        refs = list(map(lambda s: s.strip(), refs.split( ',' )))
        for i in range(len(refs)):
            ref = list(map(lambda s: s.strip(), refs[i].split( ' AS ' )))
            for j in range(len(ref)):
                ref[ j ] = '.'.join(self.quote_name( ref[ j ].split( '.' ) ))
            refs[ i ] = ' AS '.join(ref)
        return ','.join(refs)
    
    def intval( self, v ):
        if is_int( v ): return v
        elif is_array( v ): return list(map(lambda v: self.intval( v ), v))
        return int( v, 10 )
    
    def intval2str( self, v ):
        v = self.intval( v )
        if is_int( v ): return str(v)
        elif is_array( v ): return list(map(lambda v: str( v ), v))
        return v
    
    def quote_name( self, f ):
        qn = self.qn
        if is_array( f ):
            return list(map(lambda f: f if '*' == f else qn + f + qn, f))
        return f if '*' == f else qn + f + qn
    
    def quote( self, v ):
        q = self.q
        if is_array( v ):
            return list(map(lambda v: q + self.esc( v ) + q, v))
        return q + self.esc( v ) + q
    
    def esc( self, v ):
        if is_array( v ):
            if self.escdb:
                return list(map(self.escdb, v))
            else:
                return list(map(lambda v: addslashes( str(v) ), v))
        if self.escdb:
            escdb = self.escdb
            return escdb( v )
        else:
            # simple ecsaping using addslashes
            # '"\ and NUL (the NULL byte).
            return addslashes( str(v) )
    
    def esc_like( self, v ):
        if is_array( v ):
            return list(map(lambda v: addslashes_like( str(v) ), v))
        return addslashes_like( str(v) )
    
    def like( self, v ):
        q = self.q
        if is_array( v ):
            return list(map(lambda v: q + '%' + self.esc( self.esc_like( v ) ) + '%' + q, v))
        return q + '%' + self.esc( self.esc_like( v ) ) + '%' + q
    
    def multi_like( self, f, v, doTrim=True ):
        doTrim = doTrim is not False
        like = f + " LIKE "
        ORs = v.split(',')
        if doTrim: ORs = filter(len, list(map(lambda x: x.strip(), ORs)))
        for i in range(len(ORs)):
            ANDs = ORs[i].split('+')
            if doTrim: ANDs = filter(len, list(map(lambda x: x.strip(), ANDs)))
            for j in range(len(ANDs)):
                ANDs[j] = like + self.like( ANDs[j] )
            ORs[i] = '(' + ' AND '.join(ANDs) + ')'
        return ' OR '.join(ORs)
    
    def year( self, field ):
        if not isinstance(self.tpl['year'], Tpl): self.tpl['year'] = Tpl( self.tpl['year'], Dialect.TPL_RE )
        return self.tpl['year'].render( { 'field':field } )
    
    def month( self, field ):
        if not isinstance(self.tpl['month'], Tpl): self.tpl['month'] = Tpl( self.tpl['month'], Dialect.TPL_RE )
        return self.tpl['month'].render( { 'field':field } )
    
    def day( self, field ):
        if not isinstance(self.tpl['day'], Tpl): self.tpl['day'] = Tpl( self.tpl['day'], Dialect.TPL_RE )
        return self.tpl['day'].render( { 'field':field } )
    
    def hour( self, field ):
        if not isinstance(self.tpl['hour'], Tpl): self.tpl['hour'] = Tpl( self.tpl['hour'], Dialect.TPL_RE )
        return self.tpl['hour'].render( { 'field':field } )
    
    def minute( self, field ):
        if not isinstance(self.tpl['minute'], Tpl): self.tpl['minute'] = Tpl( self.tpl['minute'], Dialect.TPL_RE )
        return self.tpl['minute'].render( { 'field':field } )
    
    def second( self, field ):
        if not isinstance(self.tpl['second'], Tpl): self.tpl['second'] = Tpl( self.tpl['second'], Dialect.TPL_RE )
        return self.tpl['second'].render( { 'field':field } )
        

__all__ = ['Dialect']

