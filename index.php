	<title>PalStat</title><br>
	<form action="index.php" method="post" name="form" id="form">
	
	<input type="text" name="addr" id="addr" size="20" placeholder="Server Address">:<input type="text" name="port" id="port" size="20" placeholder="9998" value="9998"><br>
	<input type="submit" value="Submit" name="submit" id="submit">
	<input type="reset" value="Reset" name="reset" id="reset">

	</form>


<?
/*
paladminstat.php v1.2
*/

$good_connection = false;
$addr = $_REQUEST['addr'];
$port = $_REQUEST['port'];

if ($addr == "") {
	$good_connection = false; echo "Bad connection";
} else {
	
   
// hex->asc conversion functions
function FixHex($Hex) {
if (strlen($Hex) <= 1) {
$Hex = "0" . $Hex;
} elseif (strlen($Hex) <= 0) {
$Hex = "00";
};
return $Hex;
}
function HexToAsc($text) {

$a = explode(':', chunk_split($text, 2 , ':')); 

foreach($a as $k => $v) $a[$k] = chr(hexdec($v)); 

$s = join('', $a);

return $s;

}
function AscToHex($Asc) {
for($i = 0; $i <= (strlen($Asc)-1); $i++) {
$TmpData .= FixHex(strtoupper(base_convert(ord(substr($Asc,$i,1)),10,16)));
};
return $TmpData;
}
//endians
// motorola functions

function HexToNum($Hex, $intel) {
if ($intel == "true") {
return base_convert(substr($Hex,2,2) . substr($Hex,0,2),16,10);
} else { 
if ($intel == "false") {
return base_convert($Hex,16,10);
}}}

function NumToHex($Number, $intel) {
$Over256 = 0;
while ($Number >= 256) {
$Over256++;
$Number = $Number  - 256;
}
if ($intel == "true") {
return strtoupper(FixHex(base_convert($Number,10,16)) . FixHex(base_convert($Over256,10,16)));
} else {
if ($intel == "false") { 
return FixHex(base_convert($Over256,10,16))  . strtoupper(FixHex(base_convert($Number,10,16)));
}}
}

// End of Functions


//open connection
$fp = fsockopen ($addr, $port, $errno, $errstr, 30);

if (!$fp) {
	echo "$errstr ($errno)<br>\n";
	
	//let the script know this wasn't a
	//successful connection (so the lights are off, etc)
	$good_connection = false;
	
	//turn off all of the variables
	$palace['name'] = "n/a";
	$palace['currentUsers'] = 0;
	$palace['usersSinceReboot'] = 0;
	$palace['capacity'] = "n/a";
	$palace['OS'] = "n/a";
	$palace['serverOS'] = "n/a";
	$palace['serverVers'] = "n/a";

	//flags are decoded into options ($palace_optns)
	$palace['flags'] = 0;
	$palace['optns'] = "n/a";
	
	// unused:
	$palace['avurl'] = "n/a";
	
} else {

	//good connection
	$good_connection = true;

$data = fread($fp,12);

// if its tiyr (Motorola) ---------------------------------------------
if (substr($data,0,4) == "tiyr") {
	
	//let it be known as Motorola: for OS (OpTyp)
	$palace['serverOS'] = "Motorola";

	/* save the userid in tiyr as an assumed number
	 * of the number of users who have visited since
	 * last reboot. this may be false, since counters can be reset.
	 */
	$palace['usersSinceReboot'] = HexToNum(AscToHex(substr($data,10,4)),"false");
	
	// send the actual request for info
	fputs($fp,HexToAsc("73496E6600000010000011200000003F54595045000000044A617661"));

	//get first bit of the packet not used so just clear it out the buffer
	$fnis = AscToHex(fread($fp,12));
	//make a loop for each if statement
	for($i = 0; $i <= 5; $i++) {
	//store data into a variable
	$dat = fread($fp,8);
	//get packet name
	$packet = substr($dat,0,4);
	//gets palace info
	if ($packet == "AURL") {
	//gets the length for the first packet
	$length = HexToNum(AscToHex(substr($dat,6,2)),"false");
	//gets the data for the first packet
	$data = fread($fp,$length);

	//save the avatar URL as a variable (unused!)
	$palace['avurl'] = substr($data,0,$length);

} else {

if ($packet == "VERS" ) {
	$length = HexToNum(AscToHex(substr($dat,6,2)),"false");
	$data = fread($fp,$length);

	//save the pserver version
	$palace['serverVers'] = substr($data,0,$length);

} else {
	
if ($packet == "TYPE" ) {
	$length = HexToNum(AscToHex(substr($dat,6,2)),"false");
	$data = fread($fp,$length);

	//save the OS (this is actually not the type, but the OS itself)
	$palace['OS'] = substr($data,0,$length);

} else {

if ($packet == "FLAG" ) {
	$length = HexToNum(AscToHex(substr($dat,6,2)),"false");
	$data = fread($fp,$length);

	//save flags (this is later decoded into "options")
	$palace['flags'] = substr($data,0,$length);
	
	// !!!!!!!!!!!!!!
// 	echo "(debug) FLAG: ";
// 	echo AscToHex($palace['flags']);

} else {

if ($packet == "NUSR" ) {
	$length = HexToNum(AscToHex(substr($dat,6,4)),"false");
	$data = fread($fp,$length);
	
	//save user#
	$palace['currentUsers'] = HexToNum(AscToHex(substr($data,2,$length)), "false");

} else {

if ($packet == "NAME" ) {
	$length = HexToNum(AscToHex(substr($dat,6,2)),"false");
	$data = fread($fp,$length);

	//save palace server name
	$palace['name'] = substr($data,0,$length);

$i = 5;
}}}}}}}

//"bye " packet (motorola)
//fputs($fp,HexToAsc("627965200000000000000000"));

}
// end of motorola section


// if its ryit (Intel) ------------------------------------------------
if (substr($data,0,4) == "ryit") { 

	//save the number of users since last reboot
	$palace['usersSinceReboot'] = HexToNum(substr(AscToHex($data),16,4),"true");

	//let it be known as Intel: for OS (OpTyp)
	$palace['serverOS'] = "Intel";

	//request info & clear the buffer (?)
	fputs($fp,HexToAsc("666E497310000000010000003F00000045505954040000004A617661"));
	$fnis = AscToHex(fread($fp,12));
	for($i = 0; $i <= 5; $i++) {
	$dat = AscToHex(fread($fp,8));
	// would $packet = HexToAsc(substr($dat,0,8)); work here?)
	$packet = substr($dat,0,8);

//SREV -- version\server revision
if ($packet == "53524556" ) {
	$length = HexToNum(substr($dat,8,4),"true");
	$data = fread($fp,$length);

	//save pserver version
	$palace['serverVers'] = substr($data,0,$length);

} else { 

//LRUA -- avatarURL
if ($packet == "4C525541" ) {
	$length = HexToNum(substr($dat,8,4),"true");
	$data = fread($fp,$length);

	//save the avatar URL, even though its not used here
	$palace['avurl'] = substr($data,0,$length);

} else { 
	
//EPYT -- type (server type)
if ($packet == "45505954" ) {
	$length = HexToNum(substr($dat,8,4),"true");
	$data = fread($fp,$length);
	
	//save the OS (used as: "OS (type)")
	$palace['OS'] = substr($data,0,$length);

} else { 
	
//GALF -- flags (later converted to options)
if ($packet == "47414C46" ) {
	$length = HexToNum(substr($dat,8,4),"true");
	$data = AscToHex(fread($fp,$length));

	//save the flags
	$palace['flags'] = substr($data,0,$length * 2);
	
	// !!!!!!!!!!!!!!
// 	echo "(debug) FLAG: ";
// 	echo $palace['flags'];
	

} else {
	
//RSUN -- usr#
if ($packet == "5253554E" ) {
	$length = HexToNum(substr($dat,8,4),"true");
	$data = AscToHex(fread($fp,$length));

	//save # usrs
	$palace['currentUsers'] = HexToNum(substr($data,0,$length), "true");

} else { 
//EMAN -- palace server name
if ($packet == "454D414E" ) {
	$length = HexToNum(substr($dat,8,4),"true");
	$data = fread($fp,$length);

	//save the pserver name
	$palace['name'] = substr($data,0,$length);


$i = 5;
}}}}}}}

//" eyb" packet (intel)
//$bye_id = substr(AscToHex($data),16,4);
//fputs($fp,HexToAsc("206579620000000000000000"));

}
//End of Intel
//close the socket so no more packets can be sent
 fclose ($fp);
}
	}
