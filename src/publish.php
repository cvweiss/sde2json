<?php

namespace cvweiss\sde;

require __DIR__ . '/../vendor/autoload.php';

use Project\Base\Config;

$tables = MyDb::query("show tables", [], 0, false);
$exportDir = Config::get('projectDir') . '/public/';
$baseHref = Config::get('baseHref', '/');
$t = [];

foreach ($tables as $table) {
	$table = array_pop($table);
        if ($table == "mapCelestialStatistics") continue;
        if ($table == "mapDenormalize") continue;
        if ($table == "trnTranslations") continue;
	echo "Exporting $table ... \n";

	$rows = MyDb::query("select * from $table");
	if (putContents($rows, $exportDir . $table . '.json') == false) continue;

	$t[] = ['name' => $table, 'href' => $baseHref . $table . '.json'];
}

putContents($t, $exportDir . 'tables.json');

$now = date('Y/m/d H:i');
file_put_contents("$exportDir/index.html", "<html><body>A simple SDE conversion into json files. To see a list of converted tables, see <a href='/tables.json'>tables.json</a><br/>To access a table, visit table-name.json, for example, <a href='/invFlags.json'>invFlags.json</a><br/>Many thanks to FuzzySteve for the SDE conversion into MySQL<br/>Last Updated: $now<br/><a href='/installed.md5'>Current MD5</a><br/><br/><small><a href='https://github.com/cvweiss/sde2json/' target='_blank'>Github</a></body></html>");

echo "Complete, now go clear your Cloudflare cache\n";

function putContents($array, $file) {
	$array = utf8ize($array);
	$json = json_encode($array, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
	switch (json_last_error()) {
		case JSON_ERROR_NONE:
			file_put_contents($file, $json);
			return true;
			break;
		case JSON_ERROR_DEPTH:
			echo ' - Maximum stack depth exceeded';
			break;
		case JSON_ERROR_STATE_MISMATCH:
			echo ' - Underflow or the modes mismatch';
			break;
		case JSON_ERROR_CTRL_CHAR:
			echo ' - Unexpected control character found';
			break;
		case JSON_ERROR_SYNTAX:
			echo ' - Syntax error, malformed JSON';
			break;
		case JSON_ERROR_UTF8:
			echo ' - Malformed UTF-8 characters, possibly incorrectly encoded';
			break;
		default:
			echo ' - Unknown error';
			break;
	}
	die("\n");
}

function utf8ize($d) {
	if (is_array($d)) {
		foreach ($d as $k => $v) {
			$d[$k] = utf8ize($v);
		}
	} else if (is_string ($d)) {
		return utf8_encode($d);
	}
	return $d;
}
