<?php
include_once('classwar-config.php');

$conn = mysqli_connect($cw_sql_sv, $cw_sql_un, $cw_sql_pw, $cw_sql_db);
if (!$conn) {
	return;
}
mysqli_set_charset($conn, 'utf8mb4');

$select = "* FROM `$cw_sql_table`";
$where = 1;
$limit = 'LIMIT 0,25';

if (isset($_GET['q']) && $_GET['q'] != 0) {
	//quoting a single post
	$select = "* FROM `$cw_sql_table`";
	$where = "`pid`=".intval($_GET['q']);
} elseif (isset($_GET['postid']) && $_GET['postid'] != 0) {
	//displaying a single post
	$where = "`pid`=".intval($_GET['postid']);
} else {
	//displaying a full page
	if (isset($_GET['search']['value'])) {
		$search = preg_replace("/[^A-Za-z0-9_ -]/", '', $_GET['search']['value']);
		$where = "`message` LIKE '%$search%'";
	}
}
if (isset($_GET['start']) && $_GET['length'] != -1) {
	$limit = "LIMIT ".intval($_GET['start']).", ".intval($_GET['length']);
}

include_once("$cw_file_loc/nbbc-nscode.php");
include_once("$cw_file_loc/nbbc-quote.php");

function nat_lang_join(array $list, $conjunction = 'and') {
	$last = array_pop($list);
	if ($list) {
		return implode(', ', $list) . (count($list) > 2 ? "," : "") . ' ' . $conjunction . ' ' . $last;
	}
	return $last;
}

