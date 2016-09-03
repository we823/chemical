<?php
namespace Home\Controller;
use Think\Controller;


class IndexController extends Controller {
	
	private $chemicalData = null;
	
    public function index(){
		if(is_null($this->chemicalData)) $this->chemicalData = init_data();

		$this->assign('cterms', array_values($this->chemicalData['ctermData']));
		$this->assign('nterms', array_values($this->chemicalData['ntermData']));
		
		$amino = I('amino');
		$cterm = I('CTerm');
		$nterm = I('NTerm');
		
		// $cal=1 获取参数值，并赋值，便于接收外部地址链接
		$cal = I('cal', 0);
		if($cal==1){
			$this->assign('amino', $amino);
			$this->assign('cterm', $cterm);
			$this->assign('nterm', $nterm);
		}
		
		$this->assign('cal', $cal);
		
		$this->display('index');
	
    }
	
	public function index_advance(){

		$cal = I('cal', 0);
		if($cal==1){
			$amino = I('amino');
			$this->assign('amino', $amino);
		}
		
		$this->assign('cal', $cal);
		$this->display('index_advance');
	}
	
	public function result(){
		
		header('Access-Control-Allow-Origin:'.$_SERVER['HTTP_ORIGIN']);
		header('Access-Control-Allow-Credentials:true');
		$amino = I('amino');
		$cterm = I('CTerm');
		$nterm = I('NTerm');
		
		$needCheckData = array(
		   'amino'=>$amino,
		   'cterm'=>$cterm,
		   'nterm'=>$nterm
		);
	
		if(is_null($this->chemicalData)) $this->chemicalData = init_data();
		
		$result = calculateResult($this->chemicalData, $needCheckData);

		$this->ajaxReturn($result);
	}
	
	public function result_advance2(){
		header('Access-Control-Allow-Origin:'.$_SERVER['HTTP_ORIGIN']);
		header('Access-Control-Allow-Credentials:true');
		
		$amino = I('amino');
		$subject = $amino;
        
		// 二硫键信息
		$s2 = I('s2');
		$calculateS = $this->needCalculateS2($s2);

        $dataFilename = './data/data_full.xls';
		$cycloType = I('circle-type', -1);
		
		$standardIndex = C('standard_index');
		$pkIndex = C('pk_index');
		$sideSpecialIndex = C('side_special_index');
		
        $aminoUtil = new \Com\Zhang\AminoUtil($subject, $standardIndex, $cycloType);
		$aminoUtil->pkIndex = $pkIndex;
		$aminoUtil->sideSpecialIndex = $sideSpecialIndex;
		$aminoSpecial = $aminoUtil->instance();
		
		$aminoSpecial->elementIndex = C('element_index');
		$aminoSpecial->standardIndex = $standardIndex;
		$aminoSpecial->pkIndex = $pkIndex;
		$aminoSpecial->constIndex = C('const_index');
		$aminoSpecial->cycloTypes = C('cyclo_types');
		$aminoSpecial->solubilityResults = C('solubility_result');
		$aminoSpecial->hydrophilyResults = C('hydrophily_result');
		$aminoSpecial->calculateS = $calculateS;
		$aminoSpecial->customCys = $s2;
		
		$aminoUtil->initData($dataFilename);
		$aminoUtil->analyze();
		
		$result = $aminoSpecial->getResult();
		$this->ajaxReturn($result);
	}
	
	function about(){
		$this->display();
	}
	
	function test(){
		$subject = 'CKKKC';
		//$subject = 'DAEFRHDSGYEVHHQKLVFFAEDVGSNKGAIIGLMVGGVVIA(HCl salt)';
		//$subject = 'chainA(cyclo(KCDEFGL))chainB(cyclo(AEDCFGHI))';
		echo $subject.'<br>';
		
		$dataFilename = './data/data_full.xls';
		
		$aminoUtil = new \Com\Zhang\AminoUtil($subject, 3);
		$aminoUtil->initData($dataFilename);
		$aminoSpecial = $aminoUtil->getAminoSpecial();
		
		$elementIndex = C('element_index');
		$aminoSpecial->elementIndex = $elementIndex;
		
		//echo '<br>aminoSpecial:-------------------------------------------><br>';
		//print_r($aminoSpecial);
		//$array = $aminoSpecial->toArray();
		//echo json_encode($array, JSON_PRETTY_PRINT);
		//var_dump($aminoSpecial->toArray());
		$result = $aminoSpecial->getResult();
		var_dump($result);
	}
	
	public function result_advance3(){
		header('Access-Control-Allow-Origin:'.$_SERVER['HTTP_ORIGIN']);
		header('Access-Control-Allow-Credentials:true');
		
		$amino = I('amino');
		$subject = $amino;
		
		$s2 = I('s2');
		$cyclo_type = I('circle-type', -1);
		
		$dataFilename = './data/data_full.xls';
		$aminoSubject = new \Home\Model\AminoSubjectModel;
		
		$aminoSubject->mCustomCys = $s2;
		$aminoSubject->mCycloType = $cyclo_type;
		$this->initBaseData($aminoSubject, $dataFilename);
		$aminoSubject->analyze($subject);
		$aminoSubject->buildAminoInfo();
		$result = $aminoSubject->getResult();
		if(is_null($result)){
			$result = array(
			  'hasError'=>true,
			  'message'=>'系统发生异常，无法计算'
			);
		}
		$this->ajaxReturn($result);
	}

    public function result_advance(){
		header('Access-Control-Allow-Origin:'.$_SERVER['HTTP_ORIGIN']);
		header('Access-Control-Allow-Credentials:true');
		
		$amino = I('amino');
		$subject = $amino;
		
		$s2 = I('s2');
		$cyclo_type = I('cyclo-type', -1);
		$cyclo_type_a = I('cyclo-type-A', -1);
		$cyclo_type_b = I('cyclo-type-B', -1);
		
		$aminoSubjectLogic = D('AminoSubject', 'Logic');
		$aminoSubjectLogic->init($amino);
		$aminoSubjectLogic->mCycloType = $cyclo_type;
		$aminoSubjectLogic->mCycloTypeA = $cyclo_type_a;
		$aminoSubjectLogic->mCycloTypeB = $cyclo_type_b;
		$aminoSubjectLogic->mCustomCys = $s2;
		$aminoSubjectLogic->analyze();

		$result = $aminoSubjectLogic->getResult();

		if(is_null($result)){
			$result = array(
			  'hasError'=>true,
			  'message'=>'系统发生异常，无法计算'
			);
		}
		$this->ajaxReturn($result);
	}
	
	/**
	 * 是否需要计算二硫键信息
	 */
	private function needCalculateS2($s2){
		if(checkNull($s2)){
			return false;
		}
		$pattern = '/([1-9]*[0-9]+\-[1-9]*[0-9])/';
		$result = preg_match_all($pattern, $s2);
		$sList = split($s2, ',');
		if($result<count($sList)){
			return false;
		}
		return true;
	}
	
	/**
	 * 初始化相关的基础数据
	 */
	private function initBaseData(&$rAminoSubject, $excelFilename){
		$base_index = C('base_index');
		$result_type = C('result_type');
		$default_value = C('default_value');
		
		$rAminoSubject->mBaseIndex = $base_index;
		$rAminoSubject->mResultType = $result_type;
		$rAminoSubject->mDefaultValue = $default_value;
		
		$rAminoSubject->loadBaseData($excelFilename);
	}
}