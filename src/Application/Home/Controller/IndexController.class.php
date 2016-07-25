<?php
namespace Home\Controller;
use Think\Controller;


class IndexController extends Controller {
	
	private $chemicalData = null;
	
    public function index(){
		if(is_null($this->chemicalData)) $this->chemicalData = initData();
		$this->assign('cterms', $this->chemicalData['ctermData']);
		$this->assign('nterms', $this->chemicalData['ntermData']);
		
		$amino = request('amino');
		$cterm = request('CTerm');
		$nterm = request('NTerm');
		
		$cal = request('cal', 0);
		if($cal==1){
			$this->assign('amino', $amino);
			$this->assign('cterm', $cterm);
			$this->assign('nterm', $nterm);
		}
		
		$this->assign('cal', $cal);
		
		$this->display('index');
	
    }
	
	public function result(){
		
		header('Access-Control-Allow-Origin:'.$_SERVER['HTTP_ORIGIN']);
		header('Access-Control-Allow-Credentials:true');
		$amino = request('amino');
		$cterm = request('CTerm', 0);
		$nterm = request('NTerm', 0);
		
		$needCheckData = array(
		   'amino'=>$amino,
		   'cterm'=>$cterm,
		   'nterm'=>$nterm
		);
	
		if(is_null($this->chemicalData)) $this->chemicalData = initData();
		
		$result = calculateResult($this->chemicalData, $needCheckData);

		$this->ajaxReturn($result);
	}
	
	
}