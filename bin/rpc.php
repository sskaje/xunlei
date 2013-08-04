<?php
/**
 * JSON RPC debugger script
 *
 * @author sskaje (http://sskaje.me/)
 */
date_default_timezone_set('Asia/Shanghai');

if ($argc < 2) {
    usage();
}

$options = array();
$config_file = dirname(__FILE__) . '/../config/sskaje.ini';

$method = '';
$param = '';
$args = array();

for ($i=1; $i<$argc; $i++) {
    if ($argv[$i][0] == '-') {
        if ($argv[$i] == '--config' || $argv[$i] == '-c') {
            $config_file = getcwd() . '/' . $argv[++$i];
        } else if ($argv[$i] == '--help' || $argv[$i] == '-h') {
            usage();
        } else if ($argv[$i] == '--method' || $argv[$i] == '-m') {
            $method = $argv[++$i];
        } else if ($argv[$i] == '--param' || $argv[$i] == '-p') {
            $param = $argv[++$i];
        }
    }
}

if (empty($method)) {
    usage();
}

if ($param) {
    $args = json_decode($param, true);
    if (!$args) {
        die('Invalid JSON parameter.');
    }
}

$config_file = realpath($config_file);
if (empty($config_file)) {
    die("Configuration file not available. \n");
}

require(dirname(__FILE__) . '/../classes/spXunlei.php');

$config = parse_ini_file($config_file, true);

$jsonrpc = new spJsonRPC(
    $config['downloader:aria2']['rpc_url'],
    array(
        'auth_user' =>  $config['downloader:aria2']['rpc_user'],
        'auth_pass' =>  $config['downloader:aria2']['rpc_pass'],
    )
);

try {
    $result = call_user_func_array(
        array($jsonrpc, $method),
        $args
    );

    var_dump($result);
} catch (Exception $e) {
    $exception_name = get_class($e);
    echo <<<EXCEPTION
Exception[{$exception_name}]: {$e->getCode()}#{$e->getMessage()}

EXCEPTION;
    exit;
}

function usage()
{
    global $argc, $argv;
    echo <<<USAGE
Xunlei Lixian Aria2 RPC Debugger
Author: sskaje
Version: 0.1

USAGE: {$argv[0]} options
    Options:
        -c, --config                        ini config file
        -h, --help                          display this menu
        -m, --method METHOD                 JSON RPC method, name like aria.xxx, more details on
                                                http://aria2.sourceforge.net/manual/en/html/aria2c.html#methods
        -p, --param PARAMETER               parameters, json format

USAGE;
    exit;
}

# EOF