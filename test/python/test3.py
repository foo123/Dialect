#!/usr/bin/env python

import os, sys
import pprint

def import_module(name, path):
    import imp
    try:
        mod_fp, mod_path, mod_desc  = imp.find_module(name, [path])
        mod = getattr( imp.load_module(name, mod_fp, mod_path, mod_desc), name )
    except ImportError as exc:
        mod = None
        sys.stderr.write("Error: failed to import module ({})".format(exc))
    finally:
        if mod_fp: mod_fp.close()
    return mod

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

dialect = Dialect('sqlite')

query = dialect.clear().Select().Order(dialect.sql_function('random')).From('table AS main').sql( )

quoted_id = dialect.quote_name('trick"ier')
quoted_lit = dialect.quote('trick\'\\ier')

query2 = dialect.clear().Select(quoted_id+' AS trickier, "trick\'y" AS tricky').From('table').sql()

query3 = dialect.clear().Select().From('table').Where({'id':{'in':dialect.subquery().Select('id').From('anothertable').sql(),'type':'raw'}}).sql()

query4 = ''.join([
        dialect.clear().Insert('table',['col1','col2']).sql(),
        dialect.clear().Select('col1,col2').From('anothertable').Where({'id':1}).sql()
    ])

query5 = dialect.clear().Select('anothertable.col1,anothertable.col2,dynamictable.*').From(['anothertable','('+
            dialect.subquery().Select(quoted_id).From('table').Where({'col4':{'like':'foo'}}).sql()+
        ') AS dynamictable']).Where({'id':1}).sql()

echo( 'SQL dialect = ' + dialect.type )
echo( )
echo( query )
echo( )
echo( quoted_id )
echo( )
echo( quoted_lit )
echo( )
echo( query2 )
echo( )
echo( query3 )
echo( )
echo( query4 )
echo( )
echo( query5 )
