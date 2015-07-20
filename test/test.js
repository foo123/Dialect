var Dialect = require("../src/js/Dialect.js"), echo = console.log;

echo('Dialect.VERSION = ' + Dialect.VERSION)
echo( );

var dialect = new Dialect( );

var query = dialect
        .select()
        .order('field1')
        .from('table')
        .join('table2', 'table.id=table2.id', 'inner')
        .where({
            'name':{'like-prepared':'%l:name%'},
            'year':{'eq':dialect.year('date')}
        })
        .order('field2')
        .page(2, 1000)
        .sql( )
    ;
    
var prepared = dialect.prepare(query, {'name':'na%me'});

echo( query );
echo( );
echo( prepared );
echo( );
