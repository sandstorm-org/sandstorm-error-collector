<?php if (strpos($_SERVER['HTTP_X_SANDSTORM_PERMISSIONS'], "view") === false)
{
	die("Unauthorized");
} else {

echo "<html><body><h1>Sandstorm Error Collector</h1><p>

<div style=\"border:1px solid black; padding:20px;\"><h2>Last 10 errors reported:</h2>";

$db = new SQLite3('/var/errordb.sqlite');
$db->exec("CREATE TABLE IF NOT EXISTS reports (id INTEGER PRIMARY KEY, date TEXT, code TEXT, agent TEXT)");
$recenterrors = $db->query("SELECT date, code, agent FROM reports ORDER BY date DESC LIMIT 10");
$r = 0;
while ($res = $recenterrors->fetchArray(SQLITE3_ASSOC)) {
	$ord = $r + 1;
	echo $res['date'] . " - " . $res['code'] . " - " . $res['agent'] . "<br>";
	$r++;
}

echo "</div>
<p>
<div style=\"border:1px solid black; padding:20px;\"><h2>Setup/test error collection:</h2>
<script>
  function requestIframeURL() {
    var templateToken = \"\$API_TOKEN\";
	var templateHost = \"https://\$API_HOST\"
    window.parent.postMessage({renderTemplate: {
      rpcId: \"0\",
      template: templateToken,
      forSharing: true,
      roleAssignment: {roleId: 1},
      clipboardButton: 'left',
    }}, \"*\");
	window.parent.postMessage({renderTemplate: {
      rpcId: \"1\",
      template: templateHost,
      forSharing: true,
      roleAssignment: {roleId: 1},
      clipboardButton: 'left',
    }}, \"*\");
  }
  document.addEventListener(\"DOMContentLoaded\", requestIframeURL);
  
  var copyIframeURLToElement = function(event) {
    if (event.data.rpcId === \"0\") {
      if (event.data.error) {
        console.log(\"ERROR: \" + event.data.error);
      } else {
        var el = document.getElementById(\"offer-token\");
        el.setAttribute(\"src\", event.data.uri);
      }
    }
	if (event.data.rpcId === \"1\") {
      if (event.data.error) {
        console.log(\"ERROR: \" + event.data.error);
      } else {
        var el = document.getElementById(\"offer-host\");
        el.setAttribute(\"src\", event.data.uri);
      }
    }
  };
  window.addEventListener(\"message\", copyIframeURLToElement);
</script>

<p>API_ENDPOINT is:<p>
<iframe style=\"width: 100%; height: 30px; margin: 0; border: 0;\" id=\"offer-host\"></iframe>
<p>BEARER_TOKEN is:<p>
<iframe style=\"width: 100%; height: 30px; margin: 0; border: 0;\" id=\"offer-token\"></iframe><p>

<p>Test command should look like this:</p>
<div style=\"text-align: left; width: 100%; height: 30px; margin: 0; border: 0;\"><pre id=\"text\">curl --silent --max-time 20 --data \"{\\\"error_code\\\":\\\"E_TEST\\\",\\\"user-agent\\\":\\\"TestAgent/0.0.1\\\"}\" -H \"Authorization: Bearer BEARER_TOKEN\" -X POST --output \"/dev/null\" -w '%{http_code}' API_ENDPOINT</pre></div></div>

</body></html>";
}