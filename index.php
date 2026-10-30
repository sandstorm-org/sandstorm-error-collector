<?php
if (strpos(isset($_SERVER['HTTP_X_SANDSTORM_PERMISSIONS']) ? $_SERVER['HTTP_X_SANDSTORM_PERMISSIONS'] : '', 'view') === false) {
    http_response_code(403);
    die('Unauthorized');
} else {
    function secEscape($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $db = new SQLite3('/var/errordb.sqlite');
    $db->exec('CREATE TABLE IF NOT EXISTS reports (id INTEGER PRIMARY KEY, date TEXT, code TEXT, agent TEXT)');

    // Match the timestamp format and PHP timezone used by v1.php.
    $now = new DateTimeImmutable('now');
    $cutoff = $now->modify('-365 days')->format('Y/m/d H:i:s');
    $end = $now->format('Y/m/d H:i:s');
    // Exclude NULL, empty, and ASCII-whitespace-only codes before LIMIT.
    $nonblank = "code IS NOT NULL AND trim(code, char(9, 10, 11, 12, 13, 32)) <> ''";
    $statement = $db->prepare("SELECT code, COUNT(*) AS total FROM reports
        WHERE $nonblank AND date >= :cutoff AND date <= :end
        GROUP BY code ORDER BY total DESC, code ASC");
    $statement->bindValue(':cutoff', $cutoff, SQLITE3_TEXT);
    $statement->bindValue(':end', $end, SQLITE3_TEXT);
    $result = $statement->execute();
    $counts = array();
    $total = 0;
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $counts[] = $row;
        $total += (int) $row['total'];
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Sandstorm Error Collector</title></head><body>';
    echo '<h1>Sandstorm Error Collector</h1>';
    echo '<section style="border:1px solid black;padding:20px;margin-bottom:20px">';
    echo '<h2>Error codes received over the last year:</h2>';
    echo '<p>' . secEscape($cutoff) . ' through ' . secEscape($end) . '</p>';
    if ($total === 0) {
        echo '<p>No nonblank error codes were received in this period.</p>';
    } else {
        echo '<p>Total reports: ' . $total . '</p>';
        echo '<div style="display:flex;flex-wrap:wrap;gap:24px;align-items:center">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" role="img" aria-labelledby="chart-title" style="width:100%;max-width:400px;height:auto">';
        echo '<title id="chart-title">Error code distribution over the last year; counts are listed in the adjacent table.</title>';
        $angle = -M_PI / 2;
        $colors = array();
        foreach ($counts as $i => $row) {
            $color = 'hsl(' . (($i * 137) % 360) . ',65%,45%)';
            $colors[] = $color;
            $count = (int) $row['total'];
            $next = $angle + 2 * M_PI * $count / $total;
            $title = secEscape($row['code'] . ': ' . $count . ' (' . number_format(100 * $count / $total, 1) . '%)');
            if (count($counts) === 1) {
                echo '<circle cx="200" cy="200" r="180" fill="' . $color . '"><title>' . $title . '</title></circle>';
            } else {
                $x1 = 200 + 180 * cos($angle);
                $y1 = 200 + 180 * sin($angle);
                $x2 = 200 + 180 * cos($next);
                $y2 = 200 + 180 * sin($next);
                $large = ($next - $angle > M_PI) ? 1 : 0;
                // Force decimal points regardless of server locale.
                $path = sprintf('M 200 200 L %.6F %.6F A 180 180 0 %d 1 %.6F %.6F Z', $x1, $y1, $large, $x2, $y2);
                echo '<path d="' . $path . '" fill="' . $color . '" stroke="white" stroke-width="1"><title>' . $title . '</title></path>';
            }
            $angle = $next;
        }
        echo '</svg><table cellpadding="6"><caption>Reports by error code</caption>';
        echo '<thead><tr><th scope="col">Error code</th><th scope="col">Count</th><th scope="col">Share</th></tr></thead><tbody>';
        foreach ($counts as $i => $row) {
            echo '<tr><th scope="row" style="text-align:left"><span aria-hidden="true" style="display:inline-block;width:12px;height:12px;background:' . $colors[$i] . '"></span> ' . secEscape($row['code']) . '</th>';
            echo '<td>' . (int) $row['total'] . '</td><td>' . number_format(100 * $row['total'] / $total, 1) . '%</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section><div style="border:1px solid black;padding:20px"><h2>Last 10 errors reported:</h2>';
    $recenterrors = $db->query("SELECT date, code, agent FROM reports WHERE $nonblank ORDER BY date DESC, id DESC LIMIT 10");
    $hasRecent = false;
    while ($res = $recenterrors->fetchArray(SQLITE3_ASSOC)) {
        $hasRecent = true;
        echo secEscape($res['date']) . ' - ' . secEscape($res['code']) . ' - ' . secEscape($res['agent']) . '<br>';
    }
    if (!$hasRecent) {
        echo '<p>No nonblank error codes have been reported.</p>';
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