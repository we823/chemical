<?php

function show_debug($line, $param, $debug=true){
	if($debug){
		echo "<br>$line:";
		var_dump($param);
		echo "<br>";
	}
}
/**
 * 返回需要显示的结果配置
 */
function get_result_config(){
	return array(
		'solubility_result'=>array(
		   0=>'可溶于水，但放置后成凝胶',
		   1=>'分子间易聚集，可能需要有机试剂助溶',
		   2=>'水溶',
		   3=>'水可溶',
		   4=>'需要碱性缓冲液助溶',
		   5=>'需要甲酸或DMSO助溶',
		   6=>'需要碱性缓冲液和有机试剂助溶',
		   7=>'需要甲酸或DMSO助溶',
		   8=>'分子间易聚集，需要碱性缓冲液助溶'
		),
		'hydrophily_result'=>array(
		   0=>'非常亲水',
		   1=>'亲水',
		   2=>'疏水',
		   3=>'非常疏水'
		)
	);
}
function init_data() {
	vendor('PHPExcel.PHPExcel.IOFactory');
	$input_filetype = 'Excel5';
	$input_filename = './data/data.xls';

	$standard_sheetname = 'standard';
	$pk_sheetname = 'pk';
	$amino_const_sheetname = 'const';

	$obj_reader = \PHPExcel_IOFactory::createReader($input_filetype);

	$obj_PHPExcel = $obj_reader -> load($input_filename);

	$standard_sheetdata = $obj_PHPExcel -> getSheetByName($standard_sheetname) -> toArray(null, true, true, true);
	$pk_sheetdata = $obj_PHPExcel -> getSheetByName($pk_sheetname) -> toArray(null, true, true, true);
	$amino_const_sheetdata = $obj_PHPExcel -> getSheetByName($amino_const_sheetname) -> toArray(null, true, true, true);
    
    //字母数据
	$standard_data = array();
	//元素常量
	$amino_const_data = array();
	// 计算pk相关的值
	$pk_data = array();
	// nterm相关元素
	$nterm_data = array();
	// cterm相关元素
	$cterm_data = array();
	
    //需要校验的模式
	$standard_pattern = '';
	
	// 获取元素常量
	if (($const_count = count($amino_const_sheetdata)) > 0) {
		$const_values = array_values($amino_const_sheetdata);
		for ($index = 1; $index < $const_count; $index++) {
			$amino_const_data[$const_values[$index]['A']] = $const_values[$index];
		}
	}
    
	$standard_count = count($sheetStandardData);
	// 获取氨基酸数据及匹配模式
	if ($standard_count > 0) {
		// 所有元素
		$standard_values = array_values($sheetStandardData);
		$pattern_values = array();
		
		for($index=1; $index < $standard_count; $index++){
			$value = $standard_values[$index];
			$R = $value['R'];
			if($R==1){
				array_push($pattern_values, $value);
			}
			
			if($R==2){
				array_push($ntermData, $value);
			}
			
            if($R==3){
            	array_push($ctermData, $value);
            }
		}
        
		for ($index = 0, $pattern_count = count($pattern_values); $index < $pattern_count; $index++) {
			$value = $pattern_values[$index];
			$A = $value['A'];
			$B = $value['B'];
            
			$standardData[$A] = $value;
			$standardData[$B] = $value;
			
			// 特殊符号处理
			$A = str_special($A);
			$B = str_special($B);
			
			$B = '(' . $B . '\-?)';

			$A = "($A\-?)";

			$standard_pattern = $standard_pattern .$A .'|'.$B;
			if ($index < $pattern_count-1) {
				$standard_pattern = $standard_pattern . '|';
			}
		}    
	}
    
	// 计算pk相关的data值
	$pk_count=count($pk_sheetdata);
	if($pk_count > 0){
		$pk_values = array_values($pk_sheetdata);
		
		for($index=1; $index<$pk_count; $index++){
			$pk_data[$pk_sheetdata[$index]['A']] = $pk_sheetdata[$index];
		}
	}
	
	$chemicalInitData = array(
	    'standardPattern' => $standard_pattern, 
	    'standardData' => $standard_data, 
	    'aminoConstData' => $amino_const_data, 
	    'pkData' => $pk_data,
	    'ctermData' => $cterm_data,
	    'ntermData' => $nterm_data
     );
	 
	 return $chemicalInitData;
}

