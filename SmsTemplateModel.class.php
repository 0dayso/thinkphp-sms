<?php
/*=============================================================================
#     FileName: SmsTemplateModel.class.php
#         Desc: 消息模板
#       Author: 荒野无灯
#      Version: 0.0.1
#   LastChange: 2015-05-13 21:25:05
#      History:
=============================================================================*/

namespace Model;

//use Think\Model;

class SmsTemplateModel {

    //internal , db
    protected $_dataSource = 'internal';

    /*
     *  0注册会员,
        1密码找回,
        2修改密码,
        3修改手机,
        4发送新手机验证码,
        5提现,
     */

    /**
     * getTemplate
     * @TODO 从配置文件或数据读取短信模板
     *
     * @param mixed $msgType 消息类型
     * @param mixed $message 变量替换
     * @return void
     */
    public function getTemplate($msgType, $message = array()) {
        return strtr('您的验证码为： :verifyCode ', array_filter($message, 'is_scalar'));
    }
}
