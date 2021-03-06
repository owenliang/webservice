<?php

require_once 'Config.php';

/**
 * 
 * 用于向Webservice提交数据
 * @author liangdong01@baidu.com
 *
 */
class EmsClient {			
	/**
	 * 获取客户端, 会设置一些header
	 * @param unknown $clazz
	 * @param unknown $method
	 * @param unknown $beanId
	 * @return SoapClient
	 */
	private function getSoapClient($clazz, $method, $beanId, $userId = '') {
		$soapClient = new SoapClient(null, array(
				'uri' => SOAP_URI,
				'location' => SOAP_LOCATION,
				'soap_version' => SOAP_1_2,
				'style' => SOAP_RPC,
				'trace' => true
		));
		$header = new SoapHeader(SOAP_NAMESPACE, 'field', (Object)array(
			new SoapVar($beanId, XSD_STRING, null, null, 'beanId', SOAP_NAMESPACE),
			new SoapVar($clazz, XSD_STRING, null, null, 'clazz', SOAP_NAMESPACE),
			new SoapVar($method, XSD_STRING, null, null, 'methodName', SOAP_NAMESPACE),
			new SoapVar($userId, XSD_STRING, null, null, 'userId', SOAP_NAMESPACE),
			new SoapVar('testing', XSD_STRING, null, null, 'token', SOAP_NAMESPACE),
		));
		$soapClient->__setSoapHeaders($header);
		return $soapClient;
	}
	
	/**
	 * DES签名head里的信息
	 * 
	 * 服务端用的是JAVA JCE特有的DES加密，PHP无法兼容，需使用JAVA预生成后配置到Config.php里
	 * @param unknown $input
	 * @param unknown $key
	 * @return
	 */
	private function encrypt($input, $key) {
		global $ENCRYPT_INFO;
		return $ENCRYPT_INFO[$input];
		/*
		if (strlen($key) > 8) {
			$key = substr($key, 0, 8);
		}
		$size = mcrypt_get_block_size ('des', 'ecb');
		$str = $this->pkcs5Pad ( $input, $size );
		$data = mcrypt_encrypt(MCRYPT_DES, $key, $str, MCRYPT_MODE_ECB);
		return bin2hex($data);
        */
	}
	 
	private function pkcs5Pad($text, $blocksize) {
		$pad = $blocksize - (strlen($text) % $blocksize);
		for ($i = 0; $i < $pad; ++$i) {
			$text .= chr(0);
		}
		return $text;
	}

	/**
	 * 发起soap请求
	 * @param unknown $soapClient
	 * @param unknown $clazz
	 * @param unknown $method
	 * @param unknown $beanId
	 * @param string $userId
	 * @return
	 */
	private  function doSoapRequest($soapClient, $request, $clazz, $method, $beanId, $userId = '') {
		// 加密Head里的内容用作身份验证
		$encryptBytes = $this->encrypt($beanId . $clazz . $method . $userId, SOAP_DES_KEY);
		for ($i = 0; $i < 5; ++$i) { // 重试5次放弃
			try {
				$params = array(
						new SoapVar(base64_encode($request), XSD_STRING, null, null, 'receiveStrXml'),// XML需要base64编码
						new SoapVar(base64_encode($encryptBytes), XSD_STRING, null, null, 'encryptBytes'),
				);
				$response = $soapClient->__soapcall('psmSevice', $params); 
			} catch (Exception $e) {
                echo "[request fail for times=$i]$beanId | $clazz | $method | $userId" . PHP_EOL;
				//echo $soapClient->__getLastRequest();
				echo $soapClient->__getLastResponse();
				continue;
			}
			// var_dump($request);
			echo "[request succeed] $response" . PHP_EOL;
			return $response;
		}
		return null;
	}
	
	/**
	 * 3.1.1.监测点曲线数据
	 * @param unknown $dataItemCode
	 * @param unknown $dataTime
	 * @param unknown $dataValues
	 * @return
	 */
	public function monitorPointCurveService($dataItemCode, $dataTime, $dataValues) {
		$clazz = 'com.sgcc.psm.psmapi.emsenergy.datamanage.monitorpointcurve.bizc.impl.MonitorPointCurveBizcImpl';
		$method = 'monitorPointCurveService';
		$beanId = 'commonWsService';
		$soapClient = $this->getSoapClient($clazz, $method, $beanId);
        
        // 时间格式YYYYMMDD
        $dataTime = date('Ymd', strtotime($dataTime));

        foreach ($dataValues as $msNo => $values) {
        	$xmlWriter = new XMLWriter();
        	$xmlWriter->openMemory();
        	
        	$xmlWriter->startElement('Root');
        	$xmlWriter->startElement('indexDets');
        	
	        $xmlWriter->startElement('indexDet');
	        
	        // 企业编号
	        $xmlWriter->startElement('consNo');
	        $xmlWriter->text(CONS_NO);
	        $xmlWriter->endElement();
	        
	        // 监测点编号
	        $xmlWriter->startElement('msNo');
	        $xmlWriter->text($msNo);
	        $xmlWriter->endElement();
	        
	        // 数据项代码
	        $xmlWriter->startElement('dataItemCode');
	        $xmlWriter->text(strtoupper($dataItemCode));
	        $xmlWriter->endElement();
	
	        // 数据日期
	        $xmlWriter->startElement('dataDate');
	        $xmlWriter->text($dataTime);
	        $xmlWriter->endElement();
	        
	        // 数据编号，传0
	        $xmlWriter->startElement('recordNo');
	        $xmlWriter->text(0);
	        $xmlWriter->endElement();
	        
	        // 采集周期
	        $xmlWriter->startElement('curveDensity');
	        $xmlWriter->text(CURVE_DENSITY);
	        $xmlWriter->endElement();
	
	        // 数据类型
	        $xmlWriter->startElement('dataType');
	        $xmlWriter->text('01');
	        $xmlWriter->endElement();
	
	        // 数据值
	        for ($i = 0; $i < count($values); ++$i) {
	            $xmlWriter->startElement('dataValue' . strval($i + 1));
	            $xmlWriter->text($values[$i]);
	            $xmlWriter->endElement();
	        }

	        /*
	        // 数据值
	        for ($i = 0; $i < 96; ++$i) {
	        	$xmlWriter->startElement('dataValue' . strval($i + 1));
	        	$xmlWriter->text("0");
	        	$xmlWriter->endElement();
	        }*/
	        
	        $xmlWriter->endElement();
	        
	        $xmlWriter->endElement();
	        $xmlWriter->endElement();

	        $request = $xmlWriter->flush();
	        $response = $this->doSoapRequest($soapClient, $request, $clazz, $method, $beanId);
        }
    }
	
