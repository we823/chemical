<?php
	
	namespace Common\Model;
	
	class AminoSpecialModel{
		private $original;
		private $hasChain = false;
		private $chains = array();
		
		public function __set($name, $value){
			$this->$name = $value;
		}
		
		public function __get($name){
			return $this->$name;
		}
		
		public function pushChains($name, $value){
			$this->chains[$name] = $value;
		}
	}
