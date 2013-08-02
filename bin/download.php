<?php
/**
 * Xunlei Lixian Remote Downloader CLI
 *
 * @author sskaje http://sskaje.me/
 * @version 0.1
 */

date_default_timezone_set('Asia/Shanghai');

if ($argc < 2) {
    usage();
}

$urls = array();
$options = array();
$config_file = dirname(__FILE__) . '/../config/sskaje.ini';

for ($i=1; $i<$argc; $i++) {
    if ($argv[$i][0] == '-') {
        if ($argv[$i] == '--bt-download-all' || $argv[$i] == '-a') {
            $options['bt_download_all'] = true;
        } else if ($argv[$i] == '--config' || $argv[$i] == '-c') {
            $config_file = getcwd() . '/' . $argv[++$i];
        } else if ($argv[$i] == '--help' || $argv[$i] == '-h') {
            usage();
        }
    } else {
        $urls[] = $argv[$i];
    }
}

$config_file = realpath($config_file);
if (empty($config_file)) {
    die("Configuration file not available. \n");
}

require(dirname(__FILE__) . '/../classes/spXunlei.php');

$config = new spXunleiConfig($config_file);
$xunlei = new spXunlei($config);
$xunlei->login();

foreach ($urls as $url) {
    $xunlei->addTask($url, $options);
}


function usage()
{
    global $argc, $argv;
    echo <<<USAGE
Xunlei Lixian Remote Downloader
Author: sskaje
Version: 0.1

USAGE: {$argv[0]} [options] LINK ...
    Options:
        -a, --bt-download-all               download all files in magnet or files recommended by xunlei
        -c, --config                        ini config file
        -h, --help                          display this menu

USAGE;
    exit;
}

# EOF