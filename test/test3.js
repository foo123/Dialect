var Dialect = require("../src/js/Dialect.js"), echo = console.log;

echo('Dialect.VERSION = ' + Dialect.VERSION)
echo( );

var dialect = new Dialect( 'postgresql' );

var query = dialect
        .Select()
        .Order(dialect.sql_function('random'))
        .From('table AS main')
        .sql( )
    ;


var quoted_id = dialect.quote_name('trick"ier');
var quoted_lit = dialect.quote('trick\'\\ier');

var query2 = dialect.Select(quoted_id+' AS trickier, "trick\'y" AS tricky').From('table').sql();

var query3 = dialect
        .Select()
        .From('table')
        .Where({'id':{'in':dialect.subquery().Select('id').From('anothertable').sql(),'type':'raw'}})
        .sql( )
    ;

var query4 = [
        dialect.Insert('table',['col1','col2']).sql( ),
        dialect.Select('col1,col2').From('anothertable').Where({'id':1}).sql( )
    ].join('');

var query5 = dialect.Select('anothertable.col1,anothertable.col2,dynamictable.*').From(['anothertable','('+
            dialect.subquery()
            .Select(quoted_id)
            .From('table')
            .Where({'col4':{like:'foo'}})
            .sql()+
        ') AS dynamictable']).Where({'id':1}).sql( );

echo( 'SQL dialect = ' + dialect.type );
echo( );
echo( query );
echo( );
echo( quoted_id );
echo( );
echo( quoted_lit );
echo( );
echo( query2 );
echo( );
echo( query3 );
echo( );
echo( query4 );
echo( );
echo( query5 );
