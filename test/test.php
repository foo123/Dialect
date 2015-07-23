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
    'main.name'=>array('like'=>'%l:name%','type'=>'raw'),
    'main.year'=>array('eq'=>$dialect->year('date'),'type'=>'raw'),
    'main.project' => array('in'=>array(1,2,3),'type'=>'integer')
);

$dialect
    ->select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
    ->from('t')
    ->join('t2','t.id=t2.id','inner')
    ->make_view('my_view')
;

$query_soft_view = $dialect
        ->select()
        ->from('my_view')
        ->where(array('f1'=>'2'))
        ->sql()
    ;
    

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

/*echo_( $dialect->ref('t.a AS a,t.b,t2.c AS c2') );
echo_( );*/
echo_( $query_soft_view );
echo_( );
echo_( $query );
echo_( );
echo_( $prepared );
echo_( );
