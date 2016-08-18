<?php
	
	namespace Common\Model;
	
	class BaseModel{
		public function __set($name, $value){
			$this->$name = $value;
		}
		
		public function __get($name){
			return $this->$name;
		}
	}
