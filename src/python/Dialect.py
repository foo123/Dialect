##
#   Dialect, 
#   a simple and flexible Cross-Platform SQL Builder for PHP, Python, Node/XPCOM/JS, ActionScript
# 
#   @version: 0.7.0
#   https://github.com/foo123/Dialect
#
#   Abstract the construction of SQL queries
#   Support multiple DB vendors
#   Intuitive and Flexible API
##

# https://github.com/foo123/StringTemplate
import re, math

NEWLINE = re.compile(r'\n\r|\r\n|\n|\r') 
SQUOTE = re.compile(r"'")
T_REGEXP = type(SQUOTE)

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


class StringTemplate:
    
    """
    StringTemplate for Python,
    https://github.com/foo123/StringTemplate
    """
    
    VERSION = '1.0.0'
    
    createFunction = createFunction
    
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
                out += s if notIsSub else StringTemplate.arg(s)
            
            out += ')'
            
        else:
        
            out = 'return ('
            for tpli in tpl:
            
                notIsSub = tpli[ 0 ]
                s = tpli[ 1 ]
                if notIsSub: out += "'" + re.sub(NEWLINE, "' + \"\\n\" + '", re.sub(SQUOTE, "\\'", s)) + "'"
                else: out += " + str(" + StringTemplate.arg(s,"argslen") + ") + "
            
            out += ')'
        
        return createFunction('args', "    " + out)

    
    defaultArgs = re.compile(r'\$(-?[0-9]+)')
    
    def __init__(self, tpl='', replacements=None, compiled=False):
        global T_REGEXP
        
        self.id = None
        self.tpl = None
        self._renderer = None
        self._args = [tpl,StringTemplate.defaultArgs if not replacements else replacements,compiled]
        self._parsed = False

    def __del__(self):
        self.dispose()
        
    def dispose(self):
        self.id = None
        self.tpl = None
        self._renderer = None
        self._args = None
        self._parsed = None
        return self
    
    def parse(self):
        if self._parsed is False:
            # lazy init
            tpl = self._args[0]
            replacements = self._args[1]
            compiled = self._args[2]
            self._args = None
            self.tpl = StringTemplate.multisplit_re( tpl, replacements ) if isinstance(replacements, T_REGEXP) else StringTemplate.multisplit( tpl, replacements )
            self._parsed = True
            if compiled is True: self._renderer = StringTemplate.compile( self.tpl )
        return self
    
    def render(self, args=None):
        if None == args: args = [ ]
        
        if self._parsed is False:
            # lazy init
            self.parse( )
            
        if self._renderer: return self._renderer( args )
        
        out = ''
        for t in self.tpl:
            if 1 == t[0]: out += t[ 1 ]
            else:
                s = t[ 1 ]
                out += '' if s not in args else str(args[ s ])
        
        return out

# https://github.com/foo123/GrammarTemplate
import time
TPL_ID = 0
def guid( ):
    global TPL_ID
    TPL_ID += 1
    return 'grtpl--'+str(int(time.time()))+'--'+str(TPL_ID)

def is_array( v ):
    return isinstance(v, (list,tuple))
    

def walk( obj, keys ):
    o = obj
    l = len(keys)
    i = 0
    while i < l:
        k = keys[i]
        i += 1
        if o is not None:
            if isinstance(o,(list,tuple)) and int(k)<len(o):
                o = o[k]
            elif isinstance(o,dict) and (k in o):
                o = o[k]
            else:
                try:
                    o = getattr(o, k)
                except AttributeError:
                    return None
        else: return None
    return o
    

class StackEntry:
    def __init__(self, stack=None, value=None):
        self.prev = stack
        self.value = value

class TplEntry:
    def __init__(self, node=None, tpl=None ):
        if tpl: tpl.next = self
        self.node = node
        self.prev = tpl
        self.next = None

