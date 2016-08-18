<?php
	
	$subject = 'cyclo(4-8)';
	
	$pattern = '/cyclo\(([1-9]*[0-9]+\-[1-9]*[0-9]+)\)/';
	
	$result = preg_match_all($pattern, $subject, $items);
	
	var_dump($result);
	var_dump($items[0]);
	var_dump($items[1]);
	
	echo '<br><br>';
	$subject2 = 'chainA(cyclo(KCDEFGL))chainB(cyclo(AEDCFGHI))';
	$pattern2 ='/chain[A|B]/';
	$result = preg_match_all($pattern2, $subject2, $item2);
	
	var_dump($result);
	var_dump($item2);
	
	var_dump(strpos($subject2, 'chainB'));
