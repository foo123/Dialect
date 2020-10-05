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
    'main.foo':{'either':[
        {'eq':',*,'},
        {'contains':',12,'}
    ]},
    'main.null' : None,
    'main.not_null' : {'not_eq':None},
    'main.project': {'in':[1,2,3],'type':'integer'}
}

dialect.Select('COUNT(t.f0) AS f0,t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3').From('t').Join('t2',{'t.id':'t2.id'},'inner').Where({'f1':'2'}).Limit(100,100).createView('my_view')

dialect.Select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3').From('t').Where({
    'f1':{'eq':'%d:id%','type':'raw'}
}).Limit(100,100).prepareTpl('prepared_query')

dialect.prepareTpl('prepared_query2', dialect.Select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3').From('t').Where({
    'f1':{'eq':'%d:id%','type':'raw'}
}).sql( ))

query_soft_view = dialect.Select('*, f1 AS f11, f1 AS f111, COUNT( DISTINCT( f1 ) ) AS f22, COUNT( DISTINCT( f2 ) )').From('my_view').Where({'f2':'3'}, 'OR').Where({'f2':'1'}, 'OR').sql()
    
query_prepared = dialect.prepared('prepared_query',{'id':'12'})
query_prepared2 = dialect.prepared('prepared_query2',{'id':'12'})

query = dialect.Select().Order('main.field1').From('table AS main').joinConditions({
    'project' : {
            'table' : 'main',
            'id' : 'ID',
            'join' : 'usermeta',
            'join_id' : 'user_id',
            'key' : 'meta_key',
            'value' : 'meta_value'
        }
    }, conditions).Where(conditions).Order('main.field2').Page(2, 1000).sql( )
    
prepared = dialect.prepare(query, {'name':'na%me','str':'a string'})

union = dialect.Union([dialect.subquery().Select('*').From('t1').Limit(10).sql(), dialect.subquery().Select('*').From('t2').Limit(5).sql()], True).Limit(100).sql( )

sql = dialect.Select().From('table')

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
echo( union )
echo( )
echo( "\n".join([sql.sql(), sql.sql()]) )
echo( )
