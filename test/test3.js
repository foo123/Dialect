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

var query2 = dialect.Select(quoted_id+' AS trickier').From('table').sql();

echo( 'SQL dialect = ' + dialect.type );
echo( );
echo( query );
echo( );
echo( quoted_id );
echo( );
echo( quoted_lit );
echo( );
echo( query2 );
