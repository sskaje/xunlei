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
$ROOT_DIR = dirname(__FILE__) . '/..';

$urls = array();
$options = array();
$config_file = $ROOT_DIR . '/config/sskaje.ini';
$verify = '';

for ($i=1; $i<$argc; $i++) {
    if ($argv[$i][0] == '-') {
        if ($argv[$i] == '--bt-download-all' || $argv[$i] == '-a') {
            $options['bt_download_all'] = true;
        } else if ($argv[$i] == '--config' || $argv[$i] == '-c') {
            $config_file = $argv[++$i];
        } else if ($argv[$i] == '--help' || $argv[$i] == '-h') {
            usage();
        } else if ($argv[$i] == '--clear-tmp') {
            rrmdir($ROOT_DIR . '/tmp');
            mkdir($ROOT_DIR . '/tmp');
            touch($ROOT_DIR . '/tmp/.place_holder');
            echo "tmp folder cleaned\n";
            exit;
        } else if ($argv[$i] == '--verify' || $argv[$i] == '-v') {
            $verify = $argv[++$i];
        }
    } else {
        $urls[] = $argv[$i];
    }
}

$config_file = realpath($config_file);
if (empty($config_file)) {
    die("Configuration file not available. \n");
}

require($ROOT_DIR . '/classes/spXunlei.php');

$config = new spXunleiConfig($config_file);
$xunlei = new spXunlei($config);
try {
    $xunlei->login($verify);
} catch (SPExceptionXunlei_RequestVerify $e) {
    $tmpfile = tempnam($ROOT_DIR . '/tmp/', 'captcha_') . '.jpg';
    $image = $xunlei->getVerifyImage();
    file_put_contents($tmpfile, $image);

    echo "Login catpcha required, image written to {$tmpfile}. Please add -v to set captcha\n";
    exit;
} catch (Exception $e) {
    echo 'Exception: ', $e->getMessage(), '#', $e->getCode(), "\n";
    exit;
}

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
        -h, --help                          display this menu
        -a, --bt-download-all               download all files in magnet or files recommended by xunlei
        -c, --config FILE                   ini config file
        -v, --verify CODE                   verify code for login
        --clear-tmp                         clear tmp folder

USAGE;
    exit;
}

# recursively remove a directory
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir."/".$object) == "dir") {
                    rrmdir($dir."/".$object);
                } else {
                    unlink($dir."/".$object);
                }
            }
        }
        reset($objects);
        rmdir($dir);
    }
}
# EOF