def multisplit( tpl, delims ):
    IDL = delims[0]
    IDR = delims[1]
    OBL = delims[2]
    OBR = delims[3]
    TPL = delims[4]
    lenIDL = len(IDL)
    lenIDR = len(IDR)
    lenOBL = len(OBL)
    lenOBR = len(OBR)
    lenTPL = len(TPL)
    ESC = '\\'
    OPT = '?'
    OPTR = '*'
    NEG = '!'
    DEF = '|'
    REPL = '{'
    REPR = '}'
    DOT = '.'
    REF = ':'
    default_value = None
    negative = 0
    optional = 0
    l = len(tpl)
    
    a = TplEntry({'type': 0, 'val': ''})
    cur_arg = {
        'type'    : 1,
        'name'    : None,
        'key'     : None,
        'stpl'    : None,
        'dval'    : None,
        'opt'     : 0,
        'neg'     : 0,
        'start'   : 0,
        'end'     : 0
    }
    roottpl = a
    block = None
    opt_args = None
    subtpl = {}
    cur_tpl = None
    arg_tpl = {}
    start_tpl = None
    stack = None
    s = ''
    escaped = False
    
    i = 0
    while i < l:
        
        ch = tpl[i]
        
        if ESC == ch:
            escaped = not escaped
            i += 1
        
        if IDL == tpl[i:i+lenIDL]:
            i += lenIDL
            
            if escaped:
                s += IDL
                escaped = False
                continue
            
            if len(s):
                if 0 == a.node['type']: a.node['val'] += s
                else: a = TplEntry({'type': 0, 'val': s}, a)
            
            s = ''
        
        elif IDR == tpl[i:i+lenIDR]:
            i += lenIDR
            
            if escaped:
                s += IDR
                escaped = False
                continue
            
            # argument
            argument = s
            s = ''
            p = argument.find(DEF)
            if -1 < p:
                default_value = argument[p+1:]
                argument = argument[0:p]
            else:
                default_value = None
            
            c = argument[0]
            if OPT == c or OPTR == c:
                optional = 1
                if OPTR == c:
                    start_i = 1
                    end_i = -1
                else:
                    start_i = 0
                    end_i = 0
                argument = argument[1:]
                if NEG == argument[0]:
                    negative = 1
                    argument = argument[1:]
                else:
                    negative = 0
            elif REPL == c:
                s = ''
                j = 1
                jl = len(argument)
                while j < jl and REPR != argument[j]:
                    s += argument[j]
                    j += 1
                argument = argument[j+1:]
                s = s.split(',')
                if len(s) > 1:
                    start_i = s[0].strip()
                    start_i = int(start_i,10) if len(start_i) else 0
                    end_i = s[1].strip()
                    end_i = int(end_i,10) if len(end_i) else -1
                    optional = 1
                else:
                    start_i = s[0].strip()
                    start_i = int(start_i,10) if len(start_i) else 0
                    end_i = start_i
                    optional = 0
                s = ''
                negative = 0
            else:
                optional = 0
                negative = 0
                start_i = 0
                end_i = 0
            if negative and default_value is None: default_value = ''
            
            p = argument.find(REF)
            template = argument.split(REF) if -1 < p else [argument,None]
            argument = template[0]
            template = template[1]
            p = argument.find(DOT)
            nested = argument.split(DOT) if -1 < p else None
            
            if cur_tpl and (cur_tpl not in arg_tpl): arg_tpl[cur_tpl] = {}
            
            if TPL+OBL == tpl[i:i+lenTPL+lenOBL]:
                # template definition
                i += lenTPL
                template = template if template and len(template) else guid()
                start_tpl = template
                if cur_tpl and len(argument):
                    arg_tpl[cur_tpl][argument] = template
            
            if not len(argument): continue # template definition only
            
            if (template is None) and cur_tpl and (cur_tpl in arg_tpl) and (argument in arg_tpl[cur_tpl]):
                template = arg_tpl[cur_tpl][argument]
            
            if optional and not cur_arg['opt']:
                cur_arg['name'] = argument
                cur_arg['key'] = nested
                cur_arg['stpl'] = template
                cur_arg['dval'] = default_value
                cur_arg['opt'] = optional
                cur_arg['neg'] = negative
                cur_arg['start'] = start_i
                cur_arg['end'] = end_i
                # handle multiple optional arguments for same optional block
                opt_args = StackEntry(None, [argument,nested,negative,start_i,end_i])
                
            elif optional:
                # handle multiple optional arguments for same optional block
                opt_args = StackEntry(opt_args, [argument,nested,negative,start_i,end_i])
            
            elif (not optional) and (cur_arg['name'] is None):
                cur_arg['name'] = argument
                cur_arg['key'] = nested
                cur_arg['stpl'] = template
                cur_arg['dval'] = default_value
                cur_arg['opt'] = 0
                cur_arg['neg'] = negative
                cur_arg['start'] = start_i
                cur_arg['end'] = end_i
                # handle multiple optional arguments for same optional block
                opt_args = StackEntry(None, [argument,nested,negative,start_i,end_i])
            
            a = TplEntry({
                'type'    : 1,
                'name'    : argument,
                'key'     : nested,
                'stpl'    : template,
                'dval'    : default_value,
                'start'   : start_i,
                'end'     : end_i
            }, a)
        
        elif OBL == tpl[i:i+lenOBL]:
            i += lenOBL
            
            if escaped:
                s += OBL
                escaped = False
                continue
            
            # optional block
            if len(s):
                if 0 == a.node['type']: a.node['val'] += s
                else: a = TplEntry({'type': 0, 'val': s}, a)
            
            s = ''
            stack = StackEntry(stack, [a, block, cur_arg, opt_args, cur_tpl, start_tpl])
            if start_tpl: cur_tpl = start_tpl
            start_tpl = None
            cur_arg = {
                'type'    : 1,
                'name'    : None,
                'key'     : None,
                'stpl'    : None,
                'dval'    : None,
                'opt'     : 0,
                'neg'     : 0,
                'start'   : 0,
                'end'     : 0
            }
            opt_args = None
            a = TplEntry({'type': 0, 'val': ''})
            block = a
        
        elif OBR == tpl[i:i+lenOBR]:
            i += lenOBR
            
            if escaped:
                s += OBR
                escaped = False
                continue
            
            b = a
            cur_block = block
            prev_arg = cur_arg
            prev_opt_args = opt_args
            if stack:
                a = stack.value[0]
                block = stack.value[1]
                cur_arg = stack.value[2]
                opt_args = stack.value[3]
                cur_tpl = stack.value[4]
                start_tpl = stack.value[5]
                stack = stack.prev
            else:
                a = None
            
            if len(s):
                if 0 == b.node['type']: b.node['val'] += s
                else: b = TplEntry({'type': 0, 'val': s}, b)
            
            s = ''
            if start_tpl:
                subtpl[start_tpl] = TplEntry({
                    'type'    : 2,
                    'name'    : prev_arg['name'],
                    'key'     : prev_arg['key'],
                    'start'   : 0,#cur_arg.start
                    'end'     : 0,#cur_arg.end
                    'opt_args': None,#opt_args
                    'tpl'     : cur_block
                })
                start_tpl = None
            else:
                a = TplEntry({
                    'type'    : -1,
                    'name'    : prev_arg['name'],
                    'key'     : prev_arg['key'],
                    'start'   : prev_arg['start'],
                    'end'     : prev_arg['end'],
                    'opt_args': prev_opt_args,
                    'tpl'     : cur_block
                }, a)
        
        else:
            if ESC == ch:
                s += ch
            else:
                s += tpl[i]
                i += 1
    
    if len(s):
        if 0 == a.node['type']: a.node['val'] += s
        else: a = TplEntry({'type': 0, 'val': s}, a)
    
    return [roottpl, subtpl]

def optional_block( SUB, args, block, index=None ):
    out = ''
    
    if -1 == block['type']:
        # optional block, check if optional variables can be rendered
        opt_vars = block['opt_args']
        if not opt_vars: return ''
        while opt_vars:
            opt_v = opt_vars.value
            opt_arg = walk( args, opt_v[1] if opt_v[1] else [opt_v[0]] )
            if ((0 == opt_v[2]) and (opt_arg is None)) or ((1 == opt_v[2]) and (opt_arg is not None)): return ''
            opt_vars = opt_vars.prev
    
    if block['key']:
        opt_arg = walk( args, block['key'] )#nested key
        if (opt_arg is None) and (block['name'] in args): opt_arg = args[block['name']]
    else:
        opt_arg = walk( args, [block['name']] )#plain key
    
    arr = is_array( opt_arg )
    if arr and (len(opt_arg) > block['start']):
        rs = block['start']
        re = len(opt_arg)-1 if -1==block['end'] else min(block['end'], len(opt_arg)-1)
        ri = rs
        while ri <= re:
            out += main( SUB, args, block['tpl'], ri )
            ri += 1
    elif (not arr) and (block['start'] == block['end']):
        out = main( SUB, args, block['tpl'], None )
    
    return out

def non_terminal( SUB, args, symbol, index=None ):
    out = ''
    if SUB and symbol['stpl'] and (symbol['stpl'] in SUB):
        # using sub-template
        if symbol['key']:
            opt_arg = walk( args, symbol['key'] )
            if (opt_arg is None) and (symbol['name'] in args): opt_arg = args[symbol['name']]
        else:
            opt_arg = walk( args, [symbol['name']] )
        
        if (index is not None) and is_array(opt_arg):
            opt_arg = opt_arg[index]
        
        if (opt_arg is None) and (symbol['dval'] is not None):
            # default value if missing
            out = symbol['dval']
        else:
            # try to associate sub-template parameters to actual input arguments
            tpl = SUB[symbol['stpl']].node
            tpl_args = {}
            if opt_arg is not None:
                #if (tpl['name'] in opt_arg) and (symbol['name'] not in opt_arg): tpl_args = opt_arg
                #else: tpl_args[tpl['name']] = opt_arg
                if is_array(opt_arg): tpl_args[tpl['name']] = opt_arg
                else: tpl_args = opt_arg
            out = optional_block( SUB, tpl_args, tpl, None )
    else:
        # plain symbol argument
        if symbol['key']:
            opt_arg = walk( args, symbol['key'] )
            if (opt_arg is None) and (symbol['name'] in args): opt_arg = args[symbol['name']]
        else:
            opt_arg = walk( args, [symbol['name']] )
        # default value if missing
        if is_array(opt_arg):
            opt_arg = opt_arg[index] if index is not None else opt_arg[symbol['start']]
        out = symbol['dval'] if (opt_arg is None) and (symbol['dval'] is not None) else str(opt_arg)
    
    return out

