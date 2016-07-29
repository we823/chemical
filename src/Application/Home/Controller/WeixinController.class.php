<?php
namespace Home\Controller;
use Think\Controller;

define('TOKEN', 'chemicalduotaian');
define('APP_ID', 'wxa21efa542ac0a1ea');
define('APP_SECRET', '71b88000b0b1a191d8a4150b96a171f0');
define('WEB_HOST','http://www.we823.com/chemical');

class WeixinController extends Controller{
	private $appid;
	private $appsecret;
	
	private $chemicalData = null;
	
	public function index(){
		$this->appid = APP_ID;
		$this->appsecret = APP_SECRET;

		
		if(isset($_GET['echostr'])){
			$echostr = $_GET['echostr'];
			if($this->checkSignature()){
				echo $echostr;
				exit;
			}
		}else{
			$this->responseMsg();
		}
	}
	
	public function responseMsg()
    {
		//get post data, May be due to the different environments
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        
      	//extract post data
		if (!empty($postStr)){
			
            /* libxml_disable_entity_loader is to prevent XML eXternal Entity Injection,
               the best way is to check the validity of xml by yourself */
            libxml_disable_entity_loader(true);
          	$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
          
            $msgType = $postObj->MsgType;
            switch ($msgType)
            {
                case "text":
                    $resultStr = $this->receiveText($postObj);
                    break;
                case "event":
                    $resultStr = $this->receiveEvent($postObj);
                    break;
                default:
                    $resultStr = "";
                    break;
            }
            echo $resultStr;

        }else {
        	header('content-type: text/html;charset=utf-8');
        	echo '欢迎';
            
        	exit;
        }
    }

    private function getAccessToken(){
    	$appid = $this->appid;
		$appsecret = $this->appsecret;
    	$url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appid.'&secret='.$appsecret;
		$content = curl_get_file_contents($url);
        
		//输出数组
		$data = json_decode($content, true);
		if($data['access_token']){
			return $data['access_token'];
		}
		return null;
    }
	
	private function createMenu($acessToken){
		$url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$access_token; 
		$attr = array(
		   'button'=>array(
		       array(
			      'name'=>urlencode('关于我们'),
				  'type'=>'click',
				  'key'=>'aboutus'
			   ),
		       array(
			      'name'=>'多肽计算器',
			      'subbutton'=>array(
				     array(
					    'name'=>urlencode('提交序列'),
					    'type'=>'click',
					    'key'=>'submit_amino'
					 ),
				     array(
					    'name'=>urlencode('打开网页'),
					    'type'=>'view',
					    'url'=>WEB_HOST.'/index.php'
					 )
				  )
			   )
		   )
		);
		
		$jsondata = urlencode(json_encode($attr));
		curl_post($url, $jsondata);
	}

    private function getMenu($accessToken){
    	$url = 'https://api.weixin.qq.com/cgi-bin/menu/get?access_token='.$accessToken;
		$content = curl_get_file_contents($url);
		return $content;
    }
	
	private function receiveText($object)
    {
        $funcFlag = 0;
        $contentStr = $object->Content;
		if('?'==$contentStr || '？'==$contentStr){
			$contentStr = "请输入氨基酸序列： \nNTerm-氨基酸序列-CTerm\n"
			              .'如H-AC-OH'."\n"
			              .'若只输入序列，则默认NTerm=H-，CTerm=-OH'."\n"
						  .'若在序列后加上逗号(,)，则输入全部转化为大写字母'."\n"
						  .'<a href="'.WEB_HOST.'/index.php/home/index/about">软件说明</a>';
		}else{
			$contentStr = $this->aminoCalculate($contentStr);
		}
        
        $resultStr = $this->transmitText($object, $contentStr, $funcFlag);
        return $resultStr;
    }
	
	private function receiveEvent($object){
		$contentStr = "";
        switch ($object->Event)
        {
            case "subscribe":
                $contentStr = "欢迎关注多氨肽订阅号\n输入'?'查看说明";
            case "unsubscribe":
                break;
            case "CLICK":
                switch ($object->EventKey)
                {
                    case "aboutus":
                        $contentStr[] = array("Title" =>"公司简介", 
                        "Description" =>"氨基酸及多肽相关的产品及服务", 
                        "PicUrl" =>"#", 
                        "Url" =>"#");
                        break;
					case 'submit_amino':
						$contentStr[] = array(
						   "Title"=>"提交序列",
						   "Description"=>"校验氨基酸信息",
						   "PicUrl"=>"#",
						   "Url"=>"#"
						);
						break;
                    default:
                        $contentStr[] = array("Title" =>"默认菜单回复", 
                        "Description" =>"#", 
                        "PicUrl" =>"#", 
                        "Url" =>"#");
                        break;
                }
				break;
            default:
				echo 'no event';
                break;      

        }
        if (is_array($contentStr)){
            $resultStr = $this->transmitNews($object, $contentStr);
        }else{
            $resultStr = $this->transmitText($object, $contentStr);
        }
        return $resultStr;
	} 

