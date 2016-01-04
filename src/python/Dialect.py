##
#   Dialect, 
#   a simple and flexible Cross-Platform SQL Builder for PHP, Python, Node/XPCOM/JS, ActionScript
# 
#   @version: 0.4.0
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
NULL_CHAR = chr(0)

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

def addslashes( s, chars=None, esc='\\' ):
    global NULL_CHAR
    s2 = ''
    if chars is None: chars = '\\"\'' + NULL_CHAR
    for c in s:
        s2 += c if c not in chars else ('\\0' if 0 == ord(c) else (esc+c))
    return s2

def defaults( data, defau, overwrite=False ):
    overwrite = overwrite is True
    for k in defau:
        if overwrite or not(k in data):
            data[ k ] = defau[ k ]
    return data

#def filter( data, filt, positive=True ):
#    if positive is not False:
#        filtered = { }
#        for field in filt:
#            if field in data:
#                filtered[field] = data[field]
#        return filtered
#    else:
#        filtered = { }
#        for field in data:
#            if field not in filt:
#                filtered[field] = data[field]
#        return filtered


class Tpl:
    
    def multisplit_gram( tpl ):
        SEPL = '{'
        SEPR = '}'
        default_value = None
        l = len(tpl)
        i = 0
        a = []
        stack = []
        s = ''
        while i < l:
            c = tpl[i]
            i += 1
            if SEPL == c:
                if len(s): a.append([1, s])
                s = ''
                
                if i < l and '?' == tpl[i]:
                    # optional block
                    stack.append( a )
                    a = []
                    i += 1
                # else argument
            elif SEPR == c:
                if len(stack) and '?' == tpl[i-2] and '(' == tpl[i]:
                    s = s[0:-1]
                    # optional block end
                    p = tpl.find(')',i+1)
                    argument = tpl[i+1:p]
                    if '!' == argument[0]:
                        negative = 1
                        argument = argument[1:]
                    else:
                        negative = 0
                    b = a
                    if len(s): b.append([1, s])
                    s = ''
                    a = stack.pop(-1)
                    a.append([-1, argument, negative, b])
                    i = p+1
                else:
                    # argument
                    argument = s
                    s = ''
                    p = argument.find('|')
                    if -1 < p:
                        default_value = argument[p+1:]
                        argument = argument[0:p]
                    else:
                        default_value = None
                    a.append([0, argument, default_value])
            else:
                s += c
        if len(s): a.append([1, s])
        return a

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
        if replacements is True:
            # grammar template
            self.tpl = Tpl.multisplit_gram( tpl )
        else:
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
        stack = []
        tpl = self.tpl
        i = 0
        l = len(tpl)
        while i < l:
            t = tpl[ i ]
            tt = t[ 0 ]
            s = t[ 1 ]
            if 1 == tt:
                out += s
            elif -1 == tt:
                # optional block
                if (0 == t[2] and (s in args)) or (1 == t[2] and (s not in args)):
                    stack.append([tpl, i+1, l])
                    tpl = t[ 3 ]
                    i = 0
                    l = len(tpl)
                    continue
            else:
                # default value if missing
                out += str(t[ 2 ]) if (s not in args) and len(t) > 1 and t[ 2 ] else str(args[ s ])
            i += 1
            if i >= l and len(stack):
                p = stack.pop(-1)
                tpl = p[0]
                i = p[1]
                l = p[2]
        
        return out