def main( SUB, args, tpl, index=None ):
    out = ''
    while tpl:
        tt = tpl.node['type']
        out += (optional_block( SUB, args, tpl.node, index ) if -1 == tt else (non_terminal( SUB, args, tpl.node, index ) if 1 == tt else tpl.node['val']))
        tpl = tpl.next
    return out


class GrammarTemplate:
    """
    GrammarTemplate for Python,
    https://github.com/foo123/GrammarTemplate
    """
    
    VERSION = '2.0.0'
    

    #defaultDelims = ['<','>','[',']',':=','?','*','!','|','{','}']
    defaultDelims = ['<','>','[',']',':=']
    multisplit = multisplit
    main = main
    
    def __init__(self, tpl='', delims=None):
        self.id = None
        self.tpl = None
        # lazy init
        self._args = [ tpl, delims if delims else GrammarTemplate.defaultDelims ]
        self._parsed = False

    def __del__(self):
        self.dispose()
        
    def dispose(self):
        self.id = None
        self.tpl = None
        self._args = None
        self._parsed = None
        return self
    
    def parse(self):
        if self._parsed is False:
            # lazy init
            self._parsed = True
            self.tpl = GrammarTemplate.multisplit( self._args[0], self._args[1] )
            self._args = None
        return self
    
    def render(self, args=None):
        # lazy init
        if self._parsed is False: self.parse( )
        return GrammarTemplate.main( self.tpl[1], {} if None == args else args, self.tpl[0] )


import copy
NULL_CHAR = chr(0)

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
    return v == None or (isinstance(v, (tuple,list,str,dict)) and 0 == len(v))

def addslashes( s, chars=None, esc='\\' ):
    global NULL_CHAR
    s2 = ''
    if chars is None: chars = '\\"\'' + NULL_CHAR
    for c in s:
        s2 += c if c not in chars else ('\\0' if 0 == ord(c) else (esc+c))
    return s2

def defaults( data, defau, overwrite=False, array_copy=False ):
    overwrite = overwrite is True
    array_copy = array_copy is True
    for k in defau:
        if overwrite or not(k in data):
            data[ k ] = defau[ k ][:] if array_copy and isinstance(defau[ k ],list) else defau[ k ]
    return data

def map_join( arr, prop, sep=',' ):
    joined = ''
    if arr and len(arr):
        joined = getattr(arr[0], prop)
        for i in range(1,len(arr)): joined += sep + getattr(arr[i], prop)
    return joined

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

Ref_spc_re = re.compile(r'\s')
Ref_num_re = re.compile(r'[0-9]')
Ref_alf_re = re.compile(r'[a-z_]', re.I)

class Ref:

    def parse( r, d ):
        global Ref_spc_re
        global Ref_num_re
        global Ref_alf_re
        # should handle field formats like:
        # [ F1(..Fn( ] [[dtb.]tbl.]col [ )..) ] [ AS alias ]
        # and extract alias, dtb, tbl, col identifiers (if present)
        # and also extract F1,..,Fn function identifiers (if present)
        r = r.strip( )
        l = len(r)
        i = 0
        stacks = [[]]
        stack = stacks[0]
        ids = []
        funcs = []
        keywords2 = ['AS']
        # 0 = SEP, 1 = ID, 2 = FUNC, 5 = Keyword, 10 = *, 100 = Subtree
        s = ''
        err = None
        paren = 0
        quote = None
        while i < l:
            ch = r[i]
            i += 1
            
            if '"' == ch or '`' == ch or '\'' == ch or '[' == ch or ']' == ch:
                # sql quote
                if not quote:
                    if len(s) or (']' == ch):
                        err = ['invalid',i]
                        break
                    quote = ']' if '[' == ch else ch
                    continue
                
                elif quote == ch:
                    if len(s):
                        stack.insert(0,[1, s])
                        ids.insert(0,s)
                        s = ''
                    else:
                        err = ['invalid',i]
                        break
                    quote = None
                    continue
                
                elif quote:
                    s += ch
                    continue
            
            if quote:
                # part of sql-quoted value
                s += ch
                continue
            
            if '*' == ch:
                # placeholder
                if len(s):
                    err = ['invalid',i]
                    break
                stack.insert(0,[10, '*'])
                ids.insert(0,10)
            
            elif '.' == ch:
                # separator
                if len(s):
                    stack.insert(0,[1, s])
                    ids.insert(0,s)
                    s = ''
                if not len(stack) or 1 != stack[0][0]:
                    # error, mismatched separator
                    err = ['invalid',i]
                    break
                
                stack.insert(0,[0, '.'])
                ids.insert(0,0)
            
            elif '(' == ch:
                # left paren
                paren += 1
                if len(s):
                    # identifier is function
                    stack.insert(0,[2, s])
                    funcs.insert(0,s)
                    s = ''
                if not len(stack) or (2 != stack[0][0] and 1 != stack[0][0]):
                    err = ['invalid',i]
                    break
                if 1 == stack[0][0]:
                    stack[0][0] = 2
                    funcs.insert(0,ids.pop(0))
                stacks.insert(0,[])
                stack = stacks[0]
            
            elif ')' == ch:
                # right paren
                paren -= 1
                if len(s):
                    keyword = s.upper() in keywords2
                    stack.insert(0,[5 if keyword else 1, s])
                    ids.insert(0, 5 if keyword else s)
                    s = ''
                if len(stacks) < 2:
                    err = ['invalid',i]
                    break
                # reduce
                stacks[1].insert(0,[100, stacks.pop(0)])
                stack = stacks[0]
            
            elif Ref_spc_re.match(ch):
                # space separator
                if len(s):
                    keyword = s.upper() in keywords2
                    stack.insert(0,[5 if keyword else 1, s])
                    ids.insert(0, 5 if keyword else s)
                    s = ''
                continue
            
            elif Ref_num_re.match(ch):
                if not len(s):
                    err = ['invalid',i]
                    break
                # identifier
                s += ch
            
            elif Ref_alf_re.match(ch):
                # identifier
                s += ch
            
            else:
                err = ['invalid',i]
                break
        
        if len(s):
            stack.insert(0,[1, s])
            ids.insert(0,s)
            s = ''
        
        if not err and paren: err = ['paren', l]
        if not err and quote: err = ['quote', l]
        if not err and 1 != len(stacks): err = ['invalid', l]
        if err:
            err_pos = err[1]-1
            err_type = err[0]
            if 'paren' == err_type:
                # error, mismatched parentheses
                raise ValueError('Dialect: Mismatched parentheses "'+r+'" at position '+err_pos+'.')
            elif 'quote' == err_type:
                # error, mismatched quotes
                raise ValueError('Dialect: Mismatched quotes "'+r+'" at position '+err_pos+'.')
            else:# if 'invalid' == err_type:
                # error, invalid character
                raise ValueError('Dialect: Invalid character "'+r+'" at position '+err_pos+'.')
        
        alias = None
        alias_q = ''
        if (len(ids) >= 3) and (5 == ids[1]) and isinstance(ids[0],str):
            alias = ids.pop(0)
            alias_q = d.quote_name( alias )
            ids.pop(0)
        
        col = None
        col_q = ''
        if len(ids) and (isinstance(ids[0],str) or 10 == ids[0]):
            if isinstance(ids[0],str):
                col = ids.pop(0)
                col_q = d.quote_name( col )
            else:
                ids.pop(0)
                col = col_q = '*'
        
        tbl = None
        tbl_q = ''
        if (len(ids) >= 2) and (0 == ids[0]) and isinstance(ids[1],str):
            ids.pop(0)
            tbl = ids.pop(0)
            tbl_q = d.quote_name( tbl )
        
        dtb = None
        dtb_q = ''
        if (len(ids) >= 2) and (0 == ids[0]) and isinstance(ids[1],str):
            ids.pop(0)
            dtb = ids.pop(0)
            dtb_q = d.quote_name( dtb )
        
        tbl_col = (dtb+'.' if dtb else '') + (tbl+'.' if tbl else '') + (col if col else '')
        tbl_col_q = (dtb_q+'.' if dtb else '') + (tbl_q+'.' if tbl else '') + (col_q if col else '')
        return Ref(col, col_q, tbl, tbl_q, dtb, dtb_q, alias, alias_q, tbl_col, tbl_col_q, funcs)

    def __init__( self, _col, col, _tbl, tbl, _dtb, dtb, _alias, alias, _qual, qual, _func=[] ):
        self._col = _col
        self.col = col
        self._tbl = _tbl
        self.tbl = tbl
        self._dtb = _dtb
        self.dtb = dtb
        self._alias = _alias
        self._qualified =_qual
        self.qualified = qual
        self.full = self.qualified
        self._func = [] if not _func else _func
        if len(self._func):
            for f in self._func: self.full = f+'('+self.full+')'
        
        if self._alias is not None:
            self.alias = alias
            self.aliased = self.full + ' AS ' + self.alias
        else:
            self.alias = self.full
            self.aliased = self.full

    def cloned( self, alias=None, alias_q=None, func=None ):
        if alias is None and alias_q is None:
            alias = self._alias
            alias_q = self.alias
        elif alias is not None:
            alias_q = alias if alias_q is None else alias_q
        if func is None:
            func = self._func
        return Ref( self._col, self.col, self._tbl, self.tbl, self._dtb, self.dtb, alias, alias_q, 
                    self._qualified, self.qualified, func )
    
    def __del__( self ):
        self.dispose( )
        
    def dispose( self ):
        self._func = None
        self._col = None
        self.col = None
        self._tbl = None
        self.tbl = None
        self._dtb = None
        self.dtb = None
        self._alias = None
        self.alias = None
        self._qualified = None
        self.qualified = None
        self.full = None
        self.aliased = None
        return self