?>

<?
// start HTML ---------------------------------------------------------
// no important code should go beyond this point, only calling variables!
// and DONT edit this, it works fine :P
?>

<style>
.palstattable {
	font-family: Tahoma, Verdana, Arial, Helvetica;
	font-style: normal;
	font-size: 8pt;
	cursor: default;
}
</style>

<table class="palstattable" bgcolor="#FFFFFF" width="528" height="100" cellspacing="8" cellpadding="0" border="0" style="border: 1px solid black;">
<tr><td colspan="6" style="border-bottom: 1px dashed #666666;">
<!-- credits -->
<table class="palstattable" width="100%" cellspacing="0" cellpadding="0" border="0">
<tr><td height="20" align="left" valign="top">
<span style="color: #999999;"><b>PalStat</b> Palace Status Generator</span>
</td><td height="20" align="right" valign="top">
<span style="color: #999999;"><i>Brought to you by <a style="color: #666666; text-decoration: underline;" href="http://andsat.org/">Andrew Saturn</a></i></span>
</tr>
</table>
<!-- end credit -->
</td></tr>

<?

//link to the palace:
if ($good_connection == true) {
	?><tr>
<!-- palace link -->
<td width="62" align="center" valign="middle"><a href="palace://<? echo $addr .':'. $port; ?>" title="<? echo $palace['name']; ?>"><img src="palace.gif" width="62" height="65" border="0" alt="Click here to connect to this palace"></a></td>
<td width="72" align="left" valign="middle">
Click the logo<br>to connect to<br>this Palace
</td><?
} else {
	?>
<tr>
<!-- palace link -->
<td width="62" align="center" valign="middle"><img style="opacity: 0.5; filter: grayscale(100%); -webkit-filter: grayscale(100%);" src="palace.gif" width="62" height="65" border="0" alt=""></td>
<td width="72" align="left" valign="middle">
<span style="color: #999999;">Click the logo<br>to connect to<br>this Palace</span>
</td>
	<?
}
?>

