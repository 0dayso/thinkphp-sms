<?php
/**
 * @Author: 荒野无灯
 * @Date: 2015-7-15
 * @Time: 上午11:36
 * @Description:
 * montnets 梦网短信接口
 * 重要说明：相同信息内容的号码一定要打包发送，一次最大可以发送100个号码。如果对于相同的信息一条一条发送，系统检测到以后将会停止用户帐号。
 *  测试wsdl地址：
 *
 * http://ws.montnets.com:7902/MWGate/wmgw.asmx?wsdl
 *  http://61.145.229.28:7902/MWGate/wmgw.asmx?wsdl
 *
 * 返回错误编号    错误说明
 * -1    参数为空。信息、电话号码等有空指针，登陆失败
 * -12    有异常电话号码
 * -14    实际号码个数超过100
 * -999    服务器内部错误
 * -10001    用户登陆不成功(帐号不存在/停用/密码错误)
 * -10003    用户余额不足
 * -10011    信息内容超长
 * -10029    此用户没有权限从此通道发送信息(用户没有绑定该性质的通道，比如：用户发了小灵通的号码)
 * -10030    不能发送移动号码
 * -10031    手机号码(段)非法
 * -10057    IP受限
 * -10056    连接数超限
 */

/**
 *
 * 短信接口提交
 *
 */

namespace ihacklog\Utils\Sms\SP;

use ihacklog\Utils\Sms\Sms;

class Montnets extends Sms
{
    private $date_format = 'Y-m-d H:i:s';

    private $common_data = array();

    private $sp_sms_id = 0;

    private $sms_audit_stat = 0;

    private $sendUrl = 'http://61.145.229.29:9006/MWGate/wmgw.asmx';

    function __construct($config)
    {
        parent::__construct($config);
        //初始化通用数据
        $this->common_data = array(
            'userId' => $this->get_username(),
            'password' => $this->get_password(),
        );
    }

    /**
     * 发送单条短信
     * 短信内容最长为70个字符。
     * 发送的短信内容最后必须附加<strong>【公司名】</strong>字串。
     * 发送成功返回xml:
     *返回值：错误描述对应说明 发送成功：平台消息编号
     * 如： <?xml version="1.0" encoding="utf-8"?><string xmlns="http://tempuri.org/">6288499252231274047</string>
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
        $count = substr_count($mobile, ',') + 1;
        $url = $this->get_api_url('MongateCsSpSendSmsNew');
        $data = array(
            'pszMobis' => $mobile,
            'pszMsg' => $sms_content,
            'iMobiCount' => $count,
            'pszSubPort' => '*',
        );
        $data = array_merge($this->common_data, $data);
        $rs = $this->post($url, $data);
        $xml_ele = $this->parse_xml($rs);
        if ($xml_ele && ($xml_ele instanceof \SimpleXMLElement)) {
            if (strlen((string)$xml_ele) >= strlen('6288499252231274047')) {
                $this->sp_sms_id = (int)$xml_ele;
                return true;
            } else {
                $this->add_errmsg('1002', '发送失败！错误id: ' . (int)$xml_ele);
                return false;
            }
        } else {
            $this->add_errmsg('1001', '连接短信服务器失败！' . $rs);
            return false;
        }
    }

    /**
     * 帐号余额查询
     */
    public function get_balance()
    {
        $url = $this->get_api_url('MongateQueryBalance');
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

    /**
     * 获取状态报告
     *
     * @return void
     */
    public function get_status_report()
    {
        $url = $this->get_api_url('MongateCsGetStatusReportExEx');
        $rs = $this->get($url);
        var_dump($rs);
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
    private function get_username()
    {
        return $this->config('USERNAME');
    }

    /**
     * 获取配置中的api url
     * @param string $function 需要调用的功能名称
     * @return string
     */
    private function get_api_url($function = 'MongateCsSpSendSmsNew')
    {
        return rtrim($this->config('API_URL'), '/') . '/' . $function;
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
