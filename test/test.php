<?php
include "../src/php/Dialect.php";
function echo_($s='')
{
    echo $s . PHP_EOL;
}

echo_('Dialect.VERSION = ' . Dialect::VERSION);
echo_();

$dialect = new Dialect();

$query = $dialect
        ->select()
        ->order('field1')
        ->from('table')
        ->join('table2', 'table.id=table2.id', 'inner')
        ->where(array(
            'name'=>array('like-prepared'=>'%l:name%'),
            'year'=>array('eq'=>$dialect->year('date'))
        ))
        ->order('field2')
        ->page(2, 1000)
        ->sql( )
    ;
    
$prepared = $dialect->prepare($query, array('name'=>'na%me'));

echo_( $query );
echo_( );
echo_( $prepared );
echo_( );