function aminoCheck($pattern, $checkData){
	$debug = false;
	if($debug){
		echo '模式：'.$pattern.'<br>';
	    echo '字符串:'.$checkData.'<br>';
	}
	
	$item = array();
	preg_match("/$pattern/", $checkData, $item);
	$count = count($item);
	$valid = 0;
	if($count>0){
		$result = $item[0];
		
		$b = (strlen($checkData)==strlen($result));
	    if($b){
	    	$valid = 1;
	    }
	}
	if($debug){
		echo '匹配结果：'.$valid.'<br>';
		print_r($item);
		echo '<br>';
		echo '<br>';
	}
	return $valid;
}

function calculateResult($chemicalInitData, $needCheckData){
	
	$data = $needCheckData['amino'];
	$cterm = $needCheckData['cterm'];
	$nterm = $needCheckData['nterm'];
	
	$is_valid = aminoCheck($chemicalInitData['standardPattern'], $data);
	
	$aminoLength = strlen($data);
	if($aminoLength==0){
		return array(
		   'hasError'=>true,
		   'message'=>'序列为空，无法计算'
		);
	}
	
	if($is_valid==0){
		return array(
		   'hasError'=>true,
		   'message'=>'序列不正确'
		);
	}
	
	//pk相关固定值
	$pk_data = $chemicalInitData['pkData'];
	//标准的单字母序列信息
	$standard_data = $chemicalInitData['standardData'];
	//元素分子量固定值
	$amino_const_data = $chemicalInitData['aminoConstData'];
	
	//cterm 配置
	$cterm_data = $chemicalInitData['ctermData'];
	//nterm配置
	$nterm_data = $chemicalInitData['ntermData'];
	
	$result_config = get_result_config();
	//溶解性相关的文字信息
	$solubility_results = $result_config['solubilityResult'];
	
	
	$result = array();
    $character1 = ''; //单字母
	$character3 = ''; //三字母
	$mw = 0; //平均分子量
	$em = 0; //精确分子量
	$isoelectricPoint = -7; //等电点
	
	//残基信息
	$residue = array(
	   'detail'=>array(),
	   'count'=>0,
	   'hydrophily'=>0,
	   //酸个数
	   'acidCount'=>0, 
	   //碱个数
	   'baseCount'=>0,
	   //分子量
	   'molecularWeight'=>0,
	   'c'=>0,
	   'h'=>0,
	   'o'=>0,
	   'n'=>0,
	   's'=>0,
	   'p'=>0
	);
	
	if(isset($standard_data[$cterm])){
		fillBaseInfo( $residue, $standard_data[$cterm]);
	}
	
	if(isset($standard_data[$nterm])){
		fillBaseInfo( $residue, $standard_data[$nterm]);
	}
	
	
	$hasError = false;
	$message = '';
	
	if($singleValid == 1){ //只包含单字母
    	for($index=0; $index < $aminoLength; $index++){
    		
			$standardD = null;
			// 按照1字母、2字母、3字母长度获取
			for($i=1; $i<=5; $i++){
				$c = substr($data, $index, $i);
				if(isset($standardData[$c])){
					$standardD = $standardData[$c]; //获取到残基信息
					$index = ($i==1) ? $index : ($index+$i);
					break;
				}
			}
			
			if(is_null($standardD)){
				continue;
			}
			
			if(isset($residue['detail'][$c])){
				$residue['detail'][$c]['count'] = $residue['detail'][$c]['count'] + 1;
			}else{
				$residue['detail'][$c] = array(
				    'count'=>1,
				    'name1'=>$c,
				    'name3'=>$standardD['B'],
				    'mw'=>$standardD['E']
				);
			}
			
			$residue['count']++;
			fillBaseInfo( $residue, $standardD);
			
			$character3 = $character3.$standardD['B'];
		
		
			if($index < $aminoLength-1){
				if(!empty($character1) && strlen($c)>1){
					$c = "-$c-";
				}
				$character1 = $character1.$c;
				$character3 = $character3.'-';
			}else{
				if(!empty($character1) && strlen($c)>1){
					$c = "-$c";
				}
				$character1 = $character1.$c;
			}
		}
	}else{ //包含三字母
    	$index = 0;
		while($index < $aminoLength){
			$c = '';
			$tmpData = null;
			
			if($index + 2 < $aminoLength ){

				$c = substr($data, $index, 3); //按三字母获取
				
				if(isset($threeData[$c])){
					$tmpData = $threeData[$c];
					$c = $tmpData['A'];
					$index += 3;
				}
				
				if(is_null($tmpData)){ // single
					$c = substr($data, $index, 1); //按单字母获取
					if(isset($standardData[$c])){
						$tmpData = $standardData[$c];
					    $index += 1;
					}else{
						$c = substr($data, $index, 2); //按2个字母长度计算
				        
					    if(isset($standardData[$c])){
						   $tmpData = $standardData[$c]; //获取到残基信息
						   $index = $index + 2;
					    } 
					}
				}
			
			}else{
				// 'first single....<br>';
				$c = substr($data, $index, 1); //按单字母获取
				if(isset($standardData[$c])){
					$tmpData = $standardData[$c];
				    $index += 1;
				}else{
					$c = substr($data, $index, 2); //按2个字母长度计算
			        $index += 2;
			
				    if(isset($standardData[$c])){
					   $tmpData = $standardData[$c]; //获取到残基信息
				    } 
				}
			}
			
			if(!is_null($tmpData)){
				if(isset($residue['detail'][$c])){
					$residue['detail'][$c]['count'] = $residue['detail'][$c]['count'] + 1;
				}else{
					$residue['detail'][$c] = array(
					    'count'=>1,
					    'name1'=>$c,
					    'name3'=>$tmpData['B'],
					    'mw'=>$tmpData['E']
					);
				}
				
				$residue['count']++;
				fillBaseInfo( $residue, $tmpData);
				$character3 = $character3.$tmpData['B'];
			}

			if($index < $aminoLength-1){
				if(!empty($character1) && strlen($c)>1){
					$c = "-$c-";
				}
				$character1 = $character1.$c;
				$character3 = $character3.'-';
			}else{
				if(!empty($character1) && strlen($c)>1){
					$c = "-$c";
				}
				$character1 = $character1.$c;
			}
		}
	}
	
	// H加2个，O加1个
	$residue['h']+=2;
	$residue['o']+=1;
	$residue['molecularWeight']+=18;
	
	$mw = calculateWeight($constData, 'MW', $residue);
	$em = calculateWeight($constData, 'EM', $residue);
	
	// 计算分子式
	$molecularFormula = getMolecularFormula($residue);
	
	
	$character1 = $nterm.$character1.$cterm;
	$character3 = $nterm.$character3.$cterm;
	
	$result['character1'] = $character1;
	$result['character3'] = $character3;
	$result['mw'] = sprintf("%.4f",$mw);
	$result['em'] = sprintf("%.4f",$em);
	
	// pi相关计算
	$PI = calculatePI($residue, $cterm, $nterm, $pkData);
	
	$maxY = 7;
	if(!is_null($PI)){
		$result['y'] = $PI['y'];

		$pi = is_numeric($PI['pi']) ? sprintf('%.2f',$PI['pi']) : $PI['pi'];

	    $result['isoelectricPoint'] = $pi===0 ? 0 : $pi;
		$result['pi7'] = sprintf('%.2f',$PI['pi7']);
		$result['maxY'] = $PI['maxY'];
		$result['minY'] = $PI['minY'];
	}
	
	$result['hasError'] = $hasError;
	$result['message'] = $message;
	$result['residue'] = $residue;
	$result['molecularFomula'] = $molecularFormula;
	
	$otherAmino = getOtherAmino($cterm, $nterm, $standardData);
	if(!is_null($otherAmino)){
		$result['otherAmino'] = $otherAmino;
	}
	
	// 亲水性
	$hydrophily = $residue['hydrophilyCount']/$residue['count'];
	$hydrophilyResults = $chemicalConfig['hydrophilyResult'];
	
	$hydrophilyResult = $hydrophilyResults['3'];
	if($hydrophily>1){
		$hydrophilyResult = $hydrophilyResults['0'];
	}else if($hydrophily>0 && $hydrophily<=1){
		$hydrophilyResult = $hydrophilyResults['1'];
	}else if($hydrophily>-1 && $hydrophily<=0){
		$hydrophilyResult = $hydrophilyResults['2'];
	}
	$result['hydrophily'] = $hydrophily;
	$result['hydrophilyResult'] = $hydrophilyResult;

    $solubilityResult = calculateSolubility($residue, $character1, $solubilityResults);
	$result['solubilityResult'] = $solubilityResult;
	return $result;
}

