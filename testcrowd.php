<?php
    require('CrowdREST.php');

    $fields = array(
        'crowd_endpoint' => false,
        'app_name' => false,
        'app_credential' => true,
        'username' => false,
        'password' => true
        );

    if($_POST && array_key_exists('crowd_endpoint', $_POST)) {
        $username = $_POST['username'];
        $password = $_POST['password'];

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
            for($field => $value in $userinfo) {
                $msg = "${msg}<tr><td>${field}</td><td>${value}</td></tr>\n";
            }
            $msg = "$msg</table>\n";
            $messages[] = $msg;
        }
    }
?>
<html>
<head>
    <title>Crowd REST API Client Test Page</title>
</head>
<body>
<?php
    if (count($messages) > 0) {
        echo "<div style='width: 50%; float: right; padding: 30px;'>";
        for($message in $messages) {
            echo "<div>${message}</div>";
        }
        echo "</div>";
    }
?>
<div style='float: left; width: 50%; padding: 30px;'>
    <form name='testcrwod' action='<? echo $_SERVER['PHP_SELF'];?>'>
    <table>
<?php
    for($field => $password in $fields) {
        $type = $password ? "password" : "text";
        $value = isset($_POST[$field]) ? $_POST[$field] : "";
        echo "<tr><td style='text-align: right'>${field}</td><td><input type='$type' name='${field}'>${value}</input>\n";
    ?>
</table>
    </form>
</div>
</body>
</html>