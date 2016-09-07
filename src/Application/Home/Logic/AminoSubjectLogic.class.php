<?php

namespace Home\Logic;
class AminoSubjectLogic{
	
	private $mChemicalData;
	private $mDefaultValue;
	private $mAminoSubject;
	private $mResultType;
	private $mCycloType;
	private $mCycloTypeA=-1;
	private $mCycloTypeB=-1;
	private $mCustomCys;
	
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
		$this->mDefaultValue = C('default_value');
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
		$this->analyzeSubjects(2);
		$this->buildAminoInfo(3);
		$this->fixSpecialAmino();
		$this->fixMAP(4);
		$this->buildElements(5);
		$this->calculateCycloTypes(6);
		$this->calculateCys(7);
		
		// 已经在pushAminoDetail()中根据flag标记忽略不计算
		$this->fixSpecialFlags();
		$this->buildElementInfos(8);
		$this->getAttachInfo();
	}
	
	/**
	 * 根据分析结果，创建所有氨基酸信息
	 */
	public function buildAminoInfo($status){
		if($this->mAminoSubject->mHasError) return;
		
		$this->mAminoSubject->mStatus = $status;
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
	public function buildElements($status){
		if($this->mAminoSubject->mHasError) return;
		
		$this->mAminoSubject->mStatus = $status;
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
			$default_value = C('default_value');
			$Ac = $default_value['Ac'];
			$NH2 = $default_value['NH2'];
			$nterm = $default_value['nterm'];
			$cterm = $default_value['cterm'];

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
				$amino_single = $amino['detail']['single'];
				// Ac、NH2、H-和OH不计算在氨基酸个数中
				if($amino_single!=$Ac && $amino_single!= $NH2 && $amino_single!=$nterm && $amino_single!=$cterm){
					$this->mAminoSubject->mAminoCount += $amino_count;
				}

				// 亲水性总值计算
				$this->mAminoSubject->mHydrophilyCount += $tmp_standard_data['hydrophily'] * $amino_count;
				$this->mAminoSubject->mAcidCount += $tmp_standard_data['acid'] * $amino_count;
				$this->mAminoSubject->mBaseCount += $tmp_standard_data['base'] * $amino_count;
			}
			// 亲水性需要加入H-和OH
			$nterms = $this->mAminoSubject->mNterms;
			$cterms = $this->mAminoSubject->mCterms;
			
			$nterm_data = $standard_data[$nterm];
			$cterm_data = $standard_data[$cterm];
			foreach($nterms as $terms){
				foreach($terms as $term){
					if(count($term)==0) continue;
					foreach($term as $tmp){
						if($tmp==$nterm){
							$this->mAminoSubject->mHydrophilyCount += $nterm_data['hydrophily'];
							$this->mAminoSubject->mAcidCount += $nterm_data['acid'];
					        $this->mAminoSubject->mBaseCount += $nterm_data['base'];
						}
					}
				}
			}
			
			foreach($cterms as $terms){
				foreach($terms as $term){
					if(count($term)==0) continue;
					foreach($term as $tmp){
						if($tmp==$cterm){
							$this->mAminoSubject->mHydrophilyCount += $cterm_data['hydrophily'];
							$this->mAminoSubject->mAcidCount += $cterm_data['acid'];
					        $this->mAminoSubject->mBaseCount += $cterm_data['base'];
						}
					}
				}
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
		$this->getPiAminoCount();
		return $this->mAminoSubject->getResult();
	}
	/**
	 * 序列中包含MAP
	 */
	public function fixMAP($status){
		if($this->mAminoSubject->mHasError) return;
		
		$this->mAminoSubject->mStatus = $status;
		$amino_locations = $this->mAminoSubject->mAminoLocation;
		$amino_location_count = count($amino_locations);
		if($amino_location_count==0) return;
		
		$number = 0;
		$map_chain = '0';
		$map_location = 0;
		foreach($amino_locations as $chain=>$amino_chain){
			$tmp_location_count = count($amino_chain);
			for($location=0; $location<$tmp_location_count; $location++){
				$tmp = $amino_chain[$location];
				$single = $tmp['single'];
				if(strpos($single, 'MAP')>-1){
					$number = intval(str_replace('MAP', '', trim($single)));
					$map_location = $location;
					$map_chain = $chain;
					break;
				}
			}
		}
		
		if($number>0){
			$amino_details = $this->mAminoSubject->mAminoDetails;
			$element_aminos = $this->mAminoSubject->mElementAminos;
			$pi_aminos = $this->mAminoSubject->mPiAminos;
			
			$standard_data = $this->mChemicalData['standard_data'];
			$nterm_flag0 = C('nterm_flag');
			$nterm_flags = split(',', $nterm_flag0);
			foreach($amino_locations as $chain=>$amino_location){
				if($chain==$map_chain){
					$first_amino = $amino_location[0];
					$first_amino_single = $first_amino['single'];
					$tmp_amino = $standard_data[$first_amino_single];
					
					foreach($nterm_flags as $nterm_flag){
						if($tmp_amino['flag']==$nterm_flag){
							$this->mAminoSubject->mNtermValue += $tmp_amino['term_value'] * ($number-1);
						}
					}
					
					for($index=0; $index<$map_location; $index++){
						$tmp = $amino_location[$index];
						$single = $tmp['single'];
						
						$amino_details[$single]['count'] += ($number-1);
						$element_aminos[$single]['count'] += ($number - 1);
						$pi_aminos[$single]['count'] += ($number - 1);
					}
				}
			}
			
			$this->mAminoSubject->mAminoDetails = $amino_details;
			$this->mAminoSubject->mElementAminos = $element_aminos;
			$this->mAminoSubject->mPiAminos = $pi_aminos;
		}
	}
    
	 /**
	 * 计算成环类型影响
	 */
	public function calculateCycloTypes($status){
		if($this->mAminoSubject->mHasError) return;
		$this->mAminoSubject->mStatus = $status;
		
		$cycloType = $this->mCycloType;
		$cycloTypeA = $this->mCycloTypeA;
		$cycloTypeB = $this->mCycloTypeB;
		
		if($cycloTypeA>-1 || $cycloTypeB>-1){
			$this->calculateCycloType($cycloTypeA, 'A');
			$this->calculateCycloType($cycloTypeB, 'B');
		}
		
		if($cycloType>-1){
			$this->calculateCycloType($cycloType, '0');
		}
	}
  
	private function calculateCycloType($cycloType,$chain){
		$cyclo_type = $cycloType;
		
		if($cyclo_type==-1) return;
		$chains = $this->mAminoSubject->mChains;
		if(strpos(strtolower($chains[$chain]), 'cyclo')===false){
			$this->setMessage('选择了成环类型，但在序列中不存在环标记');
			return;
		}
		
		$cyclo_types = $this->mResultType['cyclo_type'];
		$elements = $this->mAminoSubject->mElements;
		
		$cyclo_error = false;
		$cyclo_message = '';
		if($cyclo_type==0){
			$cyclo_message = '已选择主链成环，但序列不满足条件，';
			
			$default_value = $this->mDefaultValue;
			$standard_data = $this->mChemicalData['standard_data'];
			$nterms = $this->mAminoSubject->mNterms[$chain]['detail'];
			
			if(count($nterms)>0){
				$has_nterm_4 = false;
				foreach($nterms as $nterm){
					// 主链成环的nterm必须为H-
					$nterm_amino = $standard_data[$nterm];
					$nterm_correct = false;
					if($nterm==$default_value['nterm'] || $nterm_amino['flag']==4){
						if($nterm_amino['flag']==4){
							$has_nterm_4 = true;
						}
						$nterm_correct = true;
					}
					if($nterm_correct==false){
						$cyclo_error = true;
						$cyclo_message = $cyclo_message . '原因：nterm必须为H或为4型氨基酸';
						$this->setMessage($cyclo_message);
						return;
					}
				}
				if($has_nterm_4){
					$pi_aminos = $this->mAminoSubject->mPiAminos;
					
					if(isset($pi_aminos[$default_value['lys_single']])){
						$pi_aminos[$default_value['lys_single']]['count'] -= 1;
					}
					
					$this->mAminoSubject->mPiAminos = $pi_aminos;
				}else{
					$this->mAminoSubject->mNtermValue -= 1;
				}
				
			}
			$cterms = $this->mAminoSubject->mCterms[$chain]['detail'];
			if(count($cterms)>0){
				$has_cterm_4 = false;
				foreach($cterms as $cterm){
					$cterm_amino = $standard_data[$cterm];
					// 主链成环的cterm必须为OH
					$cterm_correct = false;
					if($cterm == $default_value['cterm'] || $cterm_amino['flag']==4){
						if($cterm_amino['flag']==4){
							$has_cterm_4 = true;
						}
						$cterm_correct = true;
					}
					if($cterm_correct==false){
						$cyclo_error = true;
						$cyclo_message = $cyclo_message . '原因：cterm必须为OH或为4型氨基酸';
						$this->setMessage($cyclo_message);
						return;
					}
				}
				if($has_cterm_4){
					$pi_aminos = $this->mAminoSubject->mPiAminos;
					
					if(isset($pi_aminos[$default_value['glu_single']])){
						$pi_aminos[$default_value['glu_single']]['count'] -= 1;
					}
					
					$this->mAminoSubject->mPiAminos = $pi_aminos;
				}else{
					$this->mAminoSubject->mCtermValue -= 1;
				}
			}
			
			// 主链成环的cyclo必须在序列的两端。
			$subject = $this->mAminoSubject->mSubjects[$chain];

			$subject_result = stack($subject);
			$start_index = $subject_result['start_index'];
			$end_index = $subject_result['end_index'];
			if(($start_index - 5)!=0){ // cyclo标记之前还有内容
				$cyclo_error = true;
			    $cyclo_message = $cyclo_message . ' 原因：cyclo标记前有其他序列';
				$this->setMessage($cyclo_message);
				return;
			}
			
			if($end_index != (strlen($subject)-1)){
				$cyclo_error = true;
				$cyclo_message = $cyclo_message . ' 原因：cyclo标记后有其他序列';
				$this->setMessage($cyclo_message);
				return;
			}

			$attachs = $this->mAminoSubject->mAttachs;
			if($chain=='0'){
				array_push($attachs, $cyclo_types[$cyclo_type]);
			}else{
				array_push($attachs, 'chain'.$chain.': '.$cyclo_types[$cyclo_type]);
			}
			
			$this->mAminoSubject->mAttachs = $attachs;
			
			if(isset($elements['H'])){
				$elements['H'] -=2;
			}
			
			if(isset($elements['O'])){
				$elements['O'] -= 1;
			}
			
			$this->mAminoSubject->mElements = $elements;
			$this->mAminoSubject->mHydrophilyCount -= 2;
			
		}else if($cyclo_type == 1){
			$cyclo_message = '已选择侧链成环，但序列不满足条件，';
			
			$cyclo_fragments = $this->mAminoSubject->mCycloFragments;
			if(count($cyclo_fragments)==0){
				$cyclo_error = true;
				$cyclo_message = $cyclo_message . '原因： 不存在成环序列';
				
				$this->setMessage($cyclo_message);
				return;
			}
			
			$pi_aminos = $this->mAminoSubject->mPiAminos;
			$attachs = $this->mAminoSubject->mAttachs;
			$standard_data = $this->mChemicalData['standard_data'];
			$cyclo_fragment = $cyclo_fragments[$chain];

			foreach($cyclo_fragment as $fragment){
				$detail = $fragment['detail'];
				if(is_null($detail) || count($detail)==0){
					$cyclo_error = true;
					$cyclo_message = $cyclo_message.'原因： 序列无法直接计算氨基酸基团的酸碱性';
					$this->setMessage($cyclo_message);
				    return;
				}
				
				if(count($detail)==1){
					$cyclo_error = true;
					$cyclo_message = $cyclo_message.'原因： 序列只有1个氨基酸基团';
					$this->setMessage($cyclo_message);
				    return;
				}
				
				$amino_first = $detail[0];
				$amino_last = $detail[count($detail)-1];
				
				$amino_data_first = $standard_data[$amino_first];
				$amino_data_last = $standard_data[$amino_last];
				
				$amino_data_first_cyclo_enable = $amino_data_first['cyclo_enable'];	
				$amino_data_last_cyclo_enable = $amino_data_last['cyclo_enable'];
				
				// 前后氨基酸必须为一碱一酸或4型氨基酸
				$correct = false;
				$amino_data_first_flag = $amino_data_first['flag'];
				$amino_data_last_flag = $amino_data_last['flag'];
				if($amino_data_first_cyclo_enable==1){
					if($amino_data_last_cyclo_enable==-1 || $amino_data_last_flag==4){
						$correct = true;
					}
				}
				if($correct!=true && $amino_data_first_cyclo_enable==-1){
					if($amino_data_last_cyclo_enable==1 || $amino_data_last_flag==4){
						$correct = true;
					}
				}
				
				if($correct!=true && $amino_data_first_flag==4){
					if($amino_data_last_cyclo_enable==-1 || $amino_data_last_cyclo_enable==1 || $amino_data_last_flag==4){
						$correct = true;
					}
				}
				
				if(!$correct){
					$cyclo_error = true;
					$cyclo_message = $cyclo_message . '原因：存在不可成环氨基酸基团或基团不包含侧链羧基和侧链氨基';
					
					$this->setMessage($cyclo_message);
					return;
				}
				
				if(isset($pi_aminos[$amino_first])){
					$pi_aminos[$amino_first]['count'] -= 1;
				}
				
				if(isset($pi_aminos[$amino_last])){
					$pi_aminos[$amino_last]['count'] -= 1;
				}
				
				if($chain=='0'){
					array_push($attachs, $cyclo_types[$cyclo_type]);
				}else{
					array_push($attachs, 'chain' .$chain. ':' .$cyclo_types[$cyclo_type]);
				}
			}
			

            if($cyclo_error){
            	$this->setMessage($cyclo_message);
				return;
            }
			
			
			if($cyclo_error == false){
				if(isset($elements['H'])){
					$elements['H'] -=2;
				}
				
				if(isset($elements['O'])){
					$elements['O'] -= 1;
				}
				
				$this->mAminoSubject->mHydrophilyCount -= 4;
			}
			
			$this->mAminoSubject->mElements = $elements;
			$this->mAminoSubject->mAttachs = $attachs;
			$this->mAminoSubject->mPiAminos = $pi_aminos;
			
		}else if($cyclo_type == 2){
			$cyclo_message = '已选择主（N端）侧（C端）成环，但序列不满足条件，';
			
			// cyclo必须在序列的左端。
			$subjects = $this->mAminoSubject->mSubjects;
			if(count($subjects)==0) {
				$cyclo_error = true;
				$cyclo_message = $cyclo_message . ' 原因：序列为空，无需计算';
				$this->setMessage($cyclo_message);
				return;
			}
			
			$subject = $subjects[$chain];
			$subject_result = stack($subject);
			$start_index = $subject_result['start_index'];
			$end_index = $subject_result['end_index'];
			if(($start_index - 5)!=0){ // cyclo标记之前还有内容
				$cyclo_error = true;
			    $cyclo_message = $cyclo_message . ' 原因：cyclo标记前有其他序列';
				$this->setMessage($cyclo_message);
				return;
			}
			
			
			$pi_aminos = $this->mAminoSubject->mPiAminos;
			$attachs = $this->mAminoSubject->mAttachs;
			$elements = $this->mAminoSubject->mElements;
			$standard_data = $this->mChemicalData['standard_data'];
			
			$cyclo_fragment = $this->mAminoSubject->mCycloFragments[$chain];
            
			$default_value = $this->mDefaultValue;
			
			$has_nterm_4 = false;
			foreach($cyclo_fragment as $fragment){
				$detail = $fragment['detail'];
				if(is_null($detail) || count($detail)==0){
					$cyclo_error = true;
					$cyclo_message = $cyclo_message.'原因： 序列为空无法直接计算氨基酸基团的酸碱性';
					$this->setMessage($cyclo_message);
					return;
				}
				
				$amino_first = $detail[0];
				$amino_data_first = $standard_data[$amino_first];
				$correct = false;
                if($amino_data_first['flag']==4){
                	$has_nterm_4 = true;
                }
				if($amino_data_first['flag']==2 || $amino_data_first['flag']==7){
					$cyclo_error = true;
					$cyclo_message = $cyclo_message.'原因： 第一个氨基酸必须为H或为4类型氨基酸';
					$this->setMessage($cyclo_message);
					return;
				}
				
				$amino_last = $detail[count($detail)-1];
				
				$amino_data_last = $standard_data[$amino_last];
				$amino_data_last_cyclo_enable = $amino_data_last['cyclo_enable'];
				
				
				// 右氨基酸必须为酸性氨基酸
				if($amino_data_last_cyclo_enable != -1){
					$cyclo_error = true;
					$cyclo_message = $cyclo_message . '原因：最后一个氨基酸必须含有侧链羧基';
					$this->setMessage($cyclo_message);
					return;
				}
				
				if(isset($pi_aminos[$amino_last])){
					$pi_aminos[$amino_last]['count'] -= 1;
				}
				
				if($chain=='0'){
					array_push($attachs, $cyclo_types[$cyclo_type]);
				}else{
					array_push($attachs, 'chain' .$chain. ':' .$cyclo_types[$cyclo_type]);
				}
			}

			if(!$cyclo_error){
				if(isset($elements['H'])){
					$elements['H'] -=2;
				}
				
				if(isset($elements['O'])){
					$elements['O'] -= 1;
				}
				// 4类型需要减Lys
                if($has_nterm_4){
                	if(isset($pi_aminos[$default_value['lys_single']])){
                		$pi_aminos[$default_value['lys_single']]['count'] -= 1;
                	}
                }else{
                	$this->mAminoSubject->mNtermValue -= 1;
                }
				
				$this->mAminoSubject->mHydrophilyCount -= 3;
				
				$this->mAminoSubject->mPiAminos = $pi_aminos;
				$this->mAminoSubject->mElements = $elements;
				$this->mAminoSubject->mAttachs = $attachs;
			}
			
		}else if($cyclo_type == 3){
			$cyclo_message = '已选择侧（N端）主（C端）成环，但序列不满足条件，';
			
			// cyclo必须在序列的右端。
			$subjects = $this->mAminoSubject->mSubjects;
			if(count($subjects)==0){
				$cyclo_message = $cyclo_message . '原因： 序列为空，无需计算';
				$this->setMessage($cyclo_message);
				return;
			}
            
			$subject = $subjects[$chain];

			$subject_result = stack($subject);
			$start_index = $subject_result['start_index'];
			$end_index = $subject_result['end_index'];
			
			if($end_index != (strlen($subject)-1)){
				$cyclo_error = true;
				$cyclo_message = $cyclo_message . ' 原因：cyclo标记后有其他序列';
				$this->setMessage($cyclo_message);
				return;
			}

			$pi_aminos = $this->mAminoSubject->mPiAminos;
			$attachs = $this->mAminoSubject->mAttachs;
			$elements = $this->mAminoSubject->mElements;
			$standard_data = $this->mChemicalData['standard_data'];
			
			$cyclo_fragment = $this->mAminoSubject->mCycloFragments[$chain];
			
			$default_value = $this->mDefaultValue;
			$has_cterm_4 = false;
			foreach($cyclo_fragment as $fragment){
				$detail = $fragment['detail'];
				if(is_null($detail) || count($detail)==0){
					$cyclo_error = true;
					$cyclo_message = $cyclo_message.'原因： 序列无法直接计算氨基酸基团的酸碱性';
					$this->setMessage($cyclo_message);
					return;
				}
				
				
				$amino_first = $detail[0];
				$amino_data_first = $standard_data[$amino_first];
				$amino_data_first_cyclo_enable = $amino_data_first['cyclo_enable'];
				
				// 第一个氨基酸必须为碱性集团
				if($amino_data_first_cyclo_enable != 1){
					$cyclo_error = true;
					$cyclo_message = $cyclo_message . '原因：第一个氨基酸必须含有侧链氨基';
					$this->setMessage($cyclo_message);
					return;
				}
				
				$amino_last = $detail[count($detail)-1];
				$amino_last_data = $standard_data[$amino_last];
				if($amino_last_data['flag']==4){
					$has_cterm_4 = true;
				}
				if($amino_last_data['flag']==3 || $amino_last_data['flag']==8){
					$cyclo_error = true;
					$cyclo_message = $cyclo_message . '原因：最后一个氨基酸必须为OH或4型氨基酸';
					$this->setMessage($cyclo_message);
					return;
				}
				
				if(isset($pi_aminos[$amino_first])){
					$pi_aminos[$amino_first]['count'] -= 1;
				}
				
				
				if($chain=='0'){
					array_push($attachs, $cyclo_types[$cyclo_type]);
				}else{
					array_push($attachs, 'chain' .$chain. ':' .$cyclo_types[$cyclo_type]);
				}
			}
			
			
           
			if(isset($elements['H'])){
				$elements['H'] -=2;
			}
			
			if(isset($elements['O'])){
				$elements['O'] -= 1;
			}
			
			if($has_cterm_4){
				if(isset($pi_aminos[$default_value['glu_single']])){
					$pi_aminos[$default_value['glu_single']]['count'] -= 1;
				}
			}else{
				$this->mAminoSubject->mCtermValue -= 1;
			}
			
			$this->mAminoSubject->mHydrophilyCount -= 3;
			
			$this->mAminoSubject->mPiAminos = $pi_aminos;
			$this->mAminoSubject->mElements = $elements;
			$this->mAminoSubject->mAttachs = $attachs;				
			
		}else if($cyclo_type == 4){
			$cyclo_message = '已选择硫醚成环，但序列不满足条件，';
			$pi_aminos = $this->mAminoSubject->mPiAminos;
			$elements = $this->mAminoSubject->mElements;
			
			$exist_amino = '';
			$cyclo_fragments = $this->mAminoSubject->mCycloFragments;
			$standard_data = $this->mChemicalData['standard_data'];
			$cyclo_fragment = $cyclo_fragments[$chain];
			
			foreach($cyclo_fragment as $fragment){
				$detail = $fragment['detail'];
				$amino_first = $standard_data[$detail[0]];
				$amino_last = $standard_data[$detail[count($detail)-1]];
					
				if($amino_first['cyclo_enable']==2 &&  $amino_last['cyclo_enable']!=2){
					$exist_amino = $amino_first['single'];
				}
				
				if($amino_first['cyclo_enable']!=2 &&  $amino_last['cyclo_enable']==2){
					$exist_amino = $amino_last['single'];
				}
			}
			
			if(strlen($exist_amino)==0){
				$cyclo_error = true;
				$cyclo_message = $cyclo_message . '请确认成环可行性';
				$this->setMessage($cyclo_message);
				return;
			}
			
			if(isset($pi_aminos[$exist_amino])){
				$pi_aminos[$exist_amino]['count'] -= 1;
			}
			
			if(isset($elements['H'])){
				$elements['H'] -=2;
			}
			
			$this->mAminoSubject->mPiAminos = $pi_aminos;
			$this->mAminoSubject->mElements = $elements;
		}
		
	}
	/**
	 * 计算二硫键
	 */
    public function calculateCys($status){
    	if($this->mAminoSubject->mHasError) return;
    	$this->mAminoSubject->mStatus = $status;
    	$custom_cys = $this->mCustomCys;
		if(is_null($custom_cys) || strlen($custom_cys)==0) return;
        
		$custom_cys = strtoupper($custom_cys); //全部转化为大写字母
		$pattern = '/[A|B]?[1-9]*[0-9]+\-[A|B]?[1-9]*[0-9]+/';
		$result_number = preg_match_all($pattern, $custom_cys, $items);
		if($result_number==0){
			$this->setMessage('二硫键格式不正确，请检查,如（1-8,2-10或A1-B2）,字母只接受A和B');
			return;
		}

		$len = strlen($custom_cys);
		$item_len = 0;
		foreach($items[0] as $item){
			$item_len += strlen($item);
		}
		
		$item_len += count($items[0])-1;
		
		if($len!=$item_len){
			$this->setMessage('二硫键格式不完全匹配，请检查,如（1-8,2-10或A1-B2）,字母只接受A和B');
			return;
		}
		
		$cys_locations = array();
		foreach($items[0] as $item){
			$locations = split('-', $item);
			foreach($locations as $location){
				$chain = '0';
				if(strpos($location, 'A')>-1){
					$chain = 'A';
				}else if(strpos($location, 'B')>-1){
					$chain = 'B';
				}
				$location = str_replace('A', '', $location);
				$location = str_replace('B', '', $location);
				
				$cys_location = array(
				  'chain'=>$chain,
				  'location'=>$location
				);
				
				array_push($cys_locations, $cys_location);
			}
		}

		if(count($cys_locations)>0){
			$amino_locations = $this->mAminoSubject->mAminoLocation;
			$standard_data = $this->mChemicalData['standard_data'];
			$locations = array();
			$repeat = array();

			foreach($cys_locations as $index=>$cys_location){
				$chain = $cys_location['chain'];
				$location = $cys_location['location'];
				
				$amino_location = $amino_locations[$chain];
				if(is_null($amino_location)){
					$this->setMessage('序列中不存在chain'.$chain);
					return;
				}
				
				$amino = $amino_location[$location-1];
				if(is_null($amino)){
					$this->setMessage('位置'.$location.' 不存在氨基酸');
					return;
				}
                $tmp = $standard_data[$amino['single']];
				if(is_null($tmp)){
					$this->setMessage('位置'.$location.' 不是已知氨基酸');
					return;
				}
				
				// 3类型的氨基酸必须在nterm才能形成二硫键
				$correct = false;
				if($tmp['cyclo_enable']==3){
					$first_amino = current($this->mAminoSubject->mAminoDetails);
					$first_amino_single = $first_amino['detail']['single'];
					if($first_amino_single != $tmp['single']){
						$this->setMessage('位置'.$location.' 氨基酸不在N-Term上，无法形成二硫键');
					    return;
					}
					
					$correct = true;
				}
				
				// cyclo_enable=2 可成立
				
				if($correct==false && $tmp['cyclo_enable']==2){
					$correct = true;
				}
				
				if(!$correct){
					$this->setMessage('位置'.$location.' 氨基酸无法形成二硫键，氨基酸为'.$amino['full']);
					return;
				}
				
				
				
				if(isset($locations[$chain.$location])){
					if($chain=='0'){
						array_push($repeat, $location);
					}else{
						array_push($repeat, $chain.$location);
					}
				}
				$locations[$chain.$location] = $location;
				$cys_location['single'] = $tmp['single'];
				$cys_locations[$index] = $cys_location;
			}

			if(count($locations)<count($cys_locations)){		
				$this->setMessage('输入的二硫键位置 '.implode(',', $repeat).' 重复，无法形成二硫键，请检查');
				return;
			}
			
			$cys_count = count($cys_locations);
			if(($cys_count % 2) != 0){
				$this->setMessage('输入的二硫键位置未成对，请检查');
				return;
			}
			
			// 校验成功
			$this->mAminoSubject->mCysRealIndex = $cys_locations;
			
			$elements = $this->mAminoSubject->mElements;
			// 分子式影响
            if(!isset($elements['H'])){
            	$elements['H'] = 0;
            }
			
			$elements['H'] -= 2*($cys_count/2);
			$this->mAminoSubject->mElements = $elements;
			
			$pi_aminos = $this->mAminoSubject->mPiAminos;
			// pi影响
		    foreach($cys_locations as $cys_location){
		    	if(isset($pi_aminos[$cys_location['single']])){
					$pi_aminos[$cys_location['single']]['count'] -= 1;
				}
		    }
			
			$this->mAminoSubject->mPiAminos = $pi_aminos;
			// 备注
			$bridge = '';
			
			$amino_location = $this->mAminoSubject->mAminoLocation;
			for($index=0; $index<$cys_count; $index++){
				$cys_location = $cys_locations[$index];
				$chain = $cys_location['chain'];
				$chain_show = ($chain=='0') ? '' : $chain;
				$location = $cys_location['location'];
				
				$tmp_amino = $amino_location[$chain][$location - 1];

				$cys = 'Cys';
				if(!is_null($tmp_amino)){
					$cys = $tmp_amino['full'];
				}
				$bridge = $bridge . $cys. $chain_show. $location;
				if($index<($cys_count-1)){
					if($index>0 && ($index+1)%2==0){
						$bridge = $bridge . ',';
					}else{
						$bridge = $bridge . '&';
					}
				}

			}
			 
		   $bridge = $bridge.' bridge';
		   $attachs = $this->mAminoSubject->mAttachs;
		   array_push($attachs, $bridge);
		   $this->mAminoSubject->mAttachs = $attachs;
			 
		}
    }

    public function fixSpecialFlags(){
    	$special_flags = $this->mAminoSubject->mSpecialFlags;
		if(count($special_flags)>0){
			// 已经在pushAminoDetail()中根据flag标记忽略不计算
			/**
			$pi_aminos = $this->mAminoSubject->mPiAminos;
			foreach($special_flags as $amino=>$special_flag){
				$tmp = $pi_aminos[$amino];
				if(is_null($tmp)) continue;
				
				$pi_aminos[$amino]['count'] += $special_flag['flag_data']['pi'];
			}
			
			$this->mAminoSubject->mPiAminos = $pi_aminos;
			 * */
			// 亲水性需要计算
			foreach($special_flags as $amino=>$special_flag){
			   if($special_flag['flag_data']['flag']==1){
			   	    $this->mAminoSubject->mHydrophilyCount -= 2;
			   }
			}
		}
    }
	/**
	 * 根据元素表计算分子相关信息
	 */
    public function buildElementInfos($status){
    	if($this->mAminoSubject->mHasError) return;
    	$this->mAminoSubject->mStatus = $status;
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
			$_pi7 = $pi_result['pi7'];
            
			if(intval(round($_pi7,2))==0){
				$_pi7 = 0;
			}else{
				$_pi7 = sprintf('%.2f',$pi_result['pi7']);
			}
			$this->mAminoSubject->mPi7 = $_pi7;
			$this->mAminoSubject->mMinY = $pi_result['minY'];
			$this->mAminoSubject->mMaxY = $pi_result['maxY'];
		}
		
    }
	
	public function getAttachInfo(){
		$attachs = $this->mAminoSubject->mAttachs;
		if(count($attachs)>0){
			$attach = implode(';', $attachs);
			$this->mAminoSubject->mSingle = $this->mAminoSubject->mSingle . '('.$attach.')';
		    $this->mAminoSubject->mFull = $this->mAminoSubject->mFull. '('.$attach.')'; 
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
		$acid_count = $this->mAminoSubject->mAcidCount;
		$base_count = $this->mAminoSubject->mBaseCount;
		$x = $hydrophily = $this->mAminoSubject->mHydrophily;
		$character1 = $this->mAminoSubject->mOriginal;
		
		$amino_details = $this->mAminoSubject->mAminoDetails;
		$standard_data = $this->mChemicalData['standard_data'];
		
		$result_index = -1;
		
	    // 特殊序列检查
	    // RADA\TSTS\IKIE\QQQQ\NNNNN\DSSDSS等循化序列，循环2次以上
		$specials = ['RADA','TSTS','IKIE','QQQ','NNNNN','DSSDSS'];
		$pattern = '';
		for($index=0, $len = count($specials); $index<$len; $index++){
			$pattern = '(' . $specials[$index] .')';
			$speical_items = array();
		    $special_valid = preg_match_all("/$pattern/", $character1, $speical_items);
	
			if($special_valid>=2){
			    return 0;
		    }	
		}
	
		// 序列大于等于10个氨基酸 
		if($y >= 10){
			$special_aminos = ['D','E','N','Q','R','S','T','Y'];
			$special_count = 0;
			foreach($special_aminos as $amino){
				if(isset($amino_details[$amino])){
					$special_count += $amino_details[$amino]['count'];
				}
			}
	        
			// D、E、N、Q、R、S、T或Y残基个数超过60%,
			if(($special_count / $y) > 0.6){
	            
				$ab_percent = ($acid_count + $base_count)/$y;
				//酸个数（算含量、溶解性）和碱个数（算含量、溶解性）的总个数≤40%，
				if( $ab_percent <= 0.4 ){
					// 其他条件
					$result_index = 3;
					// 当碱个数（算含量、溶解性）≥25%
					if( ($base_count / $y)>=0.25){
						$result_index = 1;
					}
					// 当酸个数（算含量、溶解性）≥25%
					if( ($acid_count/$y)>=0.25){
						$result_index = 2;
					}
					
					return $result_index;
			     }
		    }
		}
	
	    // Y≤5且X＞-0.5
	    if($y<=5 && $x>-0.5){
	    	return 4;
	    }
		
		$amino_detail_values = array_values($amino_details);
		if($x>0 && $x<=0.5){
			// 需要计算连续8个氨基酸的亲水性<=0
			$acid_amino_count = $this->calculateHydrophilyMaxCount();
	
			if($acid_amino_count>=8){
				return 5;
			}
			
		}
		
		if($x > 0){
			return 4;
		}else if($x<=0 && $x>-1){
			
			$result_index = 9;
	
			if( ($base_count - $acid_count) >= 2 ){
				// 需要计算连续6个氨基酸的亲水性<=0
				$acid_amino_count = $this->calculateHydrophilyMaxCount();
	
				if($acid_amino_count>=6){
					return 6;
				}
			}
			
			if( ($base_count - $acid_count) >= 2){
				return 7;
			}
			
			if( ($acid_count - $base_count)>=2 || ($acid_count>0 && $base_count==0) ){
				return 8;
			}
			return $result_index;
		}else if($x<=-1 && $x>-2){
			
			$result_index = 11;
			
			if( ($acid_count - $base_count)>=2 || ($acid_count>0 && $base_count==0)){
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
			$flag0 += $cterm_value * $pi_cterm_data['ratio'];
		}
		
		if($nterm_value > 0){
			$pi_nterm_data = $pi_data['N-term'];
			$flag1 += $nterm_value * $pi_nterm_data['ratio'];
		}

		$count = count($pi_aminos);
		
		foreach($pi_aminos as $name=>$pi_amino){
			if(isset($pi_data[$name])){
				$tmp = $pi_data[$name];
				if($tmp['flag']==0){
					$flag0 += $pi_amino['count'] * $tmp['ratio'];
				}else{
					$flag1 += $pi_amino['count'] * $tmp['ratio'];
				}
			}
		}

		$pi = 0;
		$minY = 0;

		for($index=0; $index<=1400; $index++){
			$x = $index/100;
			$y = 0;

			if(!is_null($pi_cterm_data)){
				$y += $this->calculateSinglePi($x, $pi_cterm_data['pi'], $cterm_value, $pi_cterm_data['flag']);
			}

			if(!is_null($pi_nterm_data)){
				$y += $this->calculateSinglePi($x, $pi_nterm_data['pi'], $nterm_value, $pi_nterm_data['flag']);
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
		$nterms = $this->mAminoSubject->mNterms;
		$cterms = $this->mAminoSubject->mCterms;
        foreach($chains as $chain=>$subject){
        	$nterms[$chain]['count']++;
			$cterms[$chain]['count']++;
			$this->mAminoSubject->mNterms = $nterms;
		    $this->mAminoSubject->mCterms = $cterms;
		
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
		
		// 补足默认的nterm和cterm信息
		$default_value = $this->mDefaultValue;
		$standard_data = $this->mChemicalData['standard_data'];
		$nterms = $this->mAminoSubject->mNterms;
		$nterm_count = $this->mAminoSubject->mNtermCount;

		$cterms = $this->mAminoSubject->mCterms;
		$cterm_count = $this->mAminoSubject->mCtermCount;
		
		$default_nterm = $default_value['nterm'];
		foreach($nterms as $chain=>$tmp_nterm){
			$count = $tmp_nterm['count'];
			
			if($count==0) continue;
			
			$detail = $tmp_nterm['detail'];
			$tmp_nterm_count = $count - count($detail);
			if($tmp_nterm_count==0) continue;
			
			for($index=0; $index<$tmp_nterm_count; $index++){
				array_push($nterms[$chain]['detail'], $default_nterm);
				$this->mAminoSubject->mNtermValue += $standard_data[$default_nterm]['term_value'];
			}
			$this->mAminoSubject->mNterms = $nterms;
		}
		
		$default_cterm = $default_value['cterm'];
		foreach($cterms as $chain=>$tmp_cterm){
			$count = $tmp_cterm['count'];
			if($count==0) continue;
			
			$detail = $tmp_cterm['detail'];
			$tmp_cterm_count = $count - count($detail);
			if($tmp_cterm_count==0) continue;
			
			for($index=0; $index<$tmp_cterm_count; $index++){
				array_push($cterms[$chain]['detail'], $default_cterm);
				
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
			
			$this->getTerms($amino_result['amino_detail'], $chain);
			return true;
		}else{
			// 特殊序列处理
			$fragment_result = $this->analyzeSpecialFragements($subject, $amino_result, $chain);
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
	
	private function getTerms($aminoDetail, $chain='0'){
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
			array_push($nterms[$chain]['detail'], $first_amino);
			$this->mAminoSubject->mNterms = $nterms;
			$this->mAminoSubject->mNtermValue += $nterm['term_value'];
		}
		
		if(!is_null($cterm)){
			$cterms = $this->mAminoSubject->mCterms;
			array_push($cterms[$chain]['detail'], $last_amino);
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
	private function analyzeSpecialFragements($subject, $aminoResult=null, $chain='0'){
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
				$message = isset($aminoResult['message']) ? $aminoResult['message'] : $message;
			}
			return array(
			 'has_error'=>true,
			 'message'=>$message
			);
		}
		
		$flag_data = $special_result['flag_data'];
		$term_flag = $flag_data['term'];
		
		if($term_flag==1){
			$nterms = $this->mAminoSubject->mNterms;
			$nterms[$chain]['count']++;
			$this->mAminoSubject->mNtermCount ++;
			$this->mAminoSubject->mNterms = $nterms;
		}
		
		if($term_flag==-1){
			$cterms = $this->mAminoSubject->mCterms;
			$cterms[$chain]['count']++;
			$this->mAminoSubject->mCterms = $cterms;
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
		$fragment = $this->parse2Fragment($subject1, $index, $chain);
		if(!is_null($fragment)){
			array_push($fragments, $fragment);
		}
		
		$fragment = $this->parse2Fragment($subject2, $index, $chain, true, $flag_name, $flag_data);
		if(!is_null($fragment)){
			array_push($fragments, $fragment);
		}
		
		$fragment = $this->parse2Fragment($subject3, $index, $chain);
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
    private function parse2Fragment($subject, &$rIndex, $chain='0', $hasFlag=false, $flagName='', $flagData=null){
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
				$fragments = $this->analyzeSpecialFragements($subject, $chain);
				
				if(count($fragments)>0){
					$amino_fragment = $fragments[0];
					$amino_fragment->mIndex = $rIndex;
					$amino_fragment->mChain = $chain;
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
				$fragments = $this->analyzeSpecialFragements($subject, $chain);
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
				
				$residue = $standard_data[$flag_single]['residue'];
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
	
	private function getPiAminoCount(){
		$pi_aminos = $this->mAminoSubject->mPiAminos;
		if(count($pi_aminos)==0) return;
		
		$default_value = C('default_value');
		$Ac = $default_value['Ac'];
		$NH2 = $default_value['NH2'];
		$nterm = $default_value['nterm'];
		$cterm = $default_value['cterm'];
		
		$pi_amino_count = 0;
		foreach($pi_aminos as $pi_amino){
			$single = $pi_amino['detail']['single'];
			if($single!=$Ac && $single!=$NH2 && $single!=$nterm && $single!=$cterm){
				$pi_amino_count += $pi_amino['count'];
			}
		}
		
		$this->mAminoSubject->mPiAminoCount = $pi_amino_count;
	}
	
	/**
	 * 计算连续亲水性个数的最大值
	 * @param $hydrophilyValue 需要比较亲水性默认值
	 * @return $max_count 最大的值
	 */
	private function calculateHydrophilyMaxCount($hydrophilyValue=0){
		$amino_fragments = $this->mAminoSubject->mFragments;
		if(count($amino_fragments)==0) return 0;
		$hydrophily_counts = array();
		$amino_single_list = array();
		foreach($amino_fragments as $chain=>$amino_fragment){
			$amino_all_list = array();
			$this->getHydrophilyFragments($amino_single_list, $amino_all_list, $amino_fragment);
		}
		if(count($amino_single_list)==0) return 0;

		$standard_data = $this->mChemicalData['standard_data'];
		foreach($amino_single_list as $amino_singles){
			$hydrophily_count = 0;
			foreach($amino_singles as $amino_single){
				$tmp_amino = $standard_data[$amino_single];
				$hydrophily = $tmp_amino['hydrophily'];
				if($hydrophily <= $hydrophilyValue){
					$hydrophily_count++;
				}else{
					if($hydrophily_count>0){
						array_push($hydrophily_counts, $hydrophily_count);
					}
					$hydrophily_count = 0;
				}
			}
			if($hydrophily_count>0){
				array_push($hydrophily_counts, $hydrophily_count);
			}
		}
		$max_count = 0;
		foreach($hydrophily_counts as $hydrophily_count){
			$max_count = ($max_count<$hydrophily_count) ? $hydrophily_count : $max_count;
		}
		return $max_count;
	}
	
	private function getHydrophilyFragments(&$rAminoSingleList, &$rAminoAllList, $amino_fragment){
		$next_fragments = array();
		if(is_object($amino_fragment)){
			$amino_fragment = array($amino_fragment);
		}

		foreach($amino_fragment as $fragment){
			$detail = $fragment->mDetail;
			$has_flag = $fragment->mHasFlag;
			$flag_data = $fragment->mFlagData;
			if($has_flag){
				if($flag_data['flag']==1){ // 如果flag为1，则需要加入亲水性计算中
					array_push($rAminoAllList, $flag_data['single']);
					if(count($detail)>0){
						array_push($rAminoSingleList, $detail);
					}else{
						array_push($next_fragments, $fragment->fragments);
					}
				}else{
					array_push($next_fragments, $fragment->fragments);
				}
			}else{
				foreach($detail as $tmp){
					array_push($rAminoAllList, $tmp);
				}
			}
		}
		if(count($next_fragments)>0){
			foreach($next_fragments as $next_fragment){
				$this->getHydrophilyFragments($rAminoSingleList, $rAminoAllList, $next_fragment);
			}	
		}

		array_push($rAminoSingleList, $rAminoAllList);
	}
	
	/**
	 * 特殊氨基酸对pi影响的修正
	 */
	private function fixSpecialAmino(){
		$standard_data = $this->mChemicalData['standard_data'];
		$nterms = $this->mAminoSubject->mNterms;
		$cterms = $this->mAminoSubject->mCterms;
		
		$amino_details = $this->mAminoSubject->mAminoDetails;
		$pi_aminos = $this->mAminoSubject->mPiAminos;
		
		$default_value = $this->mDefaultValue;
		$lys_single = $default_value['lys_single'];
		$glu_single = $default_value['glu_single'];

		foreach($nterms as $chain=>$nterm_list){
			if(count($nterm_list)==0) continue;
			$nterm_details = $nterm_list['detail'];
			foreach($nterm_details as $nterm){
				$term_data = $standard_data[$nterm];
				$flag = $term_data['flag'];
				if($flag==4){
					if(isset($pi_aminos[$lys_single])){
						$pi_aminos[$lys_single]['count'] += 1;
					}else{
						$lys_data = $standard_data[$lys_single];
						$pi_aminos[$lys_single]['name'] = $lys_data['full'];
						$pi_aminos[$lys_single]['detail'] = $lys_data;
						$pi_aminos[$lys_single]['count'] = 1;
						$pi_aminos[$lys_single]['residue'] = $lys_data['residue'];
					}
					// 4类型氨基酸在Nterm时，亲水性+2
					$this->mAminoSubject->mHydrophilyCount += 2;
				}
			}
		}
		
		foreach($cterms as $chain=>$cterm_list){
			if(count($cterm_list)==0) continue;
			foreach($cterm_list as $cterm){
				$term_data = $standard_data[$cterm];
				$flag = $term_data['flag'];
				if($flag==4){
					if(isset($pi_aminos[$glu_single])){
						$pi_aminos[$glu_single]['count'] += 1;
					}else{
						$glu_data = $standard_data[$glu_single];
						$pi_aminos[$glu_single]['name'] = $glu_data['full'];
						$pi_aminos[$glu_single]['detail'] = $glu_data;
						$pi_aminos[$glu_single]['count'] = 1;
						$pi_aminos[$glu_single]['residue'] = $glu_data['residue'];
					}
                    
					// 对于序列类型为4（data-full表中的flag）的氨基酸，如果在序列的C-term或N-term，亲水性需要+2
                    $this->mAminoSubject->mHydrophilyCount += 2;
				}
			}
		}
		
		foreach($amino_details as $amino_detail){
			$detail = $amino_detail['detail'];
			$flag = $amino_detail['detail']['flag'];
			if($flag==5){
				$this->mAminoSubject->mCtermValue += 1;
			}
			
			if($flag==6){
				$this->mAminoSubject->mNtermValue += 1;
			}
			
			if($flag==7){
               if(isset($pi_aminos[$glu_single])){
					$pi_aminos[$glu_single]['count'] += 1;
				}else{
					$pi_aminos[$glu_single]['name'] = $detail['full'];
					$pi_aminos[$glu_single]['detail'] = $detail;
					$pi_aminos[$glu_single]['count'] = 1;
					$pi_aminos[$glu_single]['residue'] = $detail['residue'];
				}
			}
			
			if($flag==8){
				if(isset($pi_aminos[$lys_single])){
					$pi_aminos[$lys_single]['count'] += 1;
				}else{
					$pi_aminos[$lys_single]['name'] = $detail['full'];
					$pi_aminos[$lys_single]['detail'] = $detail;
					$pi_aminos[$lys_single]['count'] = 1;
					$pi_aminos[$lys_single]['residue'] = $detail['residue'];
				}
			}
		}
	   $this->mAminoSubject->mPiAminos = $pi_aminos;
	}
}