/**
 * @param type 0 平均分子量 1精确分子量
 */
function calculateWeight($constData, $type, $residue){

	$const = $constData[$type];
	if(is_null($const)){
		return 0;
	}
	
	$result = 0;
	$result += $const['B'] * $residue['c'];
	$result += $const['C'] * $residue['h'];
	$result += $const['D'] * $residue['o'];
	$result += $const['E'] * $residue['n'];
	$result += $const['F'] * $residue['s'];
	$result += $const['G'] * $residue['p'];


	return $result;
}

/**
 * 计算分子式
 */
function getMolecularFormula($residue){
	$result = '';
	if(is_null($residue) || empty($residue)){
		return $result;
	}
	
	if($residue['c']>0){
		$result = $result.'C'.$residue['c'];
	}
	
	if($residue['h']>0){
		$result = $result.'H'.$residue['h'];
	}
	
	if($residue['o']>0){
		$result = $result.'O'.$residue['o'];
	}
	
	if($residue['s']>0){
		$result = $result.'S'.$residue['s'];
	}
	
	if($residue['n']>0){
		$result = $result.'N'.$residue['n'];
	}
	
	if($residue['p']>0){
		$result = $result.'P'.$residue['p'];
	}
	
	return $result;
}

/**
 * 计算等电点（PI）及 净电荷图例
 */
