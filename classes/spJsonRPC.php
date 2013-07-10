<?php
/**
 * Json RPC Client Class
 * implements 2.0 without notification support and batch support
 * php_curl required
 *
 * @author sskaje http://sskaje.me/
 */
class spJsonRPC
{
    protected $options = array();
    protected $url;

    public function __construct($url, $options=array())
    {
        if (!extension_loaded('curl')) {
            throw new Exception('php_curl required');
        }

        $this->url = $url;

        foreach ($options as $k=>$v) {
            $this->setOpt($k, $v);
        }
    }

    /**
     * Set options
     * Available options: auth_user, auth_pass
     *
     * @param string $opt_key
     * @param mixed $opt_val
     */
    private function setOpt($opt_key, $opt_val)
    {
        switch ($opt_key) {
            case 'auth_user':
            case 'auth_pass':
                $this->options[$opt_key] = $opt_val;
                break;
        }
    }

    private $id = 0;
    private function generateID()
    {
        return ++$this->id;
    }

    public function __call($method, array $params)
    {
        # TODO: Notification support
        $currentId = $this->generateID();

        $response = $this->post(json_encode(array(
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
            'id'      => $currentId
        )));
        if (empty($response)) {
            throw new SPException('Server no response');
        }
        $response = json_decode($response,true);

        if (!isset($response['jsonrpc']) || $response['jsonrpc'] != '2.0') {
            throw new SPException('Wrong version');
        }
        #
        if ($response['id'] !== $currentId) {
            throw new SPException('Id mismatch (request:'.$currentId.'; response: '.$response['id'].')');
        }

        if (isset($response['error'])) {
            throw new SPException('Request error: ' . $response['error']['message'] . '#' . $response['error']['code']);
        }

        return $response['result'];
    }

    /**
     * Perform http post
     *
     * @param string $data
     * @return mixed
     */
    private function post($data)
    {
        static $curl = null;
        if (!$curl) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_VERBOSE, 0);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_URL, $this->url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-type: application/json'));

            # Basic Auth
            if (isset($this->options['auth_user']) && !empty($this->options['auth_user'])) {
                curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                $userpwd = $this->options['auth_user'] . ':';
                if (isset($this->options['auth_pass'])) {
                    $userpwd .= $this->options['auth_pass'];
                }
                curl_setopt($curl, CURLOPT_USERPWD, $userpwd);
            }
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        return curl_exec($curl);
    }
}

if (!class_exists('SPException')) {
    /**
     * Exception
     *
     * @author sskaje http://sskaje.me/
     */
    class SPException extends Exception{}
}

# EOF