<?php
function request($name, $default='', $method='request'){
	$method = strtolower($method);
	switch($method){
		case 'get':
			return isset($_GET[$name]) ? $_GET[$name] : $default;
			break;
		case 'post':
			return isset($_POST[$name]) ? $_POST[$name] : $default;
			break;
		default:
			return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
	}
}

function showDebug($line, $param, $debug=true){
	if($debug){
		echo "<br>$line:";
		var_dump($param);
		echo "<br>";
	}
}

function getConfig(){
	return array(
	   'nterm'=>array(
		   '0'=>'H',
		   '1'=>'Ac',
		   '2'=>'Biotinyl',
		   '3'=>'Pyr'
		),
		'cterm'=>array(
		   '1'=>'OH',
		   '0'=>'NH2'
		),
		'solubilityResult'=>array(
		   '0'=>'可溶于水，但放置后成凝胶',
		   '1'=>'分子间易聚集，可能需要有机试剂助溶',
		   '2'=>'水溶',
		   '3'=>'水可溶',
		   '4'=>'需要碱性缓冲液助溶',
		   '5'=>'需要甲酸或DMSO助溶',
		   '6'=>'需要碱性缓冲液和有机试剂助溶',
		   '7'=>'需要甲酸或DMSO助溶'
		),
		'hydrophilyResult'=>array(
		   '0'=>'非常亲水',
		   '1'=>'亲水',
		   '2'=>'疏水',
		   '3'=>'非常疏水'
		)
	);
}
function initData() {
	vendor('PHPExcel.PHPExcel.IOFactory');
	$inputFileType = 'Excel5';
	$inputFileName = './data/data.xls';

	$sheetStandard = 'standard';
	$sheetPK = 'pk';
	$sheetConst = 'const';

	$objReader = \PHPExcel_IOFactory::createReader($inputFileType);

	$objPHPExcel = $objReader -> load($inputFileName);

	$sheetData = $objPHPExcel -> getSheetByName($sheetStandard) -> toArray(null, true, true, true);
	$sheetPkData = $objPHPExcel -> getSheetByName($sheetPK) -> toArray(null, true, true, true);
	$sheetConstData = $objPHPExcel -> getSheetByName($sheetConst) -> toArray(null, true, true, true);

	$count = count($sheetData);

    //单字母数据
	$standardData = array();
	//三字母数据
	$threeData = array();
	//元素常量
	$constData = array();
	
    //混合模式
	$allPattern = '';
	//三字母模式
	$threePattern = '';
	//单字母模式
	$singlePattern = '';
	
	// 获取元素常量
	if (($constCount = count($sheetConstData)) > 0) {
		$values = array_values($sheetConstData);
		for ($index = 1; $index < $constCount; $index++) {
			$constData[$values[$index]['A']] = $values[$index];
		}
	}

	// 获取氨基酸数据及匹配模式
	if ($count > 0) {
		$values = array_values($sheetData);
		for ($index = 1; $index < $count; $index++) {
			$value = $values[$index];

			$B = $value['B'];

			$standardData[$value['A']] = $value;
			$threeData[$B] = $value;

			if ($value['R'] == 1) {
				$threePattern = $threePattern . '(' . $B . ')';

				$A = $value['A'];
				if (strlen($A) > 1) {
					$A = "($A)";
				}

				$singlePattern = $singlePattern . $A;

				if ($index < $count - 1) {
					$threePattern = $threePattern . '|';
					$singlePattern = $singlePattern . '|';
				}
			}

		}
	}

	$allPattern = "[$singlePattern|$threePattern]+";
	$singlePattern = "[$singlePattern]+";
	$threePattern = "[($threePattern)]+";

    $pkData = array();
	
	if(count($sheetPkData)>0){
		$pkValues = array_values($sheetPkData);
		for($index=1; $index<$count; $index++){
			$pkData[$sheetPkData[$index]['A']] = $sheetPkData[$index];
		}
	}
	
	$chemicalData = array(
	    'single' => $singlePattern, 
	    'three' => $threePattern, 
	    'all' => $allPattern, 
	    'standardData' => $standardData, 
	    'threeData' => $threeData, 
	    'constData' => $constData, 
	    'pkData' => $pkData
     );
	 
	 return $chemicalData;
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

function calculateResult($config, $needCheckData){
	
	$data = $needCheckData['amino'];
	$cterm = $needCheckData['cterm'];
	$nterm = $needCheckData['nterm'];
	
	$singleValid = aminoCheck($config['single'], $data);
	$allValid = aminoCheck($config['all'], $data);
	
	if($allValid==0){
		return array(
		   'hasError'=>true,
		   'message'=>'序列不正确';
		);
	}
	
	//pk相关固定值
	$pkData = $config['pkData'];
	//标准的单字母序列信息
	$standardData = $config['standardData'];
	$threeData = $config['threeData'];
	//元素分子量固定值
	$constData = $config['constData'];
	
	$chemicalConfig = getConfig();
	//cterm 配置
	$ctermConfig = $chemicalConfig['cterm'];
	//nterm配置
	$ntermConfig = $chemicalConfig['nterm'];
	//溶解性相关的文字信息
	$solubilityResults = $chemicalConfig['solubilityResult'];
	
	
	$result = array();
    $character1 = ''; //单字母
	$character3 = ''; //三字母
	$moleculaWeight = 0; //分子量
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
	   'c'=>0,
	   'h'=>0,
	   'o'=>0,
	   'n'=>0,
	   's'=>0,
	   'p'=>0
	);
	
	//当cterm为OH
	if($cterm==1){
		$residue['acidCount'] += 1;
	}
	//当nterm为H
	if($nterm==0){
		$residue['baseCount'] += 1;
	}
	
	$hasError = false;
	$message = '';
	
	if($singleValid == 1){ //只包含单字母
	
    	for($index=0, $lenth=strlen($data); $index<$length; $index++){
    		
    		$c = substr($data, $index, 1);
			$standardD = null;
			if(isset($standardData[$c])){
				$standardD = $standardData[$c]; //获取到残基信息
			}
			
			if(is_null($standardD)){
				$c = substr($data, $index, 2); //按2个字母长度计算
				$index = $index + 2;
				
				if(isset($standardData[$c])){
					$standardD = $standardData[$c]; //获取到残基信息
				}
			}
			
			if(is_null($standardD)){
				$c = substr($data, $index, 3); //按三个字母长度计算
			    $index = $index + 3;
				
			    if(isset($standardData[$c])){
					$standardD = $standardData[$c]; //获取到残基信息
				}
			}
			
			for($i=1; $i<=3; $i++){
				$c = substr($data, $index, 1);
				if(isset($standardData[$c])){
					$standardD = $standardData[$c]; //获取到残基信息
					$index = ($i==1) ? $index : ($index+$i);
					break;
				}
			}
			
			if(!is_null($standardD)){
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
				$residue['hydrophilyCount'] += $standardD['L'];
				$residue['count']++;
				$residue['c'] += $standardD['F'];
				$residue['o'] += $standardD['I'];
				$residue['h'] += $standardD['G'];
				$residue['n'] += $standardD['H'];
				$residue['s'] += $standardD['J'];
				$residue['p'] += $standardD['K'];
				
				$character3 = $character3.$standardD['B'];
				
				$moleculaWeight += $standardD['E'];

			}
			
			
			
			if($index < $length-1){
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
        $moleculaWeight += 18;
	}else{ //包含三字母
		$length = strlen($data);
	    if($length>0){
	    	$index = 0;
			while($index<$length){
				$c = '';
				$tmpData = null;
				
				if($index + 2 < $length ){

					$c = substr($data, $index, 3); //按三字母获取
					
					if(isset($threeData[$c])){
						$tmpData = $threeData[$c];
					}
					
					if(!is_null($tmpData)){
						$c = $tmpData['A'];
						$index = $index + 3;
					}else{ // single
						$c = substr($data, $index, 1); //按单字母获取
						if(isset($standardData[$c])){
							$tmpData = $standardData[$c];
						    $index += 1;
						}else{
							$c = substr($data, $index, 2); //按2个字母长度计算
					        $index = $index + 2;
					
						    if(isset($standardData[$c])){
							   $tmpData = $standardData[$c]; //获取到残基信息
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
				        $index = $index + 2;
				
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
					$residue['hydrophilyCount'] += $tmpData['L'];
					$residue['count']++;
					$residue['c'] += $tmpData['F'];
					$residue['o'] += $tmpData['I'];
					$residue['h'] += $tmpData['G'];
					$residue['n'] += $tmpData['H'];
					$residue['s'] += $tmpData['J'];
					$residue['p'] += $tmpData['K'];
					
					$character3 = $character3.$tmpData['B'];
					
					$moleculaWeight += $tmpData['E'];

				}

				if($index < $length-1){
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
        $moleculaWeight += 18;
	}
	
	$mw = calculateWeight($constData, 'MW', $residue);
	$em = calculateWeight($constData, 'EM', $residue);
	
	// 计算分子式
	$molecularFormula = getMolecularFormula($residue);
	
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
	
	
	$character1 = $ntermConfig[$nterm].$character1.$ctermConfig[$cterm];
	$character3 = $ntermConfig[$nterm].$character3.$ctermConfig[$cterm];
	
	$result['character1'] = $character1;
	$result['character3'] = $character3;
	$result['molecularWeight'] = $moleculaWeight;
	$result['mw'] = round($mw,4);
	$result['em'] = round($em,4);
	
	// pi相关计算
	$PI = calculatePI($residue, $cterm, $nterm, $pkData);
	
	$maxY = 7;
	if(!is_null($PI)){
		$result['y'] = $PI['y'];
	    $result['isoelectricPoint'] = $PI['pi'];
		$result['pi7'] = $PI['pi7'];
		$result['maxY'] = $PI['maxY'];
	}
	
	$result['hasError'] = $hasError;
	$result['message'] = $message;
	$result['residue'] = $residue;
	$result['molecularFomula'] = $molecularFormula;
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

	$result += $const['C'] * 2;
	$result += $const['D'] * 1;

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
		$result = $result.'H'.($residue['h']+2);
	}else{
		$result = $result.'H2';
	}
	
	if($residue['o']>0){
		$result = $result.'O'.($residue['o']+1);
	}else{
		$result = $result.'O1';
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
function calculatePI($residue, $cterm, $nterm, $pkData){
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
	$cterm = ($cterm==1) ? $cterm : 0;
	$nterm = ($nterm==1) ? 0 : 1;
	
	$ctermData = null;
	$ntermData = null;
	
	
	if($cterm == 1){
		$ctermData = $pkData['C-term'];
	}
	
	if($nterm == 1){
		$ntermData = $pkData['N-term'];
	}
	
	
	$detail = $residue['detail'];

	$count = count($detail);
	
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
		array_push($ys, array($x,$y));
		
		$maxY = (abs($y)>$maxY) ? abs($y) : $maxY;
		
		$piTemp[strval($y)] = $x;
		
		if($x==7){
			$pi7 = round($y,4);
		}
	}
	$result['y'] = $ys;

    // 计算等电点
	if(isset($piTemp['0'])){
		$pi = $piTemp['0'];
	}else{
		// 取所有值中绝对值最小的值
		$count = count($piTemp);
		$minPH = 14;
		
		foreach($piTemp as $k=>$v){
			$tmpValue =abs($k);
			if($tmpValue < $minPH){
				$minPH = $tmpValue;
				$pi=$v;
			}
		}

	}
	
	$result['pi'] = $pi;
	$result['pi7'] = $pi7;
	$result['maxY'] = round($maxY,2);
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
	$baseCount = $resiude['acidCount'];
	$hydrophily = $residue['hydrophilyCount'] / $aminoCount;
	
	$solubilityResult = '';
	
    // 特殊序列检查
	$specials = ['RADA','TSTS','IKIE','QQQQ','NNNNN','DSSDSS'];
	$pattern = '';
	for($index=0, $len = count($specials); $index<$len; $index++){
		$pattern = $pattern . '(' . $specials[$index] .'){2,}';
		if($index<$len-1){
			$pattern = $pattern . '|';	
		}
			
	}
	$speicalItems = array();
	$specialValid = preg_match_all("/$pattern/", $character1, $speicalItems);
	
	if($specialValid>0){
		$solubilityResult = $solubilityResults[0];
		return $solubilityResult;
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
			$solubilityResult = $solubilityResults[1];
			return $solubilityResult;
		}
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

