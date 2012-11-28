<?php
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

        if($crowd->authenticateUser($username,$password)) {
            $messages[] = "Authentication of '${username}' sucessful.";
        } else {
            $messages[] = "Authentication of '${username}' failed (check log).";
        }

        $userinfo = $crowd->getUserInfo($username);

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
