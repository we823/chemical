<?php
	
	namespace Com\Zhang;
	
	class AminoUtil{
		
		private $subject;
		private $aminoSpecial = null;
		public function __construct($subject){
			$this->subject = $subject;
			$this->aminoSpecial = new \Common\Model\AminoSpecialModel;
		}
		
		public function getAminoSpecial(){
			$subject = $this->subject;
			
			$this->aminoSpecial->original = $subject;
			
			if(strpos($subject, 'chain')>-1){
				$this->aminoSpecial->hasChain = true;
				
				$this->getChain($subject);

			}
			
			return $this->aminoSpecial;
		}
		
		/**
		 * 获取侧链信息
		 */
		private function getChain($subject){
			
			if(strpos($subject, 'chainA')>-1){
				
				$chainAResult = $this->stack($subject);
				$this->aminoSpecial->pushChains('A',$chainAResult);
				$endIndex = $chainAResult['endIndex'];		
				$subject = substr($subject, $endIndex+1);

			}
			
			if(strpos($subject, 'chainB')>-1){
				$chainBResult = $this->stack($subject);
				$this->aminoSpecial->pushChains('B',$chainBResult);
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
	
	}
