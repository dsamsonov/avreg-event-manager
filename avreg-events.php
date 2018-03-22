#!/usr/bin/php
<?php
$storage_dir="/var/spool/avreg/"; #directory with avreg video files
$dbuser="user";                   #database user name
$dbpass="password";               #database password
$dbname="avreg6_db";              #avreg database
$dbhost="127.0.0.1";              #database host
$dbport="3306";                   #datavase port
$tg_token="botTOKEN";             #telegram bot token
$tg_chatid="-chatid";		  #telegram chat id

function send_telegram($text,$type="text",$file="") {
    global $tg_token,$tg_chatid;
    if ($type=="text") {
        $url = "https://api.telegram.org/".$tg_token."/sendMessage?chat_id=".$tg_chatid;
	$url = $url."&text=".urlencode($text);
        $ch = curl_init();
	$optArray = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true
        );
    }
    if ($type=="video") {
        $url = "https://api.telegram.org/".$tg_token."/sendVideo?chat_id=".$tg_chatid;
	$headers =  array("Content-Type: multipart/form-data");
	//$url = $url."&text=".urlencode($text);
	//$url = $url."&video=".urlencode($file);
        $ch = curl_init();
	$optArray = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_HTTPHEADER =>  ['Content-Type: multipart/form-data'],
	    CURLOPT_POST => true,
	    CURLOPT_POSTFIELDS => ['video' => curl_file_create($file, 'video/mp4'), 'caption' => $text ]
        );
    }
    curl_setopt_array($ch, $optArray);
    $output=curl_exec($ch);
    curl_close($ch);
#    echo ($url."\n");
#    print_r($output);
}

function message($text) {
    $text="Avreg event manager - ".$text;
    send_telegram($text);
}

function die_message($text) {
    $text="Avreg event manager - ".$text;
    send_telegram($text);
    die($text);
}

function db_query($dblink,$sql) {
    $value= mysqli_query($dblink, $sql) or die_message("Не удалось выполнить SQL запрос: ".$sql."\n"); 
    return ($value);
}

$dblink = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname, $dbport);
if (mysqli_connect_errno($dblink)) {
    die_message("Не удалось подключиться к MySQL: " . mysqli_connect_error()."\n");
}

$query=db_query($dblink,"SELECT PARVAL FROM CAMERAS WHERE PARNAME='InetCam_IP'");
for ($i=0; $i <  mysqli_num_rows($query); $i++){
    $row= mysqli_fetch_array($query);
    exec("ping -c 4 ".$row[$i],$out,$exitcode);
    if ($exitcode!="0") {  message("Камера недоступна по ICMP: ".$row[$i]."\n"); }
}

unset($out);
exec ("ps aux |grep /usr/sbin/avregd | grep -v grep",$out);
if(count($out) < "1") { message("Демон avregd не запущен\n"); }

$query=db_query($dblink,"SELECT a.CAM_NR,a.DT1,a.DT2,a.EVT_ID,a.EVT_CONT,a.SESS_NR,b.PARVAL FROM EVENTS as a,CAMERAS as b, `events-manager` as c WHERE c.param='last_check_time' AND a.DT1>c.value_date AND a.CAM_NR=b.CAM_NR and b.PARNAME='text_left'");
for ($i=0; $i <  mysqli_num_rows($query); $i++){
    $row= mysqli_fetch_array($query);
    if ($row['EVT_ID']=="12") {
        send_telegram("Замечено движение на ".$row['PARVAL']." в ".$row['DT2'],"video",$storage_dir.$row['EVT_CONT']);
    }
    if ($row['EVT_ID']=="1") { message("Сообщение демона avregd: ".$row['EVT_CONT']."\n"); }
    if ($row['EVT_ID']=="3") { message("Проблемы с подключением к камере ".$row['PARVAL'].": ".$row['EVT_CONT']."\n"); }
    if ($row['EVT_ID']=="22") { message("Изменение качества видеокадра ".$row['PARVAL'].": ".$row['EVT_CONT']."\n"); }
    $last_check_time=$row['DT1'];
}
db_query($dblink,"UPDATE `events-manager` SET value_date=NOW(), date=NOW() WHERE param='last_check_time'");

mysqli_close($dblink);
?>