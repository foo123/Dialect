var Dialect = require("../src/js/Dialect.js"), echo = console.log;

echo('Dialect.VERSION = ' + Dialect.VERSION)
echo( );

var dialect = new Dialect( );

var conditions = {
    'main.name':{'like':'%l:name%', type:'raw'},
    'main.year':{'eq':dialect.year('date'), type:'raw'},
    'main.project': {'in':[1,2,3],'type':'integer'}
};

dialect
    .select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
    .from('t')
    .join('t2',{'t.id':'t2.id'},'inner')
    .make_view('my_view')
;

var query_soft_view = dialect
        .select()
        .from('my_view')
        .where({f1:'2'})
        .sql()
    ;
    
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
    
var prepared = dialect.prepare(query, {'name':'na%me'});

echo( query_soft_view );
echo( );
echo( query );
echo( );
echo( prepared );
echo( );
