<?php
namespace Common\Model;
/**
 * 氨基酸序列
 */
class AminoResidueModel{
	/**
	 * nterm
	 */
	private $nterm;
	/**
	 * cterm
	 */
	private $cterm;
	/**
	 * 氨基酸片段
	 */
	private $fragements;
	
	public function __set($name, $value){
		$this->$name = $value;
	}
	
	public function __get($name){
		return $this->$name;
	}
}
