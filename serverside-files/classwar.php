<?php
// TO USE: UNCOMMENT THE FOLLOWING LINE (remove the "//" at the BEGINNING of the line) and change "CHANGETHISTOYOURNATION" to your nation name.
//$useragent = "CLASSWAR RMB Archiver used by CHANGETHISTOYOURNATION";

if (empty($_GET['region'])) {
	if (empty(getopt('r:'))) {
		echo "You need to supply a region in the URL or command line! for example: \n\thttps://yourwebsite.url/classwar.php?region=the_internationale\n -- or, by command line:\n\tphp -e classwar.php -r \"the internationale\"\n";
		exit;
	} else {
		$cmd_line = true;
		$region = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(" ", "_", trim(getopt('r:')['r']))));
	}
} else {
	$region = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(" ", "_", trim($_GET['region']))));
}

if (empty($region)) {
	echo "You need to supply a region in the URL or command line! for example: \n\thttps://yourwebsite.url/classwar.php?region=the_internationale\n-- or, by command line:\n\tphp -e classwar.php -r \"the internationale\"\n";
	exit;
}

if (!$useragent || $useragent == "CLASSWAR RMB Archiver used by CHANGETHISTOYOURNATION") {
	//no seriously, change the useragent
	echo "You need to change the useragent (found at the top of classwar.php) to include YOUR nation name! Stopping execution.\n";
	exit;
}

//get the total number of RMB pages for the given region
$rmb_url = "https://www.nationstates.net/template-overall=none/page=display_region_rmb/region=$region/?script=classwarRMBarchiver";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $rmb_url);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
$result = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_status == 429) {
	echo "API Rate Limit reached when requesting $rmb_url at ".date("g:i:s")."\n";
	return;
} elseif ($http_status <> 200) {
	echo "NationStates gave HTTP error code $http_status when requesting $rmb_url at ".date("g:i:s")."\n";
	return;
}

$dom = new DOMDocument;
libxml_use_internal_errors(true); //hide warnings because NationStates HTML gives a bunch of them
$dom->loadHTML($result);
$classname = "pagpage-current";
$rmb = new DomXPath($dom);

$rmb_lastpage = intval(str_replace(',', '', $rmb->query("//*[contains(@class, '$classname')]")->item(0)->nodeValue));
$max_rmbposts = $rmb_lastpage * 25;
$max_rmbapi = intval(floor($max_rmbposts / 100) * 100);
$api_calltime = ($max_rmbapi / 100 / 50) * 35;

if ($api_calltime > ini_get('max_execution_time') && !$cmd_line) {
	echo "This region's RMB is too large to archive this way. You must use the command line (example: php -e classwar.php -r \"the internationale\") to archive this RMB.";
	exit;
}

$offsets = range($max_rmbapi, 0, 100);
$sql_filename = "classwar_$region.sql";

