<?php
	
	namespace Common\Model;
	
	class AminoSubjectModel{
		// 比对的标准数据
		private $chemicalDatas;
		// 元素在表格对应的关系
		private $elementIndex;
		// 标准表中对应列的关系
		private $standardIndex;
		// 计算pk值相关的表格对应
		private $pkIndex;
		// 元素分子量表对应
		private $constIndex;
		// 环类型的文字说明
		private $cycloTypes;
		// 溶解性文字说明
		private $solubilityResults;
		// 平均亲水性文字说明
		private $hydrophilyResults;

		// 单字母序列
		private $single;
		// 完整序列
		private $full;
		
		private $cycloType = -1;

		// 平均分子量
		private $mw=0;
		// 精确分子量
		private $em=0;
		// 氨基酸总个数
		private $aminoCount=0;
		// 酸基个数
		private $acidCount = 0;
		// 碱基个数
		private $baseCount = 0;
		/**
		 * 氨基酸具体详情，标记具体个数
		 */ 
		private $aminoDetails=array();
		/**
		 * 具体的元素个数，根据$aminoDetails计算
		 */ 
		private $elements=array();
		
		private $y;
		// 等电点
		private $pi;
		// ph=7时的等电点
		private $pi7;
		// y轴最大值
		private $maxY;
		// y轴最小值
		private $minY;
		// 分子式
		private $molecularFormula;
		// 分子式html
		private $formulaHtml;
		// NCTerm需要在前端显示
		private $otherAmino;
		// 亲水性总值
		private $hydrophilyCount = 0;
		// 平均亲水性值
		private $hydrophily;
		// 平均亲水性文字说明
		private $hydrophilyResult;
		
		private $amino_result_data;
		// 溶解性序列
		private $solubilityIndex;
		// 溶解性文字说明
		private $solubilityResult;
		
		private $hasError = false;
		private $message = '';
		
		private $original;
		private $cterm ;
		private $nterm;
		private $hasChain = false;
		private $chains = array();
		// cys所在位置信息
		private $sIndex = array();
		// 当用户输入需要计算二硫键时才计算
		private $calculateS = false;
		/**
		 * 自定义二硫键位置标识
		 */
		private $customCys;
		private $cysRealIndex = array();
		/**
		 *  当环类型选择正确时标记
		 */
		private $cycloTypeCorect=false;
		
		public function __set($name, $value){
			$this->$name = $value;
		}
		
		public function __get($name){
			return $this->$name;
		}
		
		/**
		 * 备注，在序列最后出现的小括号，并且前面不是cyclo、chain等标记性字符
		 */
		private $memo;
		private $hasMemo = false;
		
		public function pushChains($name, $value){
			$this->chains[$name] = $value;
		}
		
		/**
		 * 获取序列相关信息
		 */
		public function getResult(){
			return array(
			   'character1'=>$this->single,
			   'character3'=>$this->full,
			   'aminoCount'=>$this->aminoCount,
			   'acidCount'=>$this->acidCount,
			   'baseCount'=>$this->baseCount,
			   'aminoDetails'=>$this->aminoDetails,
			   'elements'=>$this->elements,
			   'mw'=>$this->mw,
			   'em'=>$this->em,
			   'y'=>$this->y,
			   'pi'=>$this->pi,
			   'pi7'=>$this->pi7,
			   'maxY'=>$this->maxY,
			   'minY'=>$this->minY,
			   'molecularFormula'=>$this->molecularFormula,
			   'formulaHtml'=>$this->formulaHtml,
			   'otherAmino'=>$this->otherAmino,
			   'hydrophily'=>$this->hydrophily,
			   'hydrophilyResult'=>$this->hydrophilyResult,
			   'amino_result_data'=>$this->amino_result_data,
			   'solubilityResult'=>$this->solubilityResult,
			   'solubilityIndex'=>$this->solubilityIndex,
			   'hasError'=>$this->hasError,
			   'message'=>$this->message
			);
		}
	
		private function getChain($full, $key){
			$chain = $this->chains[$key];
			if(is_null($chain)){
				return $full;
			}
			//var_dump($chain);
			//$full = $full.'chain'.$key.'('.$chain['amino'].')';
			return $full;
		}
		
		public function buildAminoInfo(){
			$standard_data = $this->chemicalDatas['standardData'];
			
			$single = '';
			$full = '';
			
			if($this->hasChain==false){
				$noChainResult = $this->getNoChainResult($standard_data);
				$single = $noChainResult['single'];
				$full = $noChainResult['full'];
			}else{
				$chainResult = $this->getChainResult($standard_data);
				$single = $chainResult['single'];
				$full = $chainResult['full'];
			}

           $this->single = $single;
		   $this->full = $full;
		   
		   $this->fixMAP();
		   $this->getOtherAmino();
		   $this->buildElements();
		   $this->fixElements();
		   $this->buildElementInfos();
		}

        /**
		 * 二硫键计算
		 */
        private function customCysCalculate(){
        	$customCys = $this->customCys;
			
        }
        private function getNoChainResult($standard_data){
        	$single = '';
			$full = '';
			
        	$chain = $this->chains[0];
				
			$hasCyclo = $chain->hasCyclo;
			$cyclo = $chain->cyclo;
			$preCyclo = $chain->preCyclo;
			$afterCyclo = $chain->afterCyclo;
			
			$sIndex = array();

			if(!checkNull($preCyclo)){
				
				$preCycloDetails = $this->getAminoDatas($preCyclo, $standard_data, 0);
				$single = $single . $preCycloDetails['single'];
				$full = $full .$preCycloDetails['full'];
				$sIndex = array_merge( $sIndex, $preCycloDetails['sIndex']);
			}

			$cycloDetails = $this->getAminoDatas($cyclo, $standard_data, $hasCyclo);
			$cycloSingle = $cycloDetails['single'];
			if(strlen($single)>0 && strlen($cycloSingle)>0){
				$single = $single.'-';
				$full = $full.'-';
			}
			
			$single = $single . $cycloDetails['single'];
			$full = $full . $cycloDetails['full'];
			$sIndex = array_merge($sIndex, $cycloDetails['sIndex']);
            
			if(!checkNull($afterCyclo)){

				$afterCycloDetails = $this->getAminoDatas($afterCyclo, $standard_data, 0);
				$afterSingle = $afterCycloDetails['single'];
				if(strlen($single)>0 && strlen($afterSingle)>0){
					$single = $single.'-';
					$full = $full.'-';
				}
				$single = $single . $afterCycloDetails['single'];
				$full = $full .$afterCycloDetails['full'];
				$sIndex = array_merge( $sIndex, $afterCycloDetails['sIndex']);
			}
			
			$ncTermResult = $this->plusNCTerm($single, $full);
			$single = $ncTermResult['single'];
			$full = $ncTermResult['full'];
			
			
			if($this->hasMemo==true){
				$single = $single . '('. $this->memo. ')';
				$full = $full . '('. $this->memo. ')';
			}
			
			//是否包含附件显示信息，如指示2硫键、成环类型等
			$hasAttach = false; 
			
			// 二硫键信息
			$sIndexCount = count($sIndex);
			$bridge = '';
			$this->sIndex = $sIndex;
			
			if($sIndexCount>1 && $this->calculateS){ //一对2硫键以上
				for($index=0; $index<$sIndexCount; $index++){
					$bridge = $bridge . 'Cys'.$sIndex[$index];
					if($index<($sIndexCount-1)){
						if($index>0 && ($index+1)%2==0){
							$bridge = $bridge . ',';
						}else{
							$bridge = $bridge . '&';
						}
					}

				}
			   $hasAttach = true;
			   $bridge = $bridge.' bridge';
			}
			
			$cycloType = '';

			if($this->cycloType>-1 && strpos(strtolower($this->original), 'cyclo')>-1){
				$cycloType = $this->cycloTypes[$this->cycloType];
				if($hasAttach){
					$cycloType = $cycloType . ';';
				}
				if(!is_null($cycloType)){
					$hasAttach = true;
				}
			}
			
			if($hasAttach){
				$single = $single . '('.$cycloType.$bridge.')';
			    $full = $full . '('.$cycloType.$bridge.')';
			}
			
			return array(
			   'single'=>$single,
			   'full'=>$full
			);
        }
        
		private function getChainResult($standard_data){
			$single = '';
			$full = '';
			
        	$chainA = $this->chains['A'];
			$chainB = $this->chains['B'];
			
				
			$chainAResult = $this->getSingleChainResult($standard_data, $chainA);
			$single = 'chainA('.$chainAResult['single'].')';
			$full = 'chainA('.$chainAResult['full'].')';
			
			if(!checkNull($chainB)){
				$chainBResult = $this->getSingleChainResult($standard_data, $chainB);
				$single = $single.'chainB('.$chainBResult['single'].')';
			    $full = $full . 'chainB('.$chainBResult['full'].')';
			}
			
			$ncTermResult = $this->plusNCTerm($single, $full);
			
			return array(
			   'single'=>$ncTermResult['single'],
			   'full'=>$ncTermResult['full']
			);
		}

        private function getSingleChainResult($standard_data, $chain){
        	$single = '';
			$full = '';
				
			$hasCyclo = $chain->hasCyclo;
			$cyclo = $chain->cyclo;
			$preCyclo = $chain->preCyclo;
			$afterCyclo = $chain->afterCyclo;

			if(!is_null($preCyclo)){
				$preCycloDetails = $this->getAminoDatas($preCyclo, $standard_data, 0);
				$single = $single . $preCycloDetails['single'];
				$full = $full .$preCycloDetails['full'];
			}

			$cycloDetails = $this->getAminoDatas($cyclo, $standard_data, $hasCyclo);
			$cycloSingle = $cycloDetails['single'];
			if(strlen($single)>0 && strlen($cycloSingle)>0){
				$single = $single.'-';
				$full = $full.'-';
			}
			$single = $single . $cycloDetails['single'];
			$full = $full . $cycloDetails['full'];
			
			if(!is_null($afterCyclo)){

				$afterCycloDetails = $this->getAminoDatas($afterCyclo, $standard_data, 0);
				$afterSingle = $afterCycloDetails['single'];
				if(strlen($single)>0 && strlen($afterSingle)>0){
					$single = $single.'-';
					$full = $full.'-';
				}
				$single = $single . $afterCycloDetails['single'];
				$full = $full .$afterCycloDetails['full'];
			}

			return array(
			   'single'=>$single,
			   'full'=>$full
			);
        }
        
		/**
		 * 获取氨基酸具体数据
		 */
        private function getAminoDatas($cyclo, $standard_data, $hasCyclo){
    		$single = '';
			$full = '';
			$sIndex = null;
			$aminoDetails = $this->aminoDetails;
			
        	if($cyclo['type']=='amino_detail'){
				$detail = $cyclo['detail'];
				$aminos = $detail->aminos;
				$sIndex = $detail->sIndex;
				
				$_single = $this->standardIndex['single'];
				$_full = $this->standardIndex['full'];
				$_residue = $this->standardIndex['residue'];
				$_hydrophily = $this->standardIndex['hydrophily'];
				$_acid = $this->standardIndex['acid'];
				$_base = $this->standardIndex['base'];
				$_flag = $this->standardIndex['flag'];
				
				$singleFlags = array();
				$index = 0;
				foreach($aminos as $key=>$amino ){
					$standardData = $standard_data[$amino];
					
					if(is_null($standardData)){
						continue;
					}
					$A = $standardData[$_single];
					$B = $standardData[$_full];
					
					$flag = $standardData[$_flag];
					$_B = $B;
					$_A = $A;
					
					// 处理由于在表格中term的特殊表示法，需要去除多余的-，2 Nterm 3 cterm 
					if($flag==2){ 
						$A = substr($A, 0, strlen($A)-1);
						$B = substr($B, 0, strlen($B)-1);
					}else if($flag==3){ //cterm
						$A = substr($A, 1);
						$B = substr($B, 1);
					}

					if(strlen($A)>1){
						if($key>0){
							$A = '-'.$A;
						}
						// 标记位置为多字母
						$singleFlags[$index] = 1;
					}else{
						// 标记位置为单字母
						$singleFlags[$index] = 0;
						if($index>0){
							// 当index大于0，则计算前一位置的是否为多字母，若是多字母，则加分割线
							if($singleFlags[$index-1]==1){
								$A = '-'.$A;
							}
						}
					}
					$index++;
					if(strlen($B)>1){
						if($key>0){
							$B = '-'.$B;
						}
					}
					
					$single = $single . $A;
					$full = $full . $B;
					
					// 具体氨基酸计算
					$amino = $aminoDetails[$_A];
					if(is_null($amino)){
						$aminoDetails[$_A]['count'] = 1;
						$aminoDetails[$_A]['full'] = $_B;
						$aminoDetails[$_A]['residue'] = $standardData[$_residue];
					}else{
						$aminoDetails[$_A]['count'] ++;
					}
				}
				
				$this->aminoDetails = $aminoDetails;

				if($hasCyclo){
					$single = 'cyclo('.$single.')';
					$full = 'cyclo('.$full.')';
				}
			}
			
			return array(
			   'single'=>$single,
			   'full'=>$full,
			   'sIndex'=>$sIndex
			);
        }
        
		// 获取元素具体个数
		private function buildElements(){
			$standard_data = $this->chemicalDatas['standardData'];
			$aminoDetails = $this->aminoDetails;
			$elements = $this->elements;
			
			$elementIndex = $this->elementIndex;
	
			if(count($aminoDetails)==0){
				foreach($elementIndex as $key=>$index){
					$elements[$key] = 0;
				}
			}else{
				$_hydrophily = $this->standardIndex['hydrophily'];
				$_acid = $this->standardIndex['acid'];
				$_base = $this->standardIndex['base'];
				
				foreach($aminoDetails as $key=>$amino){
					$standardData = $standard_data[$key];
					if(!is_null($standardData)){
						foreach($elementIndex as $key=>$index){
							$elements[$key] += $standardData[$index] * $amino['count'];
						}
						
						$this->aminoCount += $amino['count'];
						// 亲水性总值计算
						$this->hydrophilyCount += $standardData[$_hydrophily];
						$this->acidCount += $standardData[$_acid];
						$this->baseCount += $standardData[$_base];
					}
				}
			}
			
			if(isset($elements['H'])){
				$elements['H'] += 2;
			}
			if(isset($elements['O'])){
				$elements['O'] += 1;
			}
			
			$this->elements = $elements;
		}
		
		/**
		 * 根据二硫键信息及成环类型，修正元素个数
		 */
		private function fixElements(){
			$elements = $this->elements;
			$sIndex = $this->sIndex;
			$cycloType = $this->cycloType;
			
			$sCount = count($sIndex);

			if($sCount>1 && $this->calculateS){
				if($elements['H']>=2){
					$this->elements['H'] -= 2*($sCount/2);
				}
			}
			
			if($this->cycloTypeCorect){
				if($cycloType==0 || $cycloType==1 || $cycloType==2 || $cycloType==3){
					if($elements['H']>=2){
						$this->elements['H'] -= 2;
					}
					if($elements['O']>0){
						$this->elements['O'] -= 1;
					}
				}
				if($cycloType==4){
					if($elements['H']>0){
						$this->elements['H'] -= 1;
					}
				}
			}
			
		}
		/**
		 * 根据元素表计算分子相关信息
		 */
        private function buildElementInfos(){
        	$chemicalDatas = $this->chemicalDatas;
			$constDatas = $chemicalDatas['aminoConstData'];

        	$elements = $this->elements;
			// 分子式
			$formula = '';
			$formulaHtml = '';
			// 平均分子量
			$mw = 0;
			// 精确分子量
			$em = 0;
			
			$_mw = $this->constIndex['mw'];
			$_em = $this->constIndex['em'];

			foreach($elements as $key=>$value){
				if($value>0){
					$formula = $formula . $key . $value;
					$formulaHtml = $formulaHtml . $key . '<sub>'.$value.'</sub>';
					$constData = $constDatas[$key];
					if(!is_null($constData)){
						$mw += $constData[$_mw] * $value;
					    $em += $constData[$_em] * $value;
					}
					
				}
			}
			
			$this->molecularFormula = $formula;
			$this->formulaHtml = $formulaHtml;
			$this->mw = sprintf("%.4f",$mw);
			$this->em = sprintf("%.4f",$em);
			
			$this->fixHydrophilyCount();
			$this->hydrophily = round($this->hydrophilyCount / $this->aminoCount, 2);
			$this->hydrophilyResult = $this->getHydrophilyResult($this->hydrophily);
			
			$this->solubilityIndex = $this->getSolubilityIndex();
			$this->solubilityResult = $this->solubilityResults[$this->solubilityIndex];
			
			$piResult = $this->getPIResult();
			if(!is_null($piResult)){
				$_pi = is_numeric($piResult['pi']) ? sprintf('%.2f',$piResult['pi']) : $piResult['pi'];
				$this->pi = ($_pi===0) ? 0 : $_pi;
				$this->y = $piResult['y'];
				$this->pi7 = sprintf('%.2f',$piResult['pi7']);
				$this->minY = $piResult['minY'];
				$this->maxY = $piResult['maxY'];
			}
			
        }
		
		/**
		 * 成环类型影响亲水性，需修正
		 */
		private function fixHydrophilyCount(){
			$cycloType = $this->cycloType;
			$hydrophilyCount = $this->hydrophilyCount;
			
			if($cycloType==0){
				$hydrophilyCount -= 2;
			}else if($cycloType==1){
				$hydrophilyCount -= 4;
			}else if($cycloType==2){
				$hydrophilyCount -= 3;
			}else if($cycloType==3){
				$hydrophilyCount -= 3;
			}
			
			$this->hydrophilyCount = $hydrophilyCount;
		}
		/**
		 * 计算亲水性文字结果
		 */
		private function getHydrophilyResult($hydrophily){
			$hydrophilyResults = $this->hydrophilyResults;
			
			$hydrophilyResult = $hydrophilyResults[3];
			if($hydrophily>1){
				$hydrophilyResult = $hydrophilyResults[0];
			}else if($hydrophily>0 && $hydrophily<=1){
				$hydrophilyResult = $hydrophilyResults[1];
			}else if($hydrophily>-1 && $hydrophily<=0){
				$hydrophilyResult = $hydrophilyResults[2];
			}
			
			return $hydrophilyResult;
		}
		
		/**
		 * 计算溶水性
		 */
		private function getSolubilityIndex(){
			
			$y = $this->aminoCount;
			$acidCount = $this->acidCount;
			$baseCount = $this->baseCount;
			$x = $hydrophily = $this->hydrophily;
			$character1 = $this->original;
			
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
				$details = $this->aminoDetails;
				$specialCount = 0;
				foreach($specialAminos as $amino){
					if(isset($details[$amino])){
						$specialCount += $details[$amino]['count'];
					}
				}
		
				if(($specialCount / $y) > 0.6){
		            
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
			
			$amino_detail_values = array_values($aminoDetails);
			
			if($x>0 && $x<=0.5){
				// 需要计算连续8个氨基酸的亲水性<=0
				$acidAminoCount = 0;
				$firstIndex = 0;
				
				$_acid = $this->standardIndex['acid'];
				for($index=0, $amino_detail_count=count($amino_details); $index<$amino_detail_count; $index++){
					$standard = $standardData[$amino_detail_values[$index]];
		
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
					$_acid = $this->standardIndex['acid'];
					for($index=0, $amino_detail_count=count($amino_details); $index<$amino_detail_count; $index++){
						$standard = $standardData[$amino_detail_values[$index]];
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
		 * 二硫键及成环类型影响PI的计算，需要修正
		 */
		private function fixPrePI(){
			$residue = $this->aminoDetails;
			$cycloType = $this->cycloType;
			
			$ntermData = $this->chemicalDatas['ntermData'];
			$ctermData = $this->chemicalDatas['ctermData'];
			
			$_ncvalue = $this->standardIndex['ncterm'];
			$cterm_value = $ctermData[$this->cterm][$_ncvalue];
			$nterm_value = $ntermData[$this->nterm][$_ncvalue];
			
			$sIndex = $this->sIndex;
			$sCount = count($sIndex);

			$Cys = $residue['C'];
			$Lys = $residue['K'];
			$Asp = $residue['D'];
			
			if($sCount>1 && $this->calculateS){
				if(isset($Cys)){
					if($Cys['count']>2){
						$Cys['count'] -= 2* ($sCount/2);
						$residue['C'] = $Cys;
					}
				}
			}
			
			if($cycloType==0){
				$cterm_value -= 1;
				$nterm_value -= 1;
			}else if($cycloType==1){
				if(isset($Asp)){
					if($Asp['count']>0){
						$Asp['count'] -= 1;
						$residue['D'] = $Asp;
					}
				}
				
				if(isset($Lys)){
					if($Lys['count']>0){
						$Lys['count'] -= 1;
						$residue['K'] = $Lys;
					}
				}
			}else if($cycloType==2){
				if(isset($Asp)){
					if($Asp['count']>0){
						$Asp['count'] -= 1;
						$residue['D'] = $Asp;
					}
				}
				
				$nterm_value -= 1;
			}else if($cycloType==3){
				if(isset($Lys)){
					if($Lys['count']>0){
						$Lys['count'] -= 1;
						$residue['K'] = $Lys;
					}
				}
				
				$cterm_value -= 1;
			}else if($cycloType==4){
				if(isset($Cys)){
					if($Cys['count']>0){
						$Cys['count'] -= 1;
						$residue['C'] = $Cys;
					}
				}
			}
			
			return array(
			   'residue'=>$residue,
			   'nterm_value'=>$nterm_value,
			   'cterm_value'=>$cterm_value
			);
		}
        /**
		 * 计算等电点（PI）及 净电荷图例
		 */
		private function getPIResult(){
			$result = null;
			
			$fixPiResult = $this->fixPrePI();
			$residue  = $fixPiResult['residue'];
			$pkData = $this->chemicalDatas['pkData'];
		
			if(!isset($residue) || !isset($pkData)){
				return $result;
			}
			
			$ys = array();
			$maxY = 0;
			$pi = 0;
			$pi7 = 0; //当ph=7时的净电荷数
			
			//保存y和ph的值
			$piTemp = array(); 
			$cterm_value = $fixPiResult['cterm_value'];
			$nterm_value = $fixPiResult['nterm_value'];
			
			$ctermData = null;
			$ntermData = null;
			
			//负值的个数
			$flag0 =0;
			//正值的个数
			$flag1 =0;
			if($cterm_value == 1){
				$ctermData = $pkData['C-term'];
				$flag0++;
			}
			
			if($nterm_value == 1){
				$ntermData = $pkData['N-term'];
				$flag1++;
			}
			$detail = $residue;
			$count = count($detail);
		    
			$_pk = $this->pkIndex['pk'];
			$_flag = $this->pkIndex['flag'];
			foreach($detail as $k=>$value){
				if(isset($pkData[$k])){
					$tmp = $pkData[$k];
					
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
				
				if(!is_null($ctermData)){
					$y += $this->calculateSinglePi($x, $ctermData[$_pk], 1, $ctermData[$_flag]);
				}
				
				if(!is_null($ntermData)){
					$y += calculateSinglePi($x, $ntermData[$_pk], 1, $ntermData[$_flag]);
				}
				
				if($count==0){
					continue;
				}
				foreach($detail as $k=>$value){
					if(isset($pkData[$k])){
						$tmp = $pkData[$k];
						
						$y += calculateSinglePi($x, $tmp[$_pk], $value['count'], $tmp[$_flag]);
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
		
		private function plusNCTerm($single, $full){
			// cterm和nterm默认需要隐藏的残基
			$default_nterm = C('default_value')['nterm'];
			$default_cterm = C('default_value')['cterm'];
			$nterm = ($this->nterm==$default_nterm) ? '' : $this->nterm;
			$cterm = $this->cterm==$default_cterm ? '' : $this->cterm;
			
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
		 * 在前端显示除H和OH外的其他N/C-Term氨基酸
		 */
		private function getOtherAmino(){
			$defaultValue = C('default_value');
			$ntermData = $this->chemicalDatas['ntermData'];
			$ctermData = $this->chemicalDatas['ctermData'];
			
			$nterm = $this->nterm;
			$cterm = $this->cterm;
			
			$_full = $this->standardIndex['full'];
			$_residue = $this->standardIndex['residue'];
			
			if($defaultValue['nterm']!= $nterm){
				$_tmp = $ntermData[$nterm];
				$this->otherAmino[$nterm] = array(
				  'full'=>$_tmp[$_full],
				  'count'=>1,
				  'residue'=>$_tmp[$_residue]
				);
			}
			
			if($defaultValue['cterm']!=$cterm){
				$_tmp = $ctermData[$cterm];
				$this->otherAmino[$cterm] = array(
				  'full'=>$_tmp[$_full],
				  'count'=>1,
				  'residue'=>$_tmp[$_residue]
				);
			}
		}
		
		/**
		 * 修订MAP相关数字，当序列中存在MAP时，则其他氨基酸数量要增加
		 */
		private function fixMAP(){
			$aminoDetails = $this->aminoDetails;
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
					if(strpos($key, 'MAP')==false){
						$this->aminoDetails[$key]['count'] = $this->aminoDetails[$key]['count'] * $number;
					}
				}

				$this->cterm = $this->cterm * $number;
				$this->nterm = $this->nterm * $number;
			}
		}
	}
