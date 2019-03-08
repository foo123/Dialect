<?php
include "../src/php/Dialect.php";
function echo_($s='')
{
    echo $s . PHP_EOL;
}

echo_('Dialect.VERSION = ' . Dialect::VERSION);
echo_();

$dialect = new Dialect( 'mysql' );

$query = $dialect
        ->Select()
        ->Order($dialect->sql_function('random'))
        ->From('table AS main')
        ->sql( )
    ;

$quoted_id = $dialect->quote_name('trick`ier');
$quoted_lit = $dialect->quote('trick\'\\ier');

$query2 = $dialect->Select($quoted_id.' AS trickier')->From('table')->sql();

echo_( 'SQL dialect = ' . $dialect->type );
echo_( );
echo_( $query );
echo_( );
echo_( $quoted_id );
echo_( );
echo_( $quoted_lit );
echo_( );
echo_( $query2 );