function getRMBData($offset = 0) {
	global $useragent, $sql_filename, $region;
	
	if (file_exists($sql_filename)) {
		file_put_contents($sql_filename, "INSERT INTO `rmb_archive_$region` (`pid`, `timestamp`, `nation`, `message`, `status`, `suppressor`, `likes`, `likers`, `embassy`) VALUES \n", FILE_APPEND);
	} else {
		file_put_contents($sql_filename, "DROP TABLE IF EXISTS `rmb_archive_$region`;\n\nSET NAMES utf8mb4;\n\nCREATE TABLE `rmb_archive_$region` (\n\t`pid` INT UNSIGNED NOT NULL DEFAULT '0',\n\t`timestamp` INT(10) UNSIGNED NOT NULL DEFAULT '0',\n\t`nation` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',\n\t`message` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,\n\t`status` ENUM('0','1','2','9','10') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',\n\t`suppressor` VARCHAR(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,\n\t`likes` INT(3) UNSIGNED DEFAULT NULL,\n\t`likers` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,\n\t`embassy` VARCHAR(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL, INDEX `nation` (`nation`), UNIQUE `pid` (`pid`), FULLTEXT `message` (`message`)\n) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\nINSERT INTO `rmb_archive_$region` (`pid`, `timestamp`, `nation`, `message`, `status`, `suppressor`, `likes`, `likers`, `embassy`) VALUES \n");
	}
	
	$API_data = "https://www.nationstates.net/cgi-bin/api.cgi?region=$region&q=messages;limit=100;offset=$offset";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $API_data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
	$rmb_data = curl_exec($ch);
	$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($http_status == 429) {
		echo "API Rate Limit reached when requesting $API_data at ".date("g:i:s")."\n";
		exit;
	} elseif ($http_status <> 200) {
		echo "NationStates gave HTTP error code $http_status when requesting $API_data at ".date("g:i:s")."\n";
		exit;
	}
	
	$xml = simplexml_load_string($rmb_data, 'SimpleXMLElement', LIBXML_NOCDATA);
	$posts = $xml->MESSAGES;
	$last = $xml->xpath("/REGION/MESSAGES/POST[last()]");
	
	foreach ($posts->POST as $post) {
		$pid = intval($post->attributes()->id);
		$timestamp = $post->TIMESTAMP;
		$nation = $post->NATION;
		$message = str_replace(array("\\", "\0", "'", "\n", "\r", '"', "\x1a"), array("\\\\", "\\0", "\'", "\\n", "\\r", '\\"', '\\Z'), $post->MESSAGE);
		$status = $post->STATUS;
		if ($nation == "NationStates Moderators") {
			$status = 10;
		}
		$likes = $post->LIKES;
		if($post->LIKERS) {
			$likers = "'".$post->LIKERS."'";
		} else {
			$likes = "NULL";
			$likers = "NULL";
		}
		if($post->SUPPRESSOR) {
			$suppressor = "'".$post->SUPPRESSOR."'";
		} else {
			$suppressor = "NULL";
		}
		if($post->EMBASSY) {
			$embassy = "'".$post->EMBASSY."'";
		} else {
			$embassy = "NULL";
		}
		
		//if this is the last post in the list, terminate the SQL statement
		if ($post->attributes()->id != $last[0]->attributes()->id) {
			$valuestring = "($pid, $timestamp, '$nation', '$message', '$status', $suppressor, $likes, $likers, $embassy),\n";
		} else {
			$valuestring = "($pid, $timestamp, '$nation', '$message', '$status', $suppressor, $likes, $likers, $embassy);\n";
		}
		file_put_contents($sql_filename, $valuestring, FILE_APPEND);
	}
}

//how many API calls do we need to make? if more than 50, wait to avoid the rate limit
if (count($offsets) > 50) {
	$offsets_chunked = array_chunk($offsets, 50);
	$offset_num = count($offsets_chunked);
	foreach ($offsets_chunked as $chunk) {
		if ($chunk === end($offsets_chunked)) {
			foreach ($chunk as $offset) {
				getRMBData($offset);
			}
			if ($cmd_line) { echo "."; }
			echo "\nFinished! RMB data saved to $sql_filename.\n";
		} else {
			$time_start = time();
			foreach ($chunk as $offset) {
				getRMBData($offset);
			}
			if ($cmd_line) { echo "."; }
			$time_end = time();
			$time_delta = $time_end - $time_start;
			$time_remaining = 35 - $time_delta; // ugly af
			if ($time_remaining < 0) { $time_remaining = 35; }
			sleep($time_remaining);
		}
	}
} else {
	foreach ($offsets as $offset) {
		getRMBData($offset);
	}
	echo "\nFinished! RMB data saved to $sql_filename.\n";
}
file_put_contents($sql_filename, "\nALTER TABLE `rmb_archive_$region` ORDER BY `pid`;\nALTER TABLE `rmb_archive_$region` ADD `dbid` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;", FILE_APPEND);
?>