<?php
/**
 * Xunlei download class
 *
 * @author sskaje http://sskaje.me/
 */

define('SPXL_CLASSROOT', defined('__DIR__') ? __DIR__ : dirname(__FILE__));

class spXunlei
{

    protected $config;

    public function __construct(spXunleiConfig $config)
    {
        $this->config = $config;
    }

    public function isLoggedIn()
    {
        $this->http_get('http://dynamic.cloud.vip.xunlei.com/login?from=0', array(CURLOPT_FOLLOWLOCATION=>0));

        if ($this->http_code == '302' && strpos($this->redirect_url, 'user_task') !== false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 登录
     *
     * @throws SPException
     */
    public function login()
    {
        $login_count = 0;
        while (!$this->isLoggedIn()) {
            if ($login_count++ > 5) {
                throw new SPException('Login failed');
            }

            $url_login_check = 'http://login.xunlei.com/check?u='.urlencode($this->config->username).'&cachetime=' . time();
            $this->http_get($url_login_check);

            list(,$verify_code) = explode(':', $this->cookie_store['check_result']);
            $password = md5(md5(md5($this->config->password)) . strtoupper($verify_code));
            $login_post = 'u='.urlencode($this->config->username).'&p='.$password.'&verifycode='.$verify_code.'&login_enable=1&login_hour=720';
            $s = $this->http_post('http://login.xunlei.com/sec2login/', $login_post, array(CURLOPT_REFERER=>'http://lixian.vip.xunlei.com/task.html'));
        }
    }

    /**
     * gdriveid is the only useful cookie key for download
     *
     * @return bool|string
     */
    protected function getGDriveID()
    {
        # get cookie gdriveid
        if (!isset($this->cookie_store['gdriveid'])) {
            $j = $this->getTasks(1, 1);

            if (isset($j['info']['user']['cookie'])) {
                $gdriveid = $j['info']['user']['cookie'];

                $ch = $this->http_init();
                curl_setopt($ch, CURLOPT_COOKIE, 'gdriveid=' . $gdriveid . '; path=/; domain=.xunlei.com');
                $this->cookie_store['gdriveid'] = $gdriveid;
                return $gdriveid;
            }

            return false;
        } else {
            return $this->cookie_store['gdriveid'];
        }
    }

    public function log($message, $level=LOG_INFO)
    {
        $level_text = array(
            LOG_INFO    =>  'INFO',
            LOG_ERR     =>  'ERROR',
            LOG_NOTICE  =>  'NOTICE',
            LOG_WARNING =>  'WARNING',
        );
        $level = isset($level_text[$level]) ? $level_text[$level] : 'INFO';

        $message = date('Y-m-d H:i:s') . "[{$level}] " . trim($message);

        if (php_sapi_name() == 'cli') {
            # write to stderr
            fwrite(STDERR, "{$message}\n");
        } else {
            echo "{$message}<br />\n";
        }

        error_log($message, 3, $this->config->log_file);
    }

    public function logException($e)
    {
        $this->log("{$e->getMessage()}#{$e->getCode()}", LOG_ERR);
    }

    /**
     * add download task
     *
     * @param string $url
     * @param array $options
     * @return bool
     */
    public function addTask($url, $options=array())
    {
        $this->log("Add task: " . $url);
        try {
            if (strpos($url, 'magnet:') === 0) {
                $this->addMagnet($url, $options);
            } else if (strpos($url, 'ed2k://|file') === 0) {
                $this->addEd2k($url, $options);
            } else if (strpos($url, 'http://') === 0 ||
                strpos($url, 'https://') === 0 ||
                strpos($url, 'ftp://') === 0 ||
                strpos($url, 'thunder://') === 0 ||
                strpos($url, 'flashget://') === 0 ||
                strpos($url, 'qqdl://') === 0 ||
                strpos($url, 'mms://') === 0 ||
                strpos($url, 'rtsp://') === 0
            ){
                # 简单处理远程torrent文件
                if (preg_match('#\.torrent$#i', $url)) {
                    $this->addTorrent($url);
                } else {
                    $this->addDefault($url, $options);
                }
            } else {
                throw new SPException('URL not supported yet');
            }
            return true;
        } catch (Exception $e) {
            $this->logException($e);
            return false;
        }
    }

    public function queryTaskUrl($task_url)
    {
        $this->log('Query task url: ' . $task_url);
        # xml http?
        $url = 'http://dynamic.cloud.vip.xunlei.com/interface/url_query?callback=queryUrl&u='.urlencode($task_url).'&interfrom=task&random=1373351089035142391.49535565483&tcache=1373351093166';

        $s = $this->http_post($url, '');
        $fake_json = str_replace(
            array('queryUrl(', 'new Array(', '\')', '\''),
            array('[', '[', '\']', '"'),
            $s
        );
        $j = json_decode($fake_json, true);
        if (!$j) {
            throw new SPException('Query task url: failed, invalid json?');
        }
        $this->log('Query task url: '  . "\tinfohash={$j[1]}");
        $this->log('Query task url: '  . "\tfsize={$j[2]}");
        $this->log('Query task url: '  . "\tbt_title={$j[3]}");

        $this->log('Query task url: '  . "\tfilelist:");
        foreach ($j[5] as $k=>$file) {
            $this->log('Query task url:' . "\t".($j[8][$k] ? '+' : '-')."\t{$file} [{$j[6][$k]}]" );
        }

        return array(
            'flag'          =>  $j[0],
            'infohash'      =>  $j[1],
            'fsize'         =>  $j[2],
            'bt_title'      =>  $j[3],
            'is_full'       =>  $j[4],
            'subtitle'      =>  $j[5],
            'subformatsize' =>  $j[6],
            'size_list'     =>  $j[7],
            'valid_list'    =>  $j[8],
            'file_icon'     =>  $j[9],
            'findex'        =>  $j[10],
            'random'        =>  $j[11],
            'rtcode'        =>  $j[12],
        );
    }

    /**
     * 添加远程torrent文件，并自动切换成bt下载
     *
     * @param string $torrent
     * @param array $options
     * @throws SPException
     */
    public function addTorrent($torrent, $options=array())
    {
        if (stripos($torrent, 'http') !== 0 || !preg_match('#\.torrent$#i', $torrent)) {
            throw new SPException('Currently only support torrent url end with .torrent');
        }

        $task_info = $this->queryTaskUrl($torrent);

        $gdriveid = $this->getGDriveID();
        $this->log('gdriveid=' . $gdriveid);
        $jsonp = $this->jsonpName('jsonp');
        $url = 'http://dynamic.cloud.vip.xunlei.com/interface/bt_task_commit?callback='.$jsonp.'&t='.urlencode(strftime('%c')); #.'Tue%20Jul%2009%202013%2014:30:48%20GMT+0800%20(CST)';
        $post = 'uid=' . $this->cookie_store['userid'];
        $post .= '&btname=' . urlencode($task_info['bt_title']);
        $post .= '&cid=' . $task_info['infohash'];
        $post .= '&goldbean=0&silverbean=0';
        $post .= '&tsize=' . $task_info['fsize'];
        # findex=0_&size=296075521_

        # valid_list
        if (isset($options['bt_download_all'])) {
            $findex = implode('_', $task_info['findex']) . '_';
            $check = count($task_info['findex']);
            $this->log('Torrent: download all files');
        } else {
            $findex = '';
            $this->log('Torrent: download valid files');
            $check = 0;
            foreach ($task_info['valid_list'] as $k=>$v) {
                if ($v == 0) continue;
                ++$check;
                $findex .= $task_info['findex'][$k] . '_';

                $this->log('Torrent:' . "\t\t{$task_info['subtitle'][$k]} [{$task_info['subformatsize'][$k]}]" );
            }
        }
        $post .= '&check='  . $check;
        $post .= '&findex=' . $findex;
        $post .= '&class_id=0';

        $s = $this->http_post($url, $post);

        $this->log('bt_task_commit: ' . $s);
    }

    /**
     * add magnet link
     *
     * @param string $magnet
     * @param array  $options
     * @return bool
     * @throws SPException
     */
    public function addMagnet($magnet, $options=array())
    {
        if (strpos($magnet, 'magnet:') !== 0) {
            throw new SPException('invalid magnet url');
        }

        # query task url
        # queryUrl(1,'23276179FAA555142759E91F8985719AC5B37B3B','296075521','Under.the.Dome.S01E03.HDTV.x264-LOL.mp4','0',new Array('Under.the.Dome.S01E03.HDTV.x264-LOL.mp4'),new Array('282M'),new Array('296075521'),new Array('1'),new Array('WMA'),new Array('0'),'13733514389281489644.844500954','0')
        # queryUrl(1,'2D2DE9E1ACA6C127B1E3CF0420A09C4B96E70EF9','1175043184','Under.the.Dome.S01E03.720p.HDTV.X264-DIMENSION[rarbg]','0',new Array('RARBG.com.txt','Sample\\under.the.dome.103.720p-dimension.sample.mkv','Under.the.Dome.S01E03.720p.HDTV.X264-DIMENSION.mkv','under.the.dome.103.720p-dimension.nfo'),new Array('34.0B','30.2M','1.06G','228B'),new Array('34','31679176','1143364008','228'),new Array('0','1','1','0'),new Array('PHP','RMVB','RMVB','RAR'),new Array('0','1','2','3'),'13733515851831714256.0756551414','0')
        # commit url
        # http://dynamic.cloud.vip.xunlei.com/interface/bt_task_commit?callback=jsonp1373350685430&t=Tue%20Jul%2009%202013%2014:30:48%20GMT+0800%20(CST)
        # uid=139154715&btname=Under.the.Dome.S01E03.HDTV.x264-LOL.mp4&cid=23276179FAA555142759E91F8985719AC5B37B3B&goldbean=0&silverbean=0&tsize=296075521&findex=0_&size=296075521_&o_taskid=0&o_page=task&class_id=0&interfrom=task
        # queryUrl(flag,infohash,fsize,bt_title,is_full,subtitle,subformatsize,size_list,valid_list,file_icon,findex,random,rtcode)
        $task_info = $this->queryTaskUrl($magnet);

        $gdriveid = $this->getGDriveID();
        $this->log('gdriveid=' . $gdriveid);

        $jsonp = $this->jsonpName('jsonp');
        $url = 'http://dynamic.cloud.vip.xunlei.com/interface/bt_task_commit?callback='.$jsonp.'&t='.urlencode(strftime('%c')); #.'Tue%20Jul%2009%202013%2014:30:48%20GMT+0800%20(CST)';
        $post = 'uid=' . $this->cookie_store['userid'];
        $post .= '&btname=' . urlencode($task_info['bt_title']);
        $post .= '&cid=' . $task_info['infohash'];
        $post .= '&goldbean=0&silverbean=0';
        $post .= '&tsize=' . $task_info['fsize'];
        # findex=0_&size=296075521_

        # valid_list
        if (isset($options['bt_download_all'])) {
            $findex = implode('_', $task_info['findex']) . '_';
            $size = implode('_', $task_info['size_list']) . '_';
            $this->log('Magnet: download all files');
        } else {
            $findex = '';
            $size = '';
            $this->log('Magnet: download valid files');
            foreach ($task_info['valid_list'] as $k=>$v) {
                if ($v == 0) continue;
                $findex .= $task_info['findex'][$k] . '_';
                $size .= $task_info['size_list'] . '_';

                $this->log('Magnet:' . "\t\t{$task_info['subtitle'][$k]} [{$task_info['subformatsize'][$k]}]" );
            }
        }

        $post .= '&findex=' . $findex;
        $post .= '&size=' . $size;
        $post .= '&o_taskid=0&o_page=task&class_id=0&interfrom=task';

        $s = $this->http_post($url, $post);

        $this->log('bt_task_commit: ' . $s);

        if (strpos($s, '"progress":1') === false) {
            throw new SPException('Not yet downloaded');
        }
        $m = array();
        preg_match('#"id":"(\d+)"#', $s, $m);

        $page = 0;

        do {
            ++$page;
            $url = 'http://dynamic.cloud.vip.xunlei.com/interface/fill_bt_list?callback=fill_bt_list';
            $url .= '&tid=' . $m[1];
            $url .= '&infoid=' . $task_info['infohash'];
            $url .= '&g_net=1&p=' . $page;
            $url .= '&uid=' . $this->cookie_store['userid'];
            $url .= '&interfrom=task&noCacheIE=';

            $s = $this->http_get($url);
            $this->log('fill_bt_list: tid=' . $m[1] . ', infohash=' . $task_info['infohash']);

            $json = substr($s, strlen('fill_bt_list('), -1);
            $j = json_decode($json, true);
            if (!$j || empty($j['Result']['Record'])) {
                $this->log('fill_bt_list: No file found, something went wrong?');
                return ;
            }

            $cookie = 'gdriveid=' . $gdriveid;
            # send task:
            foreach ($j['Result']['Record'] as $file) {
                if ($file['download_status'] != 2 || empty($file['downurl'])) {
                    $this->log('Magnet: file not available yet. title='.$file['title']);
                    continue;
                }

                $url = $file['downurl'];
                $title = $file['title'];

                $this->getDownloader()->download(
                    $url,
                    array(
                        'infohash'  =>  $j['Result']['Infoid'],
                        'cookie'    =>  $cookie,
                        'filename'  =>  $title,
                    )
                );
            }

        } while (ceil($j['Result']['btnum'] / $j['Result']['btpernum']) >= $page);
    }

    public function queryTaskCid($url)
    {
        $url = 'http://dynamic.cloud.vip.xunlei.com/interface/task_check?callback=queryCid&url='.urlencode($url).'&interfrom=task&random=13734447016551856571.3246436683&tcache=1373444705857';

        # queryCid('', '', '3517912090','1125730526239124', 'Flight.of.the.Navigator.1986.720p.BluRay.X264-7SinS.mkv', 0, 0, 0,'13734447016551856571.3246436683','movie','0')
        # queryCid(cid,gcid,file_size,avail_space,tname,goldbean_need,silverbean_need,is_full,random,type,rtcode)
        $s = $this->http_post($url, '');
        $this->log('Query task cid: ' . $s);

        $fake_json = str_replace(
            array('queryCid(', 'new Array(', '\')', '\''),
            array('[', '[', '\']', '"'),
            $s
        );

        $j = json_decode($fake_json, true);

        return array(
            'cid'               =>  $j[0],
            'gcid'              =>  $j[1],
            'fsize'             =>  $j[2],
         #   'avail_space'       =>  $j[3],
            'tname'             =>  $j[4],
         #   'goldbean_need'     =>  $j[5],
         #   'silverbean_need'   =>  $j[6],
            'is_full'           =>  $j[7],
         #   'random'            =>  $j[8],
         #   'type'              =>  $j[9],
         #   'rtcode'            =>  $j[12],
        );
    }

    /**
     * add ed2k link
     *
     * @param string $ed2k
     * @param array  $options
     * @throws SPException
     */
    public function addEd2k($ed2k, $options=array())
    {
        # ed2k://|file|Flight.of.the.Navigator.1986.720p.BluRay.X264-7SinS.mkv|3517912090|D7ABA3230E00007C9887A40106838614|h=H7GB4TSG5KJ7RTBZN7MU5H7XX5KQSHQ3|/
        if (strpos($ed2k, 'ed2k://|file|') !== 0) {
            throw new SPException('Invalid ed2k url');
        }
        $str = substr(trim($ed2k), strlen('ed2k://|file|'));
        list($filename, $size, $hash, ) = explode('|', $str, 4);

        $task_info = $this->queryTaskCid($ed2k);
        if ($task_info['fsize'] === '0' && $task_info['cid'] === '' && $task_info['gcid'] === '') {
            $this->log('ed2k: fsize=0, file not downloaded yet?');
        }

        # http://dynamic.cloud.vip.xunlei.com/interface/task_commit?callback=ret_task&uid=139154715&cid=&gcid=&size=3517912090&goldbean=0&silverbean=0&t=Flight.of.the.Navigator.1986.720p.BluRay.X264-7SinS.mkv&url=ed2k%3A%2F%2F%7Cfile%7CFlight.of.the.Navigator.1986.720p.BluRay.X264-7SinS.mkv%7C3517912090%7CD7ABA3230E00007C9887A40106838614%7Ch%3DH7GB4TSG5KJ7RTBZN7MU5H7XX5KQSHQ3%7C%2F&type=2&o_page=history&o_taskid=0&class_id=0&database=undefined&interfrom=task&time=Wed%20Jul%2010%202013%2016:25:20%20GMT+0800%20(CST)&noCacheIE=1373444720566
        $url = 'http://dynamic.cloud.vip.xunlei.com/interface/task_commit?callback=ret_task';
        $url .= '&uid=' . $this->cookie_store['userid'];
        $url .= '&cid=&gcid=';
        $url .= '&size=' . $size;
        $url .= '&goldbean=0&silverbean=0';
        $url .= '&t=' . urlencode($filename);
        $url .= '&url=' . urlencode($ed2k);
        $url .= '&type=2&o_page=history&o_taskid=0&class_id=0&database=undefined&interfrom=task';
        $url .= '&time='.urlencode(strftime('%c'));
        $url .= '&noCacheIE=';

        $s = $this->http_get($url);

        $fake_json = str_replace(
            array('ret_task(', 'new Array(', '\')', '\''),
            array('[', '[', '\']', '"'),
            $s
        );
        $j = json_decode($fake_json, true);
        if ($j[0] == 0 || $j[0] == 75) {
            throw new SPException('failed to add ed2k task');
        }
        $task_id = $j[1];
        $this->log('task_commit: output=' . $s);

        $flag_found_task = false;
        $pagesize = 20;
        do {
            $this->log("Find task in latest {$pagesize}");
            $j = $this->getTasks(1, $pagesize);

            if (is_array($j) && isset($j['info']['tasks'])) {
                foreach ($j['info']['tasks'] as $task) {
                    if ($task['id'] === $task_id) {
                        $flag_found_task = true;
                        $this->log('task found: task_id='.$task['id']);
                        break;
                    }
                }
            }
            $pagesize += 20;
        } while (!$flag_found_task && $pagesize <= 60);

        if (!$flag_found_task) {
            throw new SPException('task add: task not found');
        }

        if ($task['download_status'] != 2) {
            throw new SPException('download not finished');
        }
        $this->log('task add: tid=' . $task_id . ' infohash=' . $task['cid']);

        $url = $task['lixian_url'];
        $cookie = 'gdriveid=' . $j['info']['user']['cookie'];
        $title = $filename;

        $this->getDownloader()->download(
            $url,
            array(
                'infohash'  =>  $task['cid'],
                'cookie'    =>  $cookie,
                'filename'  =>  $title,
            )
        );
    }

    public function addDefault($in_url, $options=array())
    {
        # ed2k://|file|Flight.of.the.Navigator.1986.720p.BluRay.X264-7SinS.mkv|3517912090|D7ABA3230E00007C9887A40106838614|h=H7GB4TSG5KJ7RTBZN7MU5H7XX5KQSHQ3|/

        $task_info = $this->queryTaskCid($in_url);
        if ($task_info['fsize'] === '0' && $task_info['cid'] === '' && $task_info['gcid'] === '') {
            $this->log('ed2k: fsize=0, file not downloaded yet?');
        }

        # http://dynamic.cloud.vip.xunlei.com/interface/task_commit?callback=ret_task&uid=139154715&cid=&gcid=&size=3517912090&goldbean=0&silverbean=0&t=Flight.of.the.Navigator.1986.720p.BluRay.X264-7SinS.mkv&url=ed2k%3A%2F%2F%7Cfile%7CFlight.of.the.Navigator.1986.720p.BluRay.X264-7SinS.mkv%7C3517912090%7CD7ABA3230E00007C9887A40106838614%7Ch%3DH7GB4TSG5KJ7RTBZN7MU5H7XX5KQSHQ3%7C%2F&type=2&o_page=history&o_taskid=0&class_id=0&database=undefined&interfrom=task&time=Wed%20Jul%2010%202013%2016:25:20%20GMT+0800%20(CST)&noCacheIE=1373444720566
        $url = 'http://dynamic.cloud.vip.xunlei.com/interface/task_commit?callback=ret_task';
        $url .= '&uid=' . $this->cookie_store['userid'];
        $url .= '&cid=' . $task_info['cid'];
        $url .= '&gcid=' . $task_info['gcid'];
        $url .= '&size=' . $task_info['fsize'];
        $url .= '&goldbean=0&silverbean=0';
        $url .= '&t=' . urlencode($task_info['tname']);
        $url .= '&url=' . urlencode($in_url);
        $url .= '&type=0&o_page=history&o_taskid=0&class_id=0&database=undefined&interfrom=task';
        $url .= '&time='.urlencode(strftime('%c'));
        $url .= '&noCacheIE=';

        $s = $this->http_get($url);

        $fake_json = str_replace(
            array('ret_task(', 'new Array(', '\')', '\''),
            array('[', '[', '\']', '"'),
            $s
        );
        $j = json_decode($fake_json, true);
        if ($j[0] == 0 || $j[0] == 75) {
            throw new SPException('failed to add default task');
        }
        $task_id = $j[1];
        $this->log('task_commit: output=' . $s);

        $flag_found_task = false;
        $pagesize = 20;
        do {
            $this->log("Find task in latest {$pagesize}");
            $j = $this->getTasks(1, $pagesize);

            if (is_array($j) && isset($j['info']['tasks'])) {
                foreach ($j['info']['tasks'] as $task) {
                    if ($task['id'] === $task_id) {
                        $flag_found_task = true;
                        $this->log('task found: task_id='.$task['id']);
                        break;
                    }
                }
            }
            $pagesize += 20;
        } while (!$flag_found_task && $pagesize <= 60);

        if (!$flag_found_task) {
            throw new SPException('task add: task not found');
        }

        if ($task['download_status'] != 2) {
            throw new SPException('download not finished');
        }
        $this->log('task add: tid=' . $task_id . ' infohash=' . $task['cid']);

        $url = $task['lixian_url'];
        $cookie = 'gdriveid=' . $j['info']['user']['cookie'];
        $title = $task_info['tname'];

        $this->getDownloader()->download(
            $url,
            array(
                'infohash'  =>  $task['cid'],
                'cookie'    =>  $cookie,
                'filename'  =>  $title,
            )
        );
    }

    protected $download_status = array(
        0   =>  "等待",
        1   =>  "下载中",
        2   =>  "完成",
        3   =>  "失败",
        4   =>  "",
        5   =>  "",
        6   =>  "",
    );

    protected $type_ids = array(
        4   =>  '全部',
        1   =>  '正在下载',
        2   =>  '已下载',
        11  =>  '已删除',
        13  =>  '已过期',
    );

    protected $task_types = array(
        0   =>  'default',
        1   =>  'torrent',
        2   =>  'ed2k',
        3   =>  '???',
        4   =>  'magnet',
    );

    /**
     * Random JSONP argument
     *
     * @param string $prefix
     * @return string
     */
    protected function jsonpName($prefix)
    {
        return $prefix . (microtime(1) * 1000000);
    }

    /**
     * Get task list
     *
     * @param int $page
     * @param int $pagesize
     * @return mixed
     */
    public function getTasks($page, $pagesize)
    {
        #type_id: 4 全部, 1 正在下载, 2 已下载, 13 已过期, 11 已删除
        $jsonp = $this->jsonpName('jsonp');

        $url = 'http://dynamic.cloud.vip.xunlei.com/interface/showtask_unfresh?callback='.$jsonp.'&t=' . urlencode(strftime('%c'));;
        $url .= '&type_id=4';
        $url .= '&page='.$page;
        $url .= '&tasknum=' . $pagesize;
        $url .= '&p=' . $pagesize;
        $url .= '&interfrom=task';

        $s = $this->http_get($url);
        $json = substr($s, strlen($jsonp.'('), -1);
        $j = json_decode($json, true);

        return $j;
    }


    /**
     * get downloader object by configurations
     *
     * @return spXunleiDownloader
     */
    public function getDownloader()
    {
        return spXunleiDownloader::Create($this->config->downloader, $this->config->downloader_config);
    }

    /**
     * get curl resource handler
     *
     * @return resource
     */
    protected function http_init()
    {
        static $ch = null;
        if (empty($ch)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_VERBOSE, 0);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'curlProcessHeaders'));
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->config->cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->config->cookie_file);

