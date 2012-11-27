<?php
/**
 * REST API as documented here:
 *    https://developer.atlassian.com/display/CROWDDEV/Crowd+REST+Resources#CrowdRESTResources-UserResource
 */
class CrowdREST {

	const CONFIG_SSO_ENABLED = 'CONFIG_SSO_ENABLED';
	const CROWD_REST_API_PATH = '/rest/usermanagement/1';

	private $crowd_config;
	private $cookies = null;
	private $base_url = null;

	public function CrowdREST($crowd_config) {
		$this->crowd_config = $crowd_config;
		$this->base_url = $crowd_config['crowd_url'] . self::CROWD_REST_API_PATH;
	}

	private function curlPost($url, $attrs, $post_body = false) {
		$crowd_config = $this->crowd_config;
		$full_url = $this->{base_url} . ${url}. "?" . http_build_query($attrs);
		$curl = curl_init($full_url);
		curl_setopt($curl, CURLOPT_USERPWD, '[' . $crowd_config['app_name'] . ']:[' . $crowd_config['app_credential'] . ']');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: text/xml")); 
		$post_body ? curl_setopt($curl, CURLOPT_POSTFIELDS,$post_body) : null;
		if (array_key_exists('verify_ssl_peer',$crowd_config)) {
		    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $crowd_config['verify_ssl_peer']);
			 error_log("Set CURLOPT_SSL_VERIFYPEER to " . $crowd_config['verify_ssl_peer']);
		}
		curl_setopt($curl, CURLOPT_HEADER, true);
		$this->curl_load_cookies($curl);
		$response = curl_exec($curl);

		// check for curl errors 
		if (curl_errno($curl)) {
		   throw new Exception("curl error (${full_url}): " . curl_error($curl));
		}
		$info = curl_getinfo($curl);
		$rc = $this->curl_split_headers($response,$info);
		$rc['metadata'] = $info;
		$this->curl_store_cookies($rc);
		return $rc;
	}

	private function curl_split_headers($raw_response,$info) {
		$headers = substr($raw_response, 0, $info[CURLINFO_HEADER_SIZE]);
		$response = substr($raw_response, $info[CURLINFO_HEADER_SIZE]);
		return array('headers' => $headers, 'response' => $response);
	}

	private function curl_store_cookies($rc) {
		$headers = $rc['headers'];
		preg_match_all('|Set-Cookie: (.*);|U', $data, $matches);   
		$this->cookies = implode('; ', $matches[1]);
	}

	private function curl_load_cookies($curl) {
		if($this->cookies) {
				curl_setopt($curl,CULR_COOKIES,$this->cookies);
		}
	}

	private function curl_logerror($rc, $msg_prefix = "curl error") {
		$http_response_code = $rc['metadata'][CURLINFO_HTTP_CODE];

		if($http_response_code != 200) {
			error_log("${msg_prefix}:\n" . $rc['response']);
			return false;
		}

		return true;
	}

	private function crowd_xml_logerror($rc, $msg_prefix = "error in xml response") {
		// got back a valid XML response (hopefully)
		$xmlResponse = new SimpleXMLElement($rc['response']);
		if($xmlResponse[0]->getName() == "error") {
			$reason = $xmlResponse->{reason};
			$message = $xmlResponse->{message};
			error_log("${msg_prefix}: ${reason} - ${message}");
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
		$xmlBody = $this->generateSimpleAuthXML($password);
		$rc = $this->curlPost("/authentication", array("username" => $username),$xmlBody);

		// check to make sure we got a 200 and response from the server
		if(!$this->curl_logerror($rc,"Error in performing simple authentication:\n")){
			return false;
		}

		// got back a valid XML response (hopefully)
		$xmlResponse = $this->crowd_xml_logerror($rc,"Error returned in Crowd XML response");
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
		$rc = $this->curlPost("/user/group/nested",array("username" => $username, "groupname" => $groupname));

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
		$rc = $this->curlPost('/user', array('username' => $username));

		if (!$this->curl_logerror($rc,"Error while retrieving user info for username '${username}'")){
			return null;
		}

		// got back a valid XML response (hopefully)
		$xmlResponse = $this->crowd_xml_logerror($rc,"Error returned in Crowd XML response");
		if($xmlResponse) {
			if($xmlResponse[0]->getName() == "user") {

				// break out from the XML
				$firstname = $xmlResponse->{first-name};
				$lastname = $xmlResponse->{last-name};
				$email = $xmlResponse->{email};
				$display_name = $xmlResponse->{display-name};

				// seed the array to be used for user creation
				$userData = array(
					'user_login'    => $username,
					'user_nicename' => strip_tags("${firstname} ${lastname}"),
					'user_email'    => $email,
					'display_name'  => strip_tags("${firstname} ${lastname}"),
					'first_name'    => $firstname,
					'last_name'     => $lastname
				);

				return $userData;
			} else {
				error_log("Got unexpected Crowd XML response to auth query:\n" . $rc['response']);
			}
		}
	}

	private function generateSimpleAuthXML($password) {
		$document = new DOMDocument("1.0","UTF-8");
		$passwordNode = $document->appendChild($document->createElement("password"));
		$value = $passwordNode->appendChild($document->createElement("value"));
		$cdata = $document->createCDATASection($password);
		$value->appendChild($cdata);

		return $document->saveXML();
	}

}
?>
