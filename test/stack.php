<?php
	
	$subject = 'A-Cyclo(Ac-MFQRDEC)-ALV';
	$check_stack = array();
	
	$start_index = 0;
	$end_index = 0;
	
	$subject_length = strlen($subject);
	
	for($index=0; $index<$subject_length; $index++){
		$s = substr($subject, $index, 1);
		if($s=='('){
			if($start_index==0){
				$start_index = $index;
			}
			
			array_push($check_stack, $s);
		}
		
		if($s==')'){
			array_pop($check_stack);
			if(count($check_stack)==0){
				$end_index = $index;
			}
		}
		
		var_dump($check_stack);
		echo '<br>';
	}
	
	$i = strpos(strtolower($subject),'cyclo');

    echo "cyclo位置为 $i  $start_index - $end_index<br>";
	echo $subject.'<br>';
	echo substr($subject, $start_index+1, $end_index - $start_index - 1);
