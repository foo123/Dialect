<?php
include "../src/php/Dialect.php";
function echo_($s='')
{
    echo $s . PHP_EOL;
}

echo_('Dialect.VERSION = ' . Dialect::VERSION);
echo_();

$dialect = new Dialect( 'sqlite' );

$conditions = array(
    'main.name'=>array('like'=>'%l:name%','type'=>'raw'),
    'main.str'=>array('eq'=>'%str%','type'=>'raw'),
    'main.year'=>array('eq'=>'2000','type'=>'raw'),
    'main.project' => array('in'=>array(1,2,3),'type'=>'integer')
);

$dialect
    ->select('COUNT(t.f0) AS f0,t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3', false)
    ->from('t')
    ->join('t2',array('t.id'=>'t2.id'),'inner')
    ->where(array('f1'=>'2'))
    ->limit(100,100)
    ->make_view('my_view')
;

$dialect
    ->select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
    ->from('t')
    ->where(array(
        'f1'=>array('eq'=>'%d:id%','type'=>'raw')
    ))
    ->limit(100,100)
    ->prepare_tpl('prepared_query')
;

$dialect->prepare_tpl(
    'prepared_query2',
    $dialect
        ->select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
        ->from('t')
        ->where(array(
            'f1'=>array('eq'=>'%d:id%','type'=>'raw')
        ))->sql( )
);

$query_soft_view = $dialect
        ->select('f0,f1 AS f11')
        ->from('my_view')
        ->where(array('f2'=>'3'), 'OR')
        ->where(array('f2'=>'1'), 'OR')
        ->sql()
    ;
    

$query_prepared = $dialect->prepared('prepared_query',array('id'=>'12'));
$query_prepared2 = $dialect->prepared('prepared_query2',array('id'=>'12'));

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
    
$prepared = $dialect->prepare($query, array('name'=>'na%me','str'=>'a string'));

echo_( 'SQL dialect = ' . $dialect->type );
echo_( );
echo_( $query_soft_view );
echo_( );
echo_( $query_prepared );
echo_( );
echo_( $query_prepared2 );
echo_( );
echo_( $query );
echo_( );
echo_( $prepared );
echo_( );
