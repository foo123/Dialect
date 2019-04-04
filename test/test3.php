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

$query2 = $dialect->Select($quoted_id.' AS trickier, "trick\'y" AS tricky')->From('table')->sql();

$query3 = $dialect
        ->Select()
        ->From('table')
        ->Where(array('id'=>array('in'=>$dialect->subquery()->Select('id')->From('anothertable')->sql(),'type'=>'raw')))
        ->sql( )
    ;
$query4 = implode('',array(
        $dialect->Insert('table',array('col1','col2'))->sql( ),
        $dialect->Select('col1,col2')->From('anothertable')->Where(array('id'=>1))->sql( )
    ));
echo_( 'SQL dialect = ' . $dialect->type );
echo_( );
echo_( $query );
echo_( );
echo_( $quoted_id );
echo_( );
echo_( $quoted_lit );
echo_( );
echo_( $query2 );
echo_( );
echo_( $query3 );
echo_( );
echo_( $query4 );
