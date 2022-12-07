<?php

/** DB登録済みの行番号 */
const SEEK_FILE_PATH = '/var/www/html/logs/seek.txt';
/** 実施ログ */
const INVOKE_LOG_PATH = '/var/www/html/logs/invoke.log';
/** 取得対象ログファイル */
const APP_LOG_PATH = '/var/www/html/logs/ant-media-server.log';
/** 排他制御用ロックファイル */
const APP_EXCLUSION_CONTROL_PATH = '/var/www/html/logs/lock';


file_put_contents(SEEK_FILE_PATH,'');
file_put_contents(INVOKE_LOG_PATH,'');
@unlink(APP_EXCLUSION_CONTROL_PATH);

require_once dirname(__DIR__) .  "/html/logs/accesslog_empty.php";