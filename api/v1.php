<?php
$db = new SQLite3('/var/errordb.sqlite');
$db->exec("CREATE TABLE IF NOT EXISTS reports (id INTEGER PRIMARY KEY, date TEXT, code TEXT, agent TEXT)");

$_POST = json_decode(file_get_contents('php://input'), true);

$error_code = $_POST['error_code'];
$user_agent = $_POST['user-agent'];
$date = date("Y/m/d H:i:s");

echo "error code: " . $error_code; "<br>user agent: " . $user_agent;

$reportquery = $db->prepare('INSERT INTO reports (date, code, agent) VALUES (:date,:code,:agent)');
$reportquery->bindValue(':date', $date, SQLITE3_TEXT);
$reportquery->bindValue(':code', $error_code, SQLITE3_TEXT);
$reportquery->bindValue(':agent', $user_agent, SQLITE3_TEXT);
$reportresult = $reportquery->execute();

http_response_code(200);
?>