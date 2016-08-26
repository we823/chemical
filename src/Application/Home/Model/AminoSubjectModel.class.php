<?php
	
namespace Home\Model;
/**
 * 校验氨基酸序列的对象
 */	
class AminoSubjectModel{
	/**
	 * excel存放的比对的标准数据
	 */ 
	private $mChemicalDatas;
	/**
	 * excel中列的对应关系
	 * @param element_index 元素对应关系
	 * standard_index 标准表对应关系
	 * pk_index pk相关对应关系
	 * const_index 单元素分子量关系
	 * side_special_index 侧链特殊元素对应关系
	 */
	private $mBaseIndex;
	/**
	 * 预先定义的结果类型
	 * cyclo_type 环类型文字说明
	 * solubility_result 溶解性文字说明
	 * hydrophily_result 平均亲水性文字说明
	 */
	private $mResultType;
	/**
	 * 默认设置，由于设置默认值。如nterm及cterm不显示的设置
	 */
	private $mDefaultValue;
	
	/**
	 * 用户选择的环类型
	 */
	private $mCycloType = -1;

	// 单字母序列
	private $mSingle;
	// 完整序列
	private $mFull;

	// 平均分子量
	private $mMw=0;
	// 精确分子量
	private $mEm=0;
	// 氨基酸总个数
	private $mAminoCount=0;
	// 酸基个数
	private $mAcidCount = 0;
	// 碱基个数
	private $mBaseCount = 0;
	/**
	 * 氨基酸具体详情，标记具体个数
	 */ 
	private $mAminoDetails=array();
	/**
	 * 和分子式相关的具体集合
	 */
	private $mElementAminos = array();
	/**
	 * 具体的元素个数，根据$aminoDetails计算
	 */ 
	private $mElements=array();
	/**
	 * 和PI相关的氨基酸集合
	 */
	private $mPiAminos = array();
	/**
	 * 记录氨基酸所在位置，用于二硫键校验
	 */
	private $mAminoLocation = array();
	/**
	 * 记录有环的片段
	 */
	private $mCycloFragments = array(
	   '0'=>array(),
	   'A'=>array(),
	   'B'=>array()
	);
	/**
	 * 记录特殊标记
	 */
	private $mSpecialFlags = array();
	/**
	 * 等电点在PH1-14的值
	 */
	private $mY;
	/**
	 * 等电点
	 */ 
	private $mPi;
	/**
	 * ph=7时的等电点
	 */ 
	private $mPi7;
	/**
	 * y轴最大值
	 */ 
	private $mMaxY;
	/**
	 * y轴最小值
	 */ 
	private $mMinY;
	/**
	 * 分子式
	 */
	private $mFormula;
	/**
	 * 分子式html格式
	 */ 
	private $mFormulaHtml;
	/**
	 * NCTerm需要在前端显示
	 */ 
	private $mOtherAmino;
	/**
	 * 和PI相关的c/nterm集合
	 */
	private $mPiOtherAminos=array();
	/**
	 * 亲水性总值
	 */ 
	private $mHydrophilyCount = 0;
	/**
	 * 平均亲水性值
	 */ 
	private $mHydrophily;
	/**
	 * 平均亲水性文字说明
	 */ 
	private $mHydrophilyResult;
	
	/**
	 * 溶解性序号
	 */ 
	private $mSolubilityIndex;
	/**
	 * 溶解性文字说明
	 */ 
	private $mSolubilityResult;
	
	private $mHasError = false;
	private $mMessage = '';
	/**
	 * 原始序列
	 */
	private $mOriginal;
	/**
	 * 有效序列，去除备注信息
	 */
	private $mSubject;
	/**
	 * 中间有效序列，去除nterm和cterm
	 */
	private $mMiddleSubject;
	private $mCterm ;
	private $mNterm;
	
	/**
	 * 链的基本信息
	 */
	private $mChains = array();
	
	private $mSubjects = array();
	private $mMiddleSubjects = array();
	private $mCterms = array();
	private $mNterms = array();
	/**
	 * 完整序列后需要显示的附加信息
	 */
	private $mAttachs = array();

	/**
	 * 自定义二硫键位置标识
	 */
	private $mCustomCys;
	/**
	 * 需要输出的二硫键位置
	 */
	private $mCysRealIndex = array();
	/**
	 *  当环类型选择正确时标记
	 */
	private $cycloTypeCorrect=false;
	/**
	 * 备注，在序列最后出现的小括号，并且前面不是cyclo、chain等标记性字符
	 */
	private $mMemo;
	private $mHasMemo = false;
	/**
	 * 氨基酸片段，以特殊标记为分割点
	 */
	private $mFragments = array();
	
	public function __set($name, $value){
		$this->$name = $value;
	}
	
	public function __get($name){
		return $this->$name;
	}
	
	/**
	 * 获取序列相关信息
	 */
	public function getResult(){
		return array(
		   'attachs'=>$this->mAttachs,
		   'single'=>$this->mSingle,
		   'full'=>$this->mFull,
		   'aminoCount'=>$this->mAminoCount,
		   'acidCount'=>$this->mAcidCount,
		   'baseCount'=>$this->mBaseCount,
		   'aminoDetails'=>$this->mAminoDetails,
		   'elementAminos'=>$this->mElementAminos,
		   'elements'=>$this->mElements,
		   'piAminos'=>$this->mPiAminos,
		   'specialFlags'=>$this->mSpecialFlags,
		   'aminoLocation'=>$this->mAminoLocation,
		   'cycloFragments'=>$this->mCycloFragments,
		   'mw'=>$this->mMw,
		   'em'=>$this->mEm,
		   'y'=>$this->mY,
		   'pi'=>$this->mPi,
		   'pi7'=>$this->mPi7,
		   'maxY'=>$this->mMaxY,
		   'minY'=>$this->mMinY,
		   'formula'=>$this->mFormula,
		   'formulaHtml'=>$this->mFormulaHtml,
		   'otherAmino'=>$this->mOtherAmino,
		   'piOtherAmino'=>$this->mPiOtherAminos,
		   'nterms'=>$this->mNterms,
		   'cterms'=>$this->mCterms,
		   'hydrophily'=>$this->mHydrophily,
		   'hydrophilyResult'=>$this->mHydrophilyResult,
		   'solubilityResult'=>$this->mSolubilityResult,
		   'solubilityIndex'=>$this->mSolubilityIndex,
		   'hasError'=>$this->mHasError,
		   'message'=>$this->mMessage,
		   'subjects'=>$this->mSubjects,
		   'chains'=>$this->mChains
		);
	}
    
