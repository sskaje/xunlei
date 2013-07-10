<?php
/**
 * Xunlei download class
 *
 * @author sskaje http://sskaje.me/
 */
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
        # check and compare
        $info = $this->http_info();
        if ($info['http_code'] == '302' && strpos($info['redirect_url'], 'user_task') !== false) {
            return true;
        } else {
            return false;
        }
    }
    
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
                return $gdriveid;
            }

            return false;
        } else {
            return $this->cookie_store['gdriveid'];
        }
    }

    public function addTask($url)
    {
        if (strpos($url, 'magnet:') === 0) {
            return $this->addMagnet($url);
        } else if (strpos($url, 'ed2k://|file') === 0) {
            return $this->addEd2k($url);
        } else {

        }
    }

    public function queryTaskUrl($task_url)
    {
        # xml http?
        $url = 'http://dynamic.cloud.vip.xunlei.com/interface/url_query?callback=queryUrl&u='.urlencode($task_url).'&interfrom=task&random=1373351089035142391.49535565483&tcache=1373351093166';

        $s = $this->http_post($url, '');
        $fake_json = str_replace(
            array('queryUrl(', 'new Array(', '\')', '\''),
            array('[', '[', '\']', '"'),
            $s
        );
        $j = json_decode($fake_json, true);

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
     * add magnet link
     *
     * @param string $magnet
     * @return bool
     * @throws SPException
     */
    public function addMagnet($magnet)
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

        $url = 'http://dynamic.cloud.vip.xunlei.com/interface/bt_task_commit?callback=jsonp1373350685430&t='.urlencode(strftime('%c')); #.'Tue%20Jul%2009%202013%2014:30:48%20GMT+0800%20(CST)';
        $post = 'uid=' . $this->cookie_store['userid'];
        $post .= '&btname=' . urlencode($task_info['bt_title']);
        $post .= '&cid=' . $task_info['infohash'];
        $post .= '&goldbean=0&silverbean=0';
        $post .= '&tsize=' . $task_info['fsize'];
        # findex=0_&size=296075521_

        $post .= '&findex=' . implode('_', array_keys($task_info['size_list'])) . '_';
        $post .= '&size=' . implode('_', $task_info['size_list']) . '_';
        $post .= '&o_taskid=0&o_page=task&class_id=0&interfrom=task';

        $s = $this->http_post($url, $post);

        if (strpos($s, '"progress":1') === false) {
            return false;
        }
        $m = array();
        preg_match('#"id":"(\d+)"#', $s, $m);

        $url = 'http://dynamic.cloud.vip.xunlei.com/interface/fill_bt_list?callback=fill_bt_list';
        $url .= '&tid=' . $m[1];
        $url .= '&infoid=' . $task_info['infohash'];
        $url .= '&g_net=1&p=1';
        $url .= '&uid=' . $this->cookie_store['userid'];
        $url .= '&interfrom=task&noCacheIE=';

        $s = $this->http_get($url);

        $json = substr($s, strlen('fill_bt_list('), -1);
        $j = json_decode($json, true);

        $gdriveid = $this->getGDriveID();

        # send task:
        foreach ($j['Result']['Record'] as $file) {
            if (empty($file['downurl'])) {
                continue;
            }
            $url = $file['downurl'];
            $cookie = 'gdriveid=' . $gdriveid;
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
    }
/*
    public function queryTaskCid($url)
    {
        $url = 'http://dynamic.cloud.vip.xunlei.com/interface/task_check?callback=queryCid&url='.urlencode($url).'&interfrom=task&random=13734447016551856571.3246436683&tcache=1373444705857';

        # queryCid('', '', '3517912090','1125730526239124', 'Flight.of.the.Navigator.1986.720p.BluRay.X264-7SinS.mkv', 0, 0, 0,'13734447016551856571.3246436683','movie','0')
        # queryCid(cid,gcid,file_size,avail_space,tname,goldbean_need,silverbean_need,is_full,random,type,rtcode)
        $s = $this->http_post($url, '');

        $fake_json = str_replace(
            array('queryCid(', 'new Array(', '\')', '\''),
            array('[', '[', '\']', '"'),
            $s
        );

        # var_dump($s, $fake_json, json_decode($fake_json));
        $j = json_decode($fake_json, true);

        return array(
         #   'cid'               =>  $j[0],
         #   'gcid'              =>  $j[1],
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
*/
    /**
     * add ed2k link
     *
     * @param string $ed2k
     * @throws SPException
     */
    public function addEd2k($ed2k)
    {
        # ed2k://|file|Flight.of.the.Navigator.1986.720p.BluRay.X264-7SinS.mkv|3517912090|D7ABA3230E00007C9887A40106838614|h=H7GB4TSG5KJ7RTBZN7MU5H7XX5KQSHQ3|/
        if (strpos($ed2k, 'ed2k://|file|') !== 0) {
            throw new SPException('Invalid ed2k url');
        }
        $str = substr(trim($ed2k), strlen('ed2k://|file|'));
        list($filename, $size, $hash, ) = explode('|', $str, 4);

        # $task_info = $this->queryTaskUrl($ed2k);

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
        $url .= '&noCacheIE=1373444720566';

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

        $j = $this->getTasks(1, 20);
        foreach ($j['info']['tasks'] as $task) {
            if ($task['id'] === $task_id) {
                break;
            }
        }

        if ($task['download_status'] != 2) {
            throw new SPException('download not finished');
        }

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
     * Get task list
     *
     * @param int $page
     * @param int $pagesize
     * @return mixed
     */
    public function getTasks($page, $pagesize)
    {
        #type_id: 4 全部, 1 正在下载, 2 已下载, 13 已过期, 11 已删除

        $url = 'http://dynamic.cloud.vip.xunlei.com/interface/showtask_unfresh?callback=jsonp1373444612562&t=' . urlencode(strftime('%c'));;
        $url .= '&type_id=4';
        $url .= '&page='.$page;
        $url .= '&tasknum=' . $pagesize;
        $url .= '&p=' . $pagesize;
        $url .= '&interfrom=task';

        $s = $this->http_get($url);
        $json = substr($s, strlen('jsonp1373444612562('), -1);
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
            curl_setopt($ch, CURLOPT_HEADER, 1);
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

    /**
     * execute http request
     *
     * @return mixed
     */
    protected function http_exec()
    {
        $ch = $this->http_init();
        $result = curl_exec($ch);

        list($header, $content) = explode("\r\n\r\n", $result, 2);
        $header_array = explode("\r\n", $header);

        foreach ($header_array as $row) {
            if (0 === stripos($row, 'Set-Cookie')) {
                $t = explode('=', substr($row, strlen('Set-Cookie: '), strpos($row, '; ')-strlen('Set-Cookie: ')), 2);

                $this->cookie_store[$t[0]] = $t[1];
            }
        }

        return $content;
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

    public $downloader;

    public $downloader_config = array();

    public function __construct($ini_file='')
    {
        if (!empty($ini_file)) {
            $configArray = parse_ini_file($ini_file, true);

            $this->username = $configArray['login']['username'];
            $this->password = $configArray['login']['password'];

            $this->cookie_file = $configArray['global']['cookie_file'];
            $this->download_dir = $configArray['global']['download_dir'];

            $this->downloader = $configArray['downloader']['engine'];

            if (isset($configArray['downloader:' . $this->downloader])) {
                $this->downloader_config = $configArray['downloader:' . $this->downloader];
            }
            if (!isset($this->downloader_config['download_dir'])) {
                $this->downloader_config['download_dir'] = $this->download_dir;
            }
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
                $file = __DIR__ . '/downloader/' . $engine . '.php';
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
require(__DIR__ . '/spJsonRPC.php');

# EOF