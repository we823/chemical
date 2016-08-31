<?php

namespace Home\Logic;
class AminoSubjectLogic{
	
	private $mChemicalData;
	private $mAminoSubject;
	private $mResultType;
	
	public function init($subject){
		$amino_subject = D('AminoSubject');
		
		$subject = replace_special($subject);
		$amino_subject->mOriginal = $subject;
		$amino_subject->mSubject = $subject;
		
		$this->mAminoSubject = $amino_subject;
		
		$AminoStandard = M('AminoStandard');
		$standard_data_result = $AminoStandard->select();
		$standard_data = $this->getArrayData($standard_data_result, 'single','full');
		
		$nterm_data_result = $AminoStandard->where('flag in ('.C('nterm_flag').')')->select();
		$cterm_data_result = $AminoStandard->where('flag in ('.C('cterm_flag').')')->select();
		$nterm_data = $this->getArrayData($nterm_data_result, 'single', 'full');
		$cterm_data = $this->getArrayData($cterm_data_result, 'single', 'full');
		
		$amino_max_length = $AminoStandard->getField('max(length(full)) amino_max_length');
		$AminoSideSpecial = M('AminoSideSpecial');
		$side_special_data_result = $AminoSideSpecial->select();
		$side_special_data = $this->getArrayData($side_special_data_result, 'single');
		
		$AminoConst = M('AminoConst');
		$amino_const_result = $AminoConst->select();
		$amino_const_data = $this->getArrayData($amino_const_result, 'single');
		
		$AminoPI = M('AminoPi');
		$amino_pi_result = $AminoPI->select();
		$amino_pi_data = $this->getArrayData($amino_pi_result, 'single');
		
		$this->mChemicalData = array(
		   'standard_data'=>$standard_data,
		   'nterm_data'=>$nterm_data,
		   'cterm_data'=>$cterm_data,
		   'amino_max_length'=>$amino_max_length,
		   'side_special_data'=>$side_special_data,
		   'const_data'=>$amino_const_data,
		   'pi_data'=>$amino_pi_data
		);
		
		$this->mResultType = C('result_type');
	}
	
	public function __set($name, $value){
		$this->$name = $value;
	}
	
	public function __get($name){
		return $this->$name;
	}
	
	public function analyze(){
		$this->mAminoSubject->mSubject = $this->checkMemo($this->mAminoSubject);
		$this->getChains($this->mAminoSubject);
		$this->analyzeSubjects();
		$this->buildAminoInfo();
		$this->buildElements();
		$this->buildElementInfos();
	}
	
	/**
	 * 根据分析结果，创建所有氨基酸信息
	 */
	public function buildAminoInfo(){
		$fragments = $this->mAminoSubject->mFragments;
		$fragments_count = count($fragments);
		if($this->mAminoSubject->mHasError){
			return;
		}
		if($fragments_count==0){
			$this->setMessage('氨基酸序列片段为空，无法正常分析，请检查');
			return;
		}
      
		$final_single = '';
		$final_full = '';
        
        foreach($fragments as $main_fragments){
        	$chain = '0';
			if(is_array($main_fragments)){
				$single = '';
				$full = '';
				foreach($main_fragments as $fragment){
					$amino_datas = $this->getSingleFragmentData($fragment);
					$amino_single = $amino_datas['single'];
					$amino_full = $amino_datas['full'];
					
					$chain = $fragment->mChain;
					
		            /**
					 * 片段与片段的连接符，默认为-，特殊的可以去除，在side_special表中设置
					 */
					if($fragment->mIndex>0){
						$has_flag = $fragment->mHasFlag;
						$link = '-';
						if($has_flag){
							$flag_data = $fragment->mFlagData;
							
						    $pre_link = $flag_data['pre_link'];
							
							if($pre_link==0){
								$link = '';
							}
						}
					}
					
					$single = $single . $link. $amino_single;
					$full = $full . $link. $amino_full;
				}
			
			}else if(is_object($main_fragments)){
                $fragment = $main_fragments;
				$amino_datas = $this->getSingleFragmentData($fragment);
				$amino_single = $amino_datas['single'];
				$amino_full = $amino_datas['full'];
				$chain = $fragment->mChain;
				
				$single = $amino_single;
				$full = $amino_full;
			}

			if($chain==='A'){
				$single = 'chainA(' . $single . ')';
				$full = 'chainA(' . $full . ')';
			}else if($chain==='B'){
				$single =  'chainB(' . $single . ')';
				$full =  'chainB(' . $full . ')';
			}
			
			$final_single = $final_single . $single;
		    $final_full = $final_full . $full;
        }
		
		if($this->mAminoSubject->mHasMemo){
			$final_single = $final_single . '('. $this->mAminoSubject->mMemo. ')';
			$final_full = $final_full . '('. $this->mAminoSubject->mMemo. ')';
		}
		
		$this->mAminoSubject->mSingle = $final_single;
		$this->mAminoSubject->mFull = $final_full;
		
	}
	
