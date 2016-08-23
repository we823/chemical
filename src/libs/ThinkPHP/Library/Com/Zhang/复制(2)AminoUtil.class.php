<?php
	
	namespace Com\Zhang;
	
	class AminoUtil{
		
		private $subject;
		private $aminoSpecial = null;
		private $chemicalDatas = null;
		
		private $standardIndex = null;
		
		public function __construct($subject, $standardIndex, $cycloType=-1){
			
			$this->subject = trim($subject);
			$this->standardIndex = $standardIndex;
			$this->aminoSpecial = new \Common\Model\AminoSpecialModel;
			$this->aminoSpecial->cycloType = $cycloType;
		}
		
		public function instance(){
			return $this->aminoSpecial;
		}
		/**
		 * 初始化excel表格相关的数据
		 */
		public function initData($filename){
			vendor('PHPExcel.PHPExcel.IOFactory');
			$input_filetype = 'Excel5';
			$input_filename = $filename; //'./data/data.xls';
		
			$standard_sheetname = 'standard';
			$pk_sheetname = 'pk';
			$amino_const_sheetname = 'const';
			$side_special_sheetname = 'side_special';
		
			$obj_reader = \PHPExcel_IOFactory::createReader($input_filetype);
		
			$obj_PHPExcel = $obj_reader -> load($input_filename);
		
			$standard_sheetdata = $obj_PHPExcel -> getSheetByName($standard_sheetname) -> toArray(null, true, true, true);
			$pk_sheetdata = $obj_PHPExcel -> getSheetByName($pk_sheetname) -> toArray(null, true, true, true);
			$amino_const_sheetdata = $obj_PHPExcel -> getSheetByName($amino_const_sheetname) -> toArray(null, true, true, true);
		    $side_special_sheetdata = $obj_PHPExcel->getSheetByName($side_special_sheetname)->toArray(null, true, true, true);
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
			// 侧链特殊氨基酸
			$side_special_data = array();
			
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
				
				$_single = $this->standardIndex['single'];
				$_full = $this->standardIndex['full'];
				$_flag = $this->standardIndex['flag'];
				
				for($index=1; $index < $standard_count; $index++){
					$value = $standard_values[$index];
					
					$A = $value[$_single];
					$B = $value[$_full];
					
					// 获取最长氨基酸字符长度
					$a_length = strlen($A);
					$b_length = strlen($B);
					$a_length = ($a_length>$b_length) ? $a_length : $b_length;
					$amino_max_length = ($a_length > $amino_max_length) ? $a_length : $amino_max_length;
		            
					$standard_data[$A] = $value;
					$standard_data[$B] = $value;
					
					$flag = $value[$_flag];
					if($flag==1){
						array_push($pattern_values, $value);
					}
					
					if($flag==2 || $flag==4){
						$nterm_data[$A] = $value;
						//array_push($nterm_data, $value);
					}
					
		            if($flag==3 || $flag==5){
		            	$cterm_data[$A] = $value;
		            	//array_push($cterm_data, $value);
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
			
			$side_special_count = count($side_special_sheetdata);
			if($side_special_count>0){
				$side_special_values = array_values($side_special_sheetdata);
				for($index=1; $index<$side_special_count; $index++){
					$side_special_tmp = $side_special_sheetdata[$index];
					$side_special_data[$side_special_tmp['A']] = $side_special_tmp;
				}
			}
			$this->chemicalDatas = array(
			    'aminoMaxLength'=>$amino_max_length+1,
			    'standardData' => $standard_data, 
			    'aminoConstData' => $amino_const_data, 
			    'pkData' => $pk_data,
			    'sideSpecialData'=>$side_special_data,
			    'ctermData' => $cterm_data,
			    'ntermData' => $nterm_data
		     );
			 
			 $this->aminoSpecial->chemicalDatas = $this->chemicalDatas;
		}

        /**
		 * 获取根据输入序列分析后的对象
		 */
		public function analyze(){
			$this->getTerm();
			
			$subject = $this->subject;
			$this->aminoSpecial->original = $subject;
			
			// 计算备注相关信息
			$subject = $this->checkMemo($subject);
			
			// 测试包含chain
			if(strpos(strtolower($subject), 'chain')>-1){
				$this->aminoSpecial->hasChain = true;
				$this->getChain($subject);

			}else if(strpos(strtolower($subject), 'cyclo')>-1){ //包含环
				$this->aminoSpecial->hasChain = false;
				$aminoChain = $this->checkCyclo($subject);
				$this->aminoSpecial->pushChains(0, $aminoChain);
				
			}else{ //不包含环的内容
				$this->aminoSpecial->hasChain = false;
				//$this->aminoSpecial->pushChains(0, $subject);
				
				$aminoDetail = $this->getAminoDetail($subject);
				
				if(is_array($aminoDetail) && $aminoDetail['hasError']){
					$this->aminoSpecial->hasError = $aminoDetail['hasError'];
					$this->aminoSpecial->message = $aminoDetail['message'];
					return;
				}
				
				$aminoChain = new \Common\Model\AminoChainModel;
				$aminoChain->original = $subject;
				$aminoChain->hasCyclo = false;
				$aminoChain->preCyclo = null;
				
				$cyclo = array(
				   'type'=>'amino_detail',
				   'detail'=>$aminoDetail
				);
				$aminoChain->cyclo = $cyclo;
				$aminoChain->afterCyclo=null;
				$this->aminoSpecial->pushChains(0, $aminoChain);
			}
			
			$this->aminoSpecial->buildAminoInfo($this->chemicalDatas['standardData']);
		}
		
		/**
		 * 分解nterm和cterm
		 */
		private function getTerm(){
			$subject = $this->subject;
			$nterm = 'H-';
			$cterm = 'OH';
			
			$ntermData = $this->chemicalDatas['ntermData'];
			$ctermData = $this->chemicalDatas['ctermData'];
			
			$aminos = split('-', $subject);
			$amino_count = count($aminos);
            
			$_single = $this->standardIndex['single'];
			
			if($amino_count>0){
				$_nterm = isset($ntermData[$aminos[0]]) ? $ntermData[$aminos[0]] : $ntermData[$aminos[0].'-'];
				if(!is_null($_nterm)){
					$nterm = $_nterm[$_single];
					
					$ntermLength = 0;
					// 由于nterm在表示中，可以有-也可以无-，需要重新计算并把-算进去。
					if(preg_match('/-$/', $nterm)>0){
						$ntermLength = strlen($nterm);
					}else{
						$ntermLength = strlen($nterm) + 1;
					}
					$subject = substr($subject, $ntermLength);
				}
				
				$_cterm = isset($ctermData[$aminos[$amino_count-1]]) ? $ctermData[$aminos[$amino_count-1]] : $ctermData['-'.$aminos[$amino_count-1]];
				if(!is_null($_cterm)){
					$cterm = $_cterm[$_single];
					
					$ctermLength = 0;
					if(preg_match('/^-/', $cterm)){
						$ctermLength = strlen($cterm);
					}else{
						$ctermLength = strlen($cterm) + 1;
					}
					
					$subject = substr($subject, 0, strlen($subject) - $ctermLength);

				}
			}
			
			$this->aminoSpecial->nterm = $nterm;
			$this->aminoSpecial->cterm = $cterm;
			$this->subject = $subject;
		}
		
		/**
		 * 检测序列中是否包含备注信息
		 * 条件：在序列尾部的（）中，同时要检测其中的字符串是否为有效序列，若不是则为备注
		 */
		private function checkMemo($subject){
			$pattern = '/\)$/';
			$result = preg_match($pattern, $subject);
			// 尾部不存在)
			if($result==0){
				$this->aminoSpecial->hasMemo = false;
				$this->aminoSpecial->memo = 'no )';
				return $subject;
			}
			
			$stackResult = $this->reserve_stack($subject);
			// 获取发生错误
			if($stackResult==false){
				$this->aminoSpecial->hasMemo = false;
				$this->aminoSpecial->memo = 'error';
				return $subject;
			}
			
			$startIndex = $stackResult['startIndex'];
			$amino = $stackResult['amino'];
			
			if($startIndex<=6){
				// 起始位置小于6，则不存在chain或cyclo
				
				if(strpos($amino, 'chain')>-1 || strpos($amino, 'cyclo')>-1){
					$this->aminoSpecial->hasMemo = false;
					$this->aminoSpecial->memo = 'has chain or cyclo';
					return $subject;
				}
				
				$chemicalDatas = $this->chemicalDatas;
				
				$aminoResult = $this->amino_to_array($chemicalDatas, $amino);
				if($aminoResult['hasError']==true){
					$this->aminoSpecial->hasMemo = true;
					$this->aminoSpecial->memo = $amino;
					
					$subject = substr($subject, 0, strlen($subject) - strlen($memo) - 2);
					return $subject;
				}
				
				$this->aminoSpecial->hasMemo = false;
				$this->aminoSpecial->memo = '可转化';
				return $subject;
			}
			
			$preSubject = substr($subject, $startIndex-6);
	
			if(strpos($preSubject, 'chain')>-1 || strpos($preSubject, 'cyclo')>-1){
				$this->aminoSpecial->hasMemo = false;
				$this->aminoSpecial->memo = 'has chain or cyclo';
				return $subject;
			}else{
				$this->aminoSpecial->hasMemo = true;
				$this->aminoSpecial->memo = $amino;
				
				$subject = substr($subject, 0, strlen($subject) - strlen($amino) - 2);
				return $subject;
			}
		}
		
		
		/**
		 * 获取侧链信息
		 */
		private function getChain($subject){
			if(strpos($subject, 'chainA')>-1){
				
				$chainAResult = $this->stack($subject);
				$aminoChain = $this->analyChain($chainAResult);
				
				$this->aminoSpecial->pushChains('A', $aminoChain);

				$endIndex = $chainAResult['endIndex'];		
				$subject = substr($subject, $endIndex+1);

			}
			
			if(strpos($subject, 'chainB')>-1){
				$chainBResult = $this->stack($subject);
				$aminoChainB = $this->analyChain($chainBResult);
				$this->aminoSpecial->pushChains('B', $aminoChainB);
			}

		}
		
		/**
		 * 分析侧链具体信息
		 */
		private function analyChain($chainResult){
			$amino = $chainResult['amino'];
			return $this->checkCyclo($amino);
		}
		
		/**
		 * 检查是否有环
		 */
		private function checkCyclo($subject){
			$amino = $subject;
			$aminoChain = new \Common\Model\AminoChainModel;
			
			if(strpos(strtolower($amino), 'cyclo')>-1){
				
				$aminoChain->hasCyclo = true;
				$cycloResult = $this->stack($amino);
				$aminoChain->original = $amino;
				
				$startIndex = $cycloResult['startIndex'];
				$endIndex = $cycloResult['endIndex'];
				
				$preCyclo = '';
				$afterCyclo = '';
				
				$length = strlen($amino);
				if($startIndex-6>0){
					$preCyclo = substr($amino, 0, $startIndex - 6);
					if(strlen($preCyclo)>0){
						$preCycloDetail = $this->getAminoDetail($preCyclo);
						if($preCycloDetail!=false){
							$preCyclo = array(
							   'type'=>'amino_detail',
							   'detail'=>$preCycloDetail
							);
						}
					}
				}

				if(($endIndex + 1)<$length){
					$afterCyclo = substr($amino, $endIndex+1, $length-$endIndex-1);

					$afterCycloDetail = $this->getAminoDetail($afterCyclo);
					if($afterCycloDetail!=false){
						$afterCyclo = array(
						   'type'=>'amino_detail',
						   'detail'=>$afterCycloDetail
						);
					}
				}
				
				$cycloAmino = $cycloResult['amino'];
				if(strpos(strtolower($cycloAmino), 'cyclo')>-1){
					return $this->checkCyclo($cycloAmino);
				}
				$cycloDetail = $this->getAminoDetail($cycloAmino);
				$cyclo = array();
				if($cycloDetail!=false){
					$cyclo = array(
					  'type'=>'amino_detail',
					  'detail'=>$cycloDetail
					);
				}
				$aminoChain->preCyclo = $preCyclo;
				$aminoChain->cyclo = $cyclo;
				$aminoChain->afterCyclo = $afterCyclo;
				$aminoChain->startIndex = $startIndex;
				$aminoChain->endIndex = $endIndex;
				
			}else{
				$cycloDetail = $this->getAminoDetail($amino);
				$cyclo = array();
				if($cycloDetail!=false){
					$cyclo = array(
					  'type'=>'amino_detail',
					  'detail'=>$cycloDetail
					);
				}
				$aminoChain->cyclo = $cyclo;
			}
			
			return $aminoChain;
		}
		
		/**
		 * 检查二硫键信息
		 */
		private function checkS2($aminos, &$aminoDetail){
			$index = 1;
			$array = array();
			foreach($aminos as $k => $amino){
				if($amino=='C'){
					array_push($array, $k+1);
				}
			}
			$aminoDetail->sIndex = $array;
		}
		
		private function getAminoDetail($subject){
			$chemicalDatas = $this->chemicalDatas;
			$result = $this->amino_to_array($chemicalDatas, $subject);
			if($result['hasError']===false){
				$aminoDetail = new \Common\Model\AminoDetailModel;
				$aminoDetail->original = $subject;
				$aminoDetail->aminos = $result['aminoDetail'];
				$this->checkS2($result['aminoDetail'], $aminoDetail);
				return $aminoDetail;
			}
			return $result;
		}
		/**
		 * 将序列转换为数组
		 */
		private function amino_to_array($chemical_init_data, $check_amino){
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
				$current_amino_length = strlen($check_amino); //当前校验的字符串长度
				
				// 当前字符串长度与标准表最长的字符串长度进行比较
				$sub_length = ($amino_max_length < $current_amino_length) ? $amino_max_length : $current_amino_length;
				$sub_amino_result = $this->get_sub_amino($standard_data, $amino_max_length, $check_amino);
				
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
		 * 根据data文件中给出的最长序列进行匹配，从长到短直到匹配，若最后未能找到的，则提示错误
		 */
		private function get_sub_amino($standard_data, $amino_max_length, $check_amino){
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
				return $this->get_sub_amino($standard_data, $amino_max_length, $check_amino);
			}
		}

        /**
		 * 根据小括号获取内容
		 */
		private function stack($subject){
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
						break;
					}
				}
				
				//var_dump($check_stack);
				//echo '<br>';
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
		
		/**
		 * 反向入栈获取（）内的信息
		 */
		private function reserve_stack($subject){
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
					// 若包含2个）则不满足备注条件
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
				
				//var_dump($check_stack);
				//echo '<br>';
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
	}
