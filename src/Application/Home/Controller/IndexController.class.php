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
		
		$this->assign('cal', 0);
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
	
	public function result_advance(){
		header('Access-Control-Allow-Origin:'.$_SERVER['HTTP_ORIGIN']);
		header('Access-Control-Allow-Credentials:true');
		
		$amino = I('amino');
		$subject = $amino;

        $dataFilename = './data/data_full.xls';
		$cycloType = I('circle-type', -1);
		
		$elementIndex = C('element_index');
		$standardIndex = C('standard_index');
		$pkIndex = C('pk_index');
		$cycloTypes = C('cyclo_types');
		$solubilityResults = C('solubility_result');
		$hydrophilyResults = C('hydrophily_result');
		
        $aminoUtil = new \Com\Zhang\AminoUtil($subject, $elementIndex, $cycloType);
		$aminoSpecial = $aminoUtil->instance();
		
		$aminoSpecial->standardIndex = $standardIndex;
		$aminoSpecial->pkIndex = $pkIndex;
		$aminoSpecial->constIndex = C('const_index');
		$aminoSpecial->cycloTypes = $cycloTypes;
		$aminoSpecial->solubilityResults = $solubilityResults;
		$aminoSpecial->hydrophilyResults = $hydrophilyResults;
		
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
	
	
}