	// 获取元素具体个数
	public function buildElements(){
		$standard_data = $this->mChemicalData['standard_data'];
		$element_aminos = $this->mAminoSubject->mElementAminos;
		$elements = array();
        
		$element_index = C('element_index');
		if(count($element_aminos)==0){
			foreach($element_index as $index=>$element){
				if(!isset($elements[$element])){
					$elements[$element] = 0;
                }
			}
		}else{
			foreach($element_aminos as $key=>$amino){
				$tmp_standard_data = $standard_data[$key];
				if(is_null($tmp_standard_data)){
					continue;
				}
                
				$amino_count = $amino['count'];
				foreach($element_index as $index=>$element){
					if(isset($tmp_standard_data[strtolower($element)])){
						$elements[$element] += $tmp_standard_data[strtolower($element)] * $amino_count;
					}
				}
				$this->mAminoSubject->mAminoCount += $amino_count;
				// 亲水性总值计算
				$this->mAminoSubject->mHydrophilyCount += $tmp_standard_data['hydrophily'];
				$this->mAminoSubject->mAcidCount += $tmp_standard_data['acid'] * $amino_count;
				$this->mAminoSubject->mBaseCount += $tmp_standard_data['base'] * $amino_count;

			}
		}
		
		$chains = $this->mAminoSubject->mChains;
		$chain_count = count($chains);
		if(isset($elements['H'])){
			$elements['H'] += 2 * $chain_count;
		}
		if(isset($elements['O'])){
			$elements['O'] += 1 * $chain_count;
		}

		$this->mAminoSubject->mElements = $elements;
	}

	public function getResult(){
		return $this->mAminoSubject->getResult();
	}
	
	/**
	 * 根据元素表计算分子相关信息
	 */
    public function buildElementInfos(){
		$const_datas = $this->mChemicalData['const_data'];
    	$elements = $this->mAminoSubject->mElements;
		// 分子式
		$formula = '';
		$formulaHtml = '';
		// 平均分子量
		$mw = 0;
		// 精确分子量
		$em = 0;

		foreach($elements as $key=>$value){
			if($value>0){ //元素个数大于0
				$formula = $formula . $key . $value;
				$formulaHtml = $formulaHtml . $key . '<sub>'.$value.'</sub>';
				$const_data = $const_datas[$key];
				if(!is_null($const_data)){
					$mw += $const_data['mw'] * $value;
				    $em += $const_data['em'] * $value;
				}
				
			}
		}
		
		$this->mAminoSubject->mFormula = $formula;
		$this->mAminoSubject->mFormulaHtml = $formulaHtml;
		$this->mAminoSubject->mMw = sprintf("%.4f",$mw);
		$this->mAminoSubject->mEm = sprintf("%.4f",$em);
		
		$this->mAminoSubject->mHydrophily = round($this->mAminoSubject->mHydrophilyCount / $this->mAminoSubject->mAminoCount, 2);
		$this->mAminoSubject->mHydrophilyResult = $this->getHydrophilyResult($this->mAminoSubject->mHydrophily);
		
		$this->mAminoSubject->mSolubilityIndex = $this->getSolubilityIndex();
		$solubility_results = $this->mResultType['solubility_result'];
		$this->mAminoSubject->mSolubilityResult = $solubility_results[$this->mAminoSubject->mSolubilityIndex];
		
		$pi_result = $this->getPIResult();
		if(!is_null($pi_result)){
			$_pi = is_numeric($pi_result['pi']) ? sprintf('%.2f',$pi_result['pi']) : $pi_result['pi'];
			$this->mAminoSubject->mPi = ($_pi===0) ? 0 : $_pi;
			$this->mAminoSubject->mY = $pi_result['y'];
			$this->mAminoSubject->mPi7 = sprintf('%.2f',$pi_result['pi7']);
			$this->mAminoSubject->mMinY = $pi_result['minY'];
			$this->mAminoSubject->mMaxY = $pi_result['maxY'];
		}
		
    }
	
	/**
	 * 计算亲水性文字结果
	 */
	private function getHydrophilyResult($hydrophily){
		$hydrophily_results = $this->mResultType['hydrophily_result'];
		
		$hydrophily_result = $hydrophily_results[3];
		if($hydrophily>1){
			$hydrophily_result = $hydrophily_results[0];
		}else if($hydrophily>0 && $hydrophily<=1){
			$hydrophily_result = $hydrophily_results[1];
		}else if($hydrophily>-1 && $hydrophily<=0){
			$hydrophily_result = $hydrophily_results[2];
		}
		
		return $hydrophily_result;
	}
	
