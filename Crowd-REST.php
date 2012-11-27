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
		$rc = curl_exec($curl);
		$info = curl_getinfo($curl);
		return array("response" => $rc, "metadata" => $info);
	}

	function curl_logerror($rc, $msg_prefix = "curl error") {
		$http_response_code = $rc['metadata'][CURLINFO_HTTP_CODE];

		if($http_response_code != 200) {
			error_log("${msg_prefix}:\n" . $rc['response']);
			return false;
		}

		return true;
	}

	function crowd_xml_logerror($rc, $msg_prefix = "error in xml response") {
		// got back a valid XML response (hopefully)
		$xmlResponse = new SimpleXMLElement($rc['response']);
		if($xmlResponse[0]->getName() == "error") {
			error_log("${msg_prefix}\n" . $rc['response']);
			return null;
		}

		return $xmlResponse;
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
		$xmlBody = generateSimpleAuthXML($password);
		$rc = $curlPost("/authentication", array("username" => $username),$xmlBody);

		// check to make sure we got a 200 and response from the server
		if(curl_logerror($rc,"Error in performing simple authentication:\n")){
			return false;
		}

		// got back a valid XML response (hopefully)
		$xmlResponse = crowd_xml_logerror($rc,"Error returned in Crowd XML response");
		if($xmlResponse) {
			if($xmlResponse[0]->getName() == "user") {
				return ($xmlResponse[0]-getAttr('username') == $username);
			} else {
				error_log("Got unexpected Crowd XML response to auth query:\n" . $rc['response']);
			}
		}

		return false;
	}

	function tokenAuth($username, $password) {
		return false;
	}

	function userIsInGroup($username, $groupname) {
		$rc = curlPost("/user/group/nested",array("username" => $username, "groupname" => $groupname));

		$http_response_code = $rc['metadata']['CURLINFO_HTTP_CODE'];

		if($http_response_code == 200) {
			// user belongs to group
			return true;
		} elseif ($http_response_code == 404) {
			// user not in group
			return false;
		} else {
			// some other error
			curl_logerror($rc, "Error while confirming membership of '${username}' in group '${groupname}'");
			return false;
		}
	}

	function getUserInfo($username) {
		$rc = curlPost('/user', array('username' => $username));

		if (curl_logerror($rc,"Error while retrieving user info for username '${username}'")){
			return null;
		}

		// got back a valid XML response (hopefully)
		$xmlResponse = crowd_xml_logerror($rc,"Error returned in Crowd XML response");
		if($xmlResponse) {
			if($xmlResponse[0]->getName() == "user") {
				// TODO continue here
				$firstname = $xmlResponse[1]->getValue()				
				return ($xmlResponse[0]-getAttr('username') == $username);
			} else {
				error_log("Got unexpected Crowd XML response to auth query:\n" . $rc['response']);
			}
		}
	}

	function generateSimpleAuthXML($password) {
		$document = new DOMDocument("1.0","UTF-8");
		$password = $document->appendChild($document->createElement("password"));
		$value = $password->appendChild($document->createElement("value"));
		$cdata = $document->createCDATASection($password);
		$value->appendChild($cdata);

		return $document->asXML();
	}

?>