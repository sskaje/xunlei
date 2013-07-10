<?php
/**
 * Xunlei Lixian Remote Downloader CLI
 *
 * @author sskaje http://sskaje.me/
 * @version 0.1
 */
if ($argc < 2) {
    echo <<<USAGE
Xunlei Lixian Remote Downloader
Author: sskaje
Version: 0.1

USAGE: {$argv[0]} [options] LINK ...
    Options:
        --bt-download-all               download all files in magnet or files recommended by xunlei

USAGE;
    exit;
}

#


require(__DIR__ . '/../classes/spXunlei.php');

$config = new spXunleiConfig(__DIR__ . '/../config/sskaje.ini');
$xunlei = new spXunlei($config);
$xunlei->login();

$urls = array();
$options = array();

for ($i=1; $i<$argc; $i++) {
    if ($argv[0] == '-') {
        if ($argv[$i] == '--bt-download-all') {
            $options['bt_download_all'] = true;
        }
    } else {
        $urls[] = $argv[$i];
    }
}

foreach ($urls as $url) {
    $xunlei->addTask($url, $options);
}