<?php

# http://kuai.xunlei.com/d/SY0DAAKsywCdrxNSe3a


class spXunleiPlugin_kuaichuan implements ifXunleiPlugin
{
    public function match($url)
    {
        return strpos($url, 'http://kuai.xunlei.com/d/') === 0;
    }

    /**
     * @var spXunlei
     */
    protected $xunlei;

    protected $config = array();

    public function __construct(spXunlei $xunlei, array $config)
    {
        $this->xunlei = $xunlei;
        $this->config = $config;
    }

    public function process($url, $options)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1500.95 Safari/537.36');

        curl_setopt($ch, CURLOPT_URL, $url);
        $page = curl_exec($ch);
        curl_close($ch);

        # TODO: captcha?

        $c = 0;
        if (preg_match_all('#<a\s+xsid="\d+"[^>]+class="file_name"\s+href="([^"]+)"\s+title="([^"]+)"\s+file_size="(\d+)"\s+target="_blank">#s', $page, $m)) {
            foreach ($m[1] as $k=>$u) {
                $this->xunlei->log("kuaichuan: + {$m[2][$k]}({$m[3][$k]}) [{$u}]");
                $this->xunlei->addDefault($u);
                ++$c;
            }
        }

        return $c;
    }
}

# EOF