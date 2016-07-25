<?php
	
	$id= '6MKOqxGiGU4AUk44';
    $key= 'ufu7nS8kS59awNihtjSonMETLI0KLy';
    $host = 'http://post-test.oss-cn-hangzhou.aliyuncs.com';
    $callbackUrl = "http://localhost:7091/";

    $callback_param = array('callbackUrl'=>$callbackUrl, 
                 'callbackBody'=>'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}', 
                 'callbackBodyType'=>"application/x-www-form-urlencoded");
    $callback_string = json_encode($callback_param);
	
	echo $callback_string;
	
	
	
