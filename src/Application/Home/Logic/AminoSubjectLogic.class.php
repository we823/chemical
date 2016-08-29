<?php

namespace Home\Logic;
class AminoSubjectLogic{
	
	private $mChemicalData;
	private $mAminoSubject;
	
	public function init($subject){
		$amino_subject = D('AminoSubject');
		
		$subject = replace_special($subject);
		$amino_subject->mOriginal = $subject;
		$amino_subject->mSubject = $subject;
		
		$this->mAminoSubject = $amino_subject;
		
		$AminoStandard = M('AminoStandard');
		$standard_data_result = $AminoStandard->select();
		$standard_data = $this->getArrayData($standard_data_result, 'single');
		
		$amino_max_length_result = $AminoStandard->getField('max(length(full)) amino_max_length');
		$amino_max_length = $amino_max_length_result['amino_max_length'];
		
		$AminoSideSpecial = M('AminoSideSpecial');
		$side_special_data_result = $AminoSideSpecial->select();
		$side_special_data = $this->getArrayData($side_special_data_result, 'single');
		
		$this->mChemicalData = array(
		   'standard_data'=>$standard_data,
		   'amino_max_length'=>$amino_max_length,
		   'side_special_data'=>$side_special_data
		);

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
	}
	
	public function getResult(){
		return $this->mAminoSubject->getResult();
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
        	$result = $this->analyzeSubject($subject, $chain);

			if($result==false){
				$success = false;
				break;
			}
        }
		
		if($success==false){
			$this->setMessage($this->mAminoSubject->mMessage);
			return;
		}
	}
	
	/**
	 * 分析单个subject
	 */
	private function analyzeSubject($subject, $chain='0'){
		$amino_result = $this->aminoToArray($subject);
		// 按照普通序列分析，不包含特殊标记
		if($amino_result['has_error']==false){
			$fragments = $this->mAminoSubject->mFragments;
			$amino_fragment = new \Common\Model\AminoFragementModel;
			$amino_fragment->mIndex = 0;
			$amino_fragment->mDetail = $amino_result['amino_detail'];
			$amino_fragment->mChain = $chain;
			array_push($fragments, $amino_fragment);
			$this->mAminoSubject->mFragments = $fragments;
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
		
		$fragment = $this->parse2Fragment($subject2, $index, true, $special_result['flag_name'], $special_result['flag_data']);
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
			
			$rIndex ++;
		}

		return $amino_fragment;
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
    private function getArrayData($resultDatas, $keyName){
		$array_data = array();
		if(count($resultDatas)==0){
			return $array_data;
		}
        
		
		foreach($resultDatas as $resultData){
			$array_data[$resultData['single']] = $resultData;
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
