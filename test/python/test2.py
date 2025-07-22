#!/usr/bin/env python

import os, sys
import pprint

def import_module(name, path):
    #import imp
    #try:
    #    mod_fp, mod_path, mod_desc  = imp.find_module(name, [path])
    #    mod = getattr( imp.load_module(name, mod_fp, mod_path, mod_desc), name )
    #except ImportError as exc:
    #    mod = None
    #    sys.stderr.write("Error: failed to import module ({})".format(exc))
    #finally:
    #    if mod_fp: mod_fp.close()
    #return mod
    import importlib.util, sys
    spec = importlib.util.spec_from_file_location(name, path+name+'.py')
    mod = importlib.util.module_from_spec(spec)
    sys.modules[name] = mod
    spec.loader.exec_module(mod)
    return getattr(mod, name)

# import the Dialect.py engine (as a) module, probably you will want to place this in another dir/package
Dialect = import_module('Dialect', os.path.join(os.path.dirname(__file__), '../../src/python/'))
if not Dialect:
    print ('Could not load the Dialect Module')
    sys.exit(1)
else:    
    pass


def echo(s = ''):
    print (s)

echo('Dialect.VERSION = ' + Dialect.VERSION)
echo( )

dialect = Dialect('postgres')

echo(dialect.clear().Create('new_table', {
    'ifnotexists': True,
    'columns': [
        {'column':'id', 'type':dialect.sql_type('bigint',[20]), 'isnotnull':1, 'auto_increment':1},
        {'column':'name', 'type':dialect.sql_type('varchar',[100]), 'isnotnull':1, 'default_value':"''"},
        {'column':'categoryid', 'type':dialect.sql_type('bigint',[20]), 'isnotnull':1, 'default_value':0},
        {'column':'companyid', 'type':dialect.sql_type('bigint',[20]), 'isnotnull':1, 'default_value':0},
        {'column':'fields', 'type':dialect.sql_type('text'), 'isnotnull':1, 'default_value':"''"},
        {'column':'start', 'type':dialect.sql_type('datetime'), 'isnotnull':1, 'default_value':"'0000-00-00 00:00:00'"},
        {'column':'end', 'type':dialect.sql_type('datetime'), 'isnotnull':1, 'default_value':"'0000-00-00 00:00:00'"},
        {'column':'status', 'type':dialect.sql_type('smallint',[8]), 'isnotnull':1, 'default_value':0},
        {'column':'extra', 'type':dialect.sql_type('text'), 'isnotnull':1, 'default_value':"''"}
    ],
    'table': [
        {'collation':'utf8_general_ci'}
    ]
}).sql())

echo()

echo(dialect.clear().Create('new_view', {
    'view': True,
    'ifnotexists': True,
    'columns': ['id', 'name'],
    'query': 'SELECT id, name FROM another_table'
}).sql())

