<?php
/**============================================================================
#     FileName: ihacklogSms.class.php
#         Desc: 短信发送
#       Author: 荒野无灯
#      Version: 0.0.1
#   Created: 2013-11-15 11:50:40
#   Mod: 2016-07-13 22:07:22 Wed
#      History:
/*=============================================================================*/

/*
 *

建议发送流程：接口发送 ----|> 记录短信数据到本地sms表  ----|> return TRUE

 */

namespace ihacklog\Utils\Sms;

abstract class Sms {

    public static $default   = 'Montnets';

    public static $instances = array();

    //是否启用黑名单检查
    protected $use_blacklist = false;

    /**
     * 通用配置参数
     * @var  Config
     */
    protected $_config = array(
        'API_URL'  => '',
        'USERNAME' => '',
        'PASSWORD' => '',
    );

    protected $_error = array();

    public static function instance($sms_sp='', $config=array()) {
        $sms_class = empty($sms_sp) ? self::$default : $sms_sp;
        self::$default = $sms_class;
        if (!isset(self::$instances[$sms_class]) || !(self::$instances[$sms_class] instanceof $sms_class) ) {
            require_once dirname(__FILE__). DIRECTORY_SEPARATOR . 'SP' . DIRECTORY_SEPARATOR . $sms_class. '.class.php';
            $sms_class =  'ihacklog\\Sms\\Provider\\'. $sms_class;
            self::$instances[$sms_class] = new $sms_class($config);
        }
        return self::$instances[$sms_class];
    }

    /**
     * 构造函数
     * @TODO 检测sp额外参数
     *
     * @param mixed $config
     * @access protected
     * @return object
     */
    protected function __construct($config)
    {
        $this->config($config);
    }

    /**
     * 修改／获取配置
     *
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    public function config($key = NULL, $value = NULL) {
        if ($key === NULL) {
            return $this->_config;
        }

        if (is_array($key)) {
            $this->_config = $key;
        } else {
            if ($value === NULL) {
                if (!isset($this->_config[$key])) {
                    $this->_config[$key] = '';
                }
                return $this->_config[$key];
            } else {
                $this->_config[$key] = $value;
            }
        }
        return $this;
    }

    /**
     * 除通用配置之外的其它配置信息声明
     * array(
     *  array('sms_attr_key_1'=>'description1'),
     *  array('sms_attr_key_2'=>'description2'),
     * )
     * @return array
     */
    public function get_extra_config_info() {
        return array();
    }

    public function add_errmsg($code,$msg) {
        $this->_error[] = array(
            'code'=>$code,
            'msg'=>$msg
        );
        return $this;
    }

    public function get_last_error() {
        return array_pop($this->_error);
    }

    public function get_errors() {
        return $this->_error;
    }

    /**
     * 记录日志到文件
     * @TODO 解耦
     *
     * @param mixed $err
     * @param string $level
     * @param string $category
     * @return void
     */
    public function log_err($err, $level = 'error', $category = 'sms')
    {
        Think\Log::record($err, $level, $category);
    }

    /**
     * 检测手机号码是否正确
     *
     */
    public function is_mobile($moblie) {
        return  preg_match("/^0?1((3|8)[0-9]|5[0-35-9]|4[57])\d{8}$/", $moblie);
    }

    protected function gbk_utf8($gbk_str) {
        return iconv('GBK','UTF-8',$gbk_str);
    }

    protected function utf8_gbk($utf8_str) {
        return iconv('UTF-8','GBK',$utf8_str);
    }

