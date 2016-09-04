<?php
	
namespace Home\Model;
/**
 * 校验氨基酸序列的对象
 */	
class AminoSubjectModel{
	private $mAnalyzeStatus = array(
	   -1=>'序列暂未分析',
	   0=>'获取序列备注信息',
	   1=>'获取序列chain信息',
	   2=>'分析序列字符串',
	   3=>'获取氨基酸基团信息',
	   4=>'MAP修订',
	   5=>'计算分子量相关',
	   6=>'成环类型计算',
	   7=>'二硫键计算',
	   8=>'各项属性计算',
	   200=>'分析结果成功'
	);
	private $mStatus = -1;
	
	/**
	 * 用户选择的环类型
	 */
	private $mCycloType = -1;

	// 单字母序列
	private $mSingle;
	// 完整序列
	private $mFull;

	// 平均分子量
	private $mMw=0;
	// 精确分子量
	private $mEm=0;
	// 氨基酸总个数
	private $mAminoCount=0;
	// 计算pi相关的氨基酸总个数
	private $mPiAminoCount = 0;
	// 酸基个数
	private $mAcidCount = 0;
	// 碱基个数
	private $mBaseCount = 0;
	/**
	 * 氨基酸具体详情，标记具体个数
	 */ 
	private $mAminoDetails=array();
	/**
	 * 和分子式相关的具体集合
	 */
	private $mElementAminos = array();
	/**
	 * 具体的元素个数，根据$aminoDetails计算
	 */ 
	private $mElements=array();
	/**
	 * 和PI相关的氨基酸集合
	 */
	private $mPiAminos = array();
	/**
	 * 记录氨基酸所在位置，用于二硫键校验
	 */
	private $mAminoLocation = array();
	/**
	 * 记录有环的片段
	 */
	private $mCycloFragments = array(
	   '0'=>array(),
	   'A'=>array(),
	   'B'=>array()
	);
	/**
	 * 记录特殊标记
	 */
	private $mSpecialFlags = array();
	/**
	 * 等电点在PH1-14的值
	 */
	private $mY;
	/**
	 * 等电点
	 */ 
	private $mPi;
	/**
	 * ph=7时的等电点
	 */ 
	private $mPi7;
	/**
	 * y轴最大值
	 */ 
	private $mMaxY;
	/**
	 * y轴最小值
	 */ 
	private $mMinY;
	/**
	 * 分子式
	 */
	private $mFormula;
	/**
	 * 分子式html格式
	 */ 
	private $mFormulaHtml;
	/**
	 * NCTerm需要在前端显示
	 */ 
	private $mOtherAmino;
	/**
	 * 和PI相关的c/nterm集合
	 */
	private $mPiOtherAminos=array();
	/**
	 * 亲水性总值
	 */ 
	private $mHydrophilyCount = 0;
	/**
	 * 平均亲水性值
	 */ 
	private $mHydrophily;
	/**
	 * 平均亲水性文字说明
	 */ 
	private $mHydrophilyResult;
	
	/**
	 * 溶解性序号
	 */ 
	private $mSolubilityIndex;
	/**
	 * 溶解性文字说明
	 */ 
	private $mSolubilityResult;
	
	private $mHasError = false;
	private $mMessage = '';
	/**
	 * 原始序列
	 */
	private $mOriginal;
	/**
	 * 有效序列，去除备注信息
	 */
	private $mSubject;
	/**
	 * 中间有效序列，去除nterm和cterm
	 */
	private $mMiddleSubject;
	private $mCterm;
	private $mNterm;
	
	
	/**
	 * 链的基本信息
	 */
	private $mChains = array();
	