<td width="3" style="border-right: 1px dashed #666666;">&nbsp;</td>

<?
//show online\offline lights
if ($good_connection == true) {
	echo "<td width=\"120\" align=\"center\" valign=\"middle\"><b style=\"color: green;\">Server Online</b><br>ready and accepting connections<br>
</td>";
} else {
	echo "<td width=\"120\" align=\"center\" valign=\"middle\"><b style=\"color: red;\">Server Offline</b><br>bad address or no Palace found<br>
</td>";
}
?>

<td width="3" style="border-left: 1px dashed #666666;">&nbsp;</td>
<td align="left" valign="middle">
<!-- info -->
<table class="palstattable" width="100%" cellspacing="0" cellpadding="0" border="0">
<!-- palace name -->
<tr><td width="60" align="right" valign="top"><b>Palace: &nbsp; </b></td><td valign="top">

<?
echo $palace['name'];
?>

</td></tr>
<!-- #users \ since last reboot -->
<tr><td align="right" valign="top"><b>Users: &nbsp; </b></td><td valign="top">

<?
echo "" . number_format($palace['currentUsers']) . " (" . number_format($palace['usersSinceReboot']) . " since last reboot)";
?>

</td></tr>
<!-- capacity -->
<tr><td align="right" valign="top"><b>Capacity: &nbsp; </b></td>
<td valign="top">

<?
 
function Reverse($aData) {
for($i = 0; $i <= strlen($aData); $i += 2) {
	$Reversed = substr($aData,$i,2) . $Reversed; 
};
return $Reversed;
};
 
function GetCapacity($ServerType, $Capacity) {
if ($ServerType == "Intel") {
return base_convert(Reverse($Capacity),16,10);
} else {
return base_convert($Capacity,16,10); 
};
};
 
echo GetCapacity("Intel",substr($palace['flags'],9,8));

?>



</td></tr>
<!-- operating system -->
<tr><td align="right" valign="top"><b>OS: &nbsp; </b></td><td valign="top">

<?
echo $palace['OS'] ."(". $palace['serverOS'] .")";
?>

</td></tr>
<!-- version -->
<tr><td align="right" valign="top"><b>Version: &nbsp; </b></td><td valign="top">

<?
echo $palace['serverVers'];
?>

</td></tr>

<!-- options -->
<tr><td align="right" valign="top"><b>Options: &nbsp; </b></td>
<td valign="top"><!-- none -->

<!-- // intel code below // -->

<?

$options = HexToNum(substr($palace['flags'],10,2), "true");
$palace['options'] = array();

do {
	if ($options >= 32) {
		array_push($palace['options'], "Palace Presents");
		$options = $options - 32;
	} else if ($options >= 16) {
		array_push($palace['options'], "Instant Palace");
		$options = $options - 16;
	} else if ($options >= 8) {
		array_push($palace['options'], "Unused1");
		$options = $options - 8;
	} else if ($options >= 4) {
		array_push($palace['options'], "Guests treated as Members");
		$options = $options - 4;
	} else if ($options >= 2) {
		array_push($palace['options'], "Authentication");
		$options = $options - 2;
	} else if ($options >= 1) {
		array_push($palace['options'], "DirectPlay");
		$options = $options - 1;
	} else {
		$options = 0;
	}
} while ($options > 0);

$optionCount = count($palace['options']);
$i = 0;
foreach ($palace['options'] as $option) {
	$i++;
	echo $option;
	if ($i != $optionCount) echo ', ';
}

?>


</td></tr>
</table>
<!-- end info -->
</td>
</tr>
</table>

<pre><?
// 	print_r($palace);
?></pre>