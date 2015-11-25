<?php
class CosmoCommerce_Unionpay_Adminhtml_QueryController extends Mage_Adminhtml_Controller_Action
{


    static $timezone        		= "Asia/Shanghai"; //时区
    
    static $version     			= "1.0.0"; // 版本号
    static $charset    		 		= "UTF-8"; // 字符编码
    static $sign_method 			= "MD5"; // 签名方法，目前仅支持MD5
    
    
    const VERIFY_HTTPS_CERT 		= false;
    
    const RESPONSE_CODE_SUCCESS 	= "00"; // 成功应答码
	const SIGNATURE 				= "signature"; // 签名
	const SIGN_METHOD 				= "signMethod"; // 签名方法
	const RESPONSE_CODE 			= "respCode"; // 应答码
	const RESPONSE_MSG				= "respMsg"; // 应答信息
    
    const QSTRING_SPLIT				= "&"; // &
    const QSTRING_EQUAL 			= "="; // =
    
	
    
    /**
     * 交易接口处理
     * @param req 请求要素
     * @param resp 应答要素
     * @return 是否成功
     */
    static function trade($req, &$resp) {
        Mage::log('trade');
        Mage::log($req);
		$unionpay = Mage::getModel('unionpay/payment');
		$upmp_trade_url=$unionpay->getConfigData('gateway').'trade';
        
        Mage::log('upmp_trade_url');
        Mage::log($upmp_trade_url);
    	$nvp = self::buildReq($req);
    	$respString = self::postdata($upmp_trade_url, $nvp);
        Mage::log('返回值');
        Mage::log($respString);
    	return self::verifyResponse($respString, $resp);
    }
    
	/**
	 * 交易查询处理
	 * @param req 请求要素
	 * @param resp 应答要素
	 * @return 是否成功
	 */
    static function query($req, &$resp) {
        Mage::log('query');
        Mage::log($req);
		$unionpay = Mage::getModel('unionpay/payment');
		$upmp_query_url=$unionpay->getConfigData('gateway').'query';
        
    	$nvp = self::buildReq($req);
    	$respString = self::postdata($upmp_query_url, $nvp);
    	return self::verifyResponse($respString, $resp);
    }
    
    /**
     * 拼接请求字符串
     * @param req 请求要素
     * @return 请求字符串
     */
    static function buildReq($req) {
        Mage::log('buildReq');
        Mage::log($req);
    	//除去待签名参数数组中的空值和签名参数
    	$filteredReq = self::paraFilter($req);
    	// 生成签名结果
    	$signature = self::buildSignature($filteredReq);
    	
    	// 签名结果与签名方式加入请求
    	$filteredReq[self::SIGNATURE] = $signature;
    	$filteredReq[self::SIGN_METHOD] = self::$sign_method;
    	
    	return self::createLinkstring($filteredReq, false, true);
    }
    
    /**
     * 拼接保留域
     * @param req 请求要素
     * @return 保留域
     */
    static function buildReserved($req) {
        Mage::log('buildReserved');
        Mage::log($req);
        Mage::log('createLinkstring');
        $buildstring=self::createLinkstring($req, true, true);
        Mage::log($buildstring);
    	$prestr = "{".$buildstring."}";
        Mage::log($prestr);
    	return $prestr;
    }
    
    /**
     * 应答解析
     * @param respString 应答报文
     * @param resp 应答要素
     * @return 应答是否成功
     */
    static function verifyResponse($respString, &$resp) {
    	if  ($respString != ""){
    		parse_str($respString, $para);
    		
    		$signIsValid = self::verifySignature($para);
    		
    		$resp = $para;
    		if ($signIsValid) {
    			return true;
    		}else {
    			return false;
    		}
    	}
    	
    	
    }
    
    /**
     * 异步通知消息验证
     * @param para 异步通知消息
     * @return 验证结果
     */
    static function verifySignature($para) {
    	$respSignature = $para[self::SIGNATURE];
    	// 除去数组中的空值和签名参数
    	$filteredReq = self::paraFilter($para);
    	$signature = self::buildSignature($filteredReq);
    	if ("" != $respSignature && $respSignature==$signature) {
    		return true;
    	}else {
    		return false;
    	}
    }
	
        
        
        
    /**
     * 除去请求要素中的空值和签名参数
     * @param para 请求要素
     * @return 去掉空值与签名参数后的请求要素
     */
    static function paraFilter($para) {
        Mage::log('paraFilter');
        Mage::log($para);
        $result = array ();
        while ( list ( $key, $value ) = each ( $para ) ) {
            if ($key == self::SIGNATURE || $key == self::SIGN_METHOD || $value == "") {
                continue;
            } else {
                $result [$key] = $para [$key];
            }
        }
        return $result;
    }

