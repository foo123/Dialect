var Dialect = require("../src/js/Dialect.js"), echo = console.log;

echo('Dialect.VERSION = ' + Dialect.VERSION)
echo( );

var dialect = new Dialect( 'sqlserver' );

var conditions = {
    'main.name':{'like':'%l:name%', 'type':'raw'},
    'main.str':{'eq':'%str%', 'type':'raw'},
    'main.year':{'eq':'2000', 'type':'raw'},
    'main.project': {'in':[1,2,3],'type':'integer'}
};

dialect
    .select('COUNT(t.f0) AS f0,t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
    .from('t')
    .join('t2',{'t.id':'t2.id'},'inner')
    .where({f1:'2'})
    .limit(100,100)
    .make_view('my_view')
;

dialect
    .select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
    .from('t')
    .where({
        'f1':{'eq':'%d:id%','type':'raw'}
    })
    .prepare_tpl('prepared_query')
;

dialect.prepare_tpl(
    'prepared_query2', 
    dialect
        .select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
        .from('t')
        .where({
            'f1':{'eq':'%d:id%','type':'raw'}
        }).sql( )
);

var query_soft_view = dialect
        .select('*, f1 AS f11, f1 AS f111, COUNT( DISTINCT( f1 ) ) AS f22')
        .from('my_view')
        .where({f2:'3'}, 'OR')
        .where({f2:'1'}, 'OR')
        .sql()
    ;
    
var query_prepared = dialect.prepared('prepared_query',{'id':'12'});
var query_prepared2 = dialect.prepared('prepared_query2',{'id':'12'});

var query = dialect
        .select()
        .order('main.field1')
        .from('table AS main')
        .join_conditions({
            'project' : {
                'table' : 'main',
                'id' : 'ID',
                'join' : 'usermeta',
                'join_id' : 'user_id',
                'key' : 'meta_key',
                'value' : 'meta_value'
            }
        }, conditions)
        .where(conditions)
        .order('main.field2')
        .page(2, 1000)
        .sql( )
    ;
    
var prepared = dialect.prepare(query, {'name':'na%me','str':'a string'});

echo( 'SQL dialect = ' + dialect.type );
echo( );
echo( query_soft_view );
echo( );
echo( query_prepared );
echo( );
echo( query_prepared2 );
echo( );
echo( query );
echo( );
echo( prepared );
echo( );
