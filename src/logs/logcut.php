<?php

ini_set('display_errors', "On");

// ブラウザバックしても処理が中断しないようにする
ignore_user_abort(true);

/** DB登録済みの行番号 */
const SEEK_FILE_PATH = '/var/www/html/logs/seek.txt';
/** 実施ログ */
const INVOKE_LOG_PATH = '/var/www/html/logs/invoke.log';
/** 取得対象ログファイル */
const APP_LOG_PATH = '/var/www/html/logs/ant-media-server.log';
/** 排他制御用ロックファイル */
const APP_EXCLUSION_CONTROL_PATH = '/var/www/html/logs/lock';

/** ファイル読み込み時の文字列長 */
const LINE_READ_STR_LENGTH = 4096;

/** 取得対象となるイベント情報 */
const REQUIRED_LOG_EVENT_STR = ['publish', 'unpublish'];

print <<< DOC1
<html><head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8">
<title>LogCut</title>
</head> 
<body>
DOC1;

// 処理の実施ログを追記
invokeLogging();

// MySQLに接続できなければ処理を終了
$con = getMysqlConnection();
if ($con === FALSE) {
    removeLockfile();
    print "DBサーバーに異常が発生しています。管理者へ連絡をお願いします。<br />";
    print mysqli_connect_error();
    return;
}

// 排他制御中(他のプロセスが実施中)であれば処理を終了
if (!checkProcessExecutable()) {
    print "別の処理が実施中です。時間をおいて再度実施ください。<br />";
    return;
}

// 直近のDB登録済みのアプリログを取得
$app_log_fp = fopen(APP_LOG_PATH, "r");

// 読み込み済みのseekポイントを取得
$seeked = getLatestSeek();

// アプリログを読み込み済みの位置に移動
fseek($app_log_fp, $seeked);

// 1行毎に読み込み
while (($buff = fgets($app_log_fp, LINE_READ_STR_LENGTH)) !== false) {

    // ログ内の日付を取得し、20~から始まらない場合は処理をスキップ
    $logged_date = (string)substr($buff, 0, 10);
    if (substr($logged_date, 0, 2) != "20") {
        continue;
    }

    // イベント情報を取得し unpublish or publish でなければ処理をスキップ
    $event = (string)getInnerText($buff, "x-event:", " ");
    if (!in_array($event, REQUIRED_LOG_EVENT_STR)) {
        continue;
    }

    // リクエスト元のIPが取得出来なければ処理をスキップ
    $ip = getInnerText($buff, "c-ip:", " ");
    if ($ip === 0) {
        continue;
    }

    // 実行者情報を取得出来なければ処理をスキップ
    $logged_x_name = getInnerText($buff, "x-name:");
    if ($logged_x_name === 0 || $logged_x_name === false) {
        continue;
    }

    // ログ内の日時を取得
    $logged_time = substr($buff, 11, 12);
    $db_insert_time = str_replace(",", "\,", substr($logged_time, 0, -4));

    // パラメータを結合させる
    $all_params = implode([$logged_date, $logged_time, $event, $ip, $logged_x_name]);

    // 実施するSQLを確認
    print "$buff<br />date=$logged_date time=$logged_time x-event=$event c-ip=$ip x-name=$logged_x_name<br /><br />";
    $sql = "INSERT INTO accesslog(date,time,`xevent`,`cip`,`xsname`,allpara)";
    $sql .= "VALUES ('$logged_date','$db_insert_time','$event','$ip','$logged_x_name','$all_params')";

    // DB登録処理
    try {
        $result = mysqli_query($con, $sql);
        print "$sql<br />";
        if (!$result) {
            throw new Exception();
        }
    } catch (Exception $e) {
        print "次の行のDBへの書き込み中にエラーが発生しました。<br>" . $buff . "<br/>";
        break;
    }

    // DBへの
    $seeked = getCurrentSeekPoint($app_log_fp);

}

// seek情報の更新
updateSeek($seeked);

// アプリログを閉じる
fclose($app_log_fp);

// MySQLの接続を閉じる
mysqli_close($con);

// ロックファイルを削除して排他制御を除去する
removeLockfile();

print "</body></html>";

/**
 * @param string $str
 *  検索元文字列
 * @param string $st
 *  検索対象文字列(開始文字列)
 * @param string $en
 *　検索対象文字列(終了文字列)
 * @return false|int|string
 */
function getInnerText(string $str, string $st, string $en = "\n")
{
    // 先頭文字位置を取得(出来なければ0を返す)
    $sta = strpos($str, $st);
    if ($sta === FALSE) {
        return 0;
    }

    // 取得開始位置を設定
    $sta += strlen($st);

    // 改行コードを\nに統一
    $str = str_replace(["\r\n", "\r"], "\n", $str);

    // 切り出し終了位置を取得
    $ene = strpos($str, $en, $sta);

    // 文字の切り出し（切り出し終了位置が無ければ末尾まで取得）
    return substr($str, $sta, (($ene ?: LINE_READ_STR_LENGTH) - $sta));
}

/**
 * 処理の実施時刻をログに残す
 * @return void
 */
function invokeLogging(): void
{
    $invoke_log_fp = fopen(INVOKE_LOG_PATH, 'a');
    fwrite($invoke_log_fp, date("Y-m-d H:i:s") . " Invoked.\r\n");
    fclose($invoke_log_fp);
}

/**
 * DBの接続情報を取得する
 * @return false|mysqli
 */
function getMysqlConnection()
{
    return mysqli_connect(
        'mysql', // Docker用 // TODO localhost に戻す
        "voicepa_voicepa",
        "uwfrom359road",
        "voicepa_logs"
    );
}

/**
 * 直近のシーク済みの番号を取得する
 * @return int
 */
function getLatestSeek(): int
{
    $seek_fp = fopen(SEEK_FILE_PATH, "r");
    $seek = fgets($seek_fp);
    fclose($seek_fp);
    if (filesize(APP_LOG_PATH) < $seek) {
        $seek = 0;
    }
    return $seek;
}

/**
 * ログファイルの現在の読み込み位置を取得
 * @param $app_log_fp
 * @return int
 */
function getCurrentSeekPoint($app_log_fp): int
{
    return (int)ftell($app_log_fp);
}

/**
 * DBへの登録が終了したところまでアップデートする
 * @param int $seeked
 * @return void
 */
function updateSeek(int $seeked): void
{
    $fp2 = fopen(SEEK_FILE_PATH, 'w');
    fwrite($fp2, $seeked);
    fclose($fp2);
}

/**
 * 排他制御用のロックファイルの存在を確認し、処理の実施可否を確認する
 * @return bool
 */
function checkProcessExecutable(): bool
{
    if (file_exists(APP_EXCLUSION_CONTROL_PATH)) {
        return false;
    }
    return touch(APP_EXCLUSION_CONTROL_PATH);
}

/**
 * ロックファイルを削除して、後続の処理を実施可能にする
 * @return void
 */
function removeLockfile(): void
{
    unlink(APP_EXCLUSION_CONTROL_PATH);
}