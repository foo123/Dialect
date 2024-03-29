<?php
include "../../src/php/Dialect.php";
function echo_($s = '')
{
    echo $s . PHP_EOL;
}

echo_('Dialect.VERSION = ' . Dialect::VERSION);
echo_();

$dialect = new Dialect('sqlite');

$conditions = array(
    'main.name'=>array('like'=>'%l:name%','type'=>'raw'),
    'main.str'=>array('eq'=>'%str%','type'=>'raw'),
    'main.year'=>array('eq'=>'2000','type'=>'raw'),
    'main.foo'=>array('either'=>array(
        array('eq'=>',*,'),
        array('contains'=>',12,')
    )),
    'main.null'=>null,
    'main.not_null'=>array('not_eq'=>null),
    'main.project' => array('in'=>array(1,2,3),'type'=>'int')
);

$dialect
    ->clear()
    ->Select('COUNT(t.f0) AS f0,t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
    ->From('t')
    ->Join('t2',array('t.id'=>'t2.id'),'inner')
    ->Where(array('f1'=>'2'))
    ->Limit(100,100)
    ->CreateView('my_view')
;

$dialect
    ->clear()
    ->Select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
    ->From('t')
    ->Where(array(
        'f1'=>array('eq'=>'%i:id%','type'=>'raw')
    ))
    ->Limit(100,100)
    ->PrepareTpl('prepared_query')
;

$dialect->PrepareTpl(
    'prepared_query2',
    $dialect
        ->clear()
        ->Select('t.f1 AS f1,t.f2 AS f2,t2.f3 AS f3')
        ->From('t')
        ->Where(array(
            'f1'=>array('eq'=>'%i:id%','type'=>'raw')
        ))->sql()
);

$query_soft_view = $dialect
        ->clear()
        ->Select('*, f1 AS f11, f1 AS f111, COUNT( DISTINCT( f1 ) ) AS f22, COUNT( DISTINCT( f2 ) )')
        ->From('my_view')
        ->Where(array('f2'=>'3'), 'OR')
        ->Where(array('f2'=>'1'), 'OR')
        ->sql()
    ;
    

$query_prepared = $dialect->prepared('prepared_query',array('id'=>'12'));
$query_prepared2 = $dialect->prepared('prepared_query2',array('id'=>'12'));

$query = $dialect
        ->clear()
        ->Select()
        ->Order('main.field1')
        ->From('table AS main')
        ->joinConditions(array(
            'project' => array(
                'table' => 'main',
                'id' => 'ID',
                'join' => 'usermeta',
                'join_id' => 'user_id',
                'key' => 'meta_key',
                'value' => 'meta_value'
            )
        ), $conditions)
        ->Where($conditions)
        ->Order('main.field2')
        ->Page(2, 1000)
        ->sql()
    ;
    
$prepared = $dialect->prepare($query, array('name'=>'na%me','str'=>'a string'));

$union = $dialect->clear()->Union(array($dialect->subquery()->Select('*')->From('t1')->Limit(10)->sql(), $dialect->subquery()->Select('*')->From('t2')->Limit(5)->sql()), true)->Limit(100)->sql();

$sql = $dialect->clear()->Select()->From('table');

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
echo_( $union );
echo_( );
echo_( implode("\n", array($sql->sql(), $sql->sql())) );
echo_( );