$rmbposts = $conn->query("SELECT $select
WHERE $where
$limit;");

if ( isset($_GET['q'])) {
	//quoting a post
	if (empty($rmbposts)) {
		echo "No post found with that ID!";
		return;
		//no post found with given postID
	} else {
		$bbquote = new BBQuote;
		$bbquote->SetAllowAmpersand(true);
		$bbquote->SetIgnoreNewlines(true);
		foreach($rmbposts as $post){
			echo "[quote={$post['nation']};{$post['pid']}]".$bbquote->qParse($post['message'])."[/quote]\n";
			echo "(This historical post is available from the [b]ClassWar-powered RMB Archive[/b] at $cw_web_link/?postid={$post['pid']})";
		}
		return;
	}
} else {
	//displaying post(s)
	$postcount = 0;
	$bbcode = new BBCode;
	$bbcode->SetDetectURLs(true);
	$bbcode->SetAllowAmpersand(true);
	
	$rows = array();
	foreach($rmbposts as $post){
		$post['nname'] = ucwords(str_replace('_', ' ',$post['nation']));
		
		$postHTML = "<div id=\"p{$post['pid']}\" class=\"rmbrow ".(++$postcount%2 ? "odd" : "even")." post-{$post['pid']}\">";
		if ($_GET['postid'] != 0) {
			$page = (floor(($post['dbid']-1)/25) + 1);
			$postHTML = "<p class=\"rightbox smalltext\"><a href=\"$cw_web_link/?page=$page#p{$post['pid']}\">Context</a></p>" . $postHTML;
		}
		switch ($post['status']){
			case 2: //self-deleted
				$postHTML .= "<p class=\"rmbsuppressed\"><a class=\"hiddenpermalink\" href=\"$cw_web_link/?postid={$post['pid']}\">Post</a> self-deleted by <a href=\"https://nationstates.net/nation={$post['nation']}\" class=\"nlink\"><span>{$post['nname']}</span></a>.</p>";
				break;
			
			case 9: //moderator-suppressed
				$postHTML .= "<p class=\"rmbsuppressed\"><a class=\"hiddenpermalink\" href=\"$cw_web_link/?postid={$post['pid']}\">Post</a> by <a href=\"https://nationstates.net/nation={$post['nation']}\" class=\"nlink\"><span>{$post['nname']}</span></a> suppressed by a moderator.</p>";
				break;
			
			case 1: //user-suppressed
				if (!$post['sname']) { $post['sname'] = ucwords(str_replace('_', ' ',$post['suppressor'])); }
				$postHTML .= "<div><div class=\"rmbbuttons\"><a href class=\"rmbbutton forumpaneltoggle rmbshow\" title=\"Show post\"><img src=\"/rmbark/rmbbshow.png\" alt=\"Show\" title=\"Show post\"></a></div><p class=\"rmbsuppressed\"><a class=\"hiddenpermalink\" href=\"$cw_web_link/?postid={$post['pid']}\">Post</a> by <a href=\"https://nationstates.net/nation={$post['nation']}\" class=\"nlink\"><span>{$post['nname']}</span></a> suppressed by <a href=\"https://nationstates.net/nation={$post['suppressor']}\" class=\"nlink\"><span>{$post['sname']}</span></a>.</p><div class=\"hide suppressedbody-{$post['pid']}\">";
			
			case 0: //normal post
			case 10: //Voice of Mod
				$postHTML .= "<div class=\"rmbauthor2\"><p>".($post['status'] == 0 || 1 ? "<a href=\"https://nationstates.net/nation={$post['nation']}\" class=\"nlink\"><span>{$post['nname']}</span></a>" : "<a href=\"https://nationstates.net/page=help\"><span class=\"modtag\">NationStates Moderators</span></a>")."</p>";
				$timeNow = intval(time());
				$secondsAgo = $timeNow - intval($post['timestamp']);
				if ($secondsAgo < 60) {
					$newTime = "Seconds";
				} elseif ($secondsAgo < 7200) {
					$newTime = floor($secondsAgo/60)." minute".(floor($secondsAgo/60)>1?"s":"");
				} elseif ($secondsAgo < 86400) {
					$newTime = floor($secondsAgo%86400/3600)." hour".(floor($secondsAgo%86400/3600)>1?"s":"");
				} elseif ($secondsAgo < 31536000) {
					$newTime = floor($secondsAgo/86400)." day".(floor($secondsAgo/86400)>1?"s":"");
					if ($secondsAgo >= 86400 && $secondsAgo < 345600) {
						if (floor($secondsAgo%86400/3600)) {
							$newTime .= " ".floor($secondsAgo%86400/3600)." hour".(floor($secondsAgo%86400/3600)>1?"s":"");
						}
					}
				} else {
					$newTime = floor($secondsAgo/31536000)." year".(floor($secondsAgo/31536000)>1?"s ":" ");
					if (floor(($secondsAgo%31536000)/86400)) {
						$newTime .= floor(($secondsAgo%31536000)/86400)." day".(floor(($secondsAgo%31536000)/86400)>1?"s":"");
					}
				}
				$newTime .= " ago";
				$postHTML .= "<p class=\"rmbdate\"><a href=\"$cw_web_link/?postid={$post['pid']}\"><time datetime=\"".date("Y-m-d H:i:sO", $post['timestamp'])."\" data-epoch=\"{$post['timestamp']}\" title=\"".date("n/j/Y, g:i:s A", $post['timestamp'])."\">$newTime</time></a></p>";
				//did the post come from an embassy region?
				if ($post['embassy']) { $postHTML .= "<p><span class=\"rmbembassy\"><a href=\"https://nationstates.net/region=".strtolower(str_replace(' ', '_',$post['embassy']))."\" class=\"rlink rmbembassylink\"><i class=\"icon-building\" title=\"Posted via embassy\"></i>{$post['embassy']}</a></span></p>"; }
				$postHTML .= "</div><div class=\"rmbmsg2\"><p>".$bbcode->Parse($post['message'], intval($post['timestamp']))."</p><div class=\"rmbbuttons\"><a href=\"#quotebox\" class=\"rmbbutton button rmbquote\" title=\"Quote post\"><i class=\"icon-comment\"></i>Quote</a></div>";
				//did the post have any likes?
				if ($post['likers']) {
					$postHTML .= "<p class=\"rmblikers\"><i class=\"icon-heart\"></i> ";
					$likers = explode(":", $post['likers']);
					$likers_names = array();
					foreach ($likers as $liker) {
						$likers_names["$liker"] = ucwords(str_replace('_', ' ',$liker));
					}
					$likers_num = count($likers_names);
					$likerHTML = array();
					foreach ($likers_names as $liker_slug => $liker_name) {
						$likerHTML[] .= "<a href=\"https://nationstates.net/nation={$liker_slug}\" class=\"nlink\"><span>{$liker_name}</span></a>";
					}
					if (count($likers_names) <= 4) {
						$postHTML .= nat_lang_join($likerHTML);
					} else {
						$firstFour = array_slice($likerHTML, 0, 4);
						$remaining = array_slice($likerHTML, 4);
						$postHTML .= implode(", ", $firstFour).", <span id=\"nlistmore\" class=\"nlistmore\" data-nlisttag=\"nlist\">and <a href=\"#\">".count($remaining)." other".(count($remaining) > 1 ? "s" : "" )."</a></span><span id=\"nlistothers\" class=\"hide\">".nat_lang_join($remaining)."</span>";
					}
					$postHTML .= "</p>";
				}
				$postHTML .= "</div>";
				if ($post['status'] == 1) { $postHTML .= "</div></div>"; }
				break;
		}
		$postHTML .= "<div class=\"rmbspacer\"></div></div>";
		$rows[] = array("pid" => $post['pid'], "html" => $postHTML);
	}

	$resFilterLength = mysqli_query($conn,
			"SELECT COUNT(`pid`)
			 FROM   `$cw_sql_table`
			 WHERE $where;"
	);
	$resFilterLength = mysqli_fetch_row($resFilterLength);
	$recordsFiltered = $resFilterLength[0];

	$resTotalLength = mysqli_query($conn,
		"SELECT COUNT(`pid`)
		 FROM   `$cw_sql_table`;"
	);
	$resTotalLength = mysqli_fetch_row($resTotalLength);
	$recordsTotal = $resTotalLength[0];

	$conn->close();

	if(!$rows[0]['pid']) {
		$rows = array();
	}

	echo json_encode(array(
		"draw"            => isset ( $request['draw'] ) ?
			intval( $request['draw'] ) :
			0,
		"recordsTotal"    => intval( $recordsTotal ),
		"recordsFiltered" => intval( $recordsFiltered ),
		"data"            => $rows
	));
}
?>