	public function __toString(){
		return 'hasError: '.$this->mHasError.' message: '.$this->mMessage.' single: '.$this->mSingle.' full:'.$this->mFull;
	}
	/**
	 * 加载excel表格数据
	 */
	public function loadBaseData($excelFilename){
		vendor('PHPExcel.PHPExcel.IOFactory');
		$input_filetype = 'Excel5';
		$input_filename = $excelFilename; //'./data/data.xls';
	
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

		$base_index = $this->mBaseIndex;
		$const_index_name = $base_index['const_index']['name'];
		// 获取元素常量
		if (($const_count = count($amino_const_sheetdata)) > 0) {
			$const_values = array_values($amino_const_sheetdata);
	
			for ($index = 1; $index < $const_count; $index++) {
				$amino_const_data[$const_values[$index][$const_index_name]] = $const_values[$index];
			}
		}
	    
		
		$standard_count = count($standard_sheetdata);
		// 获取氨基酸数据及匹配模式
		if ($standard_count > 0) {
			// 所有元素
			$standard_values = array_values($standard_sheetdata);
			$pattern_values = array();
			
			$standard_index = $base_index['standard_index'];
			$standard_single = $standard_index['single'];
			$standard_full = $standard_index['full'];
			$standard_flag = $standard_index['flag'];
			
			for($index=1; $index < $standard_count; $index++){
				$value = $standard_values[$index];
				
				$single = $value[$standard_single];
				$full = $value[$standard_full];
				
				// 获取最长氨基酸字符长度
				$single_length = strlen($single);
				$full_length = strlen($full);
				$single_length = ($single_length>$full_length) ? $single_length : $full_length;
				$amino_max_length = ($single_length > $amino_max_length) ? $single_length : $amino_max_length;
	            
				$standard_data[$single] = $value;
				$standard_data[$full] = $value;
				
				$flag = $value[$standard_flag];
				if($flag==1){
					array_push($pattern_values, $value);
				}
				
				if($flag==2 || $flag==4){
					$nterm_data[$single] = $value;
				}
				
	            if($flag==3 || $flag==5){
	            	$cterm_data[$single] = $value;
	            }
			}
		}

		// 计算pk相关的data值
		$pk_count=count($pk_sheetdata);
		if($pk_count > 0){
			$pk_values = array_values($pk_sheetdata);
			$pk_single = $base_index['pk_index']['single'];
			for($index=1; $index<$pk_count; $index++){
				$pk_data[$pk_sheetdata[$index][$pk_single]] = $pk_sheetdata[$index];
			}
		}
		
		$side_special_count = count($side_special_sheetdata);
		if($side_special_count>0){
			$side_special_values = array_values($side_special_sheetdata);
			$side_special_single = $base_index['side_special_index']['single'];
			for($index=1; $index<$side_special_count; $index++){
				$side_special_tmp = $side_special_values[$index];
				$side_special_data[$side_special_tmp[$side_special_single]] = $side_special_tmp;
			}
		}

		$this->mChemicalDatas = array(
		    'amino_max_length'=>$amino_max_length+1,
		    'standard_data' => $standard_data, 
		    'amino_const_data' => $amino_const_data, 
		    'pk_data' => $pk_data,
		    'side_special_data'=>$side_special_data,
		    'cterm_data' => $cterm_data,
		    'nterm_data' => $nterm_data
	     );
	}

    /**
	 * 分析序列
	 */
    public function analyze($subject){

    	// 1. 获取备注信息
    	$this->mOriginal = $subject;
    	$subject = $this->checkMemo($subject);
		$this->mSubject = $subject;
        
		$this->getChains();
		//3. 获取nterm及cterm
	    $this->getTerm();
		
		//4. 分析中间序列
		$this->analyzeMiddleSubjects();
    }
	
	/**
	 * 根据分析结果，创建所有氨基酸信息
	 */
	public function buildAminoInfo(){
		/**
		 * 获取序列相关信息,填充aminoDetail相关数组
		 */
		$amino_result = $this->getAminoDetail();
		$single = $amino_result['single'];
		$full = $amino_result['full'];
		
        if($this->mHasError){
        	return;
        }
		
	    $this->fixMAP();
	    
	    $this->buildElements();
		/**
		 * 计算成环信息
		 */
		$this->calculateCycloType();
		
		/**
		 * 计算二硫键信息
		 */
		$this->calculateCys();
		/**
		 * 特殊标记对PI产生的影响
		 */
		$this->fixSpecialFlags();
		
	    $this->buildElementInfos();
		
		$attach_info = $this->getAttachInfo();
		$single = $single . $attach_info['single'];
		$full = $full . $attach_info['full'];
		
		$this->mSingle = $single;
	    $this->mFull = $full;
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
			$this->mHasMemo = false;
			$this->mMemo = 'no )';
			return $subject;
		}
		
		$stack_result = reserve_stack($subject);
		// 获取发生错误
		if($stack_result['has_error']){
			$this->mHasMemo = false;
			$this->mMemo = $stack_result['message'];
			return $subject;
		}
		
		$start_index = $stack_result['start_index'];
		$content = $stack_result['content'];
		
		// 判断()之前是否为特殊标记符号
		$side_special_index = $this->mBaseIndex['side_special_index'];
		$side_special_data = $this->mChemicalDatas['side_special_data'];
		
		$special_flags = array();
		$side_special_single = $side_special_index['single'];
		$side_special_full = $side_special_index['full'];
		$side_special_memo_flag = $side_special_index['memo_flag'];
		
