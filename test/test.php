<?php
require(dirname(__FILE__) . '/../classes/spXunlei.php');

$config = new spXunleiConfig(dirname(__FILE__) . '/../config/sskaje.ini');
$xunlei = new spXunlei($config);
$xunlei->login();
#$xunlei->addMagnet('magnet:?xt=urn:btih:AB4AE6CA7CE081291E41DEB2A28ECD49BA827A24&dn=under+the+dome+s01e03+720p+hdtv+x264+dimension+eztv&tr=udp%3A%2F%2Ffr33domtracker.h33t.com%3A3310%2Fannounce&tr=udp%3A%2F%2Fopen.demonii.com%3A1337');
#$xunlei->addMagnet('magnet:?xt=urn:btih:B1055DAFDAF9B11D50F76EE6E79886BA0A60D04A&dn=under+the+dome+s01e01+pilot+1080p+web+dl+dd5+1+h+264+ntb+public&tr=udp%3A%2F%2Ftracker.beeimg.com%3A6969%2Fannounce&tr=udp%3A%2F%2Fopen.demonii.com%3A1337');
#$xunlei->addMagnet('magnet:?xt=urn:btih:8A1BC5D412DE8583D5DC740B0EE7A6A1F9D30750&dn=under+the+dome+s01e03+720p+hdtv+x264+dimension&tr=udp%3A%2F%2Ffr33domtracker.h33t.com%3A3310%2Fannounce&tr=udp%3A%2F%2Fopen.demonii.com%3A1337');
#$xunlei->addMagnet('magnet:?xt=urn:btih:29228BE6836C0213BBFB172984CA47110F56E2BA&dn=under+the+dome+s01e03+hdtv+x264+lol+ettv&tr=http%3A%2F%2Ftracker.trackerfix.com%2Fannounce&tr=udp%3A%2F%2Fopen.demonii.com%3A1337');
#$xunlei->addMagnet('magnet:?xt=urn:btih:2D2DE9E1ACA6C127B1E3CF0420A09C4B96E70EF9&dn=under+the+dome+s01e03+720p+hdtv+x264+dimension+rartv&tr=http%3A%2F%2Fexodus.desync.com%3A6969%2Fannounce&tr=udp%3A%2F%2Fopen.demonii.com%3A1337');

# $xunlei->addTask('magnet:?xt=urn:btih:23276179FAA555142759E91F8985719AC5B37B3B&dn=under+the+dome+s01e03+hdtv+x264+lol+eztv&tr=udp%3A%2F%2Ffr33domtracker.h33t.com%3A3310%2Fannounce&tr=udp%3A%2F%2Fopen.demonii.com%3A1337');

$xunlei->addTask('ed2k://|file|Tom.Tykwer%2CJohnny.Klimek.%26amp%3B.Reinhold.Heil.-.%5B%E4%BA%91%E5%9B%BE.-.Cloud.Atlas%5D.%E4%B8%93%E8%BE%91.%28MP3%29.rar|186421558|cc065b94a1a5c99e3491bcf2d4db991e|h=nsbqlullyrrftwjhn6v7547p6qiycoa3|/');