	/**
	 * 3.1.2.监测点日冻结数据
	 * @param unknown $dataItemCode
	 * @param unknown $dataTime
	 * @param unknown $dataValue
	 * @return
	 */
	public function monitorDailyFreezService($dataItemCode, $dataTime, $dataValue) {
		$clazz = 'com.sgcc.psm.psmapi.emsenergy.datamanage.monitordailyfreez.bizc.impl.MonitorDailyFreezBizcImpl';
		$method = 'monitorDailyFreezService';
		$beanId = 'commonWsService';
		$soapClient = $this->getSoapClient($clazz, $method, $beanId);
		
		// 时间格式YYYYMMDD
		$dataTime = date('Ymd', strtotime($dataTime));
		
        foreach ($dataValue as $msNo => $values) {
        	$xmlWriter = new XMLWriter();
        	$xmlWriter->openMemory();
        	
        	$xmlWriter->startElement('Root');
        	$xmlWriter->startElement('indexDets');
        	
        	$xmlWriter->startElement('indexDet');
	        
	        // 企业编号
	        $xmlWriter->startElement('consNo');
	        $xmlWriter->text(CONS_NO);
	        $xmlWriter->endElement();
	        
	        // 监测点编号
	        $xmlWriter->startElement('msNo');
	        $xmlWriter->text($msNo);
	        $xmlWriter->endElement();
	        
	        // 数据项代码
	        $xmlWriter->startElement('dataItemCode');
	        $xmlWriter->text(strtoupper($dataItemCode));
	        $xmlWriter->endElement();
	
	        // 数据日期
	        $xmlWriter->startElement('dataTime');
	        $xmlWriter->text($dataTime);
	        $xmlWriter->endElement();
	        
	        // 日冻结类型
	        $xmlWriter->startElement('dataType');
	        $xmlWriter->text('01');
	        $xmlWriter->endElement();
	
	        // 数据值
	        $xmlWriter->startElement('dataValue');
	        $xmlWriter->text($values[0]);
	        $xmlWriter->endElement();
	        
	        $xmlWriter->endElement();
	        
	        $xmlWriter->endElement();
	        $xmlWriter->endElement();

	        $request = $xmlWriter->flush();
	        $response = $this->doSoapRequest($soapClient, $request, $clazz, $method, $beanId);
        }
	}
	
	/**
	 * 3.1.3.监测点月冻结数据
	 * @param unknown $dataItemCode
	 * @param unknown $ym
	 * @param unknown $dataValue
	 * @return
	 */
	public function monitorMonFreezService($dataItemCode, $ym, $dataValue) {
		$clazz = 'com.sgcc.psm.psmapi.emsenergy.datamanage.monitormonfreez.bizc.impl.MonitorMonFreezBizcImpl';
		$method = 'monitorMonFreezService';
		$beanId = 'commonWsService';
		$soapClient = $this->getSoapClient($clazz, $method, $beanId);
		
        foreach ($dataValue as $msNo => $values) {
        	$xmlWriter = new XMLWriter();
        	$xmlWriter->openMemory();
        	 
        	$xmlWriter->startElement('Root');
        	$xmlWriter->startElement('indexDets');
	        $xmlWriter->startElement('indexDet');
	        
	        // 企业编号
	        $xmlWriter->startElement('consNo');
	        $xmlWriter->text(CONS_NO);
	        $xmlWriter->endElement();
	        
	        // 监测点编号
	        $xmlWriter->startElement('msNo');
	        $xmlWriter->text($msNo);
	        $xmlWriter->endElement();
	        
	        // 数据项代码
	        $xmlWriter->startElement('dataItemCode');
	        $xmlWriter->text(strtoupper($dataItemCode));
	        $xmlWriter->endElement();
	
	        // 数据日期
	        $xmlWriter->startElement('ym');
	        $xmlWriter->text($ym);
	        $xmlWriter->endElement();
	        
	        // 日冻结类型
	        $xmlWriter->startElement('dataType');
	        $xmlWriter->text('01');
	        $xmlWriter->endElement();
	
	        // 数据值
	        $xmlWriter->startElement('dataValue');
	        $xmlWriter->text($values[0]);
	        $xmlWriter->endElement();
	        
	        $xmlWriter->endElement();
	        
	        $xmlWriter->endElement();
	        $xmlWriter->endElement();
	        
	        $request = $xmlWriter->flush();
	        $response = $this->doSoapRequest($soapClient, $request, $clazz, $method, $beanId);
        }
	}
}
