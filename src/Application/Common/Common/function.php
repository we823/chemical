<?php

function curl_get_file_contents($url) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

	$file_contents = curl_exec($ch);
	curl_close($ch);

	return $file_contents;
}

function curl_post($url, $content) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
	curl_exec($ch);
	curl_close($ch);
}

function checkNull($subject) {
	$isNull = is_null($subject);

	if (is_string($subject)) {
		$isNull = empty($subject);
	}

	if (is_array($subject)) {
		$isNull = is_null($subject);
	}

	return $isNull;
}

/**
 * 根据小括号获取内容
 */
function stack($subject, $debug=false) {
	$check_stack = array();

	$start_index = 0;
	$end_index = 0;

	$subject_length = strlen($subject);

	for ($index = 0; $index < $subject_length; $index++) {
		$s = substr($subject, $index, 1);
		if ($s == '(') {
			if ($start_index == 0) {
				$start_index = $index;
			}

			array_push($check_stack, $s);
		}

		if ($s == ')') {
			array_pop($check_stack);
			if (count($check_stack) == 0) {
				$end_index = $index;
				break;
			}
		}
        if($debug){
        	echo 'check_stack:';
        	var_dump($check_stack);
		    echo '<br>';
        }
		
	}

	$original = $subject;
	$content = substr($subject, $start_index + 1, $end_index - $start_index - 1);

	return array('original' => $original, 'content' => $content, 'start_index' => $start_index, 'end_index' => $end_index);
}

/**
 * 反向入栈获取（）内的信息
 */
function reserve_stack($subject, $debug=false) {
	$check_stack = array();

	$subject_length = strlen($subject);
	if ($subject_length <= 1) {
		return array(
		  'has_error'=>true,
		  'message'=>'校验字符串为空'
		);
	}

	$start_index = $subject_length;
	$end_index = $subject_length - 1;

	for ($index = $subject_length - 1; $index > 0; $index--) {
		$s = substr($subject, $index, 1);
		if ($s == ')') {
			// 若包含2个）则不满足备注条件
			if (count($check_stack) > 0) {
				return array(
				  'has_error'=>true,
				  'message'=>'包含2组括号，无需校验'
				);
			}
			array_push($check_stack, $s);
		}

		if ($s == '(') {
			array_pop($check_stack);
			if (count($check_stack) == 0) {
				$start_index = $index;
				break;
			}
		}
        if($debug){
        	echo 'check stack:';
        	var_dump($check_stack);
		    echo '<br>';
        }
		
	}

	$original = $subject;
	$content = substr($subject, $start_index + 1, $end_index - $start_index - 1);

	return array('original' => $original, 'content' => $content, 'start_index' => $start_index, 'end_index' => $end_index);
}
