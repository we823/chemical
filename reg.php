<?php
	require 'src/libs/ThinkPHP/Common/functions.php';
	
	$pattern = '/[(G\-?)|(A\-?)|(S\-?)|(P\-?)|(V\-?)|(T\-?)|(C\-?)|(I\-?)|(L\-?)|(N\-?)|(D\-?)|(Q\-?)|(K\-?)|(E\-?)|(M\-?)|(H\-?)|(F\-?)|(R\-?)|(Y\-?)|(pS\-?)|(pT\-?)|(W\-?)|(pY\-?)|(Ser\(PO\-3H\)(\-))]+/';
	//$pattern = '[(A\-?)]+';
	$subject = 'AC-pS-A-pY';
	
	$item = array();
	$result = preg_match_all($pattern, $subject, $item);
	
	$pattern2 = '/Ser\(PO\-3H\)\-?/';
	$subject2 = 'Ser(PO-3H)-(P1-3H)ASer(PO-3H)(P1-3H)A';
	
	$result2 = preg_match_all($pattern2, $subject2, $item);
	
	$array = array(
	  'A'=>'A',
	  'B'=>'B'
	);
	
	if(array_key_exists('AA', $array)){
		echo 'true';
	}