    /**
     * 发送http请求
     * @param $url
     * @param array $data
     * @param string $method
     * @return bool|mixed
     * @throws ErrorException
     */
    public function http_request($url,$data = array(), $method = 'GET', &$error_out ='')
    {
        $query_string = '';
        if ( 'GET' == strtoupper($method)) {
            $query_string = strpos($url,'?') === false ? '?' : '&';
        }

        if ( !empty($data)) {
            foreach((array) $data as $param_key => $param_value) {
                $query_string .= $param_key . '=' . urlencode($param_value) .'&';
            }
            $query_string = rtrim($query_string,'&');
        }
        if ( 'GET' == strtoupper($method)) {
            $url .= $query_string;
        }
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_TIMEOUT,3);
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,3);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            //$this->handle_cookies_send($ch);
            // get headers too with this line
            curl_setopt($ch, CURLOPT_HEADER, 1);
            //log our request string
            curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
            if ('POST' == strtoupper($method)) {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
            }
            $ret = curl_exec($ch);
            $info = curl_getinfo($ch);
            $req_header_log = "\n>>>>req header: \n" . $info['request_header'] . "\n<<<end req header.\n";
            if (curl_errno($ch) != 0) {
                $curl_err = 'Curl error: ' . curl_error($ch);
                $error_out = $curl_err;
                $this->log_err($req_header_log . $curl_err);
            }
            curl_close($ch);
            //get cookies and transfer
            //$this->handle_cookies_recv($ret);
            //ensure that last http code is 200
            if ( $info['http_code'] != 200) {
                $err = 'Error http_code: '. $info['http_code'];
                $error_out = $err;
                $this->log_err($req_header_log .$err);
                return false;
            }
            if ( $ret === false ) {
                $err = 'curl_exec return false.';
                $error_out = $err;
                $this->log_err($req_header_log .$err);
                return false;
            } else {
                //print($ret);
                $ret_arr = explode("\r\n\r\n", $ret);
                if (isset($ret_arr[1])) {
                    $the_content = $ret_arr[1];
                    if ('POST' == strtoupper($method)
                        && strpos($ret_arr[0], 'HTTP/1.1 100 Continue') !== false
                        && isset($ret_arr[2])
                    ) {
                        $the_content = $ret_arr[2];
                    }
                    return $the_content;
                } else {
                    $err = 'Failed to get response content from API server.';
                    $error_out = $err;
                    $this->log_err($req_header_log .$err);
                    return false;
                }
            }
        } else {
            //use fsockopen
            $err       = 'curl not enabled!';
            $error_out .= $err;
            $this->log_err($err);
            throw new ErrorException('curl not enabled!');
        }
    }

    public function get($url, $data = array())
    {
        return $this->http_request($url, $data, 'GET');
    }

    public function post($url, $data = array())
    {
        return $this->http_request($url, $data, 'POST');
    }

    /**
     * 判断是否包含黑字典中的词
     *
     * @param mixed $mobile
     * @param mixed $content
     * @return bool
     */
    public function has_blackword($mobile, $content)
    {
        if(!$this->use_blacklist) {
            return false;
        }
        $black_dicts = file(dirname(__FILE__) . '/data/black_dict_sorted.csv');
        $is_black = false;//是否是黑字典
        foreach ($black_dicts as $black_word) {
            $black_word=trim($black_word);
            $result=stripos($content, $black_word);
            if ($result !== false) {
                $is_black = true;
                break;
            }
        }
        //记录包含和谐字眼的短信
        if ($is_black) {
            $log_content = 'sms_blacklist_log: blackword: '. $black_word .', sms_to: '
                . $mobile . ', sms_content: '. $content . ', sms_date: '. date('Y-m-d H:i:s');
            $this->log_err($log_content);
            $this->add_errmsg('500', $log_content);
            //发邮件给管理员
            //err_mail('RYT_sms_blackword: '.$black_word, $log_content);
        }
        return $is_black;
    }

    public function enable_blacklist()
    {
        $this->use_blacklist = true;
    }

    public function disable_blacklist()
    {
        $this->use_blacklist = false;
    }

    /**
     * 抽象方法： 发送短信
     */
    abstract function send($mobile, $content, $extra_params = array());

    /**
     * 获取已发送总条数
     * @return mixed
     */
    abstract function get_sent_count();
}//end class
