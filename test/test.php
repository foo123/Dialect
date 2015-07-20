<?php
include "../src/php/Dialect.php";
function echo_($s='')
{
    echo $s . PHP_EOL;
}

echo_('Dialect.VERSION = ' . Dialect::VERSION);
echo_();

$dialect = new Dialect();

$conditions = array(
    'main.name'=>array('like-prepared'=>'%l:name%'),
    'main.year'=>array('eq'=>$dialect->year('date')),
    'main.project' => array('in'=>array(1,2,3),'type'=>'integer')
);

$query = $dialect
        ->select()
        ->order('main.field1')
        ->from('table AS main')
        ->join_conditions(array(
            'project' => array(
                'table' => 'main',
                'id' => 'ID',
                'join' => 'usermeta',
                'join_id' => 'user_id',
                'key' => 'meta_key',
                'value' => 'meta_value'
            )
        ), $conditions)
        ->where($conditions)
        ->order('main.field2')
        ->page(2, 1000)
        ->sql( )
    ;
    
$prepared = $dialect->prepare($query, array('name'=>'na%me'));

echo_( $query );
echo_( );
echo_( $prepared );
echo_( );
