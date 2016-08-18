<?php
	
	namespace Common\Model;

	class AminoChainModel{
		private $original;
		private $hasCyclo=false;
		private $preCyclo;
		private $cyclo;
		private $afterCyclo;
		private $startIndex = -1;
		private $endIndex = -1;
		
		public function __set($name, $value){
			$this->$name = $value;
		}
		
		public function __get($name){
			return $this->$name;
		}
		
		public function __toString(){
			return array(
			  'original'=>$this->original,
			  'hasCyclo'=>$this->hasCyclo,
			  'preCyclo'=>$this->preCyclo,
			  'cyclo'=>$this->cyclo,
			  'afterCyclo'=>$this->afterCyclo,
			  'startIndex'=>$this->startIndex,
			  'endIndex'=>$this->endIndex
			);
		}
	}
