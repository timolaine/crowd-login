<?


class CrowdREST {

	private $__CROWD_REST_API_PATH = "/rest/usermanagement/1";
	private $crowd_config;
	private $crowd_app_token;


	function curlPost($url, $resource, $attrs) {
		$crowd_endpoint = $crowd_config['service_endpoint'];
		$full_url = "${crowd_endpoint}${__CROWD_REST_API_PATH}${url}?" . http_build_query($attrs);
		$curl = curl_init($full_url);
		curl_setopt($curl, CURLOPT_USERPWD, '[' . $crowd_config['app_name'] . ']:[' . $crowd_config['app_credential'] . ']');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		return curl_exec($curl);
	}
}

?>