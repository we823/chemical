<?php
namespace Common\Model;
/**
 * 氨基酸片段
 */
class AminoFragementModel{
	/**
	 * 上一级片段序号
	 */
	private $mParentIndex;
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
	
	public function toArray(){
		$detail = $this->mDetail;
		if(is_null($detail)){
			$fragments = $this->mFragments;
			if(count($fragments)>0){
				$detail = array();
				foreach($fragments as $fragment){
					if(!is_null($fragment->mDetail)){
						$detail = array_merge($detail, $fragment->mDetail);
					}else{
						$has_flag = $fragment->mHasFlag;
						if($has_flag){
							$flag_data = $fragment->mFlagData;
							if($flag_data['flag']==1){
								array_push($detail, $flag_data['single']);
							}
						}
					}
				}
			}
		}
		return array(
		   'index'=>$this->mIndex,
		   'has_flag'=>$this->mHasFlag,
		   'flag_name'=>$this->mFlagName,
		   'flag_data'=>$this->mFlagData,
		   'chain'=>$this->mChain,
		   'detail'=>$detail
		);
	}
}
