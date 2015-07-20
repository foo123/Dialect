var Dialect = require("../src/js/Dialect.js"), echo = console.log;

echo('Dialect.VERSION = ' + Dialect.VERSION)
echo( );

var dialect = new Dialect( );

var conditions = {
    'main.name':{'like-prepared':'%l:name%'},
    'main.year':{'eq':dialect.year('date')},
    'main.project': {'in':[1,2,3],'type':'integer'}
};

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

echo( query );
echo( );
echo( prepared );
echo( );