function calculatePI($residue, $ctermC, $ntermC, $pkData){
	$result = null;

	if(!isset($residue) || !isset($pkData)){
		return $result;
	}
	
	$ys = array();
	$maxY = 0;
	$pi = 0;
	$pi7 = 0; //当ph=7时的净电荷数
	
	//保存y和ph的值
	$piTemp = array(); 
	$cterm = ($ctermC!='-NH2') ? 1 : 0;
	$nterm = ($ntermC!='Ac-') ? 1 : 0;
	
	$ctermData = null;
	$ntermData = null;
	
	//负值的个数
	$flag0 =0;
	//正值的个数
	$flag1 =0;
	if($cterm == 1){
		$ctermData = $pkData['C-term'];
		$flag0++;
	}
	
	if($nterm == 1){
		$ntermData = $pkData['N-term'];
		$flag1++;
	}
	$detail = $residue['detail'];
	$count = count($detail);

	foreach($detail as $k=>$value){
		if(isset($pkData[$k])){
			$tmp = $pkData[$k];
			
			if($tmp['D']==0){
				$flag0 += $value['count'];
			}else{
				$flag1 += $value['count'];
			}
		}
	}
    
	$pi = 0;
	$minY = 0;
	for($index=0; $index<=1400; $index++){
			
		$x = $index/100;
		$y = 0;
		
		if(!is_null($ctermData)){
			$y += calculateSinglePi($x, $ctermData['C'], 1, $ctermData['D']);
		}
		
		if(!is_null($ntermData)){
			$y += calculateSinglePi($x, $ntermData['C'], 1, $ntermData['D']);
		}
		
		if($count==0){
			continue;
		}
		foreach($detail as $k=>$value){
			if(isset($pkData[$k])){
				$tmp = $pkData[$k];
				
				$y += calculateSinglePi($x, $tmp['C'], $value['count'], $tmp['D']);
			}
		}
		
		$y = round($y, 4);
		
		if($index==0){
			$minY = abs($y);
		}
		if(abs($y)<=$minY){
			$minY = abs($y);
			$pi = $x;
		}
		array_push($ys, array($x,$y));
		
		$maxY = (abs($y)>$maxY) ? abs($y) : $maxY;
		if(is_null($piTemp[$y])){
			$piTemp[$y] = array();
		}
		//$piTemp[$y] = $x;
		
		array_push($piTemp[$y], $x);
		if($x==7){
			$pi7 = $y;
		}
	}
	$result['y'] = $ys;
	
	if($flag0==0 && $flag1==0){
		$pi = '此序列不含可电离基团';
	}
	
	if($flag0 == 0 && $flag1 > 0 ){
		$pi = '此序列为碱性序列，只带正电荷';
	}

    if($flag0>0 && $flag1==0){
    	$pi = '此序列为酸性序列，只带负电荷';
    }
    
	/**
    // 计算等电点
	if(isset($piTemp['0'])){
		
		$pi = $piTemp['0'];
		
	}else{
		// 取所有值中绝对值最小的值
		$minPH = 0;
		
		foreach($piTemp as $k=>$v){
			$tmpValue =abs($k);
			if($tmpValue < $minPH){
				$minPH = $tmpValue;
				$pi=$v;
			}
		}

	}
    **/
	$result['pi'] = $pi;
	$result['pi7'] = $pi7;
	$result['maxY'] = $flag1;
	$result['minY']= $flag0;
	return $result;
}

