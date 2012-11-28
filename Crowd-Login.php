<?php
/*
Plugin Name: Crowd Login
Plugin URI: 
Description:  Authenticates Wordpress usernames against Atlassian Crowd.
Version: 0.1
Author: Andrew Teixeira
Author URI: 
*/

/**
 * Portions:
 *
 * Copyright (C) 2008 Infinite Campus, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

//  Portions:
//
//   Copyright 2012 Palantir Technologies
//      
//   Licensed under the Apache License, Version 2.0 (the "License");
//   you may not use this file except in compliance with the License.
//   You may obtain a copy of the License at
//      
//       http://www.apache.org/licenses/LICENSE-2.0
//      
//   Unless required by applicable law or agreed to in writing, software
//   distributed under the License is distributed on an "AS IS" BASIS,
//   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//   See the License for the specific language governing permissions and
//   limitations under the License.

// see https://github.com/arigesher/crowd-login for full revision history 

require_once( WP_PLUGIN_DIR."/crowd-login/Crowd.php");
require_once( WP_PLUGIN_DIR."/crowd-login/Crowd-REST.php");
require_once( ABSPATH . WPINC . '/registration.php');

//Admin
function crowd_menu() {
	include 'Crowd-Login-Admin.php';
}

function crowd_admin_actions() {
	add_options_page("Crowd Login", "Crowd Login", 10, "crowd-login", "crowd_menu");
}

function crowd_activation_hook() {
	//Store settings
	add_option('crowd_url', 'https://crowd.mydomain.local:8443/crowd');
	add_option('crowd_app_name', 'crowdlogin');
	add_option('crowd_app_password', 'crowdpassword');
	add_option('crowd_domain_controllers', 'crowd01.mydomain.local');
	add_option('crowd_security_mode', 'security_low');
	add_option('crowd_login_mode', 'mode_normal');
	add_option('crowd_api_mode', 'rest');
	add_option('crowd_account_type', 'Contributor');
}

// Reset Crowd instance and principal token
$crowd = NULL;
$princ_token = NULL;

//Add the menu
add_action('admin_menu', 'crowd_admin_actions');

//Add filter
add_filter('authenticate', 'crowd_authenticate', 1, 3);

//Authenticate function
function crowd_authenticate($user, $username, $password) {

	// some previous authentication already suceeded, defer
	if ( is_a($user, 'WP_User') ) { return $user; }

	// set up our environment
	$crowd_url = get_option('crowd_url');
	$crowd_app_name = get_option('crowd_app_name');
	$crowd_app_password = get_option('crowd_app_password');
	$crowd_api_mode = get_option('crowd_api_mode');
	$crowd_config = array(
		'crowd_url' => $crowd_url,
		'service_url' => $crowd_url . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'SecurityServer?wsdl',
		'app_name' => $crowd_app_name,
		'app_credential' => $crowd_app_password
	);


	$rc = null;
	if($crowd_api_mode == "rest") {
		// this mode can handle SSO tokens and, thusly, empty usernames and passwords
		$rc = crowd_authenticate_rest($user, $username, $password, $crowd_config);
	} elseif($crowd_api_mode == "soap") {
		// make sure we have what we need to authenticate
		if ( empty($username) || empty($password) ) {
			$error = new WP_Error();

			if ( empty($username) ) {
				$error->add('empty_username', __('<strong>ERROR</strong>: The username field is empty.'));
			}

			if ( empty($password) ) {
				$error->add('empty_password', __('<strong>ERROR</strong>: The password field is empty.'));
			}
			return $error;
		}

		$rc = crowd_authenticate_soap($user, $username, $password, $crowd_config);
	} else {
		return new WP_Error($code = null, $message = "Invalid Crowd Authentication API Mode: ${crowd_api_mode} (valid values are 'rest' and 'soap')");
	}

	if(is_a($rc,"WP_User")) {
		// authentication successful
		return $rc;
	}

	// failed, should we let it continue to lower priority authenticate methods?
	if(get_option('crowd_security_mode') == 'security_high') {
		remove_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
	}


}

function crowd_authenticate_rest($user, $username, $password, $crowd_config) {
	$crowd = new CrowdREST($crowd_config);
	$authenticated_username = $crowd->authenticateUser($username, $password);
	if($authenticated_username == null) {
		return new WP_Error($code = null, $message = "username or password incorrect");
	} else {
		$user = get_userdatabylogin($authenticated_username);

		if ( !$user || (strtolower($user->user_login) != strtolower($username)) ) {
			//No user, can we create?
			switch(get_option('crowd_login_mode')) {
				case 'mode_create_all':

					// create the new user using crowd data
					$new_user_id = crowd_create_rest_wp_user($authenticated_username);

					if(!is_a($new_user_id, 'WP_Error')) {
						//It worked
						return new WP_User($new_user_id);
					} else {
						do_action( 'wp_login_failed', $authenticated_username );				
						return new WP_Error('invalid_username', __('<strong>Crowd Login Error</strong>: Crowd credentials are correct and user creation is allowed but an error occurred creating the user in Wordpress. Actual WordPress error: '.$new_user_id->get_error_message()));
					}
					break;
					
				case 'mode_create_group':
					if(crowd_is_in_rest_group($authenticated_username)) {

					// create the new user using crowd data
					$new_user_id = crowd_create_rest_wp_user($authenticated_username);

					if(!is_a($new_user_id, 'WP_Error')) {
							//It worked
							return new WP_User($new_user_id);
						} else {
							do_action( 'wp_login_failed', $authenticated_username );				
							return new WP_Error('invalid_username', __('<strong>Crowd Login Error</strong>: Crowd credentials are correct and user creation is allowed and you are in the correct group but an error occurred creating the user in Wordpress. Actual WordPress error: '.$new_user_id->get_error_message()));
						}
					} else {
						do_action( 'wp_login_failed', $authenticated_username );				
						return new WP_Error('invalid_username', __('<strong>Crowd Login Error</strong>: Crowd Login credentials are correct and user creation is allowed but Crowd user was not in the correct group.'));
					}
					break;
					
				default:
					do_action( 'wp_login_failed', $authenticated_username );				
					return new WP_Error('invalid_username', __('<strong>Crowd Login Error</strong>: Crowd Login mode does not permit account creation.'));
			}
		} else {
			//Wordpress user exists, should we check group membership?
			if(get_option('crowd_login_mode') == 'mode_create_group') {
				if(crowd_is_in_rest_group($authenticated_username)) {
					return new WP_User($user->ID);
				} else {
					do_action( 'wp_login_failed', $authenticated_username );				
					return new WP_Error('invalid_username', __('<strong>Crowd Login Error</strong>: Crowd credentials were correct but user is not in the correct group.'));
				}
			} else {
				//Otherwise, we're ready to return the user
				return new WP_User($user->ID);
			}
		}
	}
}



function crowd_authenticate_soap($user, $username, $password, $crowd_config) {
	global $crowd;

	try {
		$crowd = new Crowd($crowd_config);
	} catch (CrowdConnectionException $e) {
		$error = new WP_Error();
		$error->add('crowd_conn_error', $e->getMessage());
		return $error;
	}

	try {
		$app_token = $crowd->authenticateApplication();
	} catch (CrowdLoginException $e) {
		$crowd = NULL;
		echo $e->getMessage();
	}


	$auth_result = crowd_can_authenticate($username, $password);
	if($auth_result == true && !is_a($auth_result, 'WP_Error')) {
		$user = get_userdatabylogin($username);

		if ( !$user || (strtolower($user->user_login) != strtolower($username)) ) {
			//No user, can we create?
			switch(get_option('crowd_login_mode')) {
				case 'mode_create_all':
					$new_user_id = crowd_create_wp_user($username);
					if(!is_a($new_user_id, 'WP_Error')) {
						//It worked
						return new WP_User($new_user_id);
					} else {
						do_action( 'wp_login_failed', $username );				
						return new WP_Error('invalid_username', __('<strong>Crowd Login Error</strong>: Crowd credentials are correct and user creation is allowed but an error occurred creating the user in Wordpress. Actual WordPress error: '.$new_user_id->get_error_message()));
					}
					break;
					
				case 'mode_create_group':
					if(crowd_is_in_group($username)) {
						$new_user_id = crowd_create_wp_user($username);
						if(!is_a($new_user_id, 'WP_Error')) {
							//It worked
							return new WP_User($new_user_id);
						} else {
							do_action( 'wp_login_failed', $username );				
							return new WP_Error('invalid_username', __('<strong>Crowd Login Error</strong>: Crowd credentials are correct and user creation is allowed and you are in the correct group but an error occurred creating the user in Wordpress. Actual WordPress error: '.$new_user_id->get_error_message()));
						}
					} else {
						do_action( 'wp_login_failed', $username );				
						return new WP_Error('invalid_username', __('<strong>Crowd Login Error</strong>: Crowd Login credentials are correct and user creation is allowed but Crowd user was not in the correct group.'));
					}
					break;
					
				default:
					do_action( 'wp_login_failed', $username );				
					return new WP_Error('invalid_username', __('<strong>Crowd Login Error</strong>: Crowd Login mode does not permit account creation.'));
			}
		} else {
			//Wordpress user exists, should we check group membership?
			if(get_option('crowd_login_mode') == 'mode_create_group') {
				if(crowd_is_in_group($username)) {
					return new WP_User($user->ID);
				} else {
					do_action( 'wp_login_failed', $username );				
					return new WP_Error('invalid_username', __('<strong>Crowd Login Error</strong>: Crowd credentials were correct but user is not in the correct group.'));
				}
			} else {
				//Otherwise, we're ready to return the user
				return new WP_User($user->ID);
			}
		}
	} else {
		if(is_a($auth_result, 'WP_Error')) {
			return $auth_result;
		} else {
			return new WP_Error('invalid_username', __('<strong>Crowd Login Error</strong>: Crowd Login could not authenticate your credentials. The security settings do not permit trying the Wordpress user database as a fallback.'));
		}
	}
}

function crowd_can_authenticate($username, $password) {
	global $crowd, $princ_token;

	// If we can't get a Crowd instance, fail
	if ($crowd == NULL) {
	  return new WP_Error('crowd_error', __('<strong>Crowd Login Error</strong>: No Crowd Instance'));
	}

	$princ_token = $crowd->authenticatePrincipal($username, $password, $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);

	if ($princ_token == NULL) {
	  return new WP_Error('no_crowd_princ_error', __('<strong>Crowd Login Error</strong>: Could not retrieve principal.'));
	}

	return $princ_token;
}

function crowd_is_in_group($username) {
	global $crowd;
	$result = false;

	// If we can't get a Crowd instance, fail
	if ($crowd == NULL) {
		return $result;
	}

	$crowd_group = get_option('crowd_group');

	$groups = $crowd->findGroupMemberships($username);
	if ($groups == NULL) {
		return $result;
	}

	$result = in_array($crowd_group, $groups);	

	return $result;
}

function crowd_is_in_rest_group($username,$rest) {
	return $rest->userIsInGroup($username,get_option('crowd_group'));
}

function crowd_create_wp_user($username) {
	global $crowd, $princ_token;
	$result = 0;

	// If we can't get a Crowd instance, fail
	if ($crowd == NULL) {
		return $result;
	}

	if ($princ_token == NULL) {
		return $result;
	}

	$person = getUserInfo($princ_token);

	//Create WP account
	$userData = array(
		'user_pass'     => microtime(),
		'user_login'    => $username,
		'user_nicename' => sanitize_title($person['givenName'] .' '.$person['sn']),
		'user_email'    => $person['mail'],
		'display_name'  => $person['givenName'] .' '. $person['sn'],
		'first_name'    => $person['givenName'],
		'last_name'     => $person['sn'],
		'role'		=> strtolower(get_option('crowd_account_type'))
	);
			
	$result = wp_insert_user($userData); 

	return $result;
}

function crowd_create_rest_wp_user($authenticated_username$authenticated_username, $rest) {;
	// create the new user using crowd data
	$user_data = $rest->getUserInfo($authenticated_username);
	$user_data['role']= strtolower(get_option('crowd_account_type'));
	$new_user_id = wp_insert_user($user_data);
	return $new_user_id;
}

function getUserInfo($principal_token) {
	global $crowd;

	$person == NULL;

	$response = $crowd->findPrincipalByToken($principal_token);
	if ($response) {
		// Convert response into person.
		for ($i=0; $i < count($response->attributes->SOAPAttribute); $i++) {
			$person[ $response->attributes->SOAPAttribute[$i]->name ] = $response->attributes->SOAPAttribute[$i]->values->string;
		}
	}

	return $person;
}

//Temporary fix for e-mail exists bug
if ( !function_exists('get_user_by_email') ) :
/**
 * Retrieve user info by email.
 *
 * @since 2.5
 *
 * @param string $email User's email address
 * @return bool|object False on failure, User DB row object
 */
function get_user_by_email($email) {
	if(strlen($email) == 0 || empty($email) || $email == '' || strpos($email, '@') == false) {
		return false;
	} else {
		return get_user_by('email', $email);
	}
}
endif;

register_activation_hook( __FILE__, 'crowd_activation_hook' );
?>