class Ref:

    def parse( r, d ):
        r = r.strip( ).split(' AS ')
        col = r[ 0 ].split( '.' )
        tbl = None if len(col) < 2 else col[ 0 ].strip( )
        col = col[ 1 ].strip( ) if tbl else col[ 0 ].strip( )
        col_q = d.quote_name( col )
        if tbl:
            tbl_q = d.quote_name( tbl )
            tbl_col = tbl + '.' + col
            tbl_col_q = tbl_q + '.' + col_q
        else:
            tbl_q = None
            tbl_col = col
            tbl_col_q = col_q
        if len(r) < 2:
            alias = tbl_col
            alias_q = tbl_col_q
            tbl_col_alias = tbl_col
            tbl_col_alias_q = tbl_col_q
        else:
            alias = r[1].strip( )
            alias_q = d.quote_name( alias )
            tbl_col_alias = tbl_col + ' AS ' + alias
            tbl_col_alias_q = tbl_col_q + ' AS ' + alias_q
        return Ref( col, col_q, tbl, tbl_q, alias, alias_q, 
                    tbl_col, tbl_col_q, tbl_col_alias, tbl_col_alias_q )

    def __init__( self, col, col_q, tbl, tbl_q, alias, alias_q, tbl_col, tbl_col_q, tbl_col_alias, tbl_col_alias_q ):
        self.col = col
        self.col_q = col_q
        self.tbl = tbl
        self.tbl_q = tbl_q
        self.alias = alias
        self.alias_q = alias_q
        self.tbl_col = tbl_col
        self.tbl_col_q = tbl_col_q
        self.tbl_col_alias = tbl_col_alias
        self.tbl_col_alias_q = tbl_col_alias_q

    def __del__( self ):
        self.dispose( )
        
    def dispose( self ):
        self.col = None
        self.col_q = None
        self.tbl = None
        self.tbl_q = None
        self.alias = None
        self.alias_q = None
        self.tbl_col = None
        self.tbl_col_q = None
        self.tbl_col_alias = None
        self.tbl_col_alias_q = None
        return self


