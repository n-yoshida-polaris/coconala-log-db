<?php

// MySQLに接続できなければ処理を終了
$con = getMysqlConnection();
if ($con === FALSE) {
    removeLockfile();
    print "DBサーバーに異常が発生しています。管理者へ連絡をお願いします。<br />";
    print mysqli_connect_error();
    return;
}
$sql = "DELETE FROM accesslog WHERE 1=1;";
try {
    $result = mysqli_query($con, $sql);
    print "$sql<br />";
    if (!$result) {
        throw new Exception();
    }
} catch (Exception $exception) {
    print "accesslogテーブルのデータ削除に失敗しました。<br>" . $sql . "<br/>";
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