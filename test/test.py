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
Dialect = import_module('Dialect', os.path.join(os.path.dirname(__file__), '../src/python/'))
if not Dialect:
    print ('Could not load the Dialect Module')
    sys.exit(1)
else:    
    pass


def echo( s='' ):
    print (s)

echo('Dialect.VERSION = ' + Dialect.VERSION)
echo( )

dialect = Dialect( 'postgres' )

conditions = {
    'main.name':{'like':'%l:name%', 'type':'raw'},
    'main.str':{'eq':'%str%', 'type':'raw'},
    'main.year':{'eq':'2000', 'type':'raw'},
    'main.project': {'in':[1,2,3],'type':'integer'}
}

dialect.select('COUNT(t.f0) AS f0,t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3').from_('t').join('t2',{'t.id':'t2.id'},'inner').where({'f1':'2'}).limit(100,100).make_view('my_view')

dialect.select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3').from_('t').where({
    'f1':{'eq':'%d:id%','type':'raw'}
}).limit(100,100).prepare_tpl('prepared_query')

dialect.prepare_tpl('prepared_query2', dialect.select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3').from_('t').where({
    'f1':{'eq':'%d:id%','type':'raw'}
}).sql( ))

query_soft_view = dialect.select('f0,f1 AS f11').from_('my_view').where({'f2':'3'}, 'OR').where({'f2':'1'}, 'OR').sql()
    
query_prepared = dialect.prepared('prepared_query',{'id':'12'})
query_prepared2 = dialect.prepared('prepared_query2',{'id':'12'})

query = dialect.select().order('main.field1').from_('table AS main').join_conditions({
    'project' : {
            'table' : 'main',
            'id' : 'ID',
            'join' : 'usermeta',
            'join_id' : 'user_id',
            'key' : 'meta_key',
            'value' : 'meta_value'
        }
    }, conditions).where(conditions).order('main.field2').page(2, 1000).sql( )
    
prepared = dialect.prepare(query, {'name':'na%me','str':'a string'})

echo( 'SQL dialect = ' + dialect.type )
echo( )
echo( query_soft_view )
echo( )
echo( query_prepared )
echo( )
echo( query_prepared2 )
echo( )
echo( query )
echo( )
echo( prepared )
echo( )