    /**
     * 生成签名
     * @param req 需要签名的要素
     * @return 签名结果字符串
     */
    static function buildSignature($req) {
        Mage::log('buildSignature');
        Mage::log($req);
		$unionpay = Mage::getModel('unionpay/payment');
		$security_key=$unionpay->getConfigData('security_code');
        
        $prestr = self::createLinkstring($req, true, false);
        $prestr = $prestr.self::QSTRING_SPLIT.md5($security_key);
        return md5($prestr);
    }

    /**
     * 把请求要素按照“参数=参数值”的模式用“&”字符拼接成字符串
     * @param para 请求要素
     * @param sort 是否需要根据key值作升序排列
     * @param encode 是否需要URL编码
     * @return 拼接成的字符串
     */
    static function createLinkString($para, $sort, $encode) {
        Mage::log('createLinkString in');
        Mage::log($para);
        $linkString  = "";
        if ($sort){
            $para = self::argSort($para);
        }
        while (list ($key, $value) = each ($para)) {
            if ($encode){
                $value = urlencode($value);
            }
            $linkString.=$key.self::QSTRING_EQUAL.$value.self::QSTRING_SPLIT;
        }
        //去掉最后一个&字符
        $linkString = substr($linkString,0,count($linkString)-2);
        
        Mage::log($linkString);
        return $linkString;
    }

    /**
     * 对数组排序
     * @param $para 排序前的数组
     * return 排序后的数组
     */
    static function argSort($para) {
        ksort($para);
        reset($para);
        return $para;
    }

    /*
     * curl_call
    *
    * @url:  string, curl url to call, may have query string like ?a=b
    * @content: array(key => value), data for post
    *
    * return param:
    *	mixed:
    *	  false: error happened
    *	  string: curl return data
    *
    */
    static function postdata($url, $content = null)
    {
        if (function_exists("curl_init")) {
            $curl = curl_init();

            if (is_array($content)) {
                $data = http_build_query($content);
            }

            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_TIMEOUT, 60); //seconds
            
            // https verify
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, self::VERIFY_HTTPS_CERT);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, self::VERIFY_HTTPS_CERT);

            $ret_data = curl_exec($curl);

            if (curl_errno($curl)) {
                printf("curl call error(%s): %s\n", curl_errno($curl), curl_error($curl));
                curl_close($curl);
                return false;
            }
            else {
                curl_close($curl);
                return $ret_data;
            }
        } else {
            throw new Exception("[PHP] curl module is required");
        }
    }
    
    
    
    
    public function queryAction()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        $order = Mage::getModel('sales/order')->load($orderId);
        
        if ($order->getId()) {
        
             
            $unionpay = Mage::getModel('unionpay/payment');
            $gateway = $unionpay->getConfigData('gateway');
            $mer_id=$unionpay->getConfigData('partner_id');
            $security_key=$unionpay->getConfigData('security_code');
            
            //需要填入的部分
            $req['version']     	= self::$version; // 版本号
            $req['charset']     	= self::$charset; // 字符编码
            $req['transType']   	= "01"; // 交易类型
            $req['merId']       	= $mer_id; // 商户代码
         
            $req['orderTime']   	= date('Ymdhjs',strtotime($order->getCreatedAt())); // 交易开始日期时间yyyyMMddHHmmss或yyyyMMdd
            $req['orderNumber'] 	= $order->getRealOrderId(); // 订单号

            // 保留域填充方法
            $merReserved['reserved']   	= "reserved";
            $req['merReserved']   	= self::buildReserved($merReserved); // 商户保留域(可选)

            
            $resp = array ();
            $validResp = self::query($req, $resp);

            
            // 商户的业务逻辑
            $msg="等待银联返回信息";
            Mage::log($req,null,'unionpay_mobile.log');
            Mage::log($resp,null,'unionpay_mobile.log');
            if ($validResp){ 
                if(($resp['transStatus']=="00")){
                    Mage::getSingleton('adminhtml/session')->addError('订单已支付成功');
                    $this->_redirect('adminhtml/sales_order/view', array('order_id' => $orderId));
                }else{
                    Mage::getSingleton('adminhtml/session')->addError('订单查询错误');
                    $this->_redirect('adminhtml/sales_order/view', array('order_id' => $orderId));
                }
                
            }else {
                Mage::getSingleton('adminhtml/session')->addError('系统验证错误');
                $this->_redirect('adminhtml/sales_order/view', array('order_id' => $orderId));
                
            }
            return;
        }
        else {
            Mage::getSingleton('adminhtml/session')->addError('Order number not exist in system');
            $this->_redirect('adminhtml/sales_order/view', array('order_id' => $orderId));
            return;
        }
    }

}