		foreach($side_special_data as $key=>$value){
			$tmp_single = $value[$side_special_single];
			$tmp_full = $value[$side_special_full];
			$tmp_flag = $value[$side_special_memo_flag];
			
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
					    $this->mHasMemo = false;
						$this->mMemo = '有特殊标记：'.$pre_subject;
						
						return $subject;
					}
				}
			}
		}
		
		$aminoResult = $this->amino_to_array($content);
	
		if($aminoResult['has_error']){
			$this->mHasMemo = true;
			$this->mMemo = $content;
			
			$subject = substr($subject, 0, strlen($subject) - strlen($content) - 2);
			return $subject;
		}
		
		$this->mHasMemo = false;
		$this->mMemo = '可转化';
		return $subject;
	}

    private function getChains(){
    	$subject = $this->mSubject;
		
		$has_chain = false;
		if(strpos($subject, 'chainA')>-1){
			$chain_result = stack($subject);
			if(isset($chain_result['hasError'])){
				return;
			}
			
			$chain_subject = $chain_result['content'];
			$subject = substr($subject, $chain_result['end_index']+1);
			
			$has_chain = true;
			$this->mChains['A'] = $chain_subject;
			$this->mSubjects['A'] = $chain_subject;
		}
		
		if(strpos($subject, 'chainB')>-1){
			$chain_result = stack($subject);
			if(isset($chain_result['hasError'])){
				return;
			}
			
			$chain_subject = $chain_result['content'];

			$has_chain = true;
			$this->mChains['B'] = $chain_subject;
			$this->mSubjects['B'] = $chain_subject;
		}
		
		if(!$has_chain){
			$this->mChains['0'] = $subject;
			$this->mSubjects['0'] = $subject;
		}
    }
	/**
	 * 分解nterm和cterm
	 */
	private function getTerm(){
		
		$default_value = $this->mDefaultValue;
		$nterm = $default_value['nterm'];
		$cterm = $default_value['cterm'];
		
		$nterm_data = $this->mChemicalDatas['nterm_data'];
		$cterm_data = $this->mChemicalDatas['cterm_data'];
		
		$standard_single = $this->mBaseIndex['standard_index']['single'];
		$standard_resiude = $this->mBaseIndex['standard_index']['residue'];
		
		$subjects = $this->mSubjects;
		if(count($subjects)==0){
			$this->mHasError = true;
			$this->mMessage = '序列为空，无法分析';
			return;
		}
		
		foreach($subjects as $chain=>$subject){
			$aminos = split('-', $subject);
			$amino_count = count($aminos);
	        
			
			if($amino_count>0){
				$tmp_amino = $aminos[0];
				$amino_nterm = isset($nterm_data[$tmp_amino]) ? $nterm_data[$tmp_amino] : $nterm_data[$tmp_amino.'-'];
				if(is_null($amino_nterm)){
					$amino_nterm = isset($nterm_data[$nterm]) ? $nterm_data[$nterm] : $nterm_data[$nterm.'-'];
					$tmp_amino = array(
					   'name'=>$nterm,
					   'count'=>1,
					   'residue'=>$amino_nterm[$standard_resiude],
					   'detail'=>$amino_nterm
					);
					if(isset($this->mPiOtherAminos[$nterm])){
						$this->mPiOtherAminos[$nterm]['count'] += 1;
					}else{
						$this->mPiOtherAminos[$nterm] = $tmp_amino;
					}
				}else{
					$nterm = $amino_nterm[$standard_single];
					
					$nterm_length = 0;
					// 由于nterm在表示中，可以有-也可以无-，需要重新计算并把-算进去。
					if(preg_match('/-$/', $nterm)>0){
						$nterm_length = strlen($nterm);
					}else{
						$nterm_length = strlen($nterm) + 1;
					}
					$subject = substr($subject, $nterm_length);
					
					$tmp_amino = array(
					   'name'=>$nterm,
					   'count'=>1,
					   'residue'=>$amino_nterm[$standard_resiude],
					   'detail'=>$amino_nterm
					);
					if($nterm != $default_value['nterm']){
						
						if(isset($this->mOtherAmino[$nterm])){
							$this->mOtherAmino[$nterm]['count'] += 1;
							$this->mPiOtherAminos[$nterm]['count'] += 1;
							
						}else{
							$this->mOtherAmino[$nterm] = $tmp_amino;
							$this->mPiOtherAminos[$nterm] = $tmp_amino;
						} 
					}
				}
				
				$tmp_cterm = $aminos[$amino_count-1]; 
				$amino_cterm = isset($cterm_data[$tmp_cterm]) ? $cterm_data[$tmp_cterm] : $ctermData['-'.$tmp_cterm];
				if(is_null($amino_cterm)){
					$amino_cterm = isset($cterm_data[$cterm]) ? $cterm_data[$cterm] : $ctermData['-'.$cterm];
					$tmp_amino = array(
					   'name'=>$cterm,
					   'count'=>1,
					   'residue'=>$amino_cterm[$standard_resiude],
					   'detail'=>$amino_cterm
					);
					
					if(isset($this->mPiOtherAminos[$cterm])){
						$this->mPiOtherAminos[$cterm]['count'] += 1;
					}else{
						$this->mPiOtherAminos[$cterm] = $tmp_amino;
					}
				}else{
					$cterm = $amino_cterm[$standard_single];
					
					$cterm_length = 0;
					if(preg_match('/^-/', $cterm)){
						$cterm_length = strlen($cterm);
					}else{
						$cterm_length = strlen($cterm) + 1;
					}
					
					$subject = substr($subject, 0, strlen($subject) - $cterm_length);
	                
					$tmp_amino = array(
					   'name'=>$cterm,
					   'count'=>1,
					   'residue'=>$amino_cterm[$standard_resiude],
					   'detail'=>$amino_cterm
					);
						
					if($cterm != $default_value['cterm']){
						if(isset($this->mOtherAmino[$cterm])){
							$this->mOtherAmino[$cterm]['count'] += 1;
							$this->mPiOtherAminos[$cterm]['count'] += 1;
						}else{
							$this->mOtherAmino[$cterm] = $tmp_amino;
							$this->mPiOtherAminos[$cterm] = $tmp_amino;
						}
					}
				}
			}
			
			$this->mNterms[$chain] = $nterm;
			$this->mCterms[$chain] = $cterm;
			$this->mMiddleSubjects[$chain] = $subject;
		}
	}
    
	private function analyzeMiddleSubjects(){
		$middle_subjects = $this->mMiddleSubjects;
		if(count($middle_subjects)==0){
			$this->mHasError = true;
			$this->mMessage = '中间序列为空，无需分析';
			return;
		}
		
		foreach($middle_subjects as $chain=>$middle_subject){
			$this->analyzeMiddleSubject($middle_subject, $chain);
		}
		
	}
	/**
	 * 分析中间序列
	 */
    private function analyzeMiddleSubject($middleSubject, $chain){
    	
    	$middle_subject = $middleSubject;
		// 1. 按照普通序列校验
		$amino_result = $this->amino_to_array($middle_subject);

		if($amino_result['has_error']==false){
			
			$amino_fragment = new \Common\Model\AminoFragementModel;
			$amino_fragment->mIndex = 0;
			$amino_fragment->mDetail = $amino_result['amino_detail'];
			$amino_fragment->mChain = $chain;
			array_push($this->mFragments, $amino_fragment);
		}else{
			// 	2. 按照特殊序列校验	
			$fragment_result = $this->analyzeSpecialFragements($middle_subject, $amino_result);

			if($fragment_result['has_error']){
				// 获取所有片段发生错误
				$this->mHasError = $fragment_result['has_error'];
				$this->mMessage = $fragment_result['message'];
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
						$this->mHasError = true;
					    $this->mMessage = $message;
					    $this->mFragments = null;
					}else{

						$this->mHasError = false;
						$this->mMessage = '成功获取所有片段';
						// 设置片段的chain信息
						$this->setFragmentChain($fragment_result, $chain);
						array_push($this->mFragments, $fragment_result);
					}
				}else{
					$this->mHasError = true;
					$this->mMessage = '未能成功获取所有片段';
					$this->mFragments = null;
				}
			}
		}
    }
	
	/**
	 * 用特殊标记分别校验，并获取结果
	 */
	private function analyzeSpecialFragements($subject, $amino_result=null){
		$side_special_data = $this->mChemicalDatas['side_special_data'];
		$flag_data = null;
		$special_result = array(
		   'start_index'=>-1,
		   'end_index'=>0,
		   'content'=>'',
		   'has_flag'=>false,
		   'flag_name'=>'',
		   'flag_data'=>$flag_data
		);

		$single_index = $this->mBaseIndex['side_special_index']['single'];
		$full_index = $this->mBaseIndex['side_special_index']['full'];
		
		foreach($side_special_data as $key=>$side_special){
			$single = $side_special[$single_index];
			$full = $side_special[$full_index];
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

			if(!is_null($amino_result)){
				$message = $amino_result['message'];
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
	 * 设置片段上的所在chain
	 */
	private function setFragmentChain(&$rFragmentResult, $chain){
		if(is_null($rFragmentResult)) return;

		$result_count = count($rFragmentResult);
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
				array_push($this->mCycloFragments[$fragment->mChain], $fragment->toArray());
			}
		}
	}
	
	/**
	 * 设置子片段所在的chain
	 */
	private function setSubFragmentChain(&$rSubFragments, $chain){
		$count = count($rSubFragments);
		if($count==0) return;
		
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
				array_push($this->mCycloFragments[$fragment->mChain], $fragment->toArray());
			}
			
			if($fragment->mHasFlag){
				array_push($this->mSpecialFlags, $fragment->toArray());
			}
		}
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
		$amino_result = $this->amino_to_array($subject);
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
	 * 二硫键计算
	 */
    private function customCysCalculate(){
    	$customCys = $this->customCys;
    }
	
	/**
	 * 获取氨基酸详细信息
	 */
    private function getAminoDetail(){
		$fragments = $this->mFragments;
		$fragments_count = count($fragments);
		if($fragments_count==0){
			$this->mHasError = true;
			$this->mMessage = '氨基酸序列片段为空，无法正常分析，请检查';
			return;
		}
      
		$final_single = '';
		$final_full = '';
		$standard_data = $this->mChemicalDatas['standard_data'];

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
						    $side_special_index = $this->mBaseIndex['side_special_index'];
							$pre_link_index = $side_special_index['pre_link'];
							
						    $pre_link = $flag_data[$pre_link_index];
							
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
				
	            /**
				 * 片段与片段的连接符，默认为-，特殊的可以去除，在side_special表中设置
				 */
				if($fragment->mIndex>0){
					$has_flag = $fragment->mHasFlag;
					$link = '-';
					if($has_flag){
						$flag_data = $fragment->mFlagData;
					    $side_special_index = $this->mBaseIndex['side_special_index'];
						$pre_link_index = $side_special_index['pre_link'];
						
					    $pre_link = $flag_data[$pre_link_index];
						
						if($pre_link==0){
							$link = '';
						}
					}
				}
				
				$single = $amino_single;
				$full = $amino_full;
			}

			$ncTermResult = $this->plusNCTerm($single, $full, $chain);
			$tmp_single = $ncTermResult['single'];
			$tmp_full = $ncTermResult['full'];

			if($chain==='A'){
				$single = 'chainA(' . $tmp_single . ')';
				$full = 'chainA(' . $tmp_full . ')';
			}else if($chain==='B'){
				$single =  'chainB(' . $tmp_single . ')';
				$full =  'chainB(' . $tmp_full . ')';
			}else{
				$single =  $tmp_single ;
				$full = $tmp_full;
			}
			
			$final_single = $final_single . $single;
		    $final_full = $final_full . $full;
        }
		
		if($this->mHasMemo){
			$final_single = $final_single . '('. $this->mMemo. ')';
			$final_full = $final_full . '('. $this->mMemo. ')';
		}
		
		return array(
		   'single'=>$final_single,
		   'full'=>$final_full
		);
    }

    
	private function getAttachInfo(){
		
		$attach_info = array();
		$attach = '';
		$single = '';
		$full = '';
		
		//是否包含附件显示信息，如指示2硫键、成环类型等
		$has_attach = false; 

		$attachs = $this->mAttachs;
		if(count($attachs)>0){
			$attach = implode(';', $attachs);
			$has_attach = true;
		}

		if($has_attach){
			$single = $single . '('.$attach.')';
		    $full = $full . '('.$attach.')';
		}
		$attach_info['single'] = $single;
		$attach_info['full']=$full;
		return $attach_info;
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
					if(!is_null($fragment_result)){
						$single = $single . $fragment_result['single'];
						$full = $full . $fragment_result['full'];
					}
				}
			}else{
				$standard_data = $this->mChemicalDatas['standard_data'];
				$amino_data = $this->getAminoData($fragment->mDetail, $standard_data, $chain);
				
				$single = $amino_data['single'];
				$full = $amino_data['full'];
			}
			
			$flag_data = $fragment->mFlagData;
			$side_special_index = $this->mBaseIndex['side_special_index'];
			
			$single_index = $side_special_index['single'];
			$full_index = $side_special_index['full'];
			
			$flag_single = $flag_data[$single_index];
			$flag_full = $flag_data[$full_index];
			$single = $flag_single . '(' . $single . ')';
			$full = $flag_full . '(' . $full . ')';
			
			// 特殊标记需要计算是否要加入氨基酸个数
			$flag_index = $side_special_index['flag'];
			$flag = $flag_data[$flag_index];
			if($flag==1){
				$standard_data = $this->mChemicalDatas['standard_data'];
				$standard_index = $this->mBaseIndex['standard_index'];
				$standard_residue = $standard_index['residue'];
				
				$residue = $standard_data[$flag_single][$standard_residue];
				
				$this->pushAminoDetail($flag_single, $flag_full, $residue, $standard_data[$flag_single]);
			}
			
		}else{
			$standard_data = $this->mChemicalDatas['standard_data'];
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
	private function pushAminoDetail($single, $full, $residue, $amino){
		if(isset($this->mAminoDetails[$single])){
			$this->mAminoDetails[$single]['count'] ++;
			$this->mElementAminos[$single]['count']++;
			$this->mPiAminos[$single]['count']++;
		}else{
			$amino_detail = array(
			   'name'=>$full,
			   'residue'=>$residue,
			   'count'=>1,
			   'detail'=>$amino
			);
			
			$this->mAminoDetails[$single] = $amino_detail;
			$this->mElementAminos[$single] = $amino_detail;
			$this->mPiAminos[$single] = $amino_detail;
		}
	}
	/**
	 * 获取氨基酸具体个数数据
	 */
    private function getAminoData($amino_details, $standard_data, $chain){
		$single = '';
		$full = '';
	    
		$standard_index = $this->mBaseIndex['standard_index'];
		$standard_single = $standard_index['single'];
		$standard_full = $standard_index['full'];
		$standard_residue = $standard_index['residue'];
		$standard_hydrophily = $standard_index['hydrophily'];
		$standard_acid = $standard_index['acid'];
		$standard_base = $standard_index['base'];
		$standard_flag = $standard_index['flag'];
		
		$amino_location = $this->mAminoLocation[$chain];
		$start_location = 0;
		//var_dump($chain);
		if(is_null($amino_location)){
			$amino_location = array();
			$this->mAminoLocation[$chain] = $amino_location;
		}else{
			$start_location = count($amino_location);
		}
		
		$single_flags = array();
		$index = 0;
		foreach($amino_details as $key=>$amino ){
			$tmp_standard_data = $standard_data[$amino];
			
			$amino_location[$start_location + $index] = array(
			   'single'=>$amino,
			   'full'=>$tmp_standard_data[$standard_full]
			);
			
			if(is_null($tmp_standard_data)){
				continue;
			}
			$amino_single = $tmp_standard_data[$standard_single];
			$amino_full = $tmp_standard_data[$standard_full];
			
			$flag = $tmp_standard_data[$standard_flag];
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
			$this->pushAminoDetail($tmp_amino_single, $tmp_amino_full, $tmp_standard_data[$standard_residue], $tmp_standard_data);
		}
        
		$this->mAminoLocation[$chain] = $amino_location;
		
		return array(
		   'single'=>$single,
		   'full'=>$full
		);
    }
    
	// 获取元素具体个数
	private function buildElements(){
		$standard_data = $this->mChemicalDatas['standard_data'];
		$amino_details = $this->mAminoDetails;
		$elements = $this->mElements;
		
		$element_index = $this->mBaseIndex['element_index'];
		$standard_index = $this->mBaseIndex['standard_index'];

		if(count($amino_details)==0){
			foreach($element_index as $key=>$index){
				if(!isset($elements[$key])){
					$elements[$key] = 0;
                }
			}
		}else{
			$standard_hydrophily = $standard_index['hydrophily'];
			$standard_acid = $standard_index['acid'];
			$standard_base = $standard_index['base'];
			
			foreach($amino_details as $key=>$amino){
				$tmp_standard_data = $standard_data[$key];
				if(is_null($tmp_standard_data)){
					continue;
				}

				foreach($element_index as $key=>$index){
					$elements[$key] += $tmp_standard_data[$index] * $amino['count'];
				}
				
				$this->mAminoCount += $amino['count'];
				// 亲水性总值计算
				$this->mHydrophilyCount += $tmp_standard_data[$standard_hydrophily];
				$this->mAcidCount += $tmp_standard_data[$standard_acid] * $amino['count'];
				$this->mBaseCount += $tmp_standard_data[$standard_base] * $amino['count'];

			}
		}
		
		$chains = $this->mChains;
		$chain_count = count($chains);
		if(isset($elements['H'])){
			$elements['H'] += 2 * $chain_count;
		}
		if(isset($elements['O'])){
			$elements['O'] += 1 * $chain_count;
		}
		
		$other_aminos = $this->mOtherAmino;
		
		if(count($other_aminos)>0){
			foreach($other_aminos as $other_amino){
				$amino = $other_amino['detail'];
				foreach($element_index as $key=>$index){
					$elements[$key] += $amino[$index] * $other_amino['count'] ;
				}
			}
		}
		$this->mElements = $elements;
	}
	
	/**
	 * 根据元素表计算分子相关信息
	 */
    private function buildElementInfos(){
		$const_datas = $this->mChemicalDatas['amino_const_data'];
    	$elements = $this->mElements;
		// 分子式
		$formula = '';
		$formulaHtml = '';
		// 平均分子量
		$mw = 0;
		// 精确分子量
		$em = 0;
		
		$const_index = $this->mBaseIndex['const_index'];
		$mw_index = $const_index['mw'];
		$em_index = $const_index['em'];

		foreach($elements as $key=>$value){
			if($value>0){ //元素个数大于0
				$formula = $formula . $key . $value;
				$formulaHtml = $formulaHtml . $key . '<sub>'.$value.'</sub>';
				$const_data = $const_datas[$key];
				if(!is_null($const_data)){
					$mw += $const_data[$mw_index] * $value;
				    $em += $const_data[$em_index] * $value;
				}
				
			}
		}
		
		$this->mFormula = $formula;
		$this->mFormulaHtml = $formulaHtml;
		$this->mMw = sprintf("%.4f",$mw);
		$this->mEm = sprintf("%.4f",$em);
		
		$this->mHydrophily = round($this->mHydrophilyCount / $this->mAminoCount, 2);
		$this->mHydrophilyResult = $this->getHydrophilyResult($this->mHydrophily);
		
		$this->mSolubilityIndex = $this->getSolubilityIndex();
		$solubility_results = $this->mResultType['solubility_result'];
		$this->mSolubilityResult = $solubility_results[$this->mSolubilityIndex];
		
		$pi_result = $this->getPIResult();
		if(!is_null($pi_result)){
			$_pi = is_numeric($pi_result['pi']) ? sprintf('%.2f',$pi_result['pi']) : $pi_result['pi'];
			$this->mPi = ($_pi===0) ? 0 : $_pi;
			$this->mY = $pi_result['y'];
			$this->mPi7 = sprintf('%.2f',$pi_result['pi7']);
			$this->mMinY = $pi_result['minY'];
			$this->mMaxY = $pi_result['maxY'];
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
		
		$y = $this->mAminoCount;
		$acidCount = $this->mAcidCount;
		$baseCount = $this->mBaseCount;
		$x = $hydrophily = $this->mHydrophily;
		$character1 = $this->mOriginal;
		
		$amino_details = $this->mAminoDetails;
		$standard_data = $this->mChemicalDatas['standard_data'];
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
		
		$pi_aminos = $this->mPiAminos;
		$pk_data = $this->mChemicalDatas['pk_data'];
	    
		if(!isset($pi_aminos) || !isset($pk_data)){
			return $result;
		}
		
		$ys = array();
		$maxY = 0;
		$pi = 0;
		$pi7 = 0; //当ph=7时的净电荷数
		
		//保存y和ph的值
		$piTemp = array(); 
		
		$ncterm_index = $this->mBaseIndex['ncterm'];
		
		$cterms = $this->mCterms;
		$nterms = $this->mNterms;
		
		$cterm_value = 0;
		$nterm_value = 0;
		
		$other_aminos = $this->mPiOtherAminos;
		
		$nterm_count = 0;
		if(count($nterms)>0){
			foreach($nterms as $tmp_nterm){
				$tmp_amino = $other_aminos[$tmp_nterm];
				if(!is_null($tmp_amino)){
					$nterm_value += $tmp_amino['count'] * $tmp_amino['detail'][$ncterm_index];
					$nterm_count += $tmp_amino['count'];
				}
			}
		}
		
		$cterm_count = 0;
		if(count($cterms)>0){
			foreach($cterms as $tmp_term){
				$tmp_amino = $other_aminos[$tmp_term];
				if(!is_null($tmp_amino)){
					$cterm_value += $tmp_amino['count'] * $tmp_amino['detail'][$ncterm_index];
					$cterm_count += $tmp_amino['count'];
				}
			}
		}
		
		$cterm_data = null;
		$nterm_data = null;

		//负值的个数
		$flag0 =0;
		//正值的个数
		$flag1 =0;
		if($cterm_value > 0){
			$cterm_data = $pk_data['C-term'];
			$flag0++;
		}
		
		if($nterm_value > 0){
			$nterm_data = $pk_data['N-term'];
			$flag1++;
		}
		$detail = $pi_aminos;
		$count = count($detail);

		$pk_index = $this->mBaseIndex['pk_index'];
		$_pk = $pk_index['pk'];
		$_flag = $pk_index['flag'];
		
		foreach($detail as $name=>$value){
			if(isset($pk_data[$name])){
				$tmp = $pk_data[$name];
				
				if($tmp[$_flag]==0){
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
			
			if(!is_null($cterm_data)){
				$y += $this->calculateSinglePi($x, $cterm_data[$_pk], $cterm_count, $cterm_data[$_flag]);
			}
			
			if(!is_null($nterm_data)){
				$y += $this->calculateSinglePi($x, $nterm_data[$_pk], $nterm_count, $nterm_data[$_flag]);
			}
			
			if($count==0){
				continue;
			}
			foreach($detail as $name=>$value){
				
				if(isset($pk_data[$name])){
					$tmp = $pk_data[$name];
					$y += $this->calculateSinglePi($x, $tmp[$_pk], $value['count'], $tmp[$_flag]);
					
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
	
	private function plusNCTerm($single, $full, $chain){
		// cterm和nterm默认需要隐藏的残基
		$default_value = $this->mDefaultValue;
		$default_nterm = $default_value['nterm'];
		$default_cterm = $default_value['cterm'];
		$nterm = ($this->mNterms[$chain]==$default_nterm) ? '' : $this->mNterms[$chain];
		$cterm = $this->mCterms[$chain]==$default_cterm ? '' : $this->mCterms[$chain];
		
		if(!checkNull($nterm) && preg_match('/-$/', $nterm)==0){
			$nterm = $nterm . '-';
		}
		
		if(!checkNull($cterm) && preg_match('/^-/', $cterm)==0){
			$cterm = '-'.$cterm;
		}
		$single = $nterm .$single . $cterm;
		$full = $nterm .$full . $cterm;
		
		return array(
		  'single'=>$single,
		  'full'=>$full
		);
	}
	
	/**
	 * 修订MAP相关数字，当序列中存在MAP时，则其他氨基酸数量要增加
	 */
	private function fixMAP(){
		$aminoDetails = $this->mAminoDetails;
		$amino_keys = array_keys($aminoDetails);
		$hasMap = false;
		$number = 0;
		foreach($amino_keys as $key){
			$location = strpos($key, 'MAP');
			if($location >-1){
				$hasMap = true;
				$number = substr($key, $location + 3);
				if(is_numeric($number)){
					$number = intval($number);
				}
				
				break;
			}
		}
 
		if($hasMap){

			foreach($amino_keys as $key){
				if(strpos($key, 'MAP')===false){
					$this->mAminoDetails[$key]['count'] = $this->mAminoDetails[$key]['count'] * $number;
					$this->mPiAminos[$key]['count'] = $this->mPiAminos[$key]['count'] * $number;
					$this->mElementAminos[$key]['count'] = $this->mElementAminos[$key]['count'] * $number;
				}
			}
			$other_aminos = $this->mOtherAmino;
			foreach($other_aminos as $key=>$other_amino){
				$this->mOtherAmino[$key]['count'] = $this->mOtherAmino[$key]['count'] * $number;
				$this->mPiOtherAminos[$key]['count'] = $this->mPiOtherAminos[$key]['count'] * $number;
			}

		}
	}
	
	/**
	 * 计算二硫键相关信息
	 * 影响1：分子式 2：PI
	 */
	private function calculateCys(){
		$custom_cys = $this->mCustomCys;
		if(!is_null($custom_cys) && strlen($custom_cys)){
			$pattern = '/[A|B]?[1-9]*[0-9]+\-[A|B]?[1-9]*[0-9]+/';
			$result_number = preg_match_all($pattern, $custom_cys, $items);
			if($result_number>0){
				$len = strlen($custom_cys);
				$item_len = 0;
				foreach($items[0] as $item){
					$item_len += strlen($item);
				}
				
				$item_len += count($items[0])-1;
				
				if($len!=$item_len){
					$this->mHasError = true;
					$this->mMessage = '二硫键格式不正确，请检查,如（1-8,2-10或A1-B2）';
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
					$amino_locations = $this->mAminoLocation;
					
					$locations = array();
					$repeat = array();
					foreach($cys_locations as $cys_location){
						$chain = $cys_location['chain'];
						$location = $cys_location['location'];
						
						$amino_location = $amino_locations[$chain];
						if(is_null($amino_location)){
							$this->mHasError = true;
							$this->mMessage = '序列中不存在侧链chain'.$chain;
							return;
						}
						
						$amino = $amino_location[$location-1];
						if(is_null($amino)){
							$this->mHasError = true;
							$this->mMessage = '位置'.$location.' 不存在氨基酸';
							return;
						}
		
						if($amino['single']!='C'){
							$this->mHasError = true;
							$this->mMessage = '位置'.$location.' 氨基酸不是Cys，而是'.$amino['full'];
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
					}

					if(count($locations)<count($cys_locations)){
						
						$this->mHasError = true;
						$this->mMessage = '输入的二硫键位置 '.implode(',', $repeat).' 重复，无法形成二硫键，请检查';
						return;
					}
					
					$cys_count = count($cys_locations);
					if(($cys_count % 2) != 0){
						$this->mHasError = true;
						$this->mMessage = '输入的二硫键位置未成对，请检查';
						return;
					}
					
					// 校验成功
					$this->mCysRealIndex = $cys_locations;
					$elements = $this->mElements;
					// 分子式影响
                    if(!isset($elements['H'])){
                    	$this->mElements['H'] = 0;
                    }
					
					$this->mElements['H'] -= 2*($cys_count/2);
					
					
					// pi影响
					$pi_aminos = $this->mPiAminos;
					if(isset($pi_aminos['C'])){
						$this->mPiAminos['C']['count'] -= 2*($cys_count/2);
					}
					
					// 备注
					$bridge = '';
					for($index=0; $index<$cys_count; $index++){
						$cys_location = $cys_locations[$index];
						$chain = $cys_location['chain'];
						$chain = ($chain=='0') ? '' : $chain;
						$location = $cys_location['location'];
						
						$bridge = $bridge . 'Cys'. $chain. $location;
						if($index<($cys_count-1)){
							if($index>0 && ($index+1)%2==0){
								$bridge = $bridge . ',';
							}else{
								$bridge = $bridge . '&';
							}
						}
		
					}
				   $bridge = $bridge.' bridge';
				   
				   array_push($this->mAttachs, $bridge);
				}
			}
		}
	}
	/**
	 * 计算成环类型的影响
	 */
	private function calculateCycloType(){
		$cyclo_types = $this->mResultType['cyclo_type'];
		$cyclo_type = $this->mCycloType;
		if($cyclo_type>-1){
			if(strpos(strtolower($this->mOriginal), 'cyclo')===false){
				$this->mHasError = true;
			    $this->mMessage = '选择了成环类型，但在序列中不存在环标记，请检查';
				return;
			}
			
			
			if($cyclo_type<4){
				array_push($this->mAttachs, $cyclo_types[$cyclo_type]);
			}
			
			$elements = $this->mElements;
			if($cyclo_type==0){
				if(isset($elements['H'])){
					$this->mElements['H'] -=2;
				}
				
				if(isset($elements['O'])){
					$this->mElements['O'] -= 1;
				}
				
				$nterms = $this->mNterms;
				if(count($nterms)>0){
					foreach($nterms as $nterm){
						$pi_other_aminos = $this->mPiOtherAminos;
						foreach($pi_other_aminos as $pi_other_amino){
							if($nterm == $pi_other_amino['name']){
								$this->mPiOtherAminos[$nterm]['count'] -= 1;
							    break;
							}
							
						}
					}
				}
				$cterms = $this->mCterms;
				if(count($cterms)>0){
					foreach($cterms as $cterm){
						$pi_other_aminos = $this->mPiOtherAminos;
						foreach($pi_other_aminos as $pi_other_amino){
							if($cterm == $pi_other_amino['name']){
								$this->mPiOtherAminos[$cterm]['count'] -= 1;
							    break;
							}
						}
					}
				}
				
				$this->mHydrophilyCount -= 2;
			}else if($cyclo_type == 1){
				if(isset($elements['H'])){
					$this->mElements['H'] -=2;
				}
				
				if(isset($elements['O'])){
					$this->mElements['O'] -= 1;
				}
				
				$pi_aminos = $this->mPiAminos;
				if(isset($pi_aminos['D'])){
					$this->mPiAminos['D']['count'] -= 1;
				}
				
				if(isset($pi_aminos['K'])){
					$this->mPiAminos['K']['count'] -= 1;
				}
				
				$this->mHydrophilyCount -= 4;
			}else if($cyclo_type == 2){
				if(isset($elements['H'])){
					$this->mElements['H'] -=2;
				}
				
				if(isset($elements['O'])){
					$this->mElements['O'] -= 1;
				}
				
				$pi_aminos = $this->mPiAminos;
				if(isset($pi_aminos['D'])){
					$this->mPiAminos['D']['count'] -= 1;
				}
				
				$nterms = $this->mNterms;
				if(count($nterms)>0){
					foreach($nterms as $nterm){
						$pi_other_aminos = $this->mPiOtherAminos;
						foreach($pi_other_aminos as $pi_other_amino){
							if($nterm == $pi_other_amino['name']){
								$this->mPiOtherAminos[$nterm]['count'] -= 1;
							    break;
							}
							
						}
					}
				}
				
				$this->mHydrophilyCount -= 3;
			}else if($cyclo_type == 3){
				if(isset($elements['H'])){
					$this->mElements['H'] -=2;
				}
				
				if(isset($elements['O'])){
					$this->mElements['O'] -= 1;
				}
				
				$pi_aminos = $this->mPiAminos;
				$cterms = $this->mCterms;
				if(count($cterms)>0){
					foreach($cterms as $cterm){
						$pi_other_aminos = $this->mPiOtherAminos;
						foreach($pi_other_aminos as $pi_other_amino){
							if($cterm == $pi_other_amino['name']){
								$this->mPiOtherAminos[$cterm]['count'] -= 1;
							    break;
							}
						}
					}
				}
				
				if(isset($pi_aminos['K'])){
					$this->mPiAminos['K']['count'] -= 1;
				}
				
				$this->mHydrophilyCount -= 3;
			}else if($cyclo_type == 4){
				if(isset($elements['H'])){
					$this->mElements['H'] -=2;
				}
			}
			
		}
	}

    /**
	 * 特殊序列对PI的影响
	 */
    private function fixSpecialFlags(){
    	$special_flags = $this->mSpecialFlags;
		if(count($special_flags)>0){
			$side_special_index = $this->mBaseIndex['side_special_index'];
			$single_index = $side_special_index['single'];
			$pi_index = $side_special_index['pi'];
			
			foreach($special_flags as $special_flag){
				$flag_data = $special_flag['flag_data'];
				if(is_null($flag_data)) continue;
				
				$single = $flag_data[$single_index];
				$pi = $flag_data[$pi_index];
				
				if(isset($this->mPiAminos[$single])){
					$this->mPiAminos[$single]['count'] += $pi;
				}
			}
		}
    }
	/**
	 * 将序列转换为数组
	 */
	private function amino_to_array($check_amino){
		$standard_data = $this->mChemicalDatas['standard_data'];
		$amino_max_length = $this->mChemicalDatas['amino_max_length'];
		
		$result = array();
		$amino_length = strlen($check_amino);
		$index = 0;
	    
		if(is_null($standard_data) || empty($standard_data)){
			return array(
			  'has_error'=>true,
			  'message'=>'可校验的标准数据为空，无法校验'
			);
		}
		
		while($index < $amino_length){
			//当前校验的字符串长度
			$current_amino_length = strlen($check_amino); 
			
			// 按照标准最长长度去计算子序列
			$sub_length = ($amino_max_length < $current_amino_length) ? $amino_max_length : $current_amino_length;
			$sub_amino_result = $this->get_sub_amino($standard_data, $amino_max_length, $check_amino);
			
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
				 $check_amino = substr($check_amino, $sub_amino_length);
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
				   'has_error'=>false,
				   'message'=>'正确匹配'
				);
			}
			// 若以-开头直接去判断，若有则返回，若无，则去除-
			$tmp_check_amino = substr($tmp_check_amino, 1);
			$sub_length--;
		}
		
		$sub_amino = substr($tmp_check_amino, 0, $sub_length);
		
		if(array_key_exists($sub_amino, $standard_data)){
			return array(
			   'sub_amino'=>$sub_amino,
			   'real_length'=>$real_length,
			   'has_error'=>false,
			   'message'=>'正确匹配'
			);
		}else{
			if($amino_max_length<=0){
				return array(
				   'has_error'=>true,
				   'message'=>"字符：$check_amino 无法完成匹配"
				);
			}
			$amino_max_length = $amino_max_length - 1;
			return $this->get_sub_amino($standard_data, $amino_max_length, $check_amino);
		}
	}
}
