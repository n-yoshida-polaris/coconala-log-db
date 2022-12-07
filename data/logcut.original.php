<?php

ini_set('display_errors', "On");

print <<< DOC1
<html><head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8">
<title>LogCut</title>
</head>
<body>
DOC1;

$fp = fopen("invoke.log", "a");
fwrite($fp, date("Y-m-d H:i:s")." Invoked.\r\n");
fclose($fp);

//サーバー
$con = mysqli_connect("mysql" , "voicepa_voicepa" , "uwfrom359road", "voicepa_logs");
if ( $con == FALSE ) {
    print "DBサーバーに異常が発生しています。管理者へ連絡をお願いします。<br />";
    print mysqli_connect_error();
}

$fp1 = fopen("/var/www/html/logs/ant-media-server.log", "r");
$fp2 = fopen("seek.txt", "r");
$seek = fgets($fp2);
fclose($fp2);

if(filesize("/var/www/html/logs/ant-media-server.log") < $seek){
    $seek = 0;
}
fseek($fp1, $seek);

while (($buff = fgets($fp1, 4096)) !== false) {
    if(substr($buff, 0, 2)!="20"){
        continue;
    }
    $dt = substr($buff, 0, 10);
    $tm = substr($buff, 11, 12);
    $ev = getInnerText($buff, "x-event:", " ");
    $ip = getInnerText($buff, "c-ip:", " ");
    $nm = getInnerText($buff, "x-name:", "");

    $nm2 = substr($nm, 0, -1);
    $tm2 = substr($tm, 0, -4);

    if($ev!=0 || $ip!=0 || $nm!=0 || $ev="unpublish" || $ev="publish" ){
        //if($ev==0){
        //	$ev = "";
        //}
        //if($ip==0){
        //	$ip = "";
        //}
        //if($nm==0){
        //	$nm = "";
        //}
        $tm2 = str_replace(",","\,",$tm2);
        $allpara = "$dt$tm$ev$ip$nm";
        print "$buff<br />date=$dt time=$tm2 x-event=$ev c-ip=$ip x-name=$nm2<br /><br />";
        $sql = "INSERT INTO accesslog(date,time,`xevent`,`cip`,`xsname`,allpara) VALUES ('$dt','$tm2','$ev','$ip','$nm2','$allpara')";
        print "$sql<br />";
        $result = mysqli_query($con, $sql);
    }
}
$seek = ftell($fp1);
$fp2 = fopen("/var/www/html/logs/seek.txt", "w");
fwrite($fp2, $seek);
fclose($fp2);
fclose($fp1);
mysqli_close($con);

print "</body></html>";

function getInnerText($str, $st, $en)
{
    //先頭文字位置を取得
    $sta = strpos($str, $st);
    if($sta === FALSE){
        return 0;
    }
    $sta += strlen($st);

    //終了文字位置を取得
    $ene = strpos($str, $en, $sta);
    if($ene === FALSE){
        //切り出し文字列を取得
        $key = substr($str, $sta);
    }else{
        //切り出し文字列を取得
        $key = substr($str, $sta, $ene-$sta);
    }

    return $key;
}
