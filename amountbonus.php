<?php
require "include/bittorrent.php";
require_once(get_langfile_path("takemessage.php",true));
dbconn();
loggedinorreturn();
checkPrivilegePanel();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if ($_POST['doit'] == 'yes') {
    $amount = 0 + $_POST['amount'];
    sql_query("UPDATE LOW_PRIORITY users SET seedbonus = seedbonus + " . $amount . " WHERE status='confirmed'");
    stderr("Bonus", $amount . " bonus point is sent to everyone...");
    die;
  }

	if ($_POST["username"] == "" || $_POST["seedbonus"] == "" || $_POST["seedbonus"] == "")
	stderr("Error", "Missing form data.");
	require_once(get_langfile_path("delete.php",true));
	$username = sqlesc($_POST["username"]);
	$seedbonus = sqlesc($_POST["seedbonus"]);
	$operator = $CURUSER["username"];
	$res = sql_query("SELECT id FROM users WHERE username=$username");
	$arr = mysql_fetch_row($res);
	$receiver = $arr[0];
	$lang = get_user_lang($receiver);
	$subject = $lang_takemessage_target[$lang]['Bonus_point_added'];
	$msg = sprintf($lang_takemessage_target[$lang]['msg'],$operator,$_POST["seedbonus"]);
	$date = sqlesc(date("Y-m-d H:i:s"));
	sql_query("UPDATE LOW_PRIORITY users SET seedbonus=seedbonus + $seedbonus WHERE username=$username") or sqlerr(__FILE__, __LINE__);
	$msgsql = sprintf("INSERT INTO messages (sender, receiver, subject, added, msg) VALUES(0, %d, '%s',%s,'%s')",$receiver,$subject,$date,$msg);
	sql_query($msgsql) or sqlerr(__FILE__, __LINE__);
	if (!$arr)
	stderr("Error", "Unable to update account.");
  header("Location: " . get_protocol_prefix() . "$BASEURL/userdetails.php?id=".htmlspecialchars($arr[0]));
	die;
}
stdhead("Update Users Upload Amounts");
?>
<h1>Update Users Bonus Amounts</h1>
<?php
begin_main_frame("",false, 30);
begin_main_frame("Add to Specific User",false,30);
echo "<form method=\"post\" action=\"amountbonus.php\">";
print("<table width=100% border=1 cellspacing=0 cellpadding=5>\n");
?>
<tr><td class="rowhead">User name</td><td class="rowfollow"><input type="text" name="username" size="30"/></td></tr>
<tr><td class="rowhead">Bonus</td><td class="rowfollow"><input type="text" name="seedbonus" size="5"/></td></tr>
<tr><td colspan="2" class="toolbox" align="center"><input type="submit" value="Okay" class="btn"/></td></tr>
<?php end_table();?>
</form>
<?php end_main_frame();?>
<?php begin_main_frame("Send bonus point to everyone",false,30);?>
<form action="amountbonus.php" method="post">
<input type="hidden" name = "doit" value = "yes" />
<label for="bonus-amount">Amount: </label><input type="text" id="bonus-amount" name="amount" value="25" />
<input type="submit" class="btn" value="OK" />
</form>
<?php
end_main_frame();
end_main_frame();
stdfoot();
