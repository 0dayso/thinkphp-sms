<?php
/**
 * @Author: 荒野无灯
 * @Date: 2013-7-15
 * @Time: 上午12:19
 * @Description: 适用于sms.cn的短信接口类.
 *
 */

/*--------------------------------
功能:		HTTP接口 发送短信
修改日期:	2011-03-04
说明:		http://api.sms.cn/mt/?uid=用户账号&pwd=MD5位32密码&mobile=号码&mobileids=号码编号&content=内容
状态:
	100 发送成功
	101 验证失败
	102 短信不足
	103 操作失败
	104 非法字符
	105 内容过多
	106 号码过多
	107 频率过快
	108 号码内容空
	109 账号冻结
	110 禁止频繁单条发送
	112 号码不正确
	120 系统升级
--------------------------------*/

namespace Ihacklog\Utils\Sms\Provider;

use Ihacklog\Utils\Sms\Sms;

class SmsCn extends Sms
{

    const STAT_NETWORK_ERR = '001';
    const STAT_OK = '100';
    const STAT_AUTH_FAILED = '101';
    const STAT_NO_BALANCE = '102';
    const STAT_OP_FAILED = '103';
    const STAT_ILLEGAL_WORD = '104';
    const STAT_OUT_OF_LEN = '105';
    const STAT_TOO_MANY_MOBILE = '106';
    const STAT_FREQ_TOO_FAST = '107';
    const STAT_NULL_MOBILE_OR_CONTENT = '108';
    const STAT_ACCOUNT_FROZEN = '109';
    const STAT_FLOOD = '110';
    const STAT_INVALID_MOBILE = '111';
    const STAT_SYSTEM_UPGRADE = '120';

    /**
     * 给指定手机号(可以是多个)发送短信
     * 提交：/mt/?uid=用户账号&pwd=MD532位密码&mobile=号码&mobileids=消息编号&content=内容
     * 发成功时响应: sms&stat=100&message=发送成功
     *
     * @param $mobile 手机号码,可以同时向多个号码发送，多个号码之间用英文半角逗号,分隔
     * @param $sms_content 短信内容
     * @param array $other_params 其它参数，如加上time参数表示定时发送
     * @return bool 发送成功返回true,否则返回false
     */
    public function send($mobile, $sms_content, $other_params = array())
    {
        //将短信内容从UTF-8转换为GBK编码，服务器默认接收gbk编码数据
        $sms_content = iconv('UTF-8', 'GBK', $sms_content);
        $data = array(
            'uid' => $this->config('username'),                    //用户账号
            'pwd' => $this->get_password(),            //MD5位32密码,密码和用户名拼接字符
            'mobile' => $mobile,                //号码
            'content' => $sms_content,            //内容
            'mobileids' => $this->get_mobileids($mobile),
        );
        if ($other_params && isset($other_pramas['time'])) {
            $data['time'] = $other_pramas['time'];
        }
        $rs = $this->http_post($this->get_api_url('mt'), $data);            //POST方式提交
        if (!empty($rs)) {
            parse_str($rs, $rs_arr);
            if ($rs_arr['stat'] && $rs_arr['stat'] == self::STAT_OK) {
                return true;
            } else {
                $message = isset($rs_arr['message']) ? iconv('GBK', 'UTF-8', $rs_arr['message']) : $this->status2message($rs_arr['stat']);
                $this->add_errmsg($rs_arr['stat'], $message);
                return false;
            }
        } else {
            $this->add_errmsg(self::STAT_NETWORK_ERR, '网络错误！');
            return false;
        }
    }

    /**
     * 取剩余短信条数
     * 提交参数： uid, pwd
     * 响应结果为纯文本：sms&stat=状态码&remain=剩余可发短信
     * @return int|bool 返回剩余短信条数，失败则返回false
     */
    public function get_balance()
    {
        return $this->get_balance_or_sent('balance');
    }

