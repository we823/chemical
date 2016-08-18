<?php
	
	namespace Common\Model;
	
	class AminoSpecialModel{
		private $original;
		private $hasChain = false;
		private $chains = array();
		
		// 单字母序列
		private $single;
		// 完整序列
		private $full;
		
		private $cycloType = -1;
		private $cycloTypes = array(
		   0=>'main chain cyclo',
		   1=>'side chain cyclo',
		   2=>'main chain(N-term)&side chain cyclo',
		   3=>'main chain&side chain cyclo',
		   4=>'main chain(C-term)&side chain cyclo',
		   5=>''
		);
		
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
		
		public function toArray(){
			return array(
			  'original'=>$this->original,
			  'hasChain'=>$this->hasChain,
			  'chains'=>$this->chains,
			  'hasMemo'=>$this->hasMemo,
			  'memo'=>$this->memo
			);
		}
		
		public function full(){
			$full = '';
			if($this->hasChain==false){
				$full = $full . $this->chains[0];
			}else{
				$full = $this->getChain($full, 'A');
				$full = $this->getChain($full, 'B');
			}
			
			if($this->hasMemo){
				$full = $full .'('.$this->memo.')';
			}
			
			return $full;
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
		
		public function buildAminoInfo($standard_data){
			
			$single = '';
			$full = '';
			
			if($this->hasChain==false){
				$chain = $this->chains[0];
				
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
				
				
				if($this->hasMemo==true){
					$single = $single . '('. $this->memo. ')';
					$full = $full . '('. $this->memo. ')';
				}
				
				// 二硫键信息
				$sIndexCount = count($sIndex);
				
				$hasAttach = false; //是否包含附件显示信息，如指示2硫键、成环类型等
				$bridge = '';
				if($sIndexCount>1){ //一对2硫键以上
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

				if($this->cycloType>-1){
					$cycloType = $this->cycloTypes[$this->cycloType];
					if($hasAttach){
						$cycloType = $cycloType . ';';
					}
					$hasAttach = true;
				}
				if($hasAttach){
					$single = $single . '('.$cycloType.$bridge.')';
				    $full = $full . '('.$cycloType.$bridge.')';
				}
				
			}

           $this->single = $single;
		   $this->full = $full;
		}


        private function getAminoDatas($cyclo, $standard_data, $hasCyclo){
        		$single = '';
				$full = '';
        	if($cyclo['type']=='amino_detail'){
				$detail = $cyclo['detail'];
				$aminos = $detail->aminos;
				$sIndex = $detail->sIndex;

				foreach($aminos as $key=>$amino ){
					$standardData = $standard_data[$amino];
					
					$A = $standardData['A'];
					$B = $standardData['B'];
					
					if(strlen($A)>1){
						if($key>0){
							$A = '-'.$A;
						}
					}
					
					if(strlen($B)>1){
						if($key>0){
							$B = '-'.$B;
						}
					}
					
					$single = $single . $A;
					$full = $full . $B;
					
				}
				
				if($hasCyclo){
					$single = 'cyclo('.$single.')';
					$full = 'cyclo('.$full.')';
				}
			}
			
			return array(
			   'single'=>$single,
			   'full'=>$full
			);
        }
	}