	private function transmitText($object, $content, $funcFlag=0){ 
        $textTpl = "<xml>
					<ToUserName><![CDATA[%s]]></ToUserName>
					<FromUserName><![CDATA[%s]]></FromUserName>
					<CreateTime>%s</CreateTime>
					<MsgType>text</MsgType>
					<Content><![CDATA[%s]]></Content>
					<FuncFlag>%d</FuncFlag>
					</xml>"; 
		$result = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $content, $funcFlag);
        return $result; 
    }
	
	private function transmitNews($object, $arr_item, $funcFlag = 0)
    {
        //首条标题28字，其他标题39字
        if(!is_array($arr_item))
            return;

        $itemTpl = "    <item>
		        <Title><![CDATA[%s]]></Title>
		        <Description><![CDATA[%s]]></Description>
		        <PicUrl><![CDATA[%s]]></PicUrl>
		        <Url><![CDATA[%s]]></Url>
		    </item>
		";
        $item_str = "";
        foreach ($arr_item as $item)
            $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);

        $newsTpl = "<xml>
			<ToUserName><![CDATA[%s]]></ToUserName>
			<FromUserName><![CDATA[%s]]></FromUserName>
			<CreateTime>%s</CreateTime>
			<MsgType><![CDATA[news]]></MsgType>
			<Content><![CDATA[]]></Content>
			<ArticleCount>%s</ArticleCount>
			<Articles>$item_str</Articles>
			<FuncFlag>%s</FuncFlag>
			</xml>";

        $resultStr = sprintf($newsTpl, $object->FromUserName, $object->ToUserName, time(), count($arr_item), $funcFlag);
        return $resultStr;
    }

    private function aminoCalculate($keyword){
    	
		if(is_null($this->chemicalData)) $this->chemicalData = init_data();
		
		$tools = split(',', $keyword);
		
		if(count($tools)>1){
			$keyword = strtoupper($tools[0]);
		}
		
		$contentStr = '';
		$nterm = '';
		$cterm = '';
		$amino = $keyword;
		
		$chemicalData = $this->chemicalData;
		$ntermData = $chemicalData['ntermData'];
		$ctermData = $chemicalData['ctermData'];
		
		// 根据nterm和cterm的参数，获取相关信息，分解出序列
		$nterm_values = array_values($ntermData);
		foreach($nterm_values as $nterm_value){
			$A = $nterm_value['A'];
			if(strpos($keyword, $A)===0){
				$nterm = $A;
				break;
			}
		}
		
		$cterm_values = array_values($ctermData);
		foreach($cterm_values as $cterm_value){
			$A = $cterm_value['A'];
			$pos = strrpos($keyword, $cterm_value['A']);
			$len = strlen($keyword)-strlen($cterm_value['A']);

			if($pos === $len){
				$cterm = $A;
				break;
			}
		}
	
		if(strlen($nterm)>0){
			$amino = substr($keyword, strlen($nterm));
		}else{
			$nterm = 'H-';
		}
		
		if(strlen($cterm)>0){
			$amino = substr($amino, 0, strlen($amino)-strlen($cterm));
		}else{
			$cterm = '-OH';
		}
		
		$needCheckData = array(
		   'amino'=>$amino,
		   'cterm'=>$cterm,
		   'nterm'=>$nterm
		);
		$result = calculateResult($this->chemicalData, $needCheckData);
		if($result['hasError']){
			$contentStr = $result['message'];
		}else{
			$residue = $result['residue'];
			$contentStr = "氨基酸个数：".$residue['count']."\n"
			              .'分子式：'.$result['molecularFomula']."\n"
			              .'1字符：'.$result['character1']."\n"
			              .'2字符:'.$result['character3']."\n"
			              .'平均分子量(MW):'.$result['mw']."g/mol\n"
			              .'精确分子量(Exact Mass):'.$result['em']."\n"
			              .'等电点（PI）：'.$result['isoelectricPoint']."\n"
			              .'pH=7时的净电荷：'.$result['pi7']."\n"
			              .'亲水性：'.$result['hydrophilyResult']."\n"
			              .'溶水性：'.$result['solubilityResult']."\n( 备注：由于溶解性不仅与氨基酸的序列有关，也和产品所带的反离子有关，若溶解性遇到问题，可咨询我们的技术人员。)\n"
			              .'<a href="'.WEB_HOST."/index.php?cal=1&nterm='.$nterm.'&cterm='.$cterm.'&amino='.$amino.'">查看详情</a>';

		}
		return $contentStr;
    }

	private function checkSignature(){
		$signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        
		$token = TOKEN;
		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
		
		if( $tmpStr == $signature ){
		   return true;
		}else{
		   return false;
		}
	}
}
