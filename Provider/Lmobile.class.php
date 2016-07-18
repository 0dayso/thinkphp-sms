<?php
/**
 * @Author: 荒野无灯
 * @Date: 2013-7-15
 * @Time: 上午11:36
 * @Description:
 * 编码格式请使用UTF-8
 * 接口API url http://cf.lmobile.cn/submitdata/Service.asmx
 * ip.addr == 60.28.200.150
 */

/**
 *
 * 短信接口提交
 * 接口参数填写规范
 * 除企业代码(scorpid)外，其余参数必填；企业代码是在有自由扩展需求的情况下填写。
 * 帐号无效
 * 请联系业务人员确认帐号密码是否正确，请确认您填写的帐号密码跟业务人员提供的完全一样(请特别注意个别字母如大写的i于小写的L是否混淆)
 * 产品编号错误
 * 请检查接口地址跟产品编号是否对应，此接口只能提交触发产品。
 * 程序代码第一行报错
 * 请检查接口地址是否正确：接口地址后面需要添加方法名(g_Submit)
 * 乱码问题
 * 程序编码格式请使用UTF-8
 * 信息提交成功之后没有收到信息
 * 提交成功表示成功提交到平台，但并不代表信息发送成功；如果提交成功之后长时间收不到信息，请联系业务人员查询
 *
 * Class lmobile_cn
 */

namespace Ihacklog\Utils\Sms\Provider;

use Ihacklog\Utils\Sms\Sms;

class Lmobile extends Sms
{
    private $date_format = 'Y-m-d H:i:s';

    private $common_data = array();

    private $sp_sms_id = 0;

    private $sms_audit_stat = 0;

    function __construct($config)
    {
        parent::__construct($config);
        //初始化通用数据
        $this->common_data = array(
            'sname' => $this->get_username(),
            'spwd' => $this->get_password(),
            'scorpid' => $this->config('scorpid'),
            'sprdid' => $this->config('sprdid'),
        );
    }

    /**
     * 发送单条短信
     * 短信内容最长为70个字符。
     * 发送的短信内容最后必须附加<strong>【公司名】</strong>字串。
     * 发送成功返回xml:
     * <code>
     * object(SimpleXMLElement)[7]
     * public 'State' => string '0' (length=1)
     * public 'MsgID' => string '30716130959040764' (length=17)
     * public 'MsgState' => string '提交成功' (length=12)
     * public 'Reserve' => string '0' (length=1)
     * </code>
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
        $url = $this->get_api_url('g_Submit');
        $data = array(
            'sdst' => $mobile,
            'smsg' => $sms_content . $this->config('corp_name'),
        );
        if ($extra_params) {
            //定时发送
            if (isset($extra_params['sbegindate'])) {
                $data['sbegindate'] = $extra_params['sbegindate'];
                $url = $this->get_api_url('g_SchedulerSubmit');
            }
        }
        $data = array_merge($this->common_data, $data);
        $rs = $this->post($url, $data);
        $xml_ele = $this->parse_xml($rs);
        if ($xml_ele) {
            //提交成功（不一定成功发送)
            if ($xml_ele->State == '0') {
                $this->sp_sms_id = $xml_ele->MsgID;
                //审核是否通过标记
                $this->sms_audit_stat = $xml_ele->MsgState;
                return true;
            } else {
                $this->add_errmsg($xml_ele->State, $xml_ele->MsgState);
                return false;
            }
        } else {
            $this->add_errmsg('1001', '连接短信服务器失败！' . $rs);
            return false;
        }
    }

    /**
     * 帐号余额查询
     *  <?xml version="1.0" encoding="utf-8"?>
     * <CRemain xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://tempuri.org/">
     * <State>-4</State>
     * <Remain>0</Remain>
     * </CRemain>
     */
    public function get_balance()
    {
        $url = $this->get_api_url('Sm_GetRemain');
        $rs = $this->post($url, $this->common_data);
        $xml_ele = $this->parse_xml($rs);
        if ($xml_ele && ($xml_ele instanceof SimpleXMLElement)) {
            if ('0' == $xml_ele->State) {
                return $xml_ele->Remain;
            } else {
                $this->add_errmsg($xml_ele->State, $xml_ele->MsgState);
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
        return array(
            array('key' => 'scorpid', 'description' => '企业代码（扩展号，可空着）'),
            array('key' => 'sprdid', 'description' => '产品编号,必须'),
            array('key' => 'corp_name', 'description' => '公司名称，必须(格式：【公司名】)'),
        );
    }

    /**
     * 获取短信在服务器端数据库中的id
     *
     * @return void
     */
    public function get_sp_sms_id()
    {
        return $this->sp_sms_id;
    }

    /**
     * 获取短信内容审查状态
     *
     * @return void
     */
    public function get_sms_audit_stat()
    {
        return $this->sms_audit_stat;
    }

    /**
     * 获取配置中的用户名
     * @return string
     */
    private function get_username()
    {
        return $this->config('username');
    }

    /**
     * 获取配置中的api url
     * @param string $function 需要调用的功能名称
     * @return string
     */
    private function get_api_url($function = 'g_Submit')
    {
        return rtrim($this->config('api_url'), '/') . '/' . $function;
    }

    /**
     * 获取密码串
     * 好吧，我要吐槽下了。。。居然是传送明文密码串
     * @return string
     */
    private function get_password()
    {
        return $this->config('password');
    }

    /**
     *
     * @param $xml_doc_str
     * @return SimpleXMLElement|bool
     */
    private function parse_xml($xml_doc_str)
    {
        $xml_ele = simplexml_load_string($xml_doc_str);
        if ($xml_ele instanceof SimpleXMLElement) {
            return $xml_ele;
        } else {
            return false;
        }
    }
}