/**
 * pi值计算
 * @param $x x轴的值，0-14
 * @param $pk pk值，根据固定给出的值
 * @param $flag 公式计算标记，0为负计算，1为正计算
 */
function calculateSinglePi($x, $pk, $num, $flag){
	$y = 0;
	if($num == 0){
		return $y;
	}
	
	if($flag==0){
		$y = -$num / ( pow(10, $pk - $x) + 1 );
	}else{
		$y = $num / ( pow(10, $x-$pk) + 1 );
	}
	
	return $y;
}

/**
 * 根据字母获取常量
 * @param $amino 字母序列
 * @param $pkData pk固定值
 * @return pk,flag
 */
function getPK($amino, $pkData){
	if(isset($pkData[$amino])){
		return $pkData[$amino];
	}else{
		return null;
	}
}

/**
 * 计算溶水性
 */
function calculateSolubility($residue, $character1, $solubilityResults){
	
	$aminoCount = $residue['count'];
	$acidCount = $residue['acidCount'];
	$baseCount = $residue['baseCount'];
	$hydrophily = $residue['hydrophilyCount'] / $aminoCount;
	
	$solubilityResult = '';
	
    // 特殊序列检查
	$specials = ['RADA','TSTS','IKIE','QQQ','NNNNN','DSSDSS'];
	$pattern = '';
	for($index=0, $len = count($specials); $index<$len; $index++){
		$pattern = '(' . $specials[$index] .')';
		$speicalItems = array();
	    $specialValid = preg_match_all("/$pattern/", $character1, $speicalItems);

		if($specialValid>=2){
		    $solubilityResult = $solubilityResults[0];
		    return $solubilityResult;
	     }	
	}

	// 氨基酸总个数大于10
	if($aminoCount>10){
		$specialAminos = ['D','E','N','Q','R','S','T','Y'];
		$details = $residue['detail'];
		$specialCount = 0;
		foreach($specialAminos as $amino){
			if(isset($details[$amino])){
				$specialCount += $details[$amino]['count'];
			}
		}

		if(($specialCount / $aminoCount) > 0.6){
			$D = isset($details['D']) ? $details['D']['count'] : 0;
			$E = isset($details['E']) ? $details['E']['count'] : 0;
			
			$solubilityResult = $solubilityResults[8];
			
			if($D/$aminoCount<0.25 || $E/$aminoCount<0.25){
				$solubilityResult = $solubilityResults[1];
			}
			
			if($D/$aminoCount>=0.25 || $E/$aminoCount>=0.25){
				$solubilityResult = $solubilityResults[8];
			}
			
			return $solubilityResult;
		}
	}

    if($aminoCount<=5 && $hydrophily>-0.5){
    	return $solubilityResults[2];
    }
	
	if($hydrophily > 0){
		$solubilityResult = $solubilityResults['2'];
	}else if($hydrophily<=0 && $hydrophily>-1){
		$solubilityResult = $solubilityResults['5'];
		if( ($baseCount - $acidCount) >= 2){
			$solubilityResult = $solubilityResults['3'];
		}
		
		if( ($acidCount - $baseCount)>=2 || ($acidCount>0 && $baseCount==0) ){
			$solubilityResult = $solubilityResults['4'];
		}
	}else if($hydrophily<=-1){
		$solubilityResult = $solubilityResults['7'];
		
		if( ($acidCount - $baseCount)>2 || ($acidCount>0 && $baseCount==0)){
			$solubilityResult = $solubilityResults['6'];
		}
	}
	
	return $solubilityResult;
}

