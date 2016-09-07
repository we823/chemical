<?php
/**
 * 排列P组合C，并且不能重复
 * P考虑顺序
 * C组合，不考虑顺序
 */
function getCombinationToString($arr, $m) {
	$result = array();
	if ($m == 1) {
		return $arr;
	}

	if ($m == count($arr)) {
		$result[] = implode(',', $arr);
		return $result;
	}

	$temp_firstelement = $arr[0];
	unset($arr[0]);
	$arr = array_values($arr);
	$temp_list1 = getCombinationToString($arr, ($m - 1));

	foreach ($temp_list1 as $s) {
		$s = $temp_firstelement . ',' . $s;
		$result[] = $s;
	}
	unset($temp_list1);

	$temp_list2 = getCombinationToString($arr, $m);
	foreach ($temp_list2 as $s) {
		$result[] = $s;
	}
	unset($temp_list2);

	return $result;
}

$arr = array('A','B','C','D', 'E');
$t = getCombinationToString($arr, 4);

foreach($t as $t1){
	echo $t1.'<br>';
}