class Dialect:
    """
    Dialect for Python,
    https://github.com/foo123/Dialect
    """
    
    VERSION = '0.4.0'
    
    TPL_RE = re.compile(r'\$\(([^\)]+)\)')
    Tpl = Tpl
    Ref = Ref

    dialect = {
     'mysql'            : {
        # https://dev.mysql.com/doc/refman/5.0/en/select.html
        # https://dev.mysql.com/doc/refman/5.0/en/join.html
        # https://dev.mysql.com/doc/refman/5.5/en/expressions.html
        # https://dev.mysql.com/doc/refman/5.0/en/insert.html
        # https://dev.mysql.com/doc/refman/5.0/en/update.html
        # https://dev.mysql.com/doc/refman/5.0/en/delete.html
        # http://dev.mysql.com/doc/refman/5.7/en/create-table.html
        # http://dev.mysql.com/doc/refman/5.7/en/drop-table.html
        # http://dev.mysql.com/doc/refman/5.7/en/alter-table.html
         'quotes'       : [ ["'","'","\\'","\\'"], ['`','`'], ['',''] ]
        ,'clauses'      : {
         'create'       : "CREATE TABLE IF NOT EXISTS {create_table}\n({create_defs}){?{create_opts}?}(create_opts)"
        ,'alter'        : "ALTER TABLE {alter_table}\n{alter_defs}{?{alter_opts}?}(alter_opts)"
        ,'drop'         : "DROP TABLE IF EXISTS {drop_tables}"
        ,'select'       : "SELECT {select_columns}\nFROM {from_tables}{?\n{join_clauses}?}(join_clauses){?\nWHERE {where_conditions}?}(where_conditions){?\nGROUP BY {group_conditions}?}(group_conditions){?\nHAVING {having_conditions}?}(having_conditions){?\nORDER BY {order_conditions}?}(order_conditions){?\nLIMIT {offset|0},{count}?}(count)"
        ,'insert'       : "INSERT INTO {insert_tables} ({insert_columns})\nVALUES {values_values}"
        ,'update'       : "UPDATE {update_tables}\nSET {set_values}{?\nWHERE {where_conditions}?}(where_conditions){?\nORDER BY {order_conditions}?}(order_conditions){?\nLIMIT {offset|0},{count}?}(count)"
        ,'delete'       : "DELETE \nFROM {from_tables}{?\nWHERE {where_conditions}?}(where_conditions){?\nORDER BY {order_conditions}?}(order_conditions){?\nLIMIT {offset|0},{count}?}(count)"
        }
    }
    ,'postgre'          : {
        # http://www.postgresql.org/docs/
        # http://www.postgresql.org/docs/9.1/static/sql-createtable.html
        # http://www.postgresql.org/docs/9.1/static/sql-droptable.html
        # http://www.postgresql.org/docs/9.1/static/sql-altertable.html
        # http://www.postgresql.org/docs/8.2/static/sql-syntax-lexical.html
         'quotes'       : [ ["E'","'","''","''"], ['"','"'], ['',''] ]
        ,'clauses'      : {
         'create'       : "CREATE TABLE IF NOT EXISTS {create_table}\n({create_defs}){?{create_opts}?}(create_opts)"
        ,'alter'        : "ALTER TABLE {alter_table}\n{alter_defs}{?{alter_opts}?}(alter_opts)"
        ,'drop'         : "DROP TABLE IF EXISTS {drop_tables}"
        ,'select'       : "SELECT {select_columns}\nFROM {from_tables}{?\n{join_clauses}?}(join_clauses){?\nWHERE {where_conditions}?}(where_conditions){?\nGROUP BY {group_conditions}?}(group_conditions){?\nHAVING {having_conditions}?}(having_conditions){?\nORDER BY {order_conditions}?}(order_conditions){?\nLIMIT {count} OFFSET {offset|0}?}(count)"
        ,'insert'       : "INSERT INTO {insert_tables} ({insert_columns})\nVALUES {values_values}"
        ,'update'       : "UPDATE {update_tables}\nSET {set_values}{?\nWHERE {where_conditions}?}(where_conditions){?\nORDER BY {order_conditions}?}(order_conditions){?\nLIMIT {count} OFFSET {offset|0}?}(count)"
        ,'delete'       : "DELETE \nFROM {from_tables}{?\nWHERE {where_conditions}?}(where_conditions){?\nORDER BY {order_conditions}?}(order_conditions){?\nLIMIT {count} OFFSET {offset|0}?}(count)"
        }
    }
    ,'sqlserver'        : {
        # https://msdn.microsoft.com/en-us/library/ms189499.aspx
        # https://msdn.microsoft.com/en-us/library/ms174335.aspx
        # https://msdn.microsoft.com/en-us/library/ms177523.aspx
        # https://msdn.microsoft.com/en-us/library/ms189835.aspx
        # https://msdn.microsoft.com/en-us/library/ms179859.aspx
        # https://msdn.microsoft.com/en-us/library/ms188385%28v=sql.110%29.aspx
        # https://msdn.microsoft.com/en-us/library/ms174979.aspx
        # https://msdn.microsoft.com/en-us/library/ms173790.aspx
        # https://msdn.microsoft.com/en-us/library/cc879314.aspx
        # http://stackoverflow.com/questions/603724/how-to-implement-limit-with-microsoft-sql-server
        # http://stackoverflow.com/questions/971964/limit-10-20-in-sql-server
         'quotes'       : [ ["'","'","''","''"], ['[',']'], [''," ESCAPE '\\'"] ]
        ,'clauses'      : {
         'create'       : "CREATE TABLE IF NOT EXISTS {create_table}\n({create_defs}){?{create_opts}?}(create_opts)"
        ,'alter'        : "ALTER TABLE {alter_table}\n{alter_defs}{?{alter_opts}?}(alter_opts)"
        ,'drop'         : "DROP TABLE IF EXISTS {drop_tables}"
        ,'select'       : "SELECT {select_columns}\nFROM {from_tables}{?\n{join_clauses}?}(join_clauses){?\nWHERE {where_conditions}?}(where_conditions){?\nGROUP BY {group_conditions}?}(group_conditions){?\nHAVING {having_conditions}?}(having_conditions){?\nORDER BY {order_conditions}{?\nOFFSET {offset|0} ROWS\nFETCH NEXT {count} ROWS ONLY?}(limit)?}(order_conditions){?{?\nORDER BY 1\nOFFSET {offset|0} ROWS\nFETCH NEXT {count} ROWS ONLY?}(limit)?}(!order_conditions)"
        ,'insert'       : "INSERT INTO {insert_tables} ({insert_columns})\nVALUES {values_values}"
        ,'update'       : "UPDATE {update_tables}\nSET {set_values}{?\nWHERE {where_conditions}?}(where_conditions){?\nORDER BY {order_conditions}?}(order_conditions)"
        ,'delete'       : "DELETE \nFROM {from_tables}{?\nWHERE {where_conditions}?}(where_conditions){?\nORDER BY {order_conditions}?}(order_conditions)"
        }
    }
    }
    
    def __init__( self, type='mysql' ):
        self.clau = None
        self.clus = None
        self.tbls = None
        self.cols = None
        self.vews = { }
        self.tpls = { }
        
        self.db = None
        self.escdb = None
        self.p = '';
        
        self.type = type
        self.clauses = Dialect.dialect[ self.type ][ 'clauses' ]
        self.q = Dialect.dialect[ self.type ][ 'quotes' ][ 0 ]
        self.qn = Dialect.dialect[ self.type ][ 'quotes' ][ 1 ]
        self.e = Dialect.dialect[ self.type ][ 'quotes' ][ 2 ] if 1 < len(Dialect.dialect[ self.type ][ 'quotes' ]) else ['','']
    
    def __del__( self ):
        self.dispose()
        
    def dispose( self ):
        self.clau = None
        self.clus = None
        self.tbls = None
        self.cols = None
        self.vews = None
        self.tpls = None
        
        self.db = None
        self.escdb = None
        self.p = None
        
        self.type = None
        self.clauses = None
        self.q = None
        self.qn = None
        self.e = None
        return self

    def __str__( self ):
        sql = self.sql( )
        return sql if sql else ''
    
    def driver( self, *args ):
        if len(args):
            db = args[0]
            self.db = db if db else None
            return self
        return self.db
    
    def escape( self, *args ):
        if len(args):
            escdb = args[0]
            self.escdb = escdb if escdb and callable(escdb) else None
            return self
        return self.escdb
    
    def prefix( self, *args ):
        if len(args):
            prefix = args[0]
            self.p = prefix if prefix else ''
            return self
        return self.p
    
    def reset( self, clause ):
        self.clus = { }
        self.tbls = { }
        self.cols = { }
        self.clau = clause
        if not isinstance(self.clauses[ self.clau ], Tpl):
            self.clauses[ self.clau ] = Tpl( self.clauses[ self.clau ], True )
        return self
    
    def clear( self ):
        self.clau = None
        self.clus = None
        self.tbls = None
        self.cols = None
        return self
    
    def sql( self ):
        query = None
        if self.clau and (self.clau in self.clauses):
            query = self.clauses[ self.clau ].render( self.clus )
        self.clear( )
        return query
    
    def prepare( self, query, args, left=None, right=None ):
        if query and args:
            # custom delimiters
            left = re.escape( left ) if left else '%'
            right = re.escape( right ) if right else '%'
            
            # custom prepared parameter format
            pattern = re.compile(left + '([rlfds]:)?([0-9a-zA-Z_]+)' + right)
            prepared = ''
            m = pattern.search( query )
            while m:
                pos = m.start(0)
                le = len(m.group(0))
                param = m.group(2)
                if param in args:
                    type = m.group(1)[0:-1] if m.group(1) else "s"
                    
                    if 'r'==type: 
                        # raw param
                        if is_array(args[param]):
                            param = ','.join(args[param])
                        else:
                            param = args[param]
                    
                    elif 'l'==type: 
                        # like param
                        param = self.like( args[param] )
                    
                    elif 'f'==type: 
                        if is_array(args[param]):
                            # array of references, e.g fields
                            tmp = array( args[param] )
                            param = Ref.parse( tmp[0], self ).tbl_col_alias_q
                            for i in range(1,len(tmp)): param += ','+Ref.parse( tmp[i], self ).tbl_col_alias_q
                        else:
                            # reference, e.g field
                            param = Ref.parse( args[param], self ).tbl_col_alias_q
                    
                    elif 'd'==type: 
                        if is_array(args[param]):
                            # array of integers param
                            param = ','.join(self.intval2str( array(args[param]) ))
                        else:
                            # integer param
                            param = self.intval2str( args[param] )
                    
                    #elif 's'==type: 
                    else: 
                        if is_array(args[param]):
                            # array of strings param
                            param = ','.join(self.quote( array(args[param]) ))
                        else:
                            # string param
                            param = self.quote( args[param] )
                    
                    prepared += query[0:pos] + param
                else:
                    prepared += query[0:pos] + self.quote('')
                query = query[pos+le:]
                m = pattern.search( query )
            
            if len(query): prepared += query
            return prepared

        return query
    
    def make_view( self, view ):
        if view and self.clau:
            self.vews[ view ] = {
                'clau':self.clau, 
                'clus':self.clus,
                'tbls':self.tbls,
                'cols':self.cols
            }
            self.clear( )
        return self
    
    def clear_view( self, view ):
        if view and (view in self.vews):
            del self.vews[ view ]
        return self
    
    def prepare_tpl( self, tpl, *args ):
                                #, query, left, right
        if tpl:
            argslen = len(args)
            
            if 0 == argslen:
                query = None
                left = None
                right = None
                use_internal_query = True
            elif 1 == argslen:
                query = args[ 0 ]
                left = None
                right = None
                use_internal_query = False
            elif 2 == argslen:
                query = None
                left = args[ 0 ]
                right = args[ 1 ]
                use_internal_query = True
            else: # if 2 < argslen:
                query = args[ 0 ]
                left = args[ 1 ]
                right = args[ 2 ]
                use_internal_query = False
            
            # custom delimiters
            left = re.escape( left ) if left else '%'
            right = re.escape( right ) if right else '%'
            # custom prepared parameter format
            pattern = re.compile(left + '(([rlfds]:)?[0-9a-zA-Z_]+)' + right)
            
            if use_internal_query:
                sql = Tpl( self.sql( ), pattern )
                #self.clear( )
            else:
                sql = Tpl( query, pattern )
            
            types = { }
            # extract parameter types
            for i in range(len(sql.tpl)):
                tpli = sql.tpl[ i ]
                if not tpli[ 0 ]:
                    k = tpli[ 1 ].split(':')
                    if len(k) > 1:
                        types[ k[1] ] = k[0]
                        sql.tpl[ i ][ 1 ] = k[1]
                    else:
                        types[ k[0] ] = "s"
                        sql.tpl[ i ][ 1 ] = k[0]
            
            self.tpls[ tpl ] = {
                'sql':sql, 
                'types':types
            }
        return self
    
    def prepared( self, tpl, args ):
        if tpl and (tpl in self.tpls):
            
            sql = self.tpls[tpl]['sql']
            types = self.tpls[tpl]['types']
            params = { }
            for k in args:
                
                v = args[k]
                type = types[k] if k in types else "s"
                if 'r'==type: 
                    # raw param
                    if is_array(v):
                        params[k] = ','.join(v)
                    else:
                        params[k] = v
                
                elif 'l'==type: 
                    # like param
                    params[k] = self.like( v )
                
                elif 'f'==type: 
                    if is_array(v):
                        # array of references, e.g fields
                        tmp = array( v )
                        params[k] = Ref.parse( tmp[0], self ).tbl_col_alias_q
                        for i in range(1,len(tmp)): params[k] += ','+Ref.parse( tmp[i], self ).tbl_col_alias_q
                    else:
                        # reference, e.g field
                        params[k] = Ref.parse( v, self ).tbl_col_alias_q
                
                elif 'd'==type: 
                    if is_array(v):
                        # array of integers param
                        params[k] = ','.join(self.intval2str( array(v) ))
                    else:
                        # integer param
                        params[k] = self.intval2str( v )
                
                #elif 's'==type: 
                else: 
                    if is_array(v):
                        # array of strings param
                        params[k] = ','.join(self.quote( array(v) ))
                    else:
                        # string param
                        params[k] = self.quote( v )
            
            return sql.render( params )
        return ''
    
    def clear_tpl( self, tpl ):
        if tpl and (tpl in self.tpls):
           self.tpls[ tpl ]['sql'].dispose( )
           del self.tpls[ tpl ]
        return self
    
    def create( self, table, defs, opts=None, format=True ):
        self.reset('create')
        if format is not False:
            table = self.refs( table, self.tbls )[0].tbl_col_q
        self.clus['create_table'] = table
        if 'create_defs' in self.clus and len(self.clus['create_defs']) > 0:
            defs = self.clus['create_defs'] + ',' + defs
        self.clus['create_defs'] = defs
        if opts:
            if 'create_opts' in self.clus and len(self.clus['create_opts']) > 0:
                opts = self.clus['create_opts'] + ',' + opts
            self.clus['create_opts'] = opts
        return self
    
    def alter( self, table, defs, opts=None, format=True ):
        self.reset('alter')
        if format is not False:
            table = self.refs( table, self.tbls )[0].tbl_col_q
        self.clus['alter_table'] = table
        if 'alter_defs' in self.clus and len(self.clus['alter_defs']) > 0:
            defs = self.clus['alter_defs'] + ',' + defs
        self.clus['alter_defs'] = defs
        if opts:
            if 'alter_opts' in self.clus and len(self.clus['alter_opts']) > 0:
                opts = self.clus['alter_opts'] + ',' + opts
            self.clus['alter_opts'] = opts
        return self
    
    def drop( self, tables, format=True ):
        self.reset('drop')
        if not tables or not len(tables) or '*' == tables: 
            tables = '*'
        else:
            if format is not False:
                tbls = self.refs( tables, self.tbls )
                tables = tbls[ 0 ].tbl_col_q
                for i in range(1,len(cols)): tables += ',' + tbls[ i ].tbl_col_q
            else:
                tables = ','.join(array( tables ))
        if 'drop_tables' in self.clus and len(self.clus['drop_tables']) > 0:
            tables = self.clus['drop_tables'] + ',' + tables
        self.clus['drop_tables'] = tables
        return self
    
    def select( self, cols='*', format=True ):
        self.reset('select')
        if not cols or not len(cols) or '*' == cols: 
            columns = '*';
        else:
            if format is not False:
                cols = self.refs( cols, self.cols )
                columns = cols[ 0 ].tbl_col_alias_q
                for i in range(1,len(cols)): columns += ',' + cols[ i ].tbl_col_alias_q
            else:
                columns = ','.join(array( cols ))
        if 'select_columns' in self.clus and len(self.clus['select_columns']) > 0:
            columns = self.clus['select_columns'] + ',' + columns
        self.clus['select_columns'] = columns
        return self
    
    def insert( self, tbls, cols, format=True ):
        self.reset('insert');
        view = tbls[0] if is_array( tbls ) else tbls
        if (view in self.vews) and self.clau == self.vews[ view ]['clau']:
            # using custom 'soft' view
            view = self.vews[ view ]
            self.clus = defaults( self.clus, view['clus'], True )
            self.tbls = defaults( {}, view['tbls'], True )
            self.cols = defaults( {}, view['cols'], True )
        else:
            if format is not False:
                tbls = self.refs( tbls, self.tbls )
                cols = self.refs( cols, self.cols )
                tables = tbls[ 0 ].tbl_col_alias_q 
                columns = cols[ 0 ].tbl_col_q
                for i in range(1,len(tbls)): tables += ',' + tbls[ i ].tbl_col_alias_q
                for i in range(1,len(cols)): columns += ',' + cols[ i ].tbl_col_q
            else:
                tables = ','.join(array( tbls ))
                columns = ','.join(array( cols ))
            if 'insert_tables' in self.clus and len(self.clus['insert_tables']) > 0:
                tables = self.clus['insert_tables'] + ',' + tables
            if 'insert_columns' in self.clus and len(self.clus['insert_columns']) > 0:
                columns = self.clus['insert_columns'] + ',' + columns
            self.clus['insert_tables'] = tables
            self.clus['insert_columns'] = columns
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
        if 'values_values' in self.clus and len(self.clus['values_values']) > 0:
            insert_values = self.clus['values_values'] + ',' + insert_values
        self.clus['values_values'] = insert_values
        return self
    
    def update( self, tbls, format=True ):
        self.reset('update')
        view = tbls[0] if is_array( tbls ) else tbls
        if (view in self.vews) and self.clau == self.vews[ view ]['clau']:
            # using custom 'soft' view
            view = self.vews[ view ]
            self.clus = defaults( self.clus, view['clus'], True )
            self.tbls = defaults( {}, view['tbls'], True )
            self.cols = defaults( {}, view['cols'], True )
        else:
            if format is not False:
                tbls = self.refs( tbls, self.tbls )
                tables = tbls[ 0 ].tbl_col_alias_q 
                for i in range(1,len(tbls)): tables += ',' + tbls[ i ].tbl_col_alias_q
            else:
                tables = ','.join(array( tbls ))
            if 'update_tables' in self.clus and len(self.clus['update_tables']) > 0:
                tables = self.clus['update_tables'] + ',' + tables
            self.clus['update_tables'] = tables
        return self
    
    def set( self, fields_values ):
        if empty(fields_values): return self
        set_values = []
        COLS = self.cols
        for f in fields_values:
            field = self.refs( f, COLS )[0].tbl_col_q
            value = fields_values[f]
            
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
        if 'set_values' in self.clus and len(self.clus['set_values']) > 0:
            set_values = self.clus['set_values'] + ',' + set_values
        self.clus['set_values'] = set_values
        return self
    
    def del_( self ):
        self.reset('delete')
        return self
    
    def from_( self, tbls, format=True ):
        if empty(tbls): return self
        view = tbls[0] if is_array( tbls ) else tbls
        if (view in self.vews) and self.clau == self.vews[ view ]['clau']:
            # using custom 'soft' view
            view = self.vews[ view ]
            self.clus = defaults( self.clus, view['clus'], True )
            self.tbls = defaults( {}, view['tbls'], True )
            self.cols = defaults( {}, view['cols'], True )
        else:
            if format is not False:
                tbls = self.refs( tbls, self.tbls )
                tables = tbls[ 0 ].tbl_col_alias_q 
                for i in range(1,len(tbls)): tables += ',' + tbls[ i ].tbl_col_alias_q
            else:
                tables = ','.join(array( tbls ))
            if 'from_tables' in self.clus and len(self.clus['from_tables']) > 0:
                tables = self.clus['from_tables'] + ',' + tables
            self.clus['from_tables'] = tables
        return self
    
    def join( self, table, on_cond=None, join_type='' ):
        table = self.refs( table, self.tbls )[0].tbl_col_alias_q
        if empty(on_cond):
            join_clause = table
        else:
            if is_string(on_cond):
                on_cond = self.refs( on_cond.split('='), self.cols )
                on_cond = '(' + on_cond[0].tbl_col_q + '=' + on_cond[1].tbl_col_q + ')'
            else:
                for field in on_cond:
                    cond = on_cond[ field ]
                    if not is_obj(cond): on_cond[field] = {'eq':cond,'type':'identifier'}
                on_cond = self.conditions( on_cond, False )
            join_clause = table + " ON " + on_cond
        join_clause = ("JOIN " if empty(join_type) else (join_type.upper() + " JOIN ")) + join_clause
        if 'join_clauses' in self.clus and len(self.clus['join_clauses']) > 0:
            join_clause = self.clus['join_clauses'] + "\n" + join_clause
        self.clus['join_clauses'] = join_clause
        return self
    
    def where( self, conditions, boolean_connective="and" ):
        if empty(conditions): return self
        boolean_connective = boolean_connective.upper() if boolean_connective else "AND"
        if "OR" != boolean_connective: boolean_connective = "AND"
        conditions = self.conditions( conditions, False )
        if 'where_conditions' in self.clus and len(self.clus['where_conditions']) > 0:
            conditions = self.clus['where_conditions'] + " "+boolean_connective+" " + conditions
        self.clus['where_conditions'] = conditions
        return self
    
    def group( self, col, dir="asc" ):
        dir = dir.upper() if dir else "ASC"
        if "DESC" != dir: dir = "ASC"
        group_condition = self.refs( col, self.cols )[0].alias_q + " " + dir
        if 'group_conditions' in self.clus and len(self.clus['group_conditions']) > 0:
            group_condition = self.clus['group_conditions'] + ',' + group_condition
        self.clus['group_conditions'] = group_condition
        return self
    
    def having( self, conditions, boolean_connective="and" ):
        if empty(conditions): return self
        boolean_connective = boolean_connective.upper() if boolean_connective else "AND"
        if "OR" != boolean_connective: boolean_connective = "AND"
        conditions = self.conditions( conditions, False )
        if 'having_conditions' in self.clus and len(self.clus['having_conditions']) > 0:
            conditions = self.clus['having_conditions'] + " "+boolean_connective+" " + conditions
        self.clus['having_conditions'] = conditions
        return self
    
    def order( self, col, dir="asc" ):
        dir = dir.upper() if dir else "ASC"
        if "DESC" != dir: dir = "ASC"
        order_condition = self.refs( col, self.cols )[0].alias_q + " " + dir
        if 'order_conditions' in self.clus and len(self.clus['order_conditions']) > 0:
            order_condition = self.clus['order_conditions'] + ',' + order_condition
        self.clus['order_conditions'] = order_condition
        return self
    
    def limit( self, count, offset=0 ):
        self.clus['count'] = int(count,10) if is_string(count) else count
        self.clus['offset'] = int(offset,10) if is_string(offset) else offset
        self.clus['limit'] = 1
        return self
    
    def page( self, page, perpage ):
        page = int(page,10) if is_string(page) else page
        perpage = int(perpage,10) if is_string(perpage) else perpage
        return self.limit( perpage, page*perpage )
    
    def join_conditions( self, join, conditions ):
        j = 0
        conditions_copied = copy.copy(conditions)
        for f in conditions_copied:
            
            ref = Ref.parse( f, self )
            field = ref.col
            if field not in join: continue
            cond = conditions[ f ]
            main_table = join[field]['table']
            main_id = join[field]['id']
            join_table = join[field]['join']
            join_id = join[field]['join_id']
            
            j += 1
            join_alias = join_table+str(j)
            
            where = { }
            if ('key' in join[field]) and field != join[field]['key']:
                join_key = join[field]['key']
                where[join_alias+'.'+join_key] = field
            else:
                join_key = field
            if 'value' in join[field]:
                join_value = join[field]['value']
                where[join_alias+'.'+join_value] = cond
            else:
                join_value = join_key
                where[join_alias+'.'+join_value] = cond
            self.join(
                join_table+" AS "+join_alias, 
                main_table+'.'+main_id+'='+join_alias+'.'+join_id, 
                "inner"
            ).where( where )
            
            del conditions[f]
        return self
    
    def refs( self, refs, lookup ):
        rs = array( refs )
        refs = [ ]
        for i in range(len(rs)):
            r = rs[ i ].split(',')
            for j in range(len(r)):
                ref = Ref.parse( r[ j ], self )
                if ref.tbl_col not in lookup:
                    lookup[ ref.tbl_col ] = ref
                    if ref.tbl_col != ref.alias: lookup[ ref.alias ] = ref
                else:
                    ref = lookup[ ref.tbl_col ]
                refs.append( ref )
        return refs
    
    def conditions( self, conditions, can_use_alias=False ):
        if empty(conditions): return ''
        if is_string(conditions): return conditions
        
        condquery = ''
        conds = []
        COLS = self.cols
        fmt = 'alias_q' if can_use_alias is True else 'tbl_col_q'
        
        for f in conditions:
            
            field = getattr(self.refs( f, COLS )[0], fmt)
            value = conditions[f]
            
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
                    elif 'identifier' == type or 'field' == type:
                        v = getattr(self.refs( v, COLS )[0], fmt)
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
                    elif 'identifier' == type or 'field' == type:
                        v = getattr(self.refs( v, COLS )[0], fmt)
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
                    elif 'identifier' == type or 'field' == type:
                        v = getattr(self.refs( v, COLS )[0], fmt)
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
                    elif 'identifier' == type or 'field' == type:
                        v = getattr(self.refs( v, COLS )[0], fmt)
                    else:
                        v = self.quote( v )
                    conds.append( field + " = " + str(v) )
            else:
                conds.append( field + " = " + (str(value) if is_int(value) else self.quote(value)) )
        
        if len(conds): condquery = '(' + ') AND ('.join(conds) + ')'
        return condquery
    
    def tbl( self, table ):
        prefix = self.p
        return list(map(lambda table: prefix+table, table)) if is_array( table ) else (prefix+table)
    
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
        return list(map(lambda f: f if '*' == f else qn[0] + f + qn[1], f)) if is_array( f ) else (f if '*' == f else qn[0] + f + qn[1])
    
    def quote( self, v ):
        q = self.q
        return list(map(lambda v: q[0] + self.esc( v ) + q[1], v)) if is_array( v ) else (q[0] + self.esc( v ) + q[1])
    
    def like( self, v ):
        q = self.q
        e = ['',''] if self.escdb else self.e
        return list(map(lambda v: e[0] + q[0] + '%' + self.esc_like( self.esc( v ) ) + '%' + q[1] + e[1], v)) if is_array( v ) else (e[0] + q[0] + '%' + self.esc_like( self.esc( v ) ) + '%' + q[1] + e[1])
    
    def multi_like( self, f, v, trimmed=True ):
        trimmed = trimmed is not False
        like = f + " LIKE "
        ORs = v.split(',')
        if trimmed: ORs = filter(len, list(map(lambda x: x.strip(), ORs)))
        for i in range(len(ORs)):
            ANDs = ORs[i].split('+')
            if trimmed: ANDs = filter(len, list(map(lambda x: x.strip(), ANDs)))
            for j in range(len(ANDs)): ANDs[j] = like + self.like( ANDs[j] )
            ORs[i] = '(' + ' AND '.join(ANDs) + ')'
        return ' OR '.join(ORs)
    
    def esc( self, v ):
        global NULL_CHAR
        escdb = self.escdb
        if escdb: return list(map(escdb, v)) if is_array( v ) else escdb( v )
        elif is_array( v ): return list(map(lambda v: self.esc( v ), v))
        else:
            # simple ecsaping using addslashes
            # '"\ and NUL (the NULL byte).
            chars = '\\' + NULL_CHAR
            esc = '\\'
            q = self.q
            ve = ''
            for c in str(v):
                if q[0] == c: ve += q[2]
                elif q[1] == c: ve += q[3]
                else: ve += addslashes( c, chars, esc )
            return ve
    
    def esc_like( self, v ):
        chars = '_%'
        esc = '\\'
        if is_array( v ):
            return list(map(lambda v: addslashes( str(v), chars, esc ), v))
        return addslashes( str(v), chars, esc )
        

__all__ = ['Dialect']

