<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category    CosmoCommerce
 * @package     CosmoCommerce_Unionpay
 * @copyright   Copyright (c) 2009-2013 CosmoCommerce,LLC. (http://www.cosmocommerce.com)
 * @contact :
 * T: +86-021-66346672
 * L: Shanghai,China
 * M:sales@cosmocommerce.com
 */
class CosmoCommerce_Unionpay_PaymentController extends Mage_Core_Controller_Front_Action
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
     * Order instance
     */
    protected $_order;
    /**
     *  Get order
     *
     *  @param    none
     *  @return	  Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if ($this->_order == null)
        {
            $session = Mage::getSingleton('checkout/session');
            $this->_order = Mage::getModel('sales/order');
            $this->_order->loadByIncrementId($session->getLastRealOrderId());
        }
        return $this->_order;
    }

    /**
     * When a customer chooses Unionpay on Checkout/Payment page
     *
     */
    public function redirectAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setUnionpayPaymentQuoteId($session->getQuoteId());

        $order = $this->getOrder();

        if (!$order->getId())
        {
            $this->norouteAction();
            return;
        }

        $order->addStatusToHistory(
        $order->getStatus(),
        Mage::helper('unionpay')->__('Customer was redirected to Unionpay')
        );
        $order->save();

        $this->getResponse()
        ->setBody($this->getLayout()
        ->createBlock('unionpay/redirect')
        ->setOrder($order)
        ->toHtml());

        $session->unsQuoteId();
    }
    public function paymobileAction(){
    
        if (function_exists("date_default_timezone_set")) {
            date_default_timezone_set(self::$timezone);
        }


        if ($this->getRequest()->isPost())
        {
            $postData = $this->getRequest()->getPost();
            $method = 'post';


        } else if ($this->getRequest()->isGet())
        {
            $postData = $this->getRequest()->getQuery();
            $method = 'get';

        } else
        {
            return;
        }
        Mage::log($postData,null,'unionpay_mobile.log');
		$unionpay = Mage::getModel('unionpay/payment');
		$gateway = $unionpay->getConfigData('gateway');
		$mer_id=$unionpay->getConfigData('partner_id');
		$security_key=$unionpay->getConfigData('security_code');

    
        if(isset($postData['orderId'])){
            $order = Mage::getModel('sales/order');
            $order=$order->loadByIncrementId($postData['orderId']);   
 
            if($order->getId()){
                $transamt = sprintf('%.2f',$order->getGrandTotal())*100;
            
                $req=array();
                $req['version']     		= self::$version; // 版本号
                $req['charset']     		= self::$charset; // 字符编码
                $req['transType']   		= "01"; // 交易类型
                $req['merId']       		= $mer_id; // 商户代码
                $req['backEndUrl']      	= Mage::getUrl('unionpay/payment/upmpnotify'); // 通知URL
                $req['frontEndUrl']     	= Mage::getUrl('unionpay/payment/upmpnotify'); // 前台通知URL(可选)
                $req['orderDescription']	= $order->getRealOrderId();// 订单描述(可选)
                $req['orderTime']   		= date('Ymdhjs',strtotime($order->getCreatedAt())); // 交易开始日期时间yyyyMMddHHmmss
                $req['orderTimeout']   		= ""; // 订单超时时间yyyyMMddHHmmss(可选)
                $req['orderNumber'] 		= $order->getRealOrderId(); //订单号(商户根据自己需要生成订单号)
                $req['orderAmount'] 		= $transamt; // 订单金额
                $req['orderCurrency'] 		= "156"; // 交易币种(可选)
                $req['reqReserved'] 		= "透传信息"; // 请求方保留域(可选，用于透传商户信息)

            
                // 保留域填充方法
                $merReserved['customerid']   		= "商户保留域";
                $req['merReserved']   		= self::buildReserved($merReserved); // 商户保留域(可选)

                Mage::log('验证请求数据',null,'unionpay_mobile.log');
                Mage::log($req,null,'unionpay_mobile.log');
                
                
                $resp = array ();
                $validResp = self::trade($req, $resp);

                Mage::log('验证返回数据',null,'unionpay_mobile.log');
                Mage::log($resp,null,'unionpay_mobile.log');
                // 商户的业务逻辑
                if ($validResp){
                    Mage::log('服务器应答签名验证成功',null,'unionpay_mobile.log');
                    //echo $req['orderNumber']." ";
                    //echo '服务器应答签名验证成功';
                    if(isset($resp['tn'])){
                        echo $resp['tn'];
                        exit();
                    }
                }else {
                    Mage::log('服务器应答签名验证失败',null,'unionpay_mobile.log');
                }
            }else{
                Mage::log('order load error',null,'unionpay_mobile.log');
            }
        }
                
    }
    public function upmpnotifyAction()
    {
        if ($this->getRequest()->isPost())
        {
            $postData = $this->getRequest()->getPost();
            $method = 'post';


        } else if ($this->getRequest()->isGet())
        {
            $postData = $this->getRequest()->getQuery();
            $method = 'get';

        } else
        {
            return;
        }
        
        Mage::log($postData,null,'unionpay_mobile.log');
		$unionpay = Mage::getModel('unionpay/payment');
		$partner=$unionpay->getConfigData('partner_id');
		$security_code=$unionpay->getConfigData('security_code');
		$gateway = $unionpay->getConfigData('gateway');

        if (self::verifySignature($postData)){// 服务器签名验证成功
            Mage::log('服务器签名验证成功',null,'unionpay_mobile.log');
            //请在这里加上商户的业务逻辑程序代码
            //获取通知返回参数，可参考接口文档中通知参数列表(以下仅供参考)
            $transStatus = $postData['transStatus'];// 交易状态
            if (""!=$transStatus && "00"==$transStatus){
                Mage::log('交易处理成功',null,'unionpay_mobile.log');
                $order = Mage::getModel('sales/order');
				$order->loadByIncrementId($postData['orderNumber']);
                if ($order->getState() == 'new' || $order->getState() != 'processing' || $order->getState() == 'pending_payment' || $order->getState() == 'payment_review') {
                    $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
                    $order->sendOrderUpdateEmail(true,'买家已付款,交易成功结束。');
                    $order->addStatusToHistory(
                    $unionpay->getConfigData('order_status_payment_accepted'),
                    Mage::helper('unionpay')->__('买家已付款,交易成功结束。'));
                    try{
                        $order->save();
                        echo "success";
                    } catch(Exception $e){
                        
                    }
                }
            }else {
                Mage::log('交易处理不成功',null,'unionpay_mobile.log');
            }
        }else {// 服务器签名验证失败
            echo "fail";
            Mage::log('服务器签名验证失败',null,'unionpay_mobile.log');
        }
    }
 

    /**
     *  Success payment page
     *
     *  @param    none
     *  @return	  void
     */
    public function successAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $session->setQuoteId($session->getUnionpayPaymentQuoteId());
        $session->unsUnionpayPaymentQuoteId();

        $order = $this->getOrder();

        if (!$order->getId())
        {
            $this->norouteAction();
            return;
        }

        $order->addStatusToHistory(
        $order->getStatus(),
        Mage::helper('unionpay')->__('Customer successfully returned from Unionpay')
        );

        $order->save();

        $this->_redirect('checkout/onepage/success');
    }

    /**
     *  Failure payment page
     *
     *  @param    none
     *  @return	  void
     */
    public function errorAction()
    {
        $session = Mage::getSingleton('checkout/session');
        $errorMsg = Mage::helper('unionpay')->__(' There was an error occurred during paying process.');

        $order = $this->getOrder();

        if (!$order->getId())
        {
            $this->norouteAction();
            return;
        }
        if ($order instanceof Mage_Sales_Model_Order && $order->getId())
        {
            $order->addStatusToHistory(
            Mage_Sales_Model_Order::STATE_CANCELED,//$order->getStatus(),
            Mage::helper('unionpay')->__('Customer returned from Unionpay.').$errorMsg
            );

            $order->save();
        }

        $this->loadLayout();
        $this->renderLayout();
        Mage::getSingleton('checkout/session')->unsLastRealOrderId();
    }
	
	
    
    /**
     * 交易接口处理
     * @param req 请求要素
     * @param resp 应答要素
     * @return 是否成功
     */
    static function trade($req, &$resp) { 
		$unionpay = Mage::getModel('unionpay/payment');
		$upmp_trade_url=$unionpay->getConfigData('gateway').'trade';
         
    	$nvp = self::buildReq($req);
    	$respString = self::postdata($upmp_trade_url, $nvp); 
    	return self::verifyResponse($respString, $resp);
    }
    
	/**
	 * 交易查询处理
	 * @param req 请求要素
	 * @param resp 应答要素
	 * @return 是否成功
	 */
    static function query($req, &$resp) { 
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
        $buildstring=self::createLinkstring($req, true, true); 
    	$prestr = "{".$buildstring."}"; 
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
    
    
    
}