            # read cookie file to initialize cookie_store
            if (is_file($this->config->cookie_file)) {
                $file = file($this->config->cookie_file);
                foreach ($file as $line) {
                    $line = trim($line);
                    if (empty($line) || strpos($line, '#') === 0) {
                        continue;
                    }
                    $cols = explode("\t", $line, 7);
                    if (isset($cols[6])) {
                        $this->cookie_store[$cols[5]] = $cols[6];
                    }
                }
            }
        }

        return $ch;
    }

    /**
     * perform http get
     *
     * @param string $url
     * @param array $opt
     * @return mixed
     */
    protected function http_get($url, array $opt=array())
    {
        $ch = $this->http_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);

        if (!empty($opt)) {
            curl_setopt_array($ch, $opt);
        }

        $content = $this->http_exec();

        return $content;
    }

    /**
     * perform http post
     * @param string $url
     * @param mixed $data
     * @param array $opt
     * @return mixed
     */
    protected function http_post($url, $data, array $opt=array())
    {
        $ch = $this->http_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPGET, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        if (!empty($opt)) {
            curl_setopt_array($ch, $opt);
        }

        $content = $this->http_exec();

        return $content;
    }

    /**
     * all cookies
     *
     * @var array
     */
    protected $cookie_store = array();

    protected $redirect_url = '';

    protected $http_code = '';

    protected function curlResetProcessedHeaders()
    {
        $this->redirect_url = '';
        $this->http_code = '';
    }

    /**
     * Process headers
     * 
     * @param resource $curl
     * @param string $header
     */
    protected function curlProcessHeaders($curl, $header)
    {
        if (0 === strpos($header, 'HTTP/')) {
            list(, $this->http_code, ) = explode(' ', $header, 3);
        }

        if (0 === stripos($header, 'Set-Cookie')) {
            $t = explode('=', substr($header, strlen('Set-Cookie: '), strpos($header, '; ')-strlen('Set-Cookie: ')), 2);

            $this->cookie_store[$t[0]] = $t[1];
        }

        # save location: url
        # TODO: url host ?
        if (0 === stripos($header, 'location')) {
            list(, $this->redirect_url) = explode(':', $header, 2);
            $this->redirect_url = trim($this->redirect_url);
        }

        return strlen($header);
    }

    /**
     * execute http request
     *
     * @return mixed
     */
    protected function http_exec()
    {
        $ch = $this->http_init();
        $this->curlResetProcessedHeaders();
        $result = curl_exec($ch);
        return $result;
    }

    /**
     * get curl info
     *
     * @return mixed
     */
    protected function http_info()
    {
        $ch = $this->http_init();
        return curl_getinfo($ch);
    }
}


