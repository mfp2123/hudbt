<?php
require_once("include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
require(get_langfile_path("",true));

$action = htmlspecialchars($_GET["action"]);
$sub = htmlspecialchars($_GET["sub"]);
$type = htmlspecialchars($_GET["type"]);

loggedinorreturn();
parked();

function check_comment_type($type)
{
	if($type != "torrent" && $type != "request" && $type != "offer")
	stderr($lang_comment['std_error'],$lang_comment['std_error']);
}

check_comment_type($type);

if ($action == "add") {

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    function href() {
      global $type, $arr, $parent_id, $BASEURL, $newid;
      if($type == "torrent") {
	return get_protocol_prefix() . "$BASEURL/details.php?id=$parent_id&page=p$newid#cid$newid";
      }
      elseif($type == "offer") {
	return get_protocol_prefix() . "$BASEURL/offers.php?id=$parent_id&off_details=1";
      }
      elseif($type == "request") {
	return get_protocol_prefix() . "$BASEURL/viewrequests.php?id=$parent_id&off_details=1";
      }
      return '';
    }
      
		// Anti Flood Code
		// This code ensures that a member can only send one comment per minute.
		if (get_user_class() < $commanage_class) {
			if (strtotime($CURUSER['last_comment']) > (TIMENOW - 10))
			{
				$secs = 10 - (TIMENOW - strtotime($CURUSER['last_comment']));
				stderr($lang_comment['std_error'],$lang_comment['std_comment_flooding_denied']."$secs".$lang_comment['std_before_posting_another']);
			}
		}

		$parent_id = 0 + $_POST["pid"];
		int_check($parent_id,true);

		if($type == "torrent")
			$res = sql_query("SELECT name, owner FROM torrents WHERE id = $parent_id") or sqlerr(__FILE__,__LINE__);
		else if($type == "offer")
			$res = sql_query("SELECT name, userid as owner FROM offers WHERE id = $parent_id") or sqlerr(__FILE__,__LINE__);
		else if($type == "request")
			$res = sql_query("SELECT requests.request as name, userid as owner FROM requests WHERE id = $parent_id") or sqlerr(__FILE__,__LINE__);

		$arr = mysql_fetch_array($res);
		if (!$arr)
			stderr($lang_comment['std_error'], $lang_comment['std_no_torrent_id']);

		$text = trim($_POST["body"]);
		if (!$text)
			stderr($lang_comment['std_error'], $lang_comment['std_comment_body_empty']);

		$quote = 'NULL';
		if (array_key_exists('quote', $_REQUEST)) {
		  $quote = 0 + $_REQUEST['quote'];
		  $where = '';
		  if($type == "torrent") {
		    $where = ' AND c.torrent=' . $parent_id;
		  }
		  elseif($type == "offer") {
		    $where = ' AND c.offer=' . $parent_id;
		  }
		  elseif($type == "request") {
		    $where = ' AND c.request=' . $parent_id;
		  }
		  $res = sql_query('SELECT c.user, u.commentpm FROM comments c INNER JOIN users u ON c.user = u.id WHERE c.id=' . $quote . $where ) or sqlerr(__FILE__, __LINE__);
		  if (mysql_num_rows($res) != 1) {
		    $quote = 'NULL';
		  }
		  $quoteduser = mysql_fetch_array($res);
		}
		$values = array($CURUSER["id"], $parent_id, "'" . date("Y-m-d H:i:s") . "'", sqlesc($text), sqlesc($text), $quote);
		if ($type == "torrent"){
		  sql_query("INSERT INTO comments (user, torrent, added, text, ori_text, quote) VALUES (" . implode(',', $values) . ")");
		  $Cache->delete_value('torrent_'.$parent_id.'_last_comment_content');
		}
		elseif ($type == "offer") {
		  sql_query("INSERT INTO comments (user, offer, added, text, ori_text, quote) VALUES (" . implode(',', $values) . ")");
			$Cache->delete_value('offer_'.$parent_id.'_last_comment_content');
		}
		elseif ($type == "request") {
		  sql_query("INSERT INTO comments (user, request, added, text, ori_text, quote) VALUES (" . implode(',', $values) . ")");
		}

		$newid = mysql_insert_id();
		if($type == "torrent") {
		  sql_query("UPDATE LOW_PRIORITY torrents SET comments = comments + 1 WHERE id = $parent_id");
		}
		elseif($type == "offer") {
		  sql_query("UPDATE offers SET comments = comments + 1 WHERE id = $parent_id");
		}
		elseif($type == "request") {
		  sql_query("UPDATE requests SET comments = comments + 1 WHERE id = $parent_id");
		}

		if($quoteduser["commentpm"] == 'yes' && $quoteduser['user'] != $CURUSER['id'] && $quoteduser['user'] != $arr['owner']) {
		  $target = $quoteduser['user'];
		  $content = $lang_comment_target[get_user_lang($target)]['msg_new_comment_quotation'] . '[url=' . href() . ']' . $arr['name'] . '[/url]';

		  send_pm($CURUSER['id'], $quoteduser['user'], $lang_comment_target[get_user_lang($target)]['msg_new_quotation'], $content);
		}

		$ras = sql_query("SELECT commentpm FROM users WHERE id = $arr[owner]") or sqlerr(__FILE__,__LINE__);
		$arg = mysql_fetch_array($ras);

		if($arg["commentpm"] == 'yes' && $CURUSER['id'] != $arr["owner"]) {
		  $subject = $lang_comment_target[get_user_lang($arr["owner"])]['msg_new_comment'];
		  if($type == "torrent") {
		    $notifs = $lang_comment_target[get_user_lang($arr["owner"])]['msg_torrent_receive_comment'] . " [url=" . href() . ']' . $arr['name'] . "[/url].";
		  }
		  elseif($type == "offer") {
		    $notifs = $lang_comment_target[get_user_lang($arr["owner"])]['msg_torrent_receive_comment'] . " [url=" . href() . "] " . $arr['name'] . "[/url].";
		  }
		  elseif($type == "request") {
		    $notifs = $lang_comment_target[get_user_lang($arr["owner"])]['msg_torrent_receive_comment'] .  " [url=" . href() . "] " . $arr['name'] . "[/url].";
		  }

		  send_pm($CURUSER['id'], $arr['owner'], $subject, $notifs);
		  $Cache->delete_value('user_'.$arr['owner'].'_unread_message_count');
		  $Cache->delete_value('user_'.$arr['owner'].'_inbox_count');
		}

		KPS("+",$addcomment_bonus,$CURUSER["id"]);

		// Update Last comment sent...
		sql_query("UPDATE LOW_PRIORITY users SET last_comment = NOW() WHERE id = ".sqlesc($CURUSER['id'])) or sqlerr(__FILE__, __LINE__);

		if($type == "torrent") {
		  header("Refresh: 0; url=details.php?id=$parent_id#cid$newid");
		}
		else if($type == "offer") {
		  header("Refresh: 0; url=offers.php?id=$parent_id&off_details=1#cid$newid");
		}
		else if($type == "request") {
		  header("Refresh: 0; url=viewrequests.php?id=$parent_id&req_details=1#cid$newid");
		}
		die;
	}

	$parent_id = 0 + $_GET["pid"];
	int_check($parent_id,true);

	$quote_input = '';
	if($sub == "quote") {
		$commentid = 0 + $_GET["cid"];
		int_check($commentid,true);

		$res2 = sql_query("SELECT comments.text, users.username FROM comments JOIN users ON comments.user = users.id WHERE comments.id=$commentid") or sqlerr(__FILE__, __LINE__);

		if (mysql_num_rows($res2) != 1)
			stderr($lang_forums['std_error'], $lang_forums['std_no_comment_id']);

		$arr2 = mysql_fetch_assoc($res2);
		$quote_input = '<input type="hidden" name="quote" value="' . $commentid . '" />';
	}

	if($type == "torrent"){
		$res = sql_query("SELECT name, owner FROM torrents WHERE id = $parent_id") or sqlerr(__FILE__,__LINE__);
		$url="details.php?id=$parent_id";
	}
	else if($type == "offer"){
		$res = sql_query("SELECT name, userid as owner FROM offers WHERE id = $parent_id") or sqlerr(__FILE__,__LINE__);
		$url="offers.php?id=$parent_id&off_details=1";
	}
	else if($type == "request"){
		$res = sql_query("SELECT requests.request as name, userid as owner FROM requests WHERE id = $parent_id") or sqlerr(__FILE__,__LINE__);
		$url="viewrequests.php?id=$parent_id&req_details=1";
	}
	$arr = mysql_fetch_array($res);
	if (!$arr)
		stderr($lang_comment['std_error'], $lang_comment['std_no_torrent_id']);

	stdhead($lang_comment['head_add_comment_to']. $arr["name"]);
	begin_main_frame();
	$title = $lang_comment['text_add_comment_to']."<a href=$url>". htmlspecialchars($arr["name"]) . "</a>";
	print("<form id=compose method=post name=\"compose\" action=\"comment.php?action=add&type=$type\">\n");
	print("<input type=\"hidden\" name=\"pid\" value=\"$parent_id\"/>\n");
	if ($sub == "quote") {
	  echo $quote_input;
	}
	begin_compose($title, ($sub == "quote" ? "quote" : "reply"), ($sub == "quote" ? htmlspecialchars("[quote=".htmlspecialchars($arr2["username"])."]".dequote(unesc($arr2["text"]))."[/quote]") : ""), false);
	end_compose();
	print("</form>");
	end_main_frame();
	stdfoot();
	die;
}
elseif ($action == "edit")
{
		$commentid = 0 + $_GET["cid"];
		int_check($commentid,true);

		if($type == "torrent")
			$res = sql_query("SELECT c.*, t.name, t.id AS parent_id FROM comments AS c JOIN torrents AS t ON c.torrent = t.id WHERE c.id=$commentid") or sqlerr(__FILE__,__LINE__);
		else if($type == "offer")
			$res = sql_query("SELECT c.*, o.name, o.id AS parent_id FROM comments AS c JOIN offers AS o ON c.offer = o.id WHERE c.id=$commentid") or sqlerr(__FILE__,__LINE__);
		else if($type == "request")
			$res = sql_query("SELECT c.*, r.request as name, r.id AS parent_id FROM comments AS c JOIN requests AS r ON c.request = r.id WHERE c.id=$commentid") or sqlerr(__FILE__,__LINE__);

		$arr = mysql_fetch_array($res);
		if (!$arr)
		stderr($lang_comment['std_error'], $lang_comment['std_invalid_id']);

		if ($arr["user"] != $CURUSER["id"] && get_user_class() < $commanage_class)
		stderr($lang_comment['std_error'], $lang_comment['std_permission_denied']);

		if ($_SERVER["REQUEST_METHOD"] == "POST")
		{
			$text = $_POST["body"];
			$returnto =  htmlspecialchars($_POST["returnto"]) ? $_POST["returnto"] : htmlspecialchars($_SERVER["HTTP_REFERER"]);

			if ($text == "")
				stderr($lang_comment['std_error'], $lang_comment['std_comment_body_empty']);
			$text = sqlesc($text);
			$editdate = sqlesc(date("Y-m-d H:i:s"));
			$editnotseen = empty($_REQUEST['editnotseen'])?'0':$_REQUEST['editnotseen'];
			$editnotseen = $editnotseen + 0;
			if ((!checkprivilege(["Posts","editnotseen"])) && $editnotseen==1){
				permissiondenied();
			}
			sql_query("UPDATE comments SET text=".$text.", editdate=".$editdate.", editedby=".$CURUSER[id].",editnotseen = ".$editnotseen." WHERE id=".sqlesc($commentid)) or sqlerr(__FILE__, __LINE__);
			if($type == "torrent")
				$Cache->delete_value('torrent_'.$arr['parent_id'].'_last_comment_content');
			elseif ($type == "offer")
				$Cache->delete_value('offer_'.$arr['parent_id'].'_last_comment_content');
			header("Location: $returnto");

			die;
		}
		$parent_id = $arr["parent_id"];
		if($type == "torrent")
			$url="details.php?id=$parent_id";
		else if($type == "offer")
			$url="offers.php?id=$parent_id&off_details=1";
		else if($type == "request")
			$url="viewrequests.php?id=$parent_id&req_details=1";
		stdhead($lang_comment['head_edit_comment_to']."\"". $arr["name"] . "\"");
		begin_main_frame();
		$title = $lang_comment['head_edit_comment_to']."<a href=$url>". htmlspecialchars($arr["name"]) . "</a>";
		print("<form id=compose method=post name=\"compose\" action=\"comment.php?action=edit&cid=$commentid&type=$type\">\n");
		print("<input type=\"hidden\" name=\"returnto\" value=\"" . htmlspecialchars($_SERVER["HTTP_REFERER"]) . "\" />\n");
		begin_compose($title, "edit", htmlspecialchars(unesc($arr["text"])), false);
		$res=sql_query("SELECT editnotseen,user FROM comments WHERE id = ".$commentid)or sqlerr(__FILE__, __LINE__);
  	$arr = mysql_fetch_assoc($res) or stderr($lang_forums['std_forum_error'], $lang_forums['std_topic_not_found']);
		$editnotseen=$arr['editnotseen'];
		$owner =$arr['user'];
  	if(CheckPrivilege(["Posts","editnotseen"]) && ($CURUSER['id']==$owner)){
  		echo "<tr><td class='center' colspan='2'><label><input type=\"checkbox\" name=\"editnotseen\" value=\"1\"".($editnotseen?"checked=\"checked\"":"").">".$lang_comment[text_editnotseen]."</label></td></tr>";
 		}
		end_compose();
		print("</form>");
		end_main_frame();
		stdfoot();
		die;
}
elseif ($action == "delete")
{
		if (get_user_class() < $commanage_class)
		stderr($lang_comment['std_error'], $lang_comment['std_permission_denied']);

		$commentid = 0 + $_GET["cid"];
		$sure = $_GET["sure"];
		int_check($commentid,true);

		if (!$sure)
		{
			$referer = $_SERVER["HTTP_REFERER"];
			stderr($lang_comment['std_delete_comment'], $lang_comment['std_delete_comment_note'] ."<a href=comment.php?action=delete&cid=$commentid&sure=1&type=$type" .($referer ? "&returnto=" . rawurlencode($referer) : "") . $lang_comment['std_here_if_sure'],false);
		}
		else
		int_check($sure,true);


		if($type == "torrent")
		$res = sql_query("SELECT torrent as pid,user FROM comments WHERE id=$commentid")  or sqlerr(__FILE__,__LINE__);
		else if($type == "offer")
		$res = sql_query("SELECT offer as pid,user FROM comments WHERE id=$commentid")  or sqlerr(__FILE__,__LINE__);
		else if($type == "request")
		$res = sql_query("SELECT request as pid,user FROM comments WHERE id=$commentid")  or sqlerr(__FILE__,__LINE__);

		$arr = mysql_fetch_array($res);
		if ($arr)
		{
			$parent_id = $arr["pid"];
			$userpostid = $arr["user"];
		}
		else
		stderr($lang_comment['std_error'], $lang_comment['std_invalid_id']);

		sql_query("DELETE FROM comments WHERE id=$commentid") or sqlerr(__FILE__,__LINE__);
		if ($type == "torrent")
			$Cache->delete_value('torrent_'.$arr['pid'].'_last_comment_content');
		elseif ($type == "offer")
			$Cache->delete_value('offer_'.$arr['pid'].'_last_comment_content');
		if ($parent_id && mysql_affected_rows() > 0)
		{
			if($type == "torrent")
			sql_query("UPDATE LOW_PRIORITY torrents SET comments = comments - 1 WHERE id = $parent_id") or sqlerr(__FILE__,__LINE__);
			else if($type == "offer")
			sql_query("UPDATE offers SET comments = comments - 1 WHERE id = $parent_id") or sqlerr(__FILE__,__LINE__);
			else if($type == "request")
			sql_query("UPDATE requests SET comments = comments - 1 WHERE id = $parent_id") or sqlerr(__FILE__,__LINE__);
		}

		KPS("-",$addcomment_bonus,$userpostid);

		$returnto = $_GET["returnto"] ? $_GET["returnto"] : htmlspecialchars($_SERVER["HTTP_REFERER"]);

		header("Location: $returnto");

		die;
}
elseif ($action == "vieworiginal")
{
	if (get_user_class() < $commanage_class)
	stderr($lang_comment['std_error'], $lang_comment['std_permission_denied']);

		$commentid = 0 + $_GET["cid"];
		int_check($commentid,true);

		if($type == "torrent")
		$res = sql_query("SELECT c.*, t.name FROM comments AS c JOIN torrents AS t ON c.torrent = t.id WHERE c.id=$commentid") or sqlerr(__FILE__,__LINE__);
		else if($type == "offer")
		$res = sql_query("SELECT c.*, o.name FROM comments AS c JOIN offers AS o ON c.offer = o.id WHERE c.id=$commentid") or sqlerr(__FILE__,__LINE__);
		else if($type == "request")
		$res = sql_query("SELECT c.*, r.request as name FROM comments AS c JOIN requests AS r ON c.request = r.id WHERE c.id=$commentid") or sqlerr(__FILE__,__LINE__);

		$arr = mysql_fetch_array($res);
		if (!$arr)
		stderr($lang_comment['std_error'], $lang_comment['std_invalid_id']);

		stdhead($lang_comment['head_original_comment']);
		print("<h1>".$lang_comment['text_original_content_of_comment']."#$commentid</h1>");
		print("<table width=\"737\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">");
		print("<tr><td class=\"text\">\n");
		echo format_comment($arr["ori_text"]);
		print("</td></tr></table>\n");

		$returnto =  htmlspecialchars($_SERVER["HTTP_REFERER"]);

		if ($returnto)
		print("<p><font size=\"small\">(<a href=\"".$returnto."\">".$lang_comment['text_back']."</a>)</font></p>\n");

		stdfoot();

		die;
}
else
stderr($lang_comment['std_error'], $lang_comment['std_unknown_action']);

die;
?>
