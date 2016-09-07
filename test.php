<?php
	
$arr = array('a','b','c','d');  
$result = array();  
$t = getCombinationToString($arr, 4);  
foreach($t as $t1){
	echo $t1.'<br>';
}  
  
function getCombinationToString($arr, $m) {  
    if ($m ==1) {  
       return $arr;  
    }  
    $result = array();  
      
    $tmpArr = $arr;  
    unset($tmpArr[0]);  
    for($i=0;$i<count($arr);$i++) {  
        $s = $arr[$i];  
        $ret = getCombinationToString(array_values($tmpArr), ($m-1), $result);  
          
        foreach($ret as $row) {  
            $result[] = $s . $row;  
        }  
    }  
      
    return $result;  
}