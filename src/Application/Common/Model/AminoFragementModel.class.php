<?php
namespace Common\Model;
/**
 * 氨基酸片段
 */
class AminoFragementModel{
	/**
	 * 片段序号
	 */
	private $mIndex;
	/**
	 * 是否包含特殊包括标记
	 */
	private $mHasFlag=false;
	/**
	 * 标记名称
	 */
	private $mFlagName='noFlag';
	/**
	 * 标记信息
	 */
	private $mFlagData;
	/**
	 * 序列具体信息
	 */
	private $mDetail;
	/**
	 * 所在的链上，默认为0，主链
	 */
	private $mChain='0';
	/**
	 * 子片段内容
	 */
	private $mFragments=null;
	
	public function __set($name, $value){
		$this->$name = $value;
	}
	
	public function __get($name){
		return $this->$name;
	}
}
