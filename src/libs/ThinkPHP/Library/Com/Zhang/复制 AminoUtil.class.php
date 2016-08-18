<?php
	
	namespace Com\Zhang;
	
	class AminoUtil{
		
		private $subject;
		private $aminoSpecial = null;
		public function __construct($subject){
			$this->subject = $subject;
			$this->aminoSpecial = array();
		}
		
		public function getAminoSpecial(){
			$subject = $this->subject;
			
			$this->aminoSpecial['original'] = $subject;
			
			// 计算备注相关信息
			$memoResult = $this->checkMemo($subject);
			$hasMemo = $memoResult['hasMemo'];
			$memo = $memoResult['memo'];
			
			if($hasMemo){
				$this->aminoSpecial['memo'] = $memo;
				$this->aminoSpecial['hasMemo'] = true;
				
				$subject = substr($subject, 0, strlen($subject) - strlen($memo) - 2);
				
				echo '<br>new subject:'.$subject;
				echo '<br>';
			}
			
			if(strpos($subject, 'chain')>-1){
				$this->aminoSpecial['hasChain'] = true;
				
				$this->getChain($subject);

			}else{
				$this->aminoSpecial['hasChain'] = false;
				$this->aminoSpecial['chains'][0] = $subject;
			}
			
			return $this->aminoSpecial;
		}
		
		/**
		 * 获取侧链信息
		 */
		private function getChain($subject){
			
			if(strpos($subject, 'chainA')>-1){
				
				$chainAResult = $this->stack($subject);
				$aminoChain = $this->analyChain($chainAResult);
				
				$this->aminoSpecial['chains']['A'] = $aminoChain;

				$endIndex = $chainAResult['endIndex'];		
				$subject = substr($subject, $endIndex+1);

			}
			
			if(strpos($subject, 'chainB')>-1){
				$chainBResult = $this->stack($subject);
				$aminoChainB = $this->analyChain($chainBResult);
				$this->aminoSpecial['chains']['B'] = $aminoChainB;
				
			}

		}
		
		/**
		 * 分析侧链具体信息
		 */
		private function analyChain($chainResult){
			$amino = $chainResult['amino'];
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
					$preCyclo = substr($amino, 0, $length - $startIndex);
				}
				
				if(($endIndex + 1)<$length){
					$afterCyclo = substr($amino, $endIndex+1, $length-$endIndex-1);
				}
				
				$aminoChain->preCyclo = $preCyclo;
				$aminoChain->cyclo = $cycloResult['amino'];
				$aminoChain->afterCyclo = $afterCyclo;
				$aminoChain->startIndex = $startIndex;
				$aminoChain->endIndex = $endIndex;
				
			}else{
				$aminoChain->cyclo = $amino;
			}
			
			return $aminoChain;
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
		 * 检测序列中是否包含备注信息
		 * 条件：在序列尾部的（）中，同时要检测其中的字符串是否为有效序列，若不是则为备注
		 */
		private function checkMemo($subject){
			$stackResult = $this->reserve_stack($subject);
			if($stackResult==false){
				return array(
				  'hasMemo'=>false,
				  'memo'=>'error'
				);
			}
			
			$startIndex = $stackResult['startIndex'];
			if($startIndex<=6){
				return $stackResult;
			}
			
			$preSubject = substr($subject, $startIndex-6);
	
			if(strpos($preSubject, 'chain')>-1 || strpos($preSubject, 'cyclo')>-1){
				return array(
				   'hasMemo'=>false,
				   'memo'=>''
				);
			}else{
				$stackResult['memo']=$stackResult['amino'];
				$stackResult['hasMemo'] = true;
				return $stackResult;
			}
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
					// 若包含2个（）则不满足备注条件
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
