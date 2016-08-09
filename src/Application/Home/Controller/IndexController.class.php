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
		
	}
	
	function about(){
		$this->display();
	}
	
	function test(){
		$subject = 'chainA(cyclo(KCDEFGL))chainB(cyclo(AEDCFGHI))';
		
		
		$aminoUtil = new \Com\Zhang\AminoUtil($subject);
		$aminoSpecial = $aminoUtil->getAminoSpecial();
		
		var_dump($aminoSpecial);
		echo 'test';
	}
	
	
}