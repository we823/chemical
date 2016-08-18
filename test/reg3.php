<?php
	
	$subject = 'DAEFRHDSGYEVHHQKLVFFAEDVGSNKGAIIGLMVGGVVIA(HCl salt))';
	
	$pattern = '/\)$/';
	
	echo preg_match_all($pattern, $subject, $items);
	echo '<br>';
	var_dump($items);
	
	echo '<br><br>';
	
	$result = checkMemo($subject);
	var_dump($result);
	
	function checkMemo($subject){
		$stackResult = reserve_stack($subject);
		if($stackResult==false){
			return array(
			  'hasMemo'=>false,
			  'memo'=>'error'
			);
		}
		
		$startIndex = $stackResult['startIndex'];
		if($startIndex<=6){
			return array(
			   'hasMemo'=>true,
			   'memo'=>$stackResult['amino']
			);
		}
		
		$preSubject = substr($subject, $startIndex-6);
		echo $preSubject;
		if(strpos($preSubject, 'chain')>-1 || strpos($preSubject, 'cyclo')>-1){
			return array(
			   'hasMemo'=>false,
			   'memo'=>''
			);
		}else{
			return $stackResult;
		}
	}
	
	function reserve_stack($subject){
		$check_stack = array();
		
		$subject_length = strlen($subject);
		if($subject_length<=1){
			return false;
		}
		
		$start_index = $subject_length;
		$end_index = $subject_length-1;

		for($index=$subject_length-1; $index>0; $index--){
			$s = substr($subject, $index, 1);
			if($s==')'){
				if(count($check_stack)>0){
					return false;
				}	
				array_push($check_stack, $s);
			}
			
			if($s=='('){
				array_pop($check_stack);
				if(count($check_stack)==0){
					$start_index = $index;
					break;
				}
			}
			
			var_dump($check_stack);
			echo '<br>';
		}
		
		$original = $subject;
		$amino = substr($subject, $start_index+1, $end_index - $start_index - 1);
		
		return array(
		   'original'=>$original,
		   'amino'=>$amino,
		   'startIndex'=>$start_index,
		   'endIndex'=>$end_index
		);
	}
	
