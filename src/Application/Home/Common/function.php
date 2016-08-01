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
		   1=>'分子间易聚集，需要酸性缓冲液助溶',
		   2=>'分子间易聚集，需要碱性缓冲液助溶',
		   3=>'分子间易聚集，可能需要有机试剂助溶',
		   4=>'水溶',
		   5=>'水溶，但放置后可能会成多聚体',
		   6=>'水可溶，但放置后可能会成多聚体',
		   7=>'水可溶',
		   8=>'需要碱性缓冲液助溶',
		   9=>'需要甲酸或DMSO助溶',
		   10=>'需要碱性缓冲液和有机试剂助溶',
		   11=>'需要甲酸或DMSO助溶',
		   12=>'难溶'
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
	// 氨基酸残基元素最长个数
	$amino_max_length = 1;
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
    
	$standard_count = count($standard_sheetdata);
	// 获取氨基酸数据及匹配模式
	if ($standard_count > 0) {
		// 所有元素
		$standard_values = array_values($standard_sheetdata);
		$pattern_values = array();
		
		for($index=1; $index < $standard_count; $index++){
			$value = $standard_values[$index];
			
			$A = $value['A'];
			$B = $value['B'];
			
			// 获取最长氨基酸字符长度
			$a_length = strlen($A);
			$b_length = strlen($B);
			$a_length = ($a_length>$b_length) ? $a_length : $b_length;
			$amino_max_length = ($a_length > $amino_max_length) ? $a_length : $amino_max_length;
            
			$standard_data[$A] = $value;
			$standard_data[$B] = $value;
			
			$R = $value['R'];
			if($R==1){
				array_push($pattern_values, $value);
			}
			
			if($R==2){
				array_push($nterm_data, $value);
			}
			
            if($R==3){
            	array_push($cterm_data, $value);
            }
		}
        
		// 获取正则表达式
		/*
		for ($index = 0, $pattern_count = count($pattern_values); $index < $pattern_count; $index++) {
			$value = $pattern_values[$index];
			
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
		 */ 
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
	    'aminoMaxLength'=>$amino_max_length+1,
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
/**
 * 将序列转换为数组
 */
function amino_to_array($chemical_init_data, $check_amino){
	$standard_data = $chemical_init_data['standardData'];

	$amino_max_length = $chemical_init_data['aminoMaxLength'];
	
	$result = array();
	$amino_length = strlen($check_amino);
	$index = 0;
    
	if(is_null($standard_data) || empty($standard_data)){
		return array(
		  'hasError'=>true,
		  'message'=>'可校验的标准数据为空，无法校验'
		);
	}
	
	while($index < $amino_length){

		$current_amino_length = strlen($check_amino);
		$sub_length = ($amino_max_length < $current_amino_length) ? $amino_max_length : $current_amino_length;
		$sub_amino_result = get_sub_amino($standard_data, $amino_max_length, $check_amino);
		
		if(is_null($sub_amino_result)){
			return array(
			   'hasError'=>true,
			   'message'=>'校验错误,未获取正确的子序列结果'
			);
		}
		
		if($sub_amino_result['hasError']){
			return $sub_amino_result;
		}
		
		$sub_amino = $sub_amino_result['sub_amino'];

		if(array_key_exists($sub_amino, $standard_data)){
		     array_push($result, $sub_amino);
			 $sub_amino_length = $sub_amino_result['real_length'];
			 $index = $index + $sub_amino_length;
			 $check_amino = substr($check_amino, $sub_amino_length);
		}
	}
	$valid_result = array(
	   'hasError'=>false,
	   'message'=>'校验正确',
	   'aminoDetail'=>$result
	);

	return $valid_result;
}

/**
 * 获取正确的子序列
 */
function get_sub_amino($standard_data, $amino_max_length, $check_amino){
	$length = strlen($check_amino);
	$sub_length = ($amino_max_length > $length) ? $length : $amino_max_length;
    $real_length = $sub_length;
	
	$tmp_check_amino = $check_amino;
	
	if(strpos($tmp_check_amino, '-')===0 && strlen($tmp_check_amino)>0){
		if(array_key_exists($tmp_check_amino, $standard_data)){
			return array(
			   'sub_amino'=>$tmp_check_amino,
			   'real_length'=>$real_length,
			   'hasError'=>false,
			   'message'=>'正确匹配'
			);
		}
		$tmp_check_amino = substr($tmp_check_amino, 1);
		$sub_length--;
	}
	
	$sub_amino = substr($tmp_check_amino, 0, $sub_length);
	
	if(array_key_exists($sub_amino, $standard_data)){
		return array(
		   'sub_amino'=>$sub_amino,
		   'real_length'=>$real_length,
		   'hasError'=>false,
		   'message'=>'正确匹配'
		);
	}else{
		if($amino_max_length<=0){
			return array(
			   'hasError'=>true,
			   'message'=>"字符：$check_amino 无法完成匹配"
			);
		}
		$amino_max_length = $amino_max_length - 1;
		return get_sub_amino($standard_data, $amino_max_length, $check_amino);
	}
}

/**
 * 检查2硫键
 */
function checkS2($amino_result_data, $s2){
	$s2 = trim($s2);
	$s2 = str_replace('，', ',', $s2);
	$s2 = str_replace('-', ',', $s2);
	
	$slist = split(',', $s2);
	
	$hasValid = true;
	$message = '二硫键位置正确'; 
	
	foreach($slist as $s){
		if(is_numeric($s)){
			$amino= $amino_result_data[intval($s-1)];
		}else{
			$hasValid = false;
			$message = '输入的二硫键值不是数字：'.$s2;
		}
		
		if(is_null($amino)){
			$hasValid = false;
			$message = '位置'.$s.'不存在氨基酸';
			break;
		}else{
			if($amino!='C'){
				$hasValid = false;
				$message = '二硫键位置不正确,位置'.$s.'的氨基酸为：'.$amino;
				break;
			}
		}
	}
	
	if(!$hasValid){
		return array(
		   'hasError'=>true,
		   'message'=>$message
		);
	}
}
function calculateResult($chemicalInitData, $needCheckData){
	
	$data = $needCheckData['amino'];
	$cterm = $needCheckData['cterm'];
	$nterm = $needCheckData['nterm'];
	
	$valid_result = amino_to_array($chemicalInitData, $data);
    if($valid_result['hasError']){
    	return $valid_result;
    }

	$aminoLength = strlen($data);
	if($aminoLength==0){
		return array(
		   'hasError'=>true,
		   'message'=>'序列为空，无法计算'
		);
	}

	$amino_result_data = $valid_result['aminoDetail'];
	//pk相关固定值
	$pk_data = $chemicalInitData['pkData'];
	//标准的单字母序列信息
	$standard_data = $chemicalInitData['standardData'];
	
	$s2 = I('s2');

	if(!is_null($s2) && strlen($s2)>0){
		$s2Result = checkS2($amino_result_data, $s2);
		if($s2Result['hasError']){
			return $s2Result;
		}
	}
	//元素分子量固定值
	$amino_const_data = $chemicalInitData['aminoConstData'];
	
	//cterm 配置
	$cterm_data = $chemicalInitData['ctermData'];
	//nterm配置
	$nterm_data = $chemicalInitData['ntermData'];
	
	// 亲水性及溶解性文字结果配置
	$result_config = get_result_config();

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
    
	$preAmino = '';
	
	for($index=0, $result_data_count = count($amino_result_data); $index<$result_data_count; $index++){
		$amino = $amino_result_data[$index];
		$standard = $standard_data[$amino];
		
		$singleAmino = $standard['A'];
		$otherAmino = $standard['B'];
		
		if(isset($residue['detail'][$singleAmino])){
			$residue['detail'][$singleAmino]['count'] = $residue['detail'][$singleAmino]['count'] + 1;

		}else{
			$residue['detail'][$singleAmino] = array(
			    'count'=>1,
			    'name1'=>$singleAmino,
			    'name3'=>$otherAmino,
			    'mw'=>$standard['E']
			);
		}
		
		$residue['count']++;
		fillBaseInfo( $residue, $standard);
		
		if(strlen($character1)==0){
			$character1 = $singleAmino;
			$preAmino = $singleAmino;
		}else{

			if(strlen($singleAmino)>1){
			   $singleAmino = '-'.$singleAmino;
			}else{
				if(strlen($preAmino)>1){
					$singleAmino = '-'.$singleAmino;
				}
			}
			$character1 = $character1.$singleAmino;
			$preAmino = str_replace('-', '', $singleAmino);
		}
		
		if(strlen($character3)==0){
			$character3 = $otherAmino;
		}else{
			if(strlen($otherAmino)>1){
				$otherAmino = '-'.$otherAmino;
			}
			$character3 = $character3.$otherAmino;
		}
	}
	
	// H加2个，O加1个
	$residue['h']+=2;
	$residue['o']+=1;
	$residue['molecularWeight']+=18;
	
	$mw = calculateWeight($amino_const_data, 'B', $residue);
	$em = calculateWeight($amino_const_data, 'C', $residue);
	
	// 计算分子式
	$molecularFormula = getMolecularFormula($residue);
	
	$character1 = $nterm.$character1.$cterm;
	$character3 = $nterm.$character3.$cterm;
	
	$result['character1'] = $character1;
	$result['character3'] = $character3;
	$result['mw'] = sprintf("%.4f",$mw);
	$result['em'] = sprintf("%.4f",$em);
	
	// pi相关计算
	$PI = calculatePI($residue, $cterm, $nterm, $pk_data);
	
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
	
	$otherAmino = getOtherAmino($cterm, $nterm, $standard_data);
	if(!is_null($otherAmino)){
		$result['otherAmino'] = $otherAmino;
	}
	
	// 亲水性
	$hydrophily = round($residue['hydrophilyCount']/$residue['count'],2);
	$hydrophilyResults = $result_config['hydrophily_result'];
	
	$hydrophilyResult = $hydrophilyResults[3];
	if($hydrophily>1){
		$hydrophilyResult = $hydrophilyResults[0];
	}else if($hydrophily>0 && $hydrophily<=1){
		$hydrophilyResult = $hydrophilyResults[1];
	}else if($hydrophily>-1 && $hydrophily<=0){
		$hydrophilyResult = $hydrophilyResults[2];
	}
	$result['hydrophily'] = $hydrophily;
	$result['hydrophilyResult'] = $hydrophilyResult;
    
	//溶解性相关的文字信息
	$solubility_results = $result_config['solubility_result'];
	$result_index = calculateSolubility($residue, $character1, $standard_data, $solubility_results);
    $solubilityResult = $solubility_results[$result_index];
	$result['solubilityResult'] = $solubilityResult;
	$result['solubilityIndex'] = $result_index;
	return $result;
}

/**
 * @param type 0 平均分子量 1精确分子量
 */
function calculateWeight($constData, $type, $residue){

	$const = $constData;
	if(is_null($const)){
		return 0;
	}
	
	$result = 0;

	$result += $const['C'][$type] * $residue['c'];
	$result += $const['H'][$type] * $residue['h'];
	$result += $const['O'][$type] * $residue['o'];
	$result += $const['N'][$type] * $residue['n'];
	$result += $const['S'][$type] * $residue['s'];
	$result += $const['P'][$type] * $residue['p'];

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
function calculateSolubility($residue, $character1, $standardData, $solubilityResults){
	
	$y = $aminoCount = $residue['count'];
	$acidCount = $residue['acidCount'];
	$baseCount = $residue['baseCount'];
	$x = $hydrophily = $residue['hydrophilyCount'] / $aminoCount;
	
	$result_index = -1;
	
    // 特殊序列检查
	$specials = ['RADA','TSTS','IKIE','QQQ','NNNNN','DSSDSS'];
	$pattern = '';
	for($index=0, $len = count($specials); $index<$len; $index++){
		$pattern = '(' . $specials[$index] .')';
		$speicalItems = array();
	    $specialValid = preg_match_all("/$pattern/", $character1, $speicalItems);

		if($specialValid>=2){
		    return 0;
	     }	
	}

	// 氨基酸总个数大于等于10
	if($y >= 10){
		$specialAminos = ['D','E','N','Q','R','S','T','Y'];
		$details = $residue['detail'];
		$specialCount = 0;
		foreach($specialAminos as $amino){
			if(isset($details[$amino])){
				$specialCount += $details[$amino]['count'];
			}
		}

		if(($specialCount / $y) > 0.6){
			$acidCount = $residue['acidCount'];
			$baseCount = $residue['baseCount'];
            
			$abPercent = ($acidCount + $baseCount)/$y;
			if( $abPercent <= 0.4 ){
				$result_index = 3;
				
				if( ($baseCount / $y)>=0.25){
					$result_index = 1;
				}
				
				if( ($acidCount/$y)>=0.25){
					$result_index = 2;
				}
				
				return $result_index;
		     }
	    }
	}

    if($y<=5 && $x>-0.5){
    	return 4;
    }
	
	$amino_details = $residue['detail'];
	$amino_detail_keys = array_keys($amino_details);
	
	if($x>0 && $x<=0.5){
		// 需要计算连续8个氨基酸的亲水性<=0
		$acidAminoCount = 0;
		$firstIndex = 0;
		for($index=0, $amino_detail_count=count($amino_details); $index<$amino_detail_count; $index++){
			$standard = $standardData[$amino_detail_keys[$index]];
			$L = $standard['L'];
			if($L<=0){
				if($firstIndex==0){
					$firstIndex = $index;
				}
				
				$acidAminoCount++;
			}else{
				if($firstIndex==0){
					continue;
				}else{
					break;
				}
			}
		}
		if($acidAminoCount>=8){
			return 5;
		}
		
	}
	
	if($x > 0){

		return 4;
		
	}else if($x<=0 && $x>-1){
		
		$result_index = 9;

		if( ($baseCount - $acidCount) >= 2 ){
			// 需要计算连续6个氨基酸的亲水性<=0
			$acidAminoCount = 0;
			$firstIndex = 0;
			for($index=0, $amino_detail_count=count($amino_details); $index<$amino_detail_count; $index++){
				$standard = $standardData[$amino_detail_keys[$index]];
				$L = $standard['L'];
				if($L<=0){
					if($firstIndex==0){
						$firstIndex = $index;
					}
					
					$acidAminoCount++;
				}else{
					if($firstIndex==0){
						continue;
					}else{
						break;
					}
				}
			}

			if($acidAminoCount>=6){
				return 6;
			}
		}
		if( ($baseCount - $acidCount) >= 2){
			return 7;
		}
		
		if( ($acidCount - $baseCount)>=2 || ($acidCount>0 && $baseCount==0) ){
			$solubilityResult = $solubilityResults[8];
			return 8;
		}
		return $result_index;
	}else if($x<=-1 && $x>-2){
		
		$result_index = 11;
		
		if( ($acidCount - $baseCount)>=2 || ($acidCount>0 && $baseCount==0)){
			$result_index = 10;
		}
		return $result_index;
	}else if($x<=-2){
		$result_index = 12;
	}
	
	return $result_index;
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
