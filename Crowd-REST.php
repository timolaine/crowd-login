`																																															`<?


class CrowdREST {

	const CONFIG_SSO_ENABLED = 'CONFIG_SSO_ENABLED';

	private $__CROWD_REST_API_PATH = "/rest/usermanagement/1";
	private $crowd_config;
	private $crowd_app_token;

	function curlPost($url, $attrs, $post_body) {
		$crowd_endpoint = $crowd_config['service_endpoint'];
		$full_url = "${crowd_endpoint}${__CROWD_REST_API_PATH}${url}?" . http_build_query($attrs);
		$curl = curl_init($full_url);
		curl_setopt($curl, CURLOPT_USERPWD, '[' . $crowd_config['app_name'] . ']:[' . $crowd_config['app_credential'] . ']');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: text/xml")); 
		curl_setopt($curl, CURLOPT_POSTFIELDS,$post_body);
		return curl_exec($curl);
	}

	function isSSOEnabled() {
		if($crowd_config[self::CONFIG_SSO_ENABLED]) {
			return true;
		} 
		return false;
	}

	function authenticateUser($username, $password) {
		if($this->isSSOEnabled()) {
			// SSO uses a different auth mechanism and handles cookies
			return $this->tokenAuth($username, $password);
		} else {
			return $this->simpleAuth($username, $password);
		}
	}

	function simpleAuth($username, $password) {
		$xmlBody = generateSimpleAuthXML($username, $password);
		$rc = $curlPost("/authentication", array("username" => $username),$xmlBody);

		$xmlResponse = new SimpleXMLElement($rc);
		if($xmlResponse[0]->getName() == "error") {
			return false;
		}

		if($xmlResponse[0]->getName() == "user") {
			return ($xmlResponse[0]-getAttr('username') == $username);
		}

		return false;
	}

	function tokenAuth($username, $password) {
		return false;
	}

	functiopn generateSimpleAuthXML($username, $password) {
		$document = new DOMDocument("1.0","UTF-8");
		$password = $document->appendChild($document->createElement("password"));
		$value = $password->appendChild($document->createElement("value"));
		$cdata = $document->createCDATASection($password);
		$value->appendChild($cdata);

		return $document->asXML();
	}
}

?>