	/**
	 * 计算溶水性
	 */
	private function getSolubilityIndex(){
		
		$y = $this->mAminoSubject->mAminoCount;
		$acidCount = $this->mAminoSubject->mAcidCount;
		$baseCount = $this->mAminoSubject->mBaseCount;
		$x = $hydrophily = $this->mAminoSubject->mHydrophily;
		$character1 = $this->mAminoSubject->mOriginal;
		
		$amino_details = $this->mAminoSubject->mAminoDetails;
		$standard_data = $this->mChemicalData['standard_data'];
		$standard_index = $this->mBaseIndex['standard_index'];
		
		$result_index = -1;
		
	    // 特殊序列检查
		$specials = ['RADA','TSTS','IKIE','QQQ','NNNNN','DSSDSS'];
		$pattern = '';
		for($index=0, $len = count($specials); $index<$len; $index++){
			$pattern = '(' . $specials[$index] .')';
			$speicalItems = array();
		    $special_valid = preg_match_all("/$pattern/", $character1, $speicalItems);
	
			if($special_valid>=2){
			    return 0;
		    }	
		}
	
		// 氨基酸总个数大于等于10
		if($y >= 10){
			$special_aminos = ['D','E','N','Q','R','S','T','Y'];
			$special_count = 0;
			foreach($special_aminos as $amino){
				if(isset($amino_details[$amino])){
					$special_count += $amino_details[$amino]['count'];
				}
			}
	
			if(($special_count / $y) > 0.6){
	            
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
		
		$amino_detail_values = array_values($amino_details);
		
		if($x>0 && $x<=0.5){
			// 需要计算连续8个氨基酸的亲水性<=0
			$acidAminoCount = 0;
			$firstIndex = 0;
			
			$_acid = $standard_index['acid'];
			for($index=0, $amino_detail_count=count($amino_details); $index<$amino_detail_count; $index++){
				$standard = $standard_data[$amino_detail_values[$index]];
	
				$acidValue = $standard[$_acid];
				if($acidValue<=0){
					$acidAminoCount++;
				}else{
					$acidAminoCount=0;
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
				$_acid = $standard_index['acid'];
				for($index=0, $amino_detail_count=count($amino_details); $index<$amino_detail_count; $index++){
					$standard = $standard_data[$amino_detail_values[$index]];
					$acidValue = $standard[$_acid];
					if($acidValue<=0){
						$acidAminoCount++;
					}else{
						$acidAminoCount=0;
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
	 * 计算等电点（PI）及 净电荷图例
	 */
	private function getPIResult(){
		$result = null;
		
		$pi_aminos = $this->mAminoSubject->mPiAminos;
		$pi_data = $this->mChemicalData['pi_data'];

		if(!isset($pi_aminos) || !isset($pi_data)){
			return $result;
		}
		
		$ys = array();
		$maxY = 0;
		$pi = 0;
		$pi7 = 0; //当ph=7时的净电荷数
		
		//保存y和ph的值
		$piTemp = array();
		
		$nterm_data = $this->mChemicalData['nterm_data'];
		$cterm_data = $this->mChemicalData['cterm_data'];
		
		$cterm_value = $this->mAminoSubject->mCtermValue;
		$nterm_value = $this->mAminoSubject->mNtermValue;
		
		$cterm_count = $this->mAminoSubject->mCtermCount;
		$nterm_count = $this->mAminoSubject->mNtermCount;

		$pi_cterm_data = null;
		$pi_nterm_data = null;

		//负值的个数
		$flag0 =0;
		//正值的个数
		$flag1 =0;
		if($cterm_value > 0){
			$pi_cterm_data = $pi_data['C-term'];
			$flag0 += $cterm_value;
		}
		
		if($nterm_value > 0){
			$pi_nterm_data = $pi_data['N-term'];
			$flag1 += $nterm_value;
		}
		
		$count = count($pi_aminos);
		
		foreach($pi_aminos as $name=>$pi_amino){
			if(isset($pi_data[$name])){
				$tmp = $pi_data[$name];
				if($tmp['flag']==0){
					$flag0 += $pi_amino['count'];
				}else{
					$flag1 += $pi_amino['count'];
				}
			}
		}
	    
		$pi = 0;
		$minY = 0;

		for($index=0; $index<=1400; $index++){
			$x = $index/100;
			$y = 0;

			if(!is_null($pi_cterm_data)){
				$y += $this->calculateSinglePi($x, $pi_cterm_data['pi'], $cterm_count, $pi_cterm_data['flag']);
			}

			if(!is_null($pi_nterm_data)){
				$y += $this->calculateSinglePi($x, $pi_nterm_data['pi'], $nterm_count, $pi_nterm_data['flag']);
			}
			
			if($count==0){
				continue;
			}
            
			foreach($pi_aminos as $name=>$value){
				if(isset($pi_data[$name])){
					$tmp = $pi_data[$name];
					$y += $this->calculateSinglePi($x, $tmp['pi'], $value['count'], $tmp['flag']);
					
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
	 * 检测序列中是否包含备注信息
	 * 条件：在序列尾部的（）中，同时要检测其中的字符串是否为有效序列，若不是则为备注
	 */
	private function checkMemo(&$rAminoSubject){
		$rAminoSubject->mStatus = 0;
		$subject = $rAminoSubject->mSubject;
		$pattern = '/\)$/';
		$result = preg_match($pattern, $subject);
		// 尾部不存在)
		if($result==0){
			$rAminoSubject->mHasMemo = false;
			$rAminoSubject->mMemo = 'no )';
			return $subject;
		}
		
		$stack_result = reserve_stack($subject);
		// 获取发生错误
		if($stack_result['has_error']){
			$rAminoSubject->mHasMemo = false;
			$rAminoSubject->mMemo = $stack_result['message'];
			return $subject;
		}
		
		$start_index = $stack_result['start_index'];
		$content = $stack_result['content'];
		
		// 判断()之前是否为特殊标记符号
		$special_flags = array();
		$side_special_data = $this->mChemicalData['side_special_data'];
		foreach($side_special_data as $key=>$value){
			$tmp_single = $value['single'];
			$tmp_full = $value['full'];
			$tmp_flag = $value['memo_flag'];
			
			$len = strlen($tmp_single);
			$pre_index = $start_index - $len;
			if($pre_index >= 0){
				$pre_subject = substr($subject, $pre_index, $len);
				if($pre_subject==$tmp_single){
					if($tmp_flag==0){ //有特殊标记无需再继续
					    $this->mHasMemo = false;
						$this->mMemo = '有特殊标记：'.$pre_subject;
						return $subject;
					}
				}
			}
			
			if($tmp_single == $tmp_full) continue;
			
			$len = strlen($tmp_full);
			$pre_index = $start_index - $len;
			if($pre_index >= 0){
				$pre_subject = substr($subject, $pre_index, $len);
				if($pre_subject==$tmp_full){
					if($tmp_flag==0){ //有特殊标记无需再继续
					    $rAminoSubject->mHasMemo = false;
						$rAminoSubject->mMemo = '有特殊标记：'.$pre_subject;
						
						return $subject;
					}
				}
			}
		}
		
		$aminoResult = $this->aminoToArray($content);
	
		if($aminoResult['has_error']){
			$rAminoSubject->mHasMemo = true;
			$rAminoSubject->mMemo = $content;
			
			$subject = substr($subject, 0, strlen($subject) - strlen($content) - 2);
			return $subject;
		}
		
		$rAminoSubject->mHasMemo = false;
		$rAminoSubject->mMemo = '可转化';
		return $subject;
	}
    
	private function getChains(&$rAminoSubject){
		$rAminoSubject->mStatus = 1;
    	$subject = $rAminoSubject->mSubject;
		$chains = $rAminoSubject->mChains;
		$subjects = $rAminoSubject->mSubjects;

		$has_chain = false;
		if(strpos($subject, 'chainA')>-1){
			$chain_result = stack($subject);
			if(isset($chain_result['hasError'])){
				return;
			}
			
			$chain_subject = $chain_result['content'];
			$subject = substr($subject, $chain_result['end_index']+1);
			
			$has_chain = true;
			$chains['A'] = $chain_subject;
			$subjects['A'] = $chain_subject;
		}
		
		if(strpos($subject, 'chainB')>-1){
			$chain_result = stack($subject);
			if(isset($chain_result['hasError'])){
				return;
			}
			
			$chain_subject = $chain_result['content'];

			$has_chain = true;
			$chains['B'] = $chain_subject;
			$subjects['B'] = $chain_subject;
		}

		if(!$has_chain){
			$chains['0'] = $subject;
			$subjects['0'] = $subject;
		}
		
        $rAminoSubject->mChains = $chains;
		$rAminoSubject->mSubjects = $subjects;
    }
	
	/**
	 * 分析所有的序列对象
	 */
	private function analyzeSubjects(){
		$this->mAminoSubject->mStatus = 2;
		$chains = $this->mAminoSubject->mChains;
		if(count($chains)==0){
			$this->setMessage('序列为空，无需分析');
			return;
		}
        
		$success = true;
        foreach($chains as $chain=>$subject){
        	$this->mAminoSubject->mNtermCount ++;
			$this->mAminoSubject->mCtermCount ++;
        	$result = $this->analyzeSubject($subject, $chain, 0);
            
			if($result==false){
				$success = false;
				break;
			}
        }
		
		if($success==false){
			$this->setMessage($this->mAminoSubject->mMessage);
			return;
		}
		
		$default_value = C('default_value');
		$standard_data = $this->mChemicalData['standard_data'];
		$nterms = $this->mAminoSubject->mNterms;
		$nterm_count = $this->mAminoSubject->mNtermCount;
		
		$cterms = $this->mAminoSubject->mCterms;
		$cterm_count = $this->mAminoSubject->mCtermCount;
		
		$tmp_nterm_count = $nterm_count - count($nterms);
		if($tmp_nterm_count>0){
			$default_nterm = $default_value['nterm'];
			for($index=0; $index<$tmp_nterm_count; $index++){
				array_push($nterms, $default_nterm);
				
				$this->mAminoSubject->mNtermValue += $standard_data[$default_nterm]['term_value'];
			}
			
			$this->mAminoSubject->mNterms = $nterms;
		}
		
		$tmp_cterm_count = $cterm_count - count($cterms);
		if($tmp_cterm_count>0){
			$default_cterm = $default_value['cterm'];
			for($index=0; $index<$tmp_cterm_count; $index++){
				array_push($cterms, $default_cterm);
				
				$this->mAminoSubject->mCtermValue += $standard_data[$default_cterm]['term_value'];
			}
			
			$this->mAminoSubject->mCterms = $cterms;
		}
	}
	
	/**
	 * 分析单个subject
	 */
	private function analyzeSubject($subject, $chain='0', $parentIndex=0){
		$amino_result = $this->aminoToArray($subject);
		// 按照普通序列分析，不包含特殊标记
		if($amino_result['has_error']==false){
			$fragments = $this->mAminoSubject->mFragments;
			$amino_fragment = new \Common\Model\AminoFragementModel;
			$amino_fragment->mParentIndex = $parentIndex;
			$amino_fragment->mIndex = 0;
			$amino_fragment->mDetail = $amino_result['amino_detail'];
			$amino_fragment->mChain = $chain;
			array_push($fragments, $amino_fragment);
			$this->mAminoSubject->mFragments = $fragments;
			
			$this->getTerms($amino_result['amino_detail']);
			return true;
		}else{
			// 特殊序列处理
			$fragment_result = $this->analyzeSpecialFragements($subject, $amino_result);
			if($fragment_result['has_error']){
				// 获取所有片段发生错误
				$this->setMessage($fragment_result['message'], $fragment_result['has_error']);
				return false;
			}else{
				// 成功获取序列后还需要判断各子序列中有无发生错误，若有则整个序列都标错
				if(is_array($fragment_result) && count($fragment_result)>0){
					$has_error = false;
					$message = '';
					
					foreach($fragment_result as $fragment){
						$detail = $fragment->mDetail;
						$fragments = $fragment->mFragments;
						if(is_null($detail) && isset($fragments['has_error'])){
							$has_error = true;
							$message = $fragments['message'];
							break;
						}
					}
					// 若发生错误，则无法继续
					if($has_error){
						$this->setMessage($message);
						return false;
					}else{
	
                        $fragments = $this->mAminoSubject->mFragments;
						$this->setMessage('成功获取所有片段', false);
						// 设置片段的chain信息
						$this->setFragmentChain($fragment_result, $chain);
						array_push($fragments, $fragment_result);
						
						$this->mAminoSubject->mFragments = $fragments;
						return true;
					}
				}else{
					$this->setMessage('未能成功获取所有片段');
					return false;
				}
			}
		}
	}
	
	private function getTerms($aminoDetail){
		$amino_detail_count = count($aminoDetail);
		
		if($amino_detail_count==0) return;
		
		$first_amino = $aminoDetail[0];
		$last_amino = $aminoDetail[$amino_detail_count - 1];
		
		$nterm_data = $this->mChemicalData['nterm_data'];
		$cterm_data = $this->mChemicalData['cterm_data'];

		$nterm = $nterm_data[$first_amino];
		$cterm = $cterm_data[$last_amino];
		
		$nterms = $this->mAminoSubject->mNterms;
		if(!is_null($nterm)){
			array_push($nterms, $first_amino);
			$this->mAminoSubject->mNterms = $nterms;
			$this->mAminoSubject->mNtermValue += $nterm['term_value'];
		}
		
		if(!is_null($cterm)){
			$cterms = $this->mAminoSubject->mCterms;
			array_push($cterms, $last_amino);
			$this->mAminoSubject->mCterms = $cterms;
			$this->mAminoSubject->mCtermValue += $cterm['term_value'];
		}
	}
	/**
	 * 设置片段上的所在chain
	 */
	private function setFragmentChain(&$rFragmentResult, $chain){
		if(is_null($rFragmentResult)) return;

		$result_count = count($rFragmentResult);
		$cyclo_fragments = $this->mAminoSubject->mCycloFragments;
		for($index=0; $index<$result_count; $index++){
			$fragment = $rFragmentResult[$index];
			$has_flag = $fragment->mHasFlag;
			$flag_name = $fragment->mFlagName;
			
			if($has_flag){
				array_push($this->mSpecialFlags, $fragment->toArray());
			}
			$fragment->mChain = $chain;
			
			$sub_fragments = $fragment->mFragments;
			if(count($sub_fragments)>0){
				$this->setSubFragmentChain($sub_fragments, $chain);
				$fragment->mFragments = $sub_fragments;
			}
			
			$rFragmentResult[$index] = $fragment;
			
			if($flag_name=='cyclo'){
				array_push($cyclo_fragments[$fragment->mChain], $fragment->toArray());
			}
		}
		
		$this->mAminoSubject->mCycloFragments = $cyclo_fragments;
	}
	
	/**
	 * 设置子片段所在的chain
	 */
	private function setSubFragmentChain(&$rSubFragments, $chain){
		$count = count($rSubFragments);
		if($count==0) return;
		
		$cyclo_fragments = $this->mAminoSubject->mCycloFragments;
		$special_flags = $this->mAminoSubject->mSpecialFlags;
		
		for($index=0; $index<$count; $index++){
			$fragment = $rSubFragments[$index];
			$fragment->mChain = $chain;
			
			$sub_fragments = $fragment->mFragments;
			if(count($sub_fragments)>0){
				$this->setSubFragmentChain($sub_fragments, $chain);
				$fragment->mFragments = $sub_fragments;
			}
			
			$rSubFragments[$index] = $fragment;
			
			if($fragment->mFlagName=='cyclo'){
				array_push($cyclo_fragments[$fragment->mChain], $fragment->toArray());
			}
			
			if($fragment->mHasFlag){
				array_push($special_flags, $fragment->toArray());
			}
		}
		
		$this->mAminoSubject->mCycloFragments = $cyclo_fragments;
		$this->mAminoSubject->mSpecialFlags = $special_flags;
	}
	
	/**
	 * 用特殊标记分别校验，并获取结果
	 */
	private function analyzeSpecialFragements($subject, $aminoResult=null){
		$side_special_data = $this->mChemicalData['side_special_data'];
		$flag_data = null;
		$special_result = array(
		   'start_index'=>-1,
		   'end_index'=>0,
		   'content'=>'',
		   'has_flag'=>false,
		   'flag_name'=>'',
		   'flag_data'=>$flag_data
		);
		
		foreach($side_special_data as $key=>$side_special){
			$single = $side_special['single'];
			$full = $side_special['full'];
			$result_number = preg_match('/'.strtolower($single).'\(.+\)/', strtolower($subject));

			if($result_number>0){
				$single_result = stack($subject);
				$start_index = strpos(strtolower($subject), strtolower($single).'(');
				$old_start_index = $special_result['start_index'];

				if($old_start_index==-1 || $start_index <= $old_start_index){
					$special_result['start_index'] = $start_index;
					$special_result['end_index'] = $single_result['end_index'];
					$special_result['content'] = $single_result['content'];
				    $special_result['has_flag'] = true;
				    $special_result['flag_name'] = $single;
					$special_result['flag_data'] = $side_special;
				}
			}
			
			if($single==$full){ // 若单多字母一样，只校验一遍
				continue;
			}
			
			$result_number = preg_match('/'.strtolower($full).'\(.+\)/', strtolower($subject));
			
			if($result_number>0){
				$full_result = stack($subject);
				$start_index = strpos(strtolower($subject), strtolower($full).'('); //必须得有()
				
				$old_start_index = $special_result['start_index'];
				if($old_start_index==-1 || $start_index <= $old_start_index){
					$special_result['start_index'] = $start_index;
					$special_result['end_index'] = $full_result['end_index'];
					$special_result['content'] = $full_result['content'];
				    $special_result['has_flag'] = true;
				    $special_result['flag_name'] = $full;
					$special_result['flag_data'] = $side_special;
				}
			}
		}
		
		$start_index = $special_result['start_index'];
		$end_index = $special_result['end_index'];
		if($start_index==-1){
			$message = '序列存在无法识别字符:'.$subject;

			if(!is_null($aminoResult)){
				$message = $aminoResult['message'];
			}
			return array(
			 'has_error'=>true,
			 'message'=>$message
			);
		}
		
		$flag_data = $special_result['flag_data'];
		$term_flag = $flag_data['term'];
		if($term_flag==1){
			$this->mAminoSubject->mNtermCount ++;
		}
		if($term_flag==-1){
			$this->mAminoSubject->mCtermCount ++;
		}
		
		$flag_name = $flag_data['single'];
		
		// 记录特殊标记
		$special_flags = $this->mAminoSubject->mSpecialFlags;
		if(isset($special_flags[$flag_name])){
			$special_flags[$flag_name]['count']++;
		}else{
			$special_flags[$flag_name] = array('flag_data'=>$flag_data, 'count'=>1);
		}
		$this->mAminoSubject->mSpecialFlags = $special_flags;
		$subject_len = strlen($subject);
		
		$subject1 = substr($subject, 0, $start_index);
		$subject2 = $special_result['content'];
		$subject3 = substr($subject, $end_index+1);
		
		$fragments = array();
		$index = 0;
		$fragment = $this->parse2Fragment($subject1, $index);
		if(!is_null($fragment)){
			array_push($fragments, $fragment);
		}
		
		$fragment = $this->parse2Fragment($subject2, $index, true, $flag_name, $flag_data);
		if(!is_null($fragment)){
			array_push($fragments, $fragment);
		}
		
		$fragment = $this->parse2Fragment($subject3, $index);
		if(!is_null($fragment)){
			array_push($fragments, $fragment);
		}
		
		return $fragments;
	}

/**
	 * 将subject转化为fragment
	 * 1、 无flag，按照正常的转化并生成flagment
	 * 2、 若有，则需要正常赋值
	 */
    private function parse2Fragment($subject, &$rIndex, $hasFlag=false, $flagName='', $flagData=null){
    	$subject_len = strlen($subject);
		if($subject_len==0){
			return null;
		}
		
		$subject = remove_str($subject);
		$amino_result = $this->aminoToArray($subject);
		$amino_fragment = new \Common\Model\AminoFragementModel;
		
		// 当无flag时，表明这个序列按照正常序列分析，flag无
		if($hasFlag == false){
			// 当不存在flag时，若无法解析，则需要进一步获取flag 
			if($amino_result['has_error']){
				$fragments = $this->analyzeSpecialFragements($subject);
				
				if(count($fragments)>0){
					$amino_fragment = $fragments[0];
					$amino_fragment->mIndex = $rIndex;
					$amino_fragment->mChain = '0';
					$rIndex++;
				}else{
					return null;
				}
			}else{
				// 正常解析，则直接赋值
				$amino_fragment->mIndex = $rIndex;
				$amino_fragment->mDetail = $amino_result['amino_detail'];
				
				$this->getTerms($amino_result['amino_detail']);
				$rIndex++;
			}
			
			
		}else{
			// 有前一个flag
			if($amino_result['has_error']){
				$fragments = $this->analyzeSpecialFragements($subject);
				if(count($fragments)>0){
					$amino_fragment->mFragments = $fragments;
				}else{
					return null;
				}
			}
			
			$amino_fragment->mHasFlag = $hasFlag;
			$amino_fragment->mFlagName = $flagName;
			$amino_fragment->mFlagData = $flagData;
			$amino_fragment->mIndex = $rIndex;
			$amino_fragment->mDetail = $amino_result['amino_detail'];
			
			$this->getTerms($amino_result['amino_detail']);
			$rIndex ++;
		}

		return $amino_fragment;
    }

    private function getSingleFragmentData($fragment){
        $single = '';
		$full = '';
		
    	$has_flag = $fragment->mHasFlag;
		$chain = $fragment->mChain;

		if($has_flag){
			$amino_detail = $fragment->mDetail;
			$fragments = $fragment->mFragments;
			
			// 需要处理标记嵌套的情况
			if(is_null($amino_detail) && count($fragments)>0){
				foreach($fragments as $sub_fragment){
					
					$fragment_result = $this->getSingleFragmentData($sub_fragment);
					if(strlen($single)==0){
						$single = $single . $fragment_result['single'];
					    $full = $full . $fragment_result['full'];
					}else{
						// fragment之间的连接
						$flag_data = $sub_fragment->mFlagData;
						$pre_link = $flag_data['pre_link'];
						$pre_link = ($pre_link==1) ? '-' : '';
						
						$single = $single . $pre_link . $fragment_result['single'];
					    $full = $full . $pre_link . $fragment_result['full'];
					}
				}
			}else{
				$standard_data = $this->mChemicalData['standard_data'];
				$amino_data = $this->getAminoData($fragment->mDetail, $standard_data, $chain);
				
				$single = $amino_data['single'];
				$full = $amino_data['full'];
			}
			
			$flag_data = $fragment->mFlagData;

			$flag_single = $flag_data['single'];
			$flag_full = $flag_data['full'];
			$single = $flag_full . '(' . $single . ')';
			$full = $flag_full . '(' . $full . ')';
			
			// 特殊标记需要计算是否要加入氨基酸个数
			$flag = $flag_data['flag'];
			if($flag==1){
				$standard_data = $this->mChemicalData['standard_data'];
				
				$residue = $standard_data['single']['residue'];
				$this->pushAminoDetail($flag_single, $flag_full, $residue, $standard_data[$flag_single], 1);
			}
			
		}else{
			$standard_data = $this->mChemicalData['standard_data'];
			$amino_data = $this->getAminoData($fragment->mDetail, $standard_data, $chain);
			
			$single = $amino_data['single'];
			$full = $amino_data['full'];
		}

       return array(
	      'single'=>$single,
	      'full'=>$full
	   );
    }
     /**
	 * 氨基酸个数计算
	 */
	private function pushAminoDetail($single, $full, $residue, $amino, $flag=0){
		$amino_details = $this->mAminoSubject->mAminoDetails;
		if(isset($amino_details[$single])){
			$amino_details[$single]['count'] ++;
		}else{
			$amino_detail = array(
			   'name'=>$full,
			   'residue'=>$residue,
			   'count'=>1,
			   'detail'=>$amino
			);
			
			$amino_details[$single] = $amino_detail;
		}
		
		$this->mAminoSubject->mAminoDetails = $amino_details;
		$this->mAminoSubject->mElementAminos = $amino_details;
		if($flag==0){ //若为flag，则不增加数量
			$this->mAminoSubject->mPiAminos = $amino_details;
		}
		
	}
	
	/**
	 * 获取氨基酸具体个数数据
	 */
    private function getAminoData($amino_details, $standard_data, $chain){
		$single = '';
		$full = '';
		
		$amino_locations = $this->mAminoSubject->mAminoLocation;
		$amino_location = $amino_locations[$chain];
		$start_location = 0;
		
		if(is_null($amino_location)){
			$amino_location = array();
			$amino_locations[$chain] = $amino_location;
			$this->mAminoSubject->mAminoLocation = $amino_locations;
		}else{
			$start_location = count($amino_location);
		}
		
		$single_flags = array();
		$index = 0;
		foreach($amino_details as $key=>$amino ){
			$tmp_standard_data = $standard_data[$amino];
			
			$amino_location[$start_location + $index] = array(
			   'single'=>$amino,
			   'full'=>$tmp_standard_data['full']
			);
			
			if(is_null($tmp_standard_data)){
				continue;
			}
			$amino_single = $tmp_standard_data['single'];
			$amino_full = $tmp_standard_data['full'];
			
			// 只有当单字母为单个字母时用单字母，否则统一用多字母
			if(strlen($amino_single)>1){
				$amino_single = $amino_full;
			}
			$flag = $tmp_standard_data['flag'];
			$tmp_amino_single = $amino_single;
			$tmp_amino_full = $amino_full;
			
			// 处理由于在表格中term的特殊表示法，需要去除多余的-，2 Nterm 3 cterm 
			if($flag==2){
				if(preg_match('/-$/', $amino_single)) {
					$amino_single = substr($amino_single, 0, strlen($amino_single)-1);
				}
				if(preg_match('/-$/', $amino_full)) {
				    $amino_full = substr($amino_full, 0, strlen($amino_full)-1);
				}
			}else if($flag==3){ //cterm
			    if(preg_match('/^-/', $amino_single)){
			    	$amino_single = substr($amino_single, 1);
			    }
				if(preg_match('/^-/', $amino_single)){
			    	$amino_full = substr($amino_full, 1);
			    }
			}

			if(strlen($amino_single)>1){
				if($key>0){
					$amino_single = '-'.$amino_single;
				}
				// 标记位置为多字母
				$single_flags[$index] = 1;
			}else{
				// 标记位置为单字母
				$single_flags[$index] = 0;
				if($index>0){
					// 当index大于0，则计算前一位置的是否为多字母，若是多字母，则加分割线
					if($single_flags[$index-1]==1){
						$amino_single = '-'.$amino_single;
					}
				}
			}
			$index++;
			if(strlen($amino_full)>1){
				if($key>0){
					$amino_full = '-'.$amino_full;
				}
			}
			
			$single = $single . $amino_single;
			$full = $full . $amino_full;
			
			// 具体氨基酸计算
			$this->pushAminoDetail($tmp_amino_single, $tmp_amino_full, $tmp_standard_data['residue'], $tmp_standard_data);
		}
        
		$amino_locations[$chain] = $amino_location;
		$this->mAminoSubject->mAminoLocation = $amino_locations;
		
		return array(
		   'single'=>$single,
		   'full'=>$full
		);
    }

	/**
	 * 将amino字符串序列序列转换为数组
	 * @param $checkAmino @mixed 需要检查的序列
	 */
	private function aminoToArray($checkAmino){
		$standard_data = $this->mChemicalData['standard_data'];
		$amino_max_length = $this->mChemicalData['amino_max_length'];
		
		$result = array();
		$amino_length = strlen($checkAmino);
		$index = 0;
	    
		if(is_null($standard_data) || empty($standard_data)){
			return array(
			  'has_error'=>true,
			  'message'=>'可校验的标准数据为空，无法校验'
			);
		}
		
		while($index < $amino_length){
			//当前校验的字符串长度
			$current_amino_length = strlen($checkAmino); 
			
			// 按照标准最长长度去计算子序列
			$sub_length = ($amino_max_length < $current_amino_length) ? $amino_max_length : $current_amino_length;
			$sub_amino_result = $this->getSubAmino($standard_data, $amino_max_length, $checkAmino);
			
			if(is_null($sub_amino_result)){
				return array(
				   'has_error'=>true,
				   'message'=>'校验错误,未获取正确的子序列结果'
				);
			}
			
			if($sub_amino_result['has_error']){
				return $sub_amino_result;
			}
			
			$sub_amino = $sub_amino_result['sub_amino'];

			if(array_key_exists($sub_amino, $standard_data)){
			     array_push($result, $sub_amino);
				 $sub_amino_length = $sub_amino_result['real_length'];
				 $index = $index + $sub_amino_length;
				 $checkAmino = substr($checkAmino, $sub_amino_length);
			}
		}
		$valid_result = array(
		   'has_error'=>false,
		   'message'=>'校验正确',
		   'amino_detail'=>$result
		);
		return $valid_result;
	}
	
	/**
	 * 获取正确的子序列
	 * 根据data文件中给出的最长序列进行匹配，从长到短直到匹配，若最后未能找到的，则提示错误
	 */
	private function getSubAmino($standardData, $aminoMaxLength, $checkAmino){
		$length = strlen($checkAmino);
		$sub_length = ($aminoMaxLength > $length) ? $length : $aminoMaxLength;
	    $real_length = $sub_length;
		
		$tmp_check_amino = $checkAmino;
		
		if(strpos($tmp_check_amino, '-')===0 && strlen($tmp_check_amino)>0){
			if(array_key_exists($tmp_check_amino, $standardData)){
				return array(
				   'sub_amino'=>$tmp_check_amino,
				   'real_length'=>$real_length,
				   'has_error'=>false,
				   'message'=>'正确匹配'
				);
			}
			// 若以-开头直接去判断，若有则返回，若无，则去除-
			$tmp_check_amino = substr($tmp_check_amino, 1);
			$sub_length--;
		}
		
		$sub_amino = substr($tmp_check_amino, 0, $sub_length);
		
		if(array_key_exists($sub_amino, $standardData)){
			return array(
			   'sub_amino'=>$sub_amino,
			   'real_length'=>$real_length,
			   'has_error'=>false,
			   'message'=>'正确匹配'
			);
		}else{
			if($aminoMaxLength<=0){
				return array(
				   'has_error'=>true,
				   'message'=>"此段序列：$checkAmino  存在无法识别字符"
				);
			}
			$amino_max_length = $aminoMaxLength - 1;
			return $this->getSubAmino($standardData, $amino_max_length, $checkAmino);
		}
	}
    
	/**
	 * 数据库记录转为array，并以keyname为key
	 */
    private function getArrayData($resultDatas, $keyName, $keyName2=''){
		$array_data = array();
		if(count($resultDatas)==0){
			return $array_data;
		}
        
		$key_length2 = strlen($keyName2);
		foreach($resultDatas as $resultData){
			$array_data[$resultData[$keyName]] = $resultData;
			if($key_length2>0){
				$array_data[$resultData[$keyName2]] = $resultData;
			}
		}
		
		return $array_data;
    }
	
	/**
	 * 设置对象的错误信息
	 */
	private function setMessage($message, $hasError=true){
		$this->mAminoSubject->mHasError = $hasError;
		$this->mAminoSubject->mMessage = $message;
	}
}
