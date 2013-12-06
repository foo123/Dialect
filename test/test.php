<?php

include "../src/php/Dialect.php";

// do some tests
    
//print_r($this->parseQuery(' SELECT * FROM post AS p JOIN postmeta AS pm ON p.ID = pm.post_id WHERE p.date=123 '));

// even if clauses are added in random order, the sql will generate correct order
// joining on same table multiple times, will generate aliases automatically
// order, group, etc.. are at least partially sanitized (eg using intval, white-lists etc..)

$dialect = Dialect::create("mysql");

$Post = $dialect->table('post');
$Post2 = $dialect->table('post2');

$postID = $dialect->field('post.ID');
$orderfield = $dialect->field('post.orderfield');
$orderfield2 = $dialect->field('post.orderfield2');
$postGroup = $dialect->field('post.group');
$postMain = $dialect->field('post.main');
$postMain2 = $dialect->field('post.main2');
$postmetaField3 = $dialect->field('postmeta.field3');
$postmetaField1 = $dialect->field('postmeta.field1');
$postmetaField2 = $dialect->field('postmeta.field2');

echo $dialect
        ->select( $postID )
        ->limit( 100 )
        ->orderBy( $orderfield2 )
        ->where( 'AND', $postMain, 'not in', array(1,2,3) )
        ->where( 'AND', $postMain2, 'in', array(2,3) )
        ->join( array( $postmetaField3, $postMain ) )
        ->join( array( $postmetaField1, $postMain ) )
        ->join( array( $postmetaField2, $postMain ) )
        ->from( $Post )
        ->from( $Post2 )
        ->groupBy( $postGroup )
        ->orderBy( $orderfield )
        //->sql()
    ;
 
 
 /*
 OUTPUT: 
SELECT post.ID
FROM post,post2
INNER JOIN postmeta AS postmeta_1 ON postmeta_1.field3 = post.main INNER JOIN postmeta AS postmeta_2 ON postmeta_2.field1 = post.main INNER JOIN postmeta AS postmeta_3 ON postmeta_3.field2 = post.main
WHERE (post.main NOT IN (1,2,3)) AND (post.main2 IN (2,3))
GROUP BY post.group ASC
ORDER BY post.orderfield2 ASC, postmeta.orderfield ASC
LIMIT 0, 100
*/   