/**
 * 根据给出的字母，查找并填充亲水性、酸基个数、碱基个数、CHONSP的个数
 */
function fillBaseInfo(& $residue, $standardD){
	if(is_null($standardD)){
		return;
	}
	
	$residue['hydrophilyCount'] += $standardD['L'];
	$residue['acidCount'] += $standardD['P'];
	$residue['baseCount'] += $standardD['Q'];
	
	$residue['molecularWeight'] += $standardD['E'];
	
	$residue['c'] += $standardD['F'];
	$residue['h'] += $standardD['G'];
	$residue['n'] += $standardD['H'];
	$residue['o'] += $standardD['I'];
	$residue['s'] += $standardD['J'];
	$residue['p'] += $standardD['K'];
}

function getOtherAmino($cterm, $nterm, $standData){

	$otherAmino = array();
	$hasData = 0;
	if($nterm!='H-'){
		if(isset($standData[$nterm])){
			$hasData++;
			$tempData = $standData[$nterm];
			$name3 = str_replace('-', '', $tempData['B']);
			$otherAmino[$nterm] = array(
			   'name1'=>$nterm,
			   'name3'=>$name3,
			   'count'=>1,
			   'mw'=>$tempData['E']
			);
		}
	}
	
	if($cterm!='-OH'){
		if(isset($standData[$cterm])){
			$hasData++;
			$tempData = $standData[$cterm];
			$name3 = str_replace('-', '', $tempData['B']);
			$otherAmino[$cterm] = array(
			   'name1'=>$cterm,
			   'name3'=>$name3,
			   'count'=>1,
			   'mw'=>$tempData['E']
			);
		}
	}

   if($hasData==0){
   	  $otherAmino = null;
   }
	
	return $otherAmino;
	
}

/**
 * 将subject中用于校验的特殊字符转换，如()-
 * @param subject mixed
 */
function str_special($subject){
	$subject = str_replace('-','\-',$subject);
	$subject = str_replace('(', '\(', $subject);
	$subject = str_replace(')', '\)', $subject);
	return $subject;
}