    /**
     * 取发送条数
     * 参数： uid=用户账号&pwd=MD5位32密码&cmd=send
     * 响应结果为纯文本：sms&stat=状态码&remain=已发短信
     * @return int|bool 返回已发送短信总条数，失败返回false
     */
    public function get_sent_count()
    {
        return $this->get_balance_or_sent('sent');
    }

    private function get_balance_or_sent($type = 'balance')
    {
        $url = $this->get_api_url('mm') . '?uid=' . $this->get_username() . '&pwd=' . $this->get_password();
        if ('balance' != $type) {
            $url .= '&cmd=send';
        }

        $rs_arr['stat'] = FALSE;
        $rs = $this->http_get($url);
        if (!empty($rs)) {
            if (is_numeric($rs)) {
                $rs_arr['stat'] = $rs;
            } else {
                parse_str($rs, $rs_arr);
            }
            if ($rs_arr['stat']) {
                if ($rs_arr['stat'] == self::STAT_OK) {
                    return $rs_arr['remain'];
                } else {
                    $message = isset($rs_arr['message']) ? iconv('GBK', 'UTF-8', $rs_arr['message']) : $this->status2message($rs_arr['stat']);
                    $this->add_errmsg($rs_arr['stat'], $message);
                    return false;
                }
            }
        } else {
            $this->add_errmsg(self::STAT_NETWORK_ERR, '网络错误！');
            return false;
        }
    }

    /**
     * 将状态码转换为消息
     * @param $status_code
     * @return string
     */
    private function status2message($status_code)
    {
        switch ($status_code) {
            case self::STAT_OK :
                $message = '100 发送成功';
                break;
            case self::STAT_AUTH_FAILED :
                $message = '101 验证失败,请检测用户名和密码是否正确.';
                break;
            case self::STAT_NO_BALANCE :
                $message = '102 短信不足';
                break;
            case self::STAT_OP_FAILED :
                $message = '103 操作失败';
                break;
            case self::STAT_ILLEGAL_WORD :
                $message = '104 非法字符';
                break;
            case self::STAT_OUT_OF_LEN :
                $message = '105 内容过多';
                break;
            case self::STAT_TOO_MANY_MOBILE :
                $message = '106 号码过多';
                break;
            case self::STAT_FREQ_TOO_FAST :
                $message = '107 频率过快';
                break;
            case self::STAT_NULL_MOBILE_OR_CONTENT :
                $message = '108 号码内容空';
                break;
            case self::STAT_ACCOUNT_FROZEN :
                $message = '109 账号冻结';
                break;
            case self::STAT_FLOOD :
                $message = '110 禁止频繁单条发送';
                break;
            case self::STAT_INVALID_MOBILE :
                $message = '112 号码不正确';
                break;
            case self::STAT_SYSTEM_UPGRADE :
                $message = '120 系统升级';
                break;
            default:
                $message = '未知错误';
                break;
        }
        return $message;
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
     * @param string $sub_path
     * @return string
     */
    private function get_api_url($sub_path = 'mt')
    {
        return rtrim($this->config('api_url'), '/') . '/' . $sub_path . '/';
    }

    /**
     * 获取加密过后的密码串
     * @return string
     */
    private function get_password()
    {
        return $this->encrypt_password($this->config('password'), $this->config('username'));
    }

    /**
     * 密码加密算法
     * @param $raw_password 原始密码
     * @param $username 用户名
     * @return string 加密后的密码串
     */
    private function encrypt_password($raw_password, $username)
    {
        return md5($raw_password . $username);
    }

    private function get_mobileids($mobile)
    {
        $mobileids = '';
        if (is_string($mobile)) {
            $mobileids = $mobile . time();
        } else {
            $mobile_arr = explode(',', $mobile);
            foreach ($mobile_arr as $mobile_num) {
                if (!empty($mobile_num)) {
                    $mobileids_arr[] = $mobile_num . time();
                }
            }
            $mobileids = implode(',', $mobileids_arr);
        }
        return $mobileids;
    }
}
