<?php
/**
 * Web UI
 *
 * @author sskaje
 */
require(__DIR__ . '/../classes/spXunlei.php');
$config = new spXunleiConfig(__DIR__ . '/../config/sskaje.ini');

if ($config->webui['auth']) {
    if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== $config->webui['auth_user'] ||
        !isset($_SERVER['PHP_AUTH_PW']) || $_SERVER['PHP_AUTH_PW'] !== $config->webui['auth_pass']) {

        header('WWW-Authenticate: Basic realm="Xunlei Lixian Remote Downloader"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Login required';
        exit;
    }
}



if (isset($_POST['urls'])) {
    # TODO: multi-task support
    $urls = (array) $_POST['urls'];


    $xunlei = new spXunlei($config);
    $xunlei->login();

    # process options
    $options = array();
    if (isset($_POST['bt_download_all']) && $_POST['bt_download_all'] == 1) {
        $options['bt_download_all'] = true;
    }

    foreach ($urls as $url) {
        $xunlei->addTask($url, $options);
    }
}

?>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Xunlei Lixian Remote Downloader Web UI</title>
</head>
<body>
    <form action="" method="post">
        URL: <input type="text" name="urls[]" value="" placeholder="URL..." /><br />
        URL: <input type="text" name="urls[]" value="" placeholder="URL..." /><br />
        <label><input type="checkbox" name="bt_download_all" value="1" />Download All Files in Torrent/Magnet?</label><br />
        <input type="submit" name="" value="Add taskit" />
    </form>

</body>
</html>