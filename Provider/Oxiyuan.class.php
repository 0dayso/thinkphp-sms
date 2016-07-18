<?php
/**
 * Created by 荒野无灯.
 * User: sh4d0walker
 * Date: 7/18/16
 * Time: 5:40 PM
 * Description: 熹源短信接口api
 *
 */
namespace Ihacklog\Utils\Sms\Provider;

use Ihacklog\Utils\Sms\Sms;

/**
 * Class Oxiyuan
 * @package ihacklog\Utils\Sms\SP
 */

class Oxiyuan extends Sms
{
    private $date_format = 'Y-m-d H:i:s';

    private $common_data = array();

    private $sp_sms_id = 0;

    private $sms_audit_stat = 0;

    private $sendUrl = 'http://120.25.202.19:8888/sms.aspx';

    function __construct($config)
    {
        parent::__construct($config);
        //初始化通用数据
        $this->common_data = array(
            'userid' => $this->get_userid(),
            'account' => $this->get_account(),
            'password' => $this->get_password(),
        );
    }

    /**
     * 发送单条/多条短信
     * 短信内容最长为70个字符。
     * 发送多条：mobile直接用半角逗号隔开
     * 发送成功返回xml:
     *返回值：错误描述对应说明 发送成功：Success
     * 如： <?xml version="1.0" encoding="utf-8" ?>
    <returnsms>
    <returnstatus>Success</returnstatus>
    <message>ok</message>
    <remainpoint>238187</remainpoint>
    <taskID>10770349</taskID>
    <successCounts>1</successCounts>
    </returnsms>
     * @param $mobile 单个手机号码
     * @param $sms_content
     * @param array $extra_params
     * @return bool 发送成功与否
     */
    public function send($mobile, $sms_content, $extra_params = array())
    {
        //先检测黑名单
        if ($this->has_blackword($mobile, $sms_content)) {
            return false;
        }
//        $count = substr_count($mobile, ',') + 1;
        $url = $this->get_api_url();
        $data = array(
            'action' => 'send',
            'mobile' => $mobile,
            'content' => $sms_content,
            'sendTime' => '',
            'extno' => '',
        );
        $data = array_merge($this->common_data, $data);
        $rs = $this->post($url, $data);
        $xml_ele = $this->parse_xml($rs);
        if ($xml_ele && ($xml_ele instanceof \SimpleXMLElement)) {
            if ($xml_ele->returnstatus == 'Success') {
                return $xml_ele->successCounts;
            } else {
                $this->add_errmsg('1002', '发送失败！错误信息: ' . $xml_ele->message);
                return false;
            }
        } else {
            $this->add_errmsg('1001', '连接短信服务器失败！' . $rs);
            return false;
        }
    }

    /**
     * 帐号余额查询
     * @TODO 看文档 fix
     */
    public function get_balance()
    {
        $url = $this->get_api_url();
        $rs = $this->post($url, $this->common_data);
        $xml_ele = $this->parse_xml($rs);
        if ($xml_ele && ($xml_ele instanceof \SimpleXMLElement)) {
            if ('0' <= (int)$xml_ele) {
                return (int)$xml_ele;
            } else {
                $this->add_errmsg((int)$xml_ele, 'error.');
                return false;
            }
        } else {
            $this->add_errmsg('1001', '连接短信服务器失败！' . $rs);
            return false;
        }
    }

    public function get_sent_count()
    {
        return 'NOT SUPPORTED.';
    }

    public function get_extra_config_info()
    {
        return array();
    }

    /**
     * 获取配置中的用户名
     * @return string
     */
    private function get_userid()
    {
        return $this->config('USERID');
    }

    /**
     * 获取配置中的account
     * @return string
     */
    private function get_account()
    {
        return $this->config('ACCOUNT');
    }

    /**
     * 获取配置中的api url
     * @param string $function 需要调用的功能名称
     * @return string
     */
    private function get_api_url()
    {
        $config_url = rtrim($this->config('API_URL'), '/');
        return $config_url ? $config_url : $this->sendUrl;
    }

    /**
     * 获取密码串
     * 好吧，我要吐槽下了。。。居然是传送明文密码串
     * @return string
     */
    private function get_password()
    {
        return $this->config('PASSWORD');
    }

    /**
     *
     * @param $xml_doc_str
     * @return SimpleXMLElement|bool
     */
    private function parse_xml($xml_doc_str)
    {
        $xml_ele = simplexml_load_string($xml_doc_str);
        if ($xml_ele instanceof \SimpleXMLElement) {
            return $xml_ele;
        } else {
            return false;
        }
    }
}