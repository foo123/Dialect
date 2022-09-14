var Dialect = require("../../src/js/Dialect.js"), echo = console.log;

echo('Dialect.VERSION = ' + Dialect.VERSION)
echo( );

var dialect = new Dialect('sqlserver');

var conditions = {
    'main.name':{'like':'%l:name%', 'type':'raw'},
    'main.str':{'eq':'%str%', 'type':'raw'},
    'main.year':{'eq':'2000', 'type':'raw'},
    'main.foo':{'either':[
        {'eq':',*,'},
        {'contains':',12,'}
    ]},
    'main.null':null,
    'main.not_null':{'not_eq':null},
    'main.project': {'in':[1,2,3],'type':'int'}
};

dialect
    .clear()
    .Select('COUNT(t.f0) AS f0,t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
    .From('t')
    .Join('t2',{'t.id':'t2.id'},'inner')
    .Where({f1:'2'})
    .Limit(100,100)
    .createView('my_view')
;

dialect
    .clear()
    .Select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
    .From('t')
    .Where({
        'f1':{'eq':'%d:id%','type':'raw'}
    })
    .prepareTpl('prepared_query')
;

dialect.prepareTpl(
    'prepared_query2', 
    dialect
        .clear()
        .Select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
        .From('t')
        .Where({
            'f1':{'eq':'%d:id%','type':'raw'}
        }).sql()
);

var query_soft_view = dialect
        .clear()
        .Select('*, f1 AS f11, f1 AS f111, COUNT( DISTINCT( f1 ) ) AS f22, COUNT( DISTINCT( f2 ) )')
        .From('my_view')
        .Where({f2:'3'}, 'OR')
        .Where({f2:'1'}, 'OR')
        .sql()
    ;
    
var query_prepared = dialect.prepared('prepared_query',{'id':'12'});
var query_prepared2 = dialect.prepared('prepared_query2',{'id':'12'});

var query = dialect
        .clear()
        .Select()
        .Order('main.field1')
        .From('table AS main')
        .joinConditions({
            'project' : {
                'table' : 'main',
                'id' : 'ID',
                'join' : 'usermeta',
                'join_id' : 'user_id',
                'key' : 'meta_key',
                'value' : 'meta_value'
            }
        }, conditions)
        .Where(conditions)
        .Order('main.field2')
        .Page(2, 1000)
        .sql()
    ;
    
var prepared = dialect.prepare(query, {'name':'na%me','str':'a string'});

var union = dialect.clear().Union([dialect.subquery().Select('*').From('t1').Limit(10).sql(), dialect.subquery().Select('*').From('t2').Limit(5).sql()], true).Limit(100).sql();

var sql = dialect.clear().Select().From('table');

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
echo( union );
echo( );
echo( [sql.sql(), sql.sql()].join("\n") );
echo( );
