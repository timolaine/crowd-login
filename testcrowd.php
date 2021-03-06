<?php

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

    header("Content-Type: text/plain");

    require('Crowd-REST.php');

    $fields = array(
        'crowd_url' => false,
        'app_name' => false,
        'app_credential' => true,
        'username' => false,
        'password' => true
        );

    if($_POST && array_key_exists('crowd_url', $_POST)) {
        $username = $_POST['username'];
        $password = $_POST['password'];

		  $_POST['verify_ssl_peer'] = false;
        $crowd = new CrowdREST($_POST);

        $messages = array();
	$auth_response = $crowd->authenticateUser($username,$password);
	$messages[] = "Got auth response of '${auth_response}'";
        if (!empty($auth_response)) {
            $messages[] = "Authentication of '${auth_response}' sucessful.";
        } else {
            $messages[] = "Authentication of '${username}' failed (check log).";
        }

        $userinfo = $crowd->getUserInfo($auth_response);

        if($userinfo) {
            $messages[] = "Got user info for '${username}'";
            $msg = "<table><tr><th>field</th><th>value</th></tr>\n";
            foreach($userinfo as $field => $value) {
                $msg = "${msg}<tr><td>${field}</td><td>${value}</td></tr>\n";
            }
            $msg = "$msg</table>\n";
            $messages[] = $msg;
        }
    }
    header("Content-Type: text/html",true);
?>
<html>
<head>
    <title>Crowd REST API Client Test Page</title>
</head>
<body>
<div style='float: left; padding: 30px;'>
    <form name='testcrwod' action='<?php echo $_SERVER['PHP_SELF'];?>' method='post'>
    <table>
<?php
    foreach($fields as $field => $password) {
        $type = $password ? "password" : "text";
        $value = isset($_POST[$field]) ? $_POST[$field] : "";
        echo "<tr><td style='text-align: right'>${field}</td><td><input type='$type' name='${field}' value='${value}'/>\n";
	}
?>
</table>
	 <input type='submit'/>
    </form>
</div>
<?php
    if (isset($messages) && count($messages) > 0) {
        echo "<div style='float: left; padding: 30px;'>";
        foreach($messages as $message) {
            echo "<div>${message}</div>";
        }
        echo "</div>";
    }
?>
</body>
</html>
