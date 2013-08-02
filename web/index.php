<?php
/**
 * Web UI
 *
 * @author sskaje
 */
header('Content-type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Shanghai');

require(dirname(__FILE__) . '/../classes/spXunlei.php');
$config = new spXunleiConfig(dirname(__FILE__) . '/../config/sskaje.ini');

if ($config->webui['auth']) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== $config->webui['auth_user'] ||
        !isset($_SERVER['PHP_AUTH_PW']) || $_SERVER['PHP_AUTH_PW'] !== $config->webui['auth_pass']) {

        header('WWW-Authenticate: Basic realm="Xunlei Lixian Remote Downloader"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Login required';
        exit;
    }
}

?>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Xunlei Lixian Remote Downloader Web UI</title>
    <style type="text/css">
        .url_text {width:480px; height: 300px}
    </style>
</head>
<body>
<h1>Xunlei Lixian Remote Downloader</h1>
<h3>Author: sskaje (<a href="http://sskaje.me/">http://sskaje.me/</a>)</h3>
<form action="" method="post">
    URL: <textarea name="urls" placeholder="URL..." class="url_text"></textarea><br />
    <label><input type="checkbox" name="bt_download_all" value="1" />Download All Files in Torrent/Magnet?</label><br />
    <input type="submit" name="" value="Add task" />
</form>
<?php
if (isset($_POST['urls'])) {
    $xunlei = new spXunlei($config);
    try {
        $xunlei->login();
    } catch (Exception $e) {
        $xunlei->logException($e);
        exit;
    }

    # process options
    $options = array();
    if (isset($_POST['bt_download_all']) && $_POST['bt_download_all'] == 1) {
        $options['bt_download_all'] = true;
    }

    $urls = preg_split('#[\r\n]#', $_POST['urls'], -1, PREG_SPLIT_NO_EMPTY);

    foreach ($urls as $url) {
        $url = trim($url);
        if (empty($url)) {
            continue;
        }
        $xunlei->addTask($url, $options);
    }
}
?>
</body>
</html>