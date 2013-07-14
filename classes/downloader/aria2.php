<?php
/**
 * Download with aria2
 * uses JSON-RPC
 *
 * @author sskaje http://sskaje.me/
 */
class spXunleiDownloader_aria2 extends spXunleiDownloader
{
    /**
     * @var spJsonRPC
     */
    protected $jsonrpc;

    protected $download_dir;

    public function __construct($config=array())
    {
        # init json rpc

        $this->jsonrpc = new spJsonRPC(
            $config['rpc_url'],
            array(
                'auth_user' =>  $config['rpc_user'],
                'auth_pass' =>  $config['rpc_pass'],
            )
        );

        $this->download_dir = $config['download_dir'];
    }

    protected function checkMkdir($filename)
    {
        $fullpath = $this->download_dir . '/' . $filename;
        $dir = dirname($fullpath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    public function download($url, $options=array())
    {
        $rpcopts = array(
            'header'    =>  'Cookie: ' . $options['cookie']
        );

        # $this->checkMkdir($options['filename']);

        $rpcopts['dir'] = $this->download_dir;
        $rpcopts['out'] = $options['infohash'] . '/' .$options['filename'];

        $s = $this->jsonrpc->{'aria2.addUri'}(
            array($url),
            $rpcopts
        );

        var_dump($s);
    }
}

# EOF