/**
 * Config class
 *
 * @author sskaje http://sskaje.me/
 */
class spXunleiConfig
{
    public $username;
    public $password;

    public $cookie_file;
    public $download_dir;
    public $log_file;

    public $downloader;

    public $downloader_config = array();

    public $webui = array();

    public function __construct($ini_file='')
    {
        if (!empty($ini_file)) {
            $configArray = parse_ini_file($ini_file, true);

            $this->username = $configArray['login']['username'];
            $this->password = $configArray['login']['password'];

            $this->cookie_file = $configArray['global']['cookie_file'];
            $this->download_dir = $configArray['global']['download_dir'];

            $this->log_file = $configArray['global']['log_file'];

            $this->downloader = $configArray['downloader']['engine'];

            if (isset($configArray['downloader:' . $this->downloader])) {
                $this->downloader_config = $configArray['downloader:' . $this->downloader];
            }
            if (!isset($this->downloader_config['download_dir'])) {
                $this->downloader_config['download_dir'] = $this->download_dir;
            }

            $this->webui = $configArray['webui'];
        }
    }
}


/**
 * Xunlei downloader class
 *
 * @author sskaje http://sskaje.me/
 */
abstract class spXunleiDownloader
{
    static protected $objects = array();
    /**
     * Create downloader object
     *
     * @param string $engine
     * @param array $config
     * @return spXunleiDownloader
     * @throws SPException
     */
    static public function Create($engine, array $config)
    {
        $key = md5(json_encode($config));
        if (!isset(self::$objects[$engine][$key])) {

            $class = 'spXunleiDownloader_' . $engine;
            if (!class_exists($class)) {
                $file = SPXL_CLASSROOT . '/downloader/' . $engine . '.php';
                if (!is_file($file)) {
                    throw new SPException('Engine '.$engine.' not found');
                }

                require($file);
                if (!class_exists($class)) {
                    throw new SPException('Engine '.$engine.' not found');
                }
            }

            $object = new $class($config);
            self::$objects[$engine][$key] = $object;
        }

        return self::$objects[$engine][$key];
    }

    abstract public function __construct($config=array());
    abstract public function download($url, $options=array());
}

if (!class_exists('SPException')) {
    /**
     * Exception
     *
     * @author sskaje http://sskaje.me/
     */
    class SPException extends Exception{}
}


/**
 * import json-rpc client
 */
require(SPXL_CLASSROOT . '/spJsonRPC.php');

# EOF