	private $mSubjects = array();
	private $mMiddleSubjects = array();
	private $mCterms = array(
	   '0'=>array(
		      'count'=>0,
		      'detail'=>array()
		   ),
		   'A'=>array(
		      'count'=>0,
		      'detail'=>array()
		   ),
		   'B'=>array(
		      'count'=>0,
		      'detail'=>array()
		   )
	);
	private $mNterms = array(
	   '0'=>array(
		      'count'=>0,
		      'detail'=>array()
		   ),
		   'A'=>array(
		      'count'=>0,
		      'detail'=>array()
		   ),
		   'B'=>array(
		      'count'=>0,
		      'detail'=>array()
		   )
	);
	/**
	 * pi相关nterm值
	 */
	private $mNtermValue = 0;
	/**
	 * pi相关的cterm值
	 */
	private $mCtermValue = 0;
	private $mNtermCount = 0;
	private $mCtermCount = 0;
	/**
	 * 完整序列后需要显示的附加信息
	 */
	private $mAttachs = array();

	/**
	 * 自定义二硫键位置标识
	 */
	private $mCustomCys;
	/**
	 * 需要输出的二硫键位置
	 */
	private $mCysRealIndex = array();
	/**
	 *  当环类型选择正确时标记
	 */
	private $cycloTypeCorrect=false;
	/**
	 * 备注，在序列最后出现的小括号，并且前面不是cyclo、chain等标记性字符
	 */
	private $mMemo;
	private $mHasMemo = false;
	/**
	 * 氨基酸片段，以特殊标记为分割点
	 */
	private $mFragments = array();
	
	public function __set($name, $value){
		$this->$name = $value;
	}
	
	public function __get($name){
		return $this->$name;
	}
	
	/**
	 * 获取序列相关信息
	 */
	public function getResult(){
		$status = $this->mStatus;
		$has_error = $this->mHasError;
		if(!$has_error){
			$status = 200;
			$this->mStatus = $status;
		}
		if($status==200){
			$this->mHasError = false;
			$this->mMessage = $this->mAnalyzeStatus[$status];
		}else{
			$this->mHasError = true;
			$message = $this->mMessage ;
			$message = ' [步骤('.$this->mAnalyzeStatus[$status] .')] ' .$message ;
			$this->mMessage = $message;
		}
		
		return array(
		   'attachs'=>$this->mAttachs,
		   'single'=>$this->mSingle,
		   'full'=>$this->mFull,
		   'aminoCount'=>$this->mAminoCount,
		   'acidCount'=>$this->mAcidCount,
		   'baseCount'=>$this->mBaseCount,
		   'aminoDetails'=>$this->mAminoDetails,
		   'elementAminos'=>$this->mElementAminos,
		   'elements'=>$this->mElements,
		   'piAminos'=>$this->mPiAminos,
		   'piAminoCount'=>$this->mPiAminoCount,
		   'specialFlags'=>$this->mSpecialFlags,
		   'aminoLocation'=>$this->mAminoLocation,
		   'cycloFragments'=>$this->mCycloFragments,
		   'mw'=>$this->mMw,
		   'em'=>$this->mEm,
		   'y'=>$this->mY,
		   'pi'=>$this->mPi,
		   'pi7'=>$this->mPi7,
		   'maxY'=>$this->mMaxY,
		   'minY'=>$this->mMinY,
		   'formula'=>$this->mFormula,
		   'formulaHtml'=>$this->mFormulaHtml,
		   'otherAmino'=>$this->mOtherAmino,
		   'piOtherAmino'=>$this->mPiOtherAminos,
		   'nterms'=>$this->mNterms,
		   'cterms'=>$this->mCterms,
		   'ntermCount'=>$this->mNtermCount,
		   'ctermCount'=>$this->mCtermCount,
		   'ntermValue'=>$this->mNtermValue,
		   'ctermValue'=>$this->mCtermValue,
		   'hydrophily'=>$this->mHydrophily,
		   'hydrophilyResult'=>$this->mHydrophilyResult,
		   'solubilityResult'=>$this->mSolubilityResult,
		   'solubilityIndex'=>$this->mSolubilityIndex,
		   'hasError'=>$this->mHasError,
		   'message'=>$this->mMessage,
		   'subjects'=>$this->mSubjects,
		   'chains'=>$this->mChains
		);
	}
    
	public function __toString(){
		return 'hasError: '.$this->mHasError.' message: '.$this->mMessage.' single: '.$this->mSingle.' full:'.$this->mFull;
	}
}