class Dialect:
    """
    Dialect for Python,
    https://github.com/foo123/Dialect
    """
    
    VERSION = '0.7.0'
    
    #TPL_RE = re.compile(r'\$\(([^\)]+)\)')
    StringTemplate = StringTemplate
    GrammarTemplate = GrammarTemplate
    Ref = Ref

    dialects = {
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
        # http://dev.mysql.com/doc/refman/5.7/en/string-functions.html
        ,'functions'    : {
         'strpos'       : ['POSITION(',2,' IN ',1,')']
        ,'strlen'       : ['LENGTH(',1,')']
        ,'strlower'     : ['LCASE(',1,')']
        ,'strupper'     : ['UCASE(',1,')']
        ,'trim'         : ['TRIM(',1,')']
        ,'quote'        : ['QUOTE(',1,')']
        ,'random'       : ['RAND()']
        ,'now'          : ['NOW()']
        }
        ,'clauses'      : {
         'create'       : "CREATE TABLE IF NOT EXISTS <create_table>\n(<create_defs>)[<?create_opts>]"
        ,'alter'        : "ALTER TABLE <alter_table>\n<alter_defs>[<?alter_opts>]"
        ,'drop'         : "DROP TABLE IF EXISTS <drop_tables>[,<*drop_tables>]"
        ,'select'       : "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>[\n<*join_clauses>]][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING (<?having_conditions_required>) AND (<?having_conditions>)][\nHAVING <?having_conditions_required><?!having_conditions>][\nHAVING <?!having_conditions_required><?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]"
        ,'insert'       : "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
        ,'update'       : "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]"
        ,'delete'       : "DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <offset|0>,<?count>]"
        }
    }
    ,'postgres'          : {
        # http://www.postgresql.org/docs/
        # http://www.postgresql.org/docs/9.1/static/sql-createtable.html
        # http://www.postgresql.org/docs/9.1/static/sql-droptable.html
        # http://www.postgresql.org/docs/9.1/static/sql-altertable.html
        # http://www.postgresql.org/docs/8.2/static/sql-syntax-lexical.html
         'quotes'       : [ ["E'","'","''","''"], ['"','"'], ['',''] ]
        # http://www.postgresql.org/docs/9.1/static/functions-string.html
        ,'functions'    : {
         'strpos'       : ['position(',2,' in ',1,')']
        ,'strlen'       : ['length(',1,')']
        ,'strlower'     : ['lower(',1,')']
        ,'strupper'     : ['upper(',1,')']
        ,'trim'         : ['trim(',1,')']
        ,'quote'        : ['quote(',1,')']
        ,'random'       : ['random()']
        ,'now'          : ['now()']
        }
        ,'clauses'      : {
         'create'       : "CREATE TABLE IF NOT EXISTS <create_table>\n(<create_defs>)[<?create_opts>]"
        ,'alter'        : "ALTER TABLE <alter_table>\n<alter_defs>[<?alter_opts>]"
        ,'drop'         : "DROP TABLE IF EXISTS <drop_tables>[,<*drop_tables>]"
        ,'select'       : "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>[\n<*join_clauses>]][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING (<?having_conditions_required>) AND (<?having_conditions>)][\nHAVING <?having_conditions_required><?!having_conditions>][\nHAVING <?!having_conditions_required><?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
        ,'insert'       : "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
        ,'update'       : "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
        ,'delete'       : "DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
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
        # https://msdn.microsoft.com/en-us/library/ms186323.aspx
        ,'functions'    : {
         'strpos'       : ['CHARINDEX(',2,',',1,')']
        ,'strlen'       : ['LEN(',1,')']
        ,'strlower'     : ['LOWER(',1,')']
        ,'strupper'     : ['UPPER(',1,')']
        ,'trim'         : ['LTRIM(RTRIM(',1,'))']
        ,'quote'        : ['QUOTENAME(',1,',"\'")']
        ,'random'       : ['RAND()']
        ,'now'          : ['CURRENT_TIMESTAMP']
        }
        ,'clauses'      : {
         'create'       : "CREATE TABLE IF NOT EXISTS <create_table>\n(<create_defs>)[<?create_opts>]"
        ,'alter'        : "ALTER TABLE <alter_table>\n<alter_defs>[<?alter_opts>]"
        ,'drop'         : "DROP TABLE IF EXISTS <drop_tables>[,<*drop_tables>]"
        ,'select'       : "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>[\n<*join_clauses>]][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING (<?having_conditions_required>) AND (<?having_conditions>)][\nHAVING <?having_conditions_required><?!having_conditions>][\nHAVING <?!having_conditions_required><?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>][\nOFFSET <offset|0> ROWS FETCH NEXT <?count> ROWS ONLY]][<?!order_conditions>[\nORDER BY 1\nOFFSET <offset|0> ROWS FETCH NEXT <?count> ROWS ONLY]]"
        ,'insert'       : "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
        ,'update'       : "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]]"
        ,'delete'       : "DELETE \nFROM <from_tables>[,<*from_tables>][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]]"
        }
    }
    ,'sqlite'           : {
        # https://www.sqlite.org/lang_createtable.html
        # https://www.sqlite.org/lang_select.html
        # https://www.sqlite.org/lang_insert.html
        # https://www.sqlite.org/lang_update.html
        # https://www.sqlite.org/lang_delete.html
        # https://www.sqlite.org/lang_expr.html
        # https://www.sqlite.org/lang_keywords.html
         'quotes'       : [ ["'","'","''","''"], ['"','"'], [''," ESCAPE '\\'"] ]
        # https://www.sqlite.org/lang_corefunc.html
        ,'functions'    : {
         'strpos'       : ['instr(',2,',',1,')']
        ,'strlen'       : ['length(',1,')']
        ,'strlower'     : ['lower(',1,')']
        ,'strupper'     : ['upper(',1,')']
        ,'trim'         : ['trim(',1,')']
        ,'quote'        : ['quote(',1,')']
        ,'random'       : ['random()']
        ,'now'          : ['datetime(\'now\')']
        }
        ,'clauses'      : {
         'create'       : "CREATE TABLE IF NOT EXISTS <create_table>\n(<create_defs>)[<?create_opts>]"
        ,'alter'        : "ALTER TABLE <alter_table>\n<alter_defs>[<?alter_opts>]"
        ,'drop'         : "DROP TABLE IF EXISTS <drop_tables>[,<*drop_tables>]"
        ,'select'       : "SELECT <select_columns>[,<*select_columns>]\nFROM <from_tables>[,<*from_tables>][\n<?join_clauses>[\n<*join_clauses>]][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>][\nGROUP BY <?group_conditions>[,<*group_conditions>]][\nHAVING (<?having_conditions_required>) AND (<?having_conditions>)][\nHAVING <?having_conditions_required><?!having_conditions>][\nHAVING <?!having_conditions_required><?having_conditions>][\nORDER BY <?order_conditions>[,<*order_conditions>]][\nLIMIT <?count> OFFSET <offset|0>]"
        ,'insert'       : "INSERT INTO <insert_tables> (<insert_columns>[,<*insert_columns>])\nVALUES <values_values>[,<*values_values>]"
        ,'update'       : "UPDATE <update_tables>\nSET <set_values>[,<*set_values>][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>]"
        ,'delete'       : "[<?!order_conditions><?!count>DELETE FROM <from_tables> [, <*from_tables>][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>]][DELETE FROM <from_tables> [, <*from_tables>] WHERE rowid IN (\nSELECT rowid FROM <from_tables> [, <*from_tables>][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>]\nORDER BY <?order_conditions> [, <*order_conditions>][\nLIMIT <?count> OFFSET <offset|0>]\n)][<?!order_conditions>DELETE FROM <from_tables> [, <*from_tables>] WHERE rowid IN (\nSELECT rowid FROM <from_tables> [, <*from_tables>][\nWHERE (<?where_conditions_required>) AND (<?where_conditions>)][\nWHERE <?where_conditions_required><?!where_conditions>][\nWHERE <?!where_conditions_required><?where_conditions>]\nLIMIT <?count> OFFSET <offset|0>\n)]"
        }
    }
    }
    
    def __init__( self, type='mysql' ):
        if (not type) or (type not in Dialect.dialects) or ('clauses' not in Dialect.dialects[ type ]):
            raise ValueError('Dialect: SQL dialect does not exist for "'+type+'"')
        
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
        self.clauses = Dialect.dialects[ self.type ][ 'clauses' ]
        self.q = Dialect.dialects[ self.type ][ 'quotes' ][ 0 ]
        self.qn = Dialect.dialects[ self.type ][ 'quotes' ][ 1 ]
        self.e = Dialect.dialects[ self.type ][ 'quotes' ][ 2 ] if 1 < len(Dialect.dialects[ self.type ][ 'quotes' ]) else ['','']
    
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
        if not clause or (clause not in self.clauses):
            raise ValueError('Dialect: SQL clause "'+str(clause)+'" does not exist for dialect "'+self.type+'"')
        self.clus = { }
        self.tbls = { }
        self.cols = { }
        self.clau = clause
        if not isinstance(self.clauses[ self.clau ], Dialect.GrammarTemplate):
            self.clauses[ self.clau ] = Dialect.GrammarTemplate( self.clauses[ self.clau ] )
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
            if 'select_columns' in self.clus:
                self.clus['select_columns'] = map_join( self.clus['select_columns'], 'aliased' )
            if 'from_tables' in self.clus:
                self.clus['from_tables'] = map_join( self.clus['from_tables'], 'aliased' )
            if 'insert_tables' in self.clus:
                self.clus['insert_tables'] = map_join( self.clus['insert_tables'], 'aliased' )
            if 'insert_columns' in self.clus:
                self.clus['insert_columns'] = map_join( self.clus['insert_columns'], 'full' )
            if 'update_tables' in self.clus:
                self.clus['update_tables'] = map_join( self.clus['update_tables'], 'aliased' )
            if 'create_table' in self.clus:
                self.clus['create_table'] = map_join( self.clus['create_table'], 'full' )
            if 'alter_table' in self.clus:
                self.clus['alter_table'] = map_join( self.clus['alter_table'], 'full' )
            if 'drop_tables' in self.clus:
                self.clus['drop_tables'] = map_join( self.clus['drop_tables'], 'full' )
            query = self.clauses[ self.clau ].render( self.clus )
        self.clear( )
        return query
    
    def createView( self, view ):
        if view and self.clau:
            self.vews[ view ] = {
                'clau':self.clau, 
                'clus':self.clus,
                'tbls':self.tbls,
                'cols':self.cols
            }
            # make existing where / having conditions required
            if 'where_conditions' in self.vews[ view ][ 'clus' ]:
                if len(self.vews[ view ][ 'clus' ][ 'where_conditions' ]):
                    self.vews[ view ][ 'clus' ][ 'where_conditions_required' ] = self.vews[ view ][ 'clus' ][ 'where_conditions' ]
                del self.vews[ view ][ 'clus' ][ 'where_conditions' ]
            if 'having_conditions' in self.vews[ view ][ 'clus' ]:
                if len(self.vews[ view ][ 'clus' ][ 'having_conditions' ]):
                    self.vews[ view ][ 'clus' ][ 'having_conditions_required' ] = self.vews[ view ][ 'clus' ][ 'having_conditions' ]
                del self.vews[ view ][ 'clus' ][ 'having_conditions' ]
            self.clear( )
        return self
    
    def useView( self, view ):
        # using custom 'soft' view
        selected_columns = self.clus['select_columns']
        
        view = self.vews[ view ]
        self.clus = defaults( self.clus, view['clus'], True, True )
        self.tbls = defaults( {}, view['tbls'], True )
        self.cols = defaults( {}, view['cols'], True )
        
        # handle name resolution and recursive re-aliasing in views
        if selected_columns:
            selected_columns = self.refs( selected_columns, self.cols, True )
            select_columns = []
            for selected_column in selected_columns:
                if '*' == selected_column.full:
                    select_columns = select_columns + self.clus['select_columns']
                else:
                    select_columns.append( selected_column )
            self.clus['select_columns'] = select_columns
        
        return self
    
    def dropView( self, view ):
        if view and (view in self.vews):
            del self.vews[ view ]
        return self
    
    def prepareTpl( self, tpl, *args ):
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
                sql = Dialect.StringTemplate( self.sql( ), pattern )
                #self.clear( )
            else:
                sql = Dialect.StringTemplate( query, pattern )
            
            self.tpls[ tpl ] = {
                'sql':sql, 
                'types':None
            }
        return self
    
    def prepared( self, tpl, args ):
        if tpl and (tpl in self.tpls):
            
            sql = self.tpls[tpl]['sql']
            types = self.tpls[tpl]['types']
            if types is None:
                # lazy init
                sql.parse( )
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
                self.tpls[tpl]['types'] = types
            
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
                        params[k] = Ref.parse( tmp[0], self ).aliased
                        for i in range(1,len(tmp)): params[k] += ','+Ref.parse( tmp[i], self ).aliased
                    else:
                        # reference, e.g field
                        params[k] = Ref.parse( v, self ).aliased
                
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
                            param = Ref.parse( tmp[0], self ).aliased
                            for i in range(1,len(tmp)): param += ','+Ref.parse( tmp[i], self ).aliased
                        else:
                            # reference, e.g field
                            param = Ref.parse( args[param], self ).aliased
                    
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
    
    def dropTpl( self, tpl ):
        if tpl and (tpl in self.tpls):
           self.tpls[ tpl ]['sql'].dispose( )
           del self.tpls[ tpl ]
        return self
    
    def Create( self, table, defs, opts=None, create_clause='create' ):
        if self.clau != create_clause: self.reset(create_clause)
        table = self.refs( table, self.tbls )[0].full
        self.clus['create_table'] = table
        if 'create_defs' in self.clus and len(self.clus['create_defs']) > 0:
            defs = self.clus['create_defs'] + ',' + defs
        self.clus['create_defs'] = defs
        if opts:
            if 'create_opts' in self.clus and len(self.clus['create_opts']) > 0:
                opts = self.clus['create_opts'] + ',' + opts
            self.clus['create_opts'] = opts
        return self
    
    def Alter( self, table, defs, opts=None, alter_clause='alter' ):
        if self.clau != alter_clause: self.reset(alter_clause)
        table = self.refs( table, self.tbls )[0].full
        self.clus['alter_table'] = table
        if 'alter_defs' in self.clus and len(self.clus['alter_defs']) > 0:
            defs = self.clus['alter_defs'] + ',' + defs
        self.clus['alter_defs'] = defs
        if opts:
            if 'alter_opts' in self.clus and len(self.clus['alter_opts']) > 0:
                opts = self.clus['alter_opts'] + ',' + opts
            self.clus['alter_opts'] = opts
        return self
    
    def Drop( self, tables='*', drop_clause='drop' ):
        if self.clau != drop_clause: self.reset(drop_clause)
        view = tables[0] if is_array( tbls ) else tables
        if (view in self.vews):
            # drop custom 'soft' view
            self.dropView( view )
            return self
        
        tables = self.refs( '*' if not tables else tables, self.tbls )
        if ('drop_tables' not in self.clus) or not len(self.clus['drop_tables']):
            self.clus['drop_tables'] = tables
        else:
            self.clus['drop_tables'] = self.clus['drop_tables'] + tables
        return self
    
    def Select( self, columns='*', select_clause='select' ):
        if self.clau != select_clause: self.reset(select_clause)
        columns = self.refs( '*' if not columns else columns, self.cols )
        if ('select_columns' not in self.clus) or not len(self.clus['select_columns']):
            self.clus['select_columns'] = columns
        else:
            self.clus['select_columns'] = self.clus['select_columns'] + columns
        return self
    
    def Insert( self, tables, columns, insert_clause='insert' ):
        if self.clau != insert_clause: self.reset(insert_clause);
        view = tables[0] if is_array( tbls ) else tables
        if (view in self.vews) and self.clau == self.vews[ view ]['clau']:
            # using custom 'soft' view
            self.useView( view )
        else:
            tables = self.refs( tables, self.tbls )
            columns = self.refs( columns, self.cols )
            if ('insert_tables' not in self.clus) or not len(self.clus['insert_tables']):
                self.clus['insert_tables'] = tables
            else:
                self.clus['insert_tables'] = self.clus['insert_tables'] + tables
            if ('insert_columns' not in self.clus) or not len(self.clus['insert_columns']):
                self.clus['insert_columns'] = columns
            else:
                self.clus['insert_columns'] = self.clus['insert_columns'] + columns
        return self
    
    def Values( self, values ):
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
    
    def Update( self, tables, update_clause='update' ):
        if self.clau != update_clause: self.reset(update_clause)
        view = tables[0] if is_array( tables ) else tables
        if (view in self.vews) and self.clau == self.vews[ view ]['clau']:
            # using custom 'soft' view
            self.useView( view )
        else:
            tables = self.refs( tables, self.tbls )
            if ('update_tables' not in self.clus) or not len(self.clus['update_tables']):
                self.clus['update_tables'] = tables
            else:
                self.clus['update_tables'] = self.clus['update_tables'] + tables
        return self
    
    def Set( self, fields_values ):
        if empty(fields_values): return self
        set_values = []
        COLS = self.cols
        for f in fields_values:
            field = self.refs( f, COLS )[0].full
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
                elif 'case' in value:
                    set_case_value = field + " = CASE"
                    if 'when' in value['case']:
                        for case_value in value['case']['when']:
                            set_case_value += "\nWHEN " + self.conditions(value['case']['when'][case_value],False) + " THEN " + self.quote(case_value)
                        if 'else' in value['case']:
                            set_case_value += "\nELSE " + self.quote(value['case']['else'])
                    else:
                        for case_value in value['case']:
                            set_case_value += "\nWHEN " + self.conditions(value['case'][case_value],False) + " THEN " + self.quote(case_value)
                    set_case_value += "\nEND"
                    set_values.append( set_case_value )
            else:
                set_values.append( field + " = " + (str(value) if is_int(value) else self.quote(value)) )
        set_values = ','.join(set_values)
        if 'set_values' in self.clus and len(self.clus['set_values']) > 0:
            set_values = self.clus['set_values'] + ',' + set_values
        self.clus['set_values'] = set_values
        return self
    
    def Delete( self, delete_clause='delete' ):
        if self.clau != delete_clause: self.reset(delete_clause)
        return self
    
    def From( self, tables ):
        if empty(tables): return self
        view = tables[0] if is_array( tables ) else tables
        if (view in self.vews) and (self.clau == self.vews[ view ]['clau']):
            # using custom 'soft' view
            self.useView( view )
        else:
            tables = self.refs( tables, self.tbls )
            if ('from_tables' not in self.clus) or not len(self.clus['from_tables']):
                self.clus['from_tables'] = tables
            else:
                self.clus['from_tables'] = self.clus['from_tables'] + tables
        return self
    
    def Join( self, table, on_cond=None, join_type='' ):
        table = self.refs( table, self.tbls )[0].aliased
        if empty(on_cond):
            join_clause = table
        else:
            if is_string(on_cond):
                on_cond = self.refs( on_cond.split('='), self.cols )
                on_cond = '(' + on_cond[0].full + '=' + on_cond[1].full + ')'
            else:
                for field in on_cond:
                    cond = on_cond[ field ]
                    if not is_obj(cond): on_cond[field] = {'eq':cond,'type':'identifier'}
                on_cond = '(' + self.conditions( on_cond, False ) + ')'
            join_clause = table + " ON " + on_cond
        join_clause = ("JOIN " if empty(join_type) else (join_type.upper() + " JOIN ")) + join_clause
        if 'join_clauses' in self.clus and len(self.clus['join_clauses']) > 0:
            join_clause = self.clus['join_clauses'] + "\n" + join_clause
        self.clus['join_clauses'] = join_clause
        return self
    
    def Where( self, conditions, boolean_connective="and" ):
        if empty(conditions): return self
        boolean_connective = boolean_connective.upper() if boolean_connective else "AND"
        if "OR" != boolean_connective: boolean_connective = "AND"
        conditions = self.conditions( conditions, False )
        if 'where_conditions' in self.clus and len(self.clus['where_conditions']) > 0:
            conditions = self.clus['where_conditions'] + " "+boolean_connective+" " + conditions
        self.clus['where_conditions'] = conditions
        return self
    
    def Group( self, col, dir="asc" ):
        dir = dir.upper() if dir else "ASC"
        if "DESC" != dir: dir = "ASC"
        group_condition = self.refs( col, self.cols )[0].alias + " " + dir
        if 'group_conditions' in self.clus and len(self.clus['group_conditions']) > 0:
            group_condition = self.clus['group_conditions'] + ',' + group_condition
        self.clus['group_conditions'] = group_condition
        return self
    
    def Having( self, conditions, boolean_connective="and" ):
        if empty(conditions): return self
        boolean_connective = boolean_connective.upper() if boolean_connective else "AND"
        if "OR" != boolean_connective: boolean_connective = "AND"
        conditions = self.conditions( conditions, True )
        if 'having_conditions' in self.clus and len(self.clus['having_conditions']) > 0:
            conditions = self.clus['having_conditions'] + " "+boolean_connective+" " + conditions
        self.clus['having_conditions'] = conditions
        return self
    
    def Order( self, col, dir="asc" ):
        dir = dir.upper() if dir else "ASC"
        if "DESC" != dir: dir = "ASC"
        order_condition = self.refs( col, self.cols )[0].alias + " " + dir
        if 'order_conditions' in self.clus and len(self.clus['order_conditions']) > 0:
            order_condition = self.clus['order_conditions'] + ',' + order_condition
        self.clus['order_conditions'] = order_condition
        return self
    
    def Limit( self, count, offset=0 ):
        self.clus['count'] = int(count,10) if is_string(count) else count
        self.clus['offset'] = int(offset,10) if is_string(offset) else offset
        return self
    
    def Page( self, page, perpage ):
        page = int(page,10) if is_string(page) else page
        perpage = int(perpage,10) if is_string(perpage) else perpage
        return self.Limit( perpage, page*perpage )
    
    def conditions( self, conditions, can_use_alias=False ):
        if empty(conditions): return ''
        if is_string(conditions): return conditions
        
        condquery = ''
        conds = []
        COLS = self.cols
        fmt = 'alias' if can_use_alias is True else 'full'
        
        for f in conditions:
            
            value = conditions[f]
            
            if is_obj( value ):
                if 'raw' in value:
                    conds.append(str(value['raw']))
                    continue
                
                if 'or' in value:
                    cases = []
                    for or_cl in value['or']:
                        cases.append(self.conditions(or_cl, can_use_alias))
                    conds.append(' OR '.join(cases))
                    continue
                
                if 'and' in value:
                    cases = []
                    for and_cl in value['and']:
                        cases.append(self.conditions(and_cl, can_use_alias))
                    conds.append(' AND '.join(cases))
                    continue
                
                if 'either' in value:
                    cases = []
                    for either in value['either']:
                        case_i = {}
                        case_i[f] = either
                        cases.append(self.conditions(case_i, can_use_alias))
                    conds.append(' OR '.join(cases))
                    continue
                
                if 'together' in value:
                    cases = []
                    for together in value['together']:
                        case_i = {}
                        case_i[f] = together
                        cases.append(self.conditions(case_i, can_use_alias))
                    conds.append(' AND '.join(cases))
                    continue
                
                field = getattr(self.refs( f, COLS )[0], fmt)
                type = value['type'] if 'type' in value else 'string'
                
                if 'case' in value:
                    cases = field + " = CASE"
                    if 'when' in value['case']:
                        for case_value in value['case']['when']:
                            cases += " WHEN " + self.conditions(value['case']['when'][case_value], can_use_alias) + " THEN " + self.quote(case_value)
                        if 'else' in value['case']:
                            cases += " ELSE " + self.quote(value['case']['else'])
                    else:
                        for case_value in value['case']:
                            cases += " WHEN " + self.conditions(value['case'][case_value], can_use_alias) + " THEN " + self.quote(case_value)
                    cases += " END"
                    conds.append( cases )
                elif 'multi_like' in value:
                    conds.append( self.multi_like(field, value['multi_like']) )
                elif 'like' in value:
                    conds.append( field + " LIKE " + (str(value['like']) if 'raw' == type else self.like(value['like'])) )
                elif 'not_like' in value:
                    conds.append( field + " NOT LIKE " + (str(value['not_like']) if 'raw' == type else self.like(value['not_like'])) )
                elif 'contains' in value:
                    v = str(value['contains'])
                    
                    if 'raw' == type:
                        # raw, do nothing
                        pass
                    else:
                        v = self.quote( v )
                    conds.append(self.sql_function('strpos', [field,v]) + ' > 0')
                elif 'not_contains' in value:
                    v = str(value['not_contains'])
                    
                    if 'raw' == type:
                        # raw, do nothing
                        pass
                    else:
                        v = self.quote( v )
                    conds.append(self.sql_function('strpos', [field,v]) + ' = 0')
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
                    
                    # partial between clause
                    if v[0] is None:
                        # switch to lte clause
                        if 'raw' == type:
                            # raw, do nothing
                            pass
                        elif 'integer' == type or is_int(v[1]):
                            v[1] = self.intval( v[1] )
                        else:
                            v[1] = self.quote( v[1] )
                        conds.append( field + " <= " + str(v[1]) )
                    elif v[1] is None:
                        # switch to gte clause
                        if 'raw' == type:
                            # raw, do nothing
                            pass
                        elif 'integer' == type or is_int(v[0]):
                            v[0] = self.intval( v[0] )
                        else:
                            v[0] = self.quote( v[0] )
                        conds.append( field + " >= " + str(v[0]) )
                    else:
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
                    
                    # partial between clause
                    if v[0] is None:
                        # switch to gt clause
                        if 'raw' == type:
                            # raw, do nothing
                            pass
                        elif 'integer' == type or is_int(v[1]):
                            v[1] = self.intval( v[1] )
                        else:
                            v[1] = self.quote( v[1] )
                        conds.append( field + " > " + str(v[1]) )
                    elif v[1] is None:
                        # switch to lt clause
                        if 'raw' == type:
                            # raw, do nothing
                            pass
                        elif 'integer' == type or is_int(v[0]):
                            v[0] = self.intval( v[0] )
                        else:
                            v[0] = self.quote( v[0] )
                        conds.append( field + " < " + str(v[0]) )
                    else:
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
                field = getattr(self.refs( f, COLS )[0], fmt)
                conds.append( field + " = " + (str(value) if is_int(value) else self.quote(value)) )
        
        if len(conds): condquery = '(' + ') AND ('.join(conds) + ')'
        return condquery
    
    def joinConditions( self, join, conditions ):
        j = 0
        conditions_copied = copy.copy(conditions)
        for f in conditions_copied:
            
            ref = Ref.parse( f, self )
            field = ref._col
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
            self.Join(
                join_table+" AS "+join_alias, 
                main_table+'.'+main_id+'='+join_alias+'.'+join_id, 
                "inner"
            ).Where( where )
            
            del conditions[f]
        return self
    
    def refs( self, refs, lookup, re_alias=False ):
        if re_alias is True:
            for i in range(len(refs)):
                ref = refs[i]
                alias = ref.alias
                qualified = ref.qualified
                qualified_full = ref.full
                
                if '*' == qualified_full: continue
                
                if alias not in lookup:
                    
                    if qualified_full in lookup:
                        
                        ref2 = lookup[ qualified_full ]
                        alias2 = ref2.alias
                        qualified_full2 = ref2.full
                        
                        if (qualified_full2 != qualified_full) and (alias2 != alias) and (alias2 == qualified_full):
                            
                            # handle recursive aliasing
                            #if (qualified_full2 != alias2) and (alias2 in lookup):
                            #    del lookup[ alias2 ]
                            
                            ref2 = ref2.cloned( ref.alias )
                            refs[i] = lookup[ alias ] = ref2
                    
                    elif qualified in lookup:
                        ref2 = lookup[ qualified ]
                        if ref2.qualified != qualified: ref2 = lookup[ ref2.qualified ]
                        if ref.full != ref.alias:
                            ref2 = ref2.cloned( ref.alias, None, ref._func )
                        else:
                            ref2 = ref2.cloned( None, ref2.alias, ref._func )
                        refs[i] = lookup[ ref2.alias ] = ref2
                        if (ref2.alias != ref2.full) and (ref2.full not in lookup):
                            lookup[ ref2.full ] = ref2
                    
                    else:
                        
                        lookup[ alias ] = ref
                        
                        if (alias != qualified_full) and (qualified_full not in lookup):
                            lookup[ qualified_full ] = ref
                
                else:
                    
                    refs[i] = lookup[ alias ]
                
        else:
            rs = array( refs )
            refs = [ ]
            for i in range(len(rs)):
                r = rs[ i ].split(',')
                for j in range(len(r)):
                    ref = Ref.parse( r[ j ], self )
                    alias = ref.alias
                    qualified = ref.full
                    if alias not in lookup:
                        lookup[ alias ] = ref
                        if (qualified != alias) and (qualified not in lookup):
                            lookup[ qualified ] = ref
                    else:
                        ref = lookup[ alias ]
                    refs.append( ref )
        return refs
    
    def tbl( self, table ):
        if is_array( table ): return [self.tbl( x ) for x in table]
        return self.p + table
    
    def intval( self, v ):
        if is_int( v ): return v
        elif is_array( v ): return [self.intval( x ) for x in v]
        else: return int( v, 10 )
    
    def intval2str( self, v ):
        if is_int( v ): return str(v)
        elif is_array( v ): return [self.intval2str( x ) for x in v]
        else: return str(self.intval( v ))
    
    def quote_name( self, v, optional=False ):
        optional = optional is True
        qn = self.qn
        if is_array( v ): return [self.quote_name( x, optional ) for x in v]
        elif optional: return ('' if qn[0] == v[0:len(qn[0])] else qn[0]) + v + ('' if qn[1] == v[-len(qn[1]):] else qn[1])
        else: return qn[0] + v + qn[1]
    
    def quote( self, v ):
        if is_array( v ): return [self.quote( x ) for x in v]
        q = self.q
        return q[0] + self.esc( v ) + q[1]
    
    def like( self, v ):
        if is_array( v ): return [self.like( x ) for x in v]
        q = self.q
        e = ['',''] if self.escdb else self.e
        return e[0] + q[0] + '%' + self.esc_like( self.esc( v ) ) + '%' + q[1] + e[1]
    
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
        
        if is_array( v ): return [self.esc( x ) for x in v]
        
        escdb = self.escdb
        if escdb: return escdb( v )
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
        if is_array( v ): return [self.esc_like( x ) for x in v]
        return addslashes( str(v), '_%', '\\' )
    
    def sql_function( self, f, args=None ):
        if f not in Dialect.dialects[ self.type ][ 'functions' ]:
            raise ValueError('Dialect: SQL function "'+f+'" does not exist for dialect "'+self.type+'"')
        f = Dialect.dialects[ self.type ][ 'functions' ][ f ]
        func = ''
        args = [] if args is None else array(args)
        argslen = len(args)
        is_arg = False
        for fi in f:
            func += (args[fi-1] if 0<fi and argslen>=fi else '') if is_arg else fi
            is_arg = not is_arg
        return func

__all__ = ['Dialect']

