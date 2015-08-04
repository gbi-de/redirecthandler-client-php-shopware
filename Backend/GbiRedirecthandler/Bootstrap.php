<?php
/**
 * Client Plugin for Goldbach Interactive (Germany) AG - Redirect Handler. 
 * 
 * @author Stefan Meinlschmidt
 *
 */
class Shopware_Plugins_Backend_GbiRedirecthandler_Bootstrap extends Shopware_Components_Plugin_Bootstrap {
	private $plugininfo;

	public function getCapabilities() {
		return array(
			'install' => true,
			'update' => true,
			'enable' => true
		);
	}

	public function getInfo() {
		if($this->plugininfo == null){
			$this->plugininfo =  json_decode(file_get_contents(__DIR__.'/plugin.json'),true);
		}
		
		return $this->plugininfo;
	}
	public function install() {
		/* Frontend */
		$this->subscribeEvent('Enlight_Controller_Front_PostDispatch', 'onEnlightControllerFrontPostDispatch');
		$this->createConfiguration();
		return array('success' => true);
	}
	
	/**
	 * Listener function will request the Redirecthandler server if the shop would return an 404 or 500 page
	 * this Function is called from showpware core
	 * @param Enlight_Event_EventArgs $args
	 */
	public function onEnlightControllerFrontPostDispatch(Enlight_Event_EventArgs $args) {
		if (in_array($args->getSubject()->Response()->getHttpResponseCode(),array( 404, 500 ))){
			$this->handleError($args->getSubject()->Request());
		}
	}
	
	/**
	 * Creating the Plugin configuration Form
	 */
	private function createConfiguration()	{
		$form = $this->Form();
		$form->setElement('text', 'apikey',            
			array(
				'label' => 'API-Key',
				'value' => '',
				'description' => 'Your API Key',
				'required' => true
			)
		);
		$form->setElement('text', 'apiurl',            
			array(
				'label' => 'API-URL',
				'value' => 'https://tao.goldbach.com/redirect/',
				'description' => 'API URL of the redirecthandler. Default: https://tao.goldbach.com/redirect/',
				'required' => true
			)
		);
	}

	/**
	 * Default php redirecthandler request function
	 * 
	 * @param Enlight_Controller_Request_Request $request
	 * @return boolean false if there is no redirect, otherwise the redirect header is directly send. no further php execution.
	 */
	protected function handleError(Enlight_Controller_Request_Request $request){
		$sUrl = $request->getPathInfo();
		
		$redirectServerBaseUrl = $this->Config()->get('apiurl');
		$apikey = $this->Config()->get('apikey');
		
		$originalRequstUri = urlencode($sUrl);
		$redirectUrl = $redirectServerBaseUrl . '?r=' . $originalRequstUri;
		$headers = $this->getHeaders($redirectUrl,$apikey);
		
		$responseStatus = $headers[0];
		
		if (strpos($responseStatus, '404 Not Found') || $responseStatus =='') {
			return false;
		}
		
		foreach ($headers as $headerValue) {
			header($headerValue);
		}
		
		exit;
	}
	
	/**
	 * Requesting the Redirecthandler Server
	 * @param string $url to request normaly https://tao.goldbach.com/redirect
	 * @param string $apikey your api key (some md5 hash)
	 * @return array of headers
	 */
	private function getHeaders($url, $apikey) {

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-gbi-key: " . $apikey));

		$r = curl_exec($ch);

		$r = @split("\n", $r);
		return $r;
	}
}
