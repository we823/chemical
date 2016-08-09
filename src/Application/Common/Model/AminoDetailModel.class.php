<?php
	namespace Common\Model;
	use Think\Model;
	
	class AminoDetailModel extends Model {
		private $original;
		private $aminos = array();
		private $sIndex = array();
		
		/**
		 * 放入氨基酸字母
		 * @param $amino mixed 氨基酸单字母
		 */
		public function pushAmino($amino){
			array_push($amino, $this->aminos);
		}
		
		/**
		 * 获取氨基酸数组
		 */
		public function getAminos(){
			return $this->aminos;
		}
		
		/**
		 * 放入二硫键位置值
		 * @param $index mixed 二硫键位置值
		 */
		public function pushSIndex($index){
			array_push($index, $this->sIndex);
		}
		
		/**
		 * 获取二硫键数组
		 */
		public function getSIndex(){
			return $this->sIndex;
		}
	}
