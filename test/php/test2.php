<?php
include "../../src/php/Dialect.php";
function echo_($s = '')
{
    echo $s . PHP_EOL;
}

echo_('Dialect.VERSION = ' . Dialect::VERSION);
echo_();

$dialect = new Dialect('sqlserver');

echo_($dialect->clear()->Create('new_table', array(
    'ifnotexists' => true,
    'columns' => array(
        array('column'=>'id', 'type'=>$dialect->sql_type('bigint',[20]), 'isnotnull'=>1, 'auto_increment'=>1),
        array('column'=>'name', 'type'=>$dialect->sql_type('varchar',[100]), 'isnotnull'=>1, 'default_value'=>"''"),
        array('column'=>'categoryid', 'type'=>$dialect->sql_type('bigint',[20]), 'isnotnull'=>1, 'default_value'=>0),
        array('column'=>'companyid', 'type'=>$dialect->sql_type('bigint',[20]), 'isnotnull'=>1, 'default_value'=>0),
        array('column'=>'fields', 'type'=>$dialect->sql_type('text'), 'isnotnull'=>1, 'default_value'=>"''"),
        array('column'=>'start', 'type'=>$dialect->sql_type('datetime'), 'isnotnull'=>1, 'default_value'=>"'0000-00-00 00:00:00'"),
        array('column'=>'end', 'type'=>$dialect->sql_type('datetime'), 'isnotnull'=>1, 'default_value'=>"'0000-00-00 00:00:00'"),
        array('column'=>'status', 'type'=>$dialect->sql_type('smallint',[8]), 'isnotnull'=>1, 'default_value'=>0),
        array('column'=>'extra', 'type'=>$dialect->sql_type('text'), 'isnotnull'=>1, 'default_value'=>"''")
    ),
    'table'=> array(
        array('collation'=>'utf8_general_ci')
    )
))->sql());

echo_();

echo_($dialect->clear()->Create('new_view', array(
    'view'=> true,
    'ifnotexists'=> true,
    'columns'=> array('id', 'name'),
    'query'=> 'SELECT id, name FROM another_table'
))->sql());

