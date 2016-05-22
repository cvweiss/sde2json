<?php

namespace cvweiss\sde;

require __DIR__ . '/../vendor/autoload.php';

use Project\Base\Config;

$tables = MyDb::query("show tables", [], 0, false);
$exportDir = Config::get('projectDir') . '/public/';
$t = [];

foreach ($tables as $table) {
	$table = array_pop($table);
	//if ($table == "mapCelestialStatistics") continue;
	echo "Exporting $table ... \n";

	$rows = MyDb::query("select * from $table");
	if (putContents($rows, $exportDir . $table . '.json') == false) continue;

	$t[] = ['name' => $table, 'href' => '/' . $table . 'json'];
}

putContents($t, $exportDir . 'tables.json');

echo "Complete, now go clear your Cloudflare cache\n";

function putContents($array, $file) {
	$array = utf8ize($array);
	$json = json_encode($array, JSON_UNESCAPED_UNICODE);
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
	return false;
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
