<?php
/*=============================================================================
#     FileName: SmsModel.class.php
#         Desc:

-- --------------------------------------------------------

--
-- Table structure for table `sms`
--
CREATE TABLE IF NOT EXISTS `sms` (
  `id` bigint(20) NOT NULL,
  `channelType` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '通道类型（1验证码通道，2 通知类短信通道）',
  `codeType` tinyint(5) NOT NULL DEFAULT '0' COMMENT '短信模板代号(0注册会员, 1密码找回, 2修改密码, 3修改手机, 4发送新手机验证码, 5提现)',
  `mobile` varchar(20) NOT NULL COMMENT '接收方手机号',
  `content` varchar(1024) NOT NULL DEFAULT '' COMMENT '短信内容',
  `deviceId` varchar(30) DEFAULT NULL COMMENT '设备ID号（WEB端发起的填写web）',
  `verifyCode` varchar(10) NOT NULL COMMENT '校验码内容',
  `verifyResult` tinyint(5) NOT NULL DEFAULT '0' COMMENT '短信校验结果（0,未校验，1成功，2失败）针对校验类短信',
  `sendStatus` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '发送状态：0未发送，1发送成功，2发送失败',
  `errorMsg` varchar(128) NOT NULL DEFAULT '' COMMENT '短信发送错误代码信息记录',
  `clientIp` int(11) NOT NULL COMMENT '客户端IPv4 地址',
  `createTime` int(10) unsigned NOT NULL COMMENT '创建时间 ',
  `updateTime` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '状态更新时间',
  `sp` varchar(20) NOT NULL DEFAULT '' COMMENT '服务商名称'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='短信发送验证记录';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `sms`
--
ALTER TABLE `sms`
  ADD PRIMARY KEY (`id`),
  ADD INDEX KEY `sms_idx1` (`channelType`,`codeType`,`mobile`,`verifyCode`) USING BTREE;

#       Author: 荒野无灯
#      Version: 0.0.1
#   LastChange: 2015-05-13 21:36:53
#      History:
=============================================================================*/

namespace Model;
use Think\Model;

use Ihacklog\Utils\Sms\Sms;

class SmsModel extends Model {

    //通道类型 1 验证类 ,  2 通知类
    const CHANNEL_TYPE_VERIFY = 1;
    const CHANNEL_TYPE_NOTICE = 2;

    //0注册会员, 1密码找回, 2修改密码, 3修改手机, 4发送新手机验证码, 5提现
    const CODE_TYPE_REG        = 0;
    const CODE_TYPE_FIND_PWD   = 1;
    const CODE_TYPE_MOD_PWD    = 2;
    const CODE_TYPE_MOD_MOBILE = 3;
    const CODE_TYPE_NEW_MOBILE = 4;
    const CODE_TYPE_DRAWAL     = 5;

    //校验状态
    const VERIFY_RESULT_INIT = 0;
    const VERIFY_RESULT_SUCC = 1;
    const VERIFY_RESULT_FAIL = 2;

    //发送状态
    const SEND_STATUS_INIT = 0;
    const SEND_STATUS_SUCC = 1;
    const SEND_STATUS_FAIL = 2;

    public $_validate = array(
      //array(验证字段1,验证规则,错误提示,[验证条件,附加规则,验证时间]),
      array('mobile','require','手机号必须！'),
      array('channelType', array(1, 2), 'channelType值的范围不正确！', self::MODEL_BOTH, 'in'),
      array('codeType', array('0', '1', '2', '3', '4', '5'), 'codeType值的范围不正确！', self::MODEL_BOTH, 'in'),
    );

    /**
     * 生成支付时的订单号等，带明显的日期标识，方便查看
     * 生成长度： 4 + 2 + 2 + 2 + 2 + 2 + 3 + 2 = 19位
     *
     * @return void
     */
    public static function makeOrderId() {
        //操作时间（yyyyMMddHHmmssSSS）, S 毫秒
        $dt = new \DateTime();
        //$mer_order = substr($dt->format('YmdHisu'), 0, strlen('yyyyMMddHHmmssSSS'));
        $mer_order = $dt->format('YmdHis');
        $mer_order .= substr((string)microtime(), 2, 3). mt_rand(10, 99);
        return $mer_order;
    }

    /**
     * 添加一行短信发送任务
     *
     * @param mixed $data
     * @return 主键id或false
     */
    public function addTask($data) {
        if ($this->create($data)) {
            $id = self::makeOrderId();
            $this->id           = $id;
            $this->verifyResult = self::VERIFY_RESULT_INIT;
            $this->clientIp     = empty($data['clientIp']) ? get_client_ip(1) : $data['clientIp'];
            $this->createTime   = empty($data['createTime']) ? time() : $data['createTime'];
            if($this->add()) {
                return $id;
            } else {
                return false;
            }
        } else {
             return false;
        }
    }

    /**
     * 发送短信(附带数据库记录操作)
     * @TODO 同一IP多次发送检测 ， 同一客户端多次重复请求检测， 黑名单检测
     *
     * @return void
     */
    public function send($channelType, $mobile, $content, $verifyCode, $codeType = 1, $deviceID = '', &$errorMsg) {
        $spConfKey = self::CHANNEL_TYPE_VERIFY == $channelType ? 'SP' : 'SP_NOTICE';
        $spBakConfKey = self::CHANNEL_TYPE_VERIFY == $channelType ? 'SP_BAK' : 'SP_BAK_NOTICE';
        //主用sp名称
        $masterSp = C('SMS.' . $spConfKey);
        //备用sp名称
        $secondSp = C('SMS.'. $spBakConfKey);
        $masterConfig = C('SMS.' . $masterSp);
        if ($secondSp) {
            $secondSpConfig = C('SMS.'. $secondSp);
        }
        //防恶意请求
        if (C('SMS.MIN_TIME_SPAN')) {
            if ($this->countByTime($mobile, C('SMS.MIN_TIME_SPAN')) > 0) {
                $errorMsg = sprintf('Send too frequently in %d s!', C('SMS.MIN_TIME_SPAN'));
                return false;
            }
        }
        if (C('SMS.SINGLE_IP_LIMIT')) {
            $timeLimit = C('SMS.SINGLE_IP_LIMIT_TIME');
            if ($this->countByIp(get_client_ip(1), $timeLimit) > C('SMS.SINGLE_IP_LIMIT')) {
                $errorMsg = sprintf('Send too frequently in %d s for IP: %s!', $timeLimit, get_client_ip(0));
                return false;
            }
        }
        //插入发送记录到库中
        $insertData = array(
            'channelType'  => $channelType,
            'codeType'     => $codeType ,
            'mobile'       => $mobile ,
            'content'      => $content ,
            'deviceId'     => $deviceID ,
            'verifyCode'   => $verifyCode,
            'verifyResult' => self::VERIFY_RESULT_INIT,
            'sendStatus'   => self::SEND_STATUS_INIT,
            'errorMsg'     => '',
            'clientIp'     => '',
            'createTime'   => '',
            'updateTime'   => 0,
            'sp'           => $masterSp,
        );
        $smsId = $this->addTask($insertData);
        if (!$smsId) {
            $errorMsg = 'failed to add sms task:' . $this->getError();
            return false;
        }
        $sendRs = $this->doSend($mobile, $content, $masterSp, $masterConfig, $errorMsg, $bwEn = 'BWEnable');
        $updateRs = $this->updateSendStatus($sendRs, $smsId, $errorMsg);
        if (true == $sendRs) {
            return true;
        }
        /**
         * 存在备用接口,则继续走备用短信接口
         */
        if ($secondSp && !empty($secondSpConfig)) {
            $sendRs = $this->doSend($mobile, $content, $secondSp, $secondSpConfig, $errorMsg, $bwEn = 'BWEnable');
            $updateRs = $this->updateSendStatus($sendRs, $smsId, $errorMsg);
            if (true == $sendRs) {
                return true;
            }
        }
        return false;
    }

    /**
     * 统计指定ip,在规定的时间内发送成功的短信条数(只计算发送成功的)
     *
     * @param mixed $ip
     * @param int $timeBefore
     * @return void
     */
    public function countByIp($ip, $timeBefore = 3600) {
        $timeStart = time() - $timeBefore;
        $map = array(
            'clientIp'   => $ip,
            'createTime' => array('GT', $timeStart),
            'sendStatus' => self::SEND_STATUS_SUCC,
        );
        return $this->where($map)->count();
    }

    /**
     * 统计指定手机号，在规定的时间内请求发送短信数
     *
     * @param mixed $mobile
     * @param int $timeSpan
     * @return void
     */
    public function countByTime($mobile, $timeSpan = 60) {
        $timeStart = time() - $timeSpan;
        $map = array(
            'mobile'   => $mobile,
            'createTime' => array('GT', $timeStart),
        );
        return $this->where($map)->count();
    }

    public function updateSendStatus($sendRs, $smsId, $errorMsg) {
        $st = false == $sendRs ? self::SEND_STATUS_FAIL : self::SEND_STATUS_SUCC;
        //更新发送结果
        return $this->setSendStatus($smsId, $st, $errorMsg);
    }

    /**
        * 更新发送状态
        *
        * @param mixed $id
        * @param mixed $st
        * @param string $msg
        * @return void
        */
    public function setSendStatus($id, $st, $msg ='') {
        $updateData = array(
            'sendStatus' => $st,
            'errorMsg'   => $msg,
            //'updateTime' => time(),
        );
        return $this->where(array('id' => $id))->save($updateData);
    }

    /**
        * 更新验证状态
        *
        * @param mixed $id
        * @param mixed $st
        * @return void
        */
    public function setVerifyRs($id, $st) {
        $updateData = array(
            'verifyResult' => $st,
            'updateTime' => time(),
        );
        return $this->where(array('id' => $id))->save($updateData);
    }

    /**
     * 执行短信发送
     *
     * @param mixed $mobile
     * @param mixed $content
     * @param mixed $spName
     * @param mixed $spConf
     * @param string $bwEn BWEnable ,  BWDisable
     * @return void
     */
    public static function doSend($mobile, $content, $spName, $spConf, &$errorMsg, $bwEn = 'BWEnable') {
        $sms = Sms::instance($spName, $spConf);
        'BWEnable' == $bwEn && $sms->enable_blacklist();
        $smsSendRs = $sms->send($mobile, $content);
        if (false == $smsSendRs) {
            $err_arr = $sms->get_last_error();
            $err_msg = 'sms_send_failed: sp:'. $masterSp . ', to:'.  $mobile . ', content: '. $content .
                ', sp_error_msg: '. $err_arr['msg'];
            Log::record($err_msg);
            $errorMsg = $err_msg;
            return false;
        } else {
            return true;
        }
    }

   /**
     * 发送短信(验证码之类的)
     *
     * @return void
     */
    public function sendVerify($mobile, $verifyCode = '', $content = '', $codeType = 1, $deviceID = '') {
        $errorMsg = '';
        $verifyCode = empty($verifyCode) ? mt_rand(1000,9999) : $verifyCode;
        $content = empty($content) ? D('SmsTemplate')->getTemplate($codeType, array(':verifyCode' => $verifyCode)) : $content;
        $rs = self::send(self::CHANNEL_TYPE_VERIFY, $mobile, $content, $verifyCode, $codeType, $deviceID, $errorMsg);
        if (!$rs) {
             $this->error = $errorMsg;
        }
        //var_dump($this->error);
        return $rs;
    }

    /**
     * 通知类短信发送
     *
     * @return void
     */
    public function sendNotice($mobile, $content = '', $codeType = 1, $deviceID = '') {
        $errorMsg = '';
        $verifyCode = '';
        $content = empty($content) ? D('SmsTemplate')->getTemplate($codeType, array()) : $content;
        $rs = self::send(self::CHANNEL_TYPE_NOTICE, $mobile, $content, $verifyCode, $codeType, $deviceID, $errorMsg);
        if (!$rs) {
             $this->error = $errorMsg;
        }
        return $rs;
    }

    public function verify($mobile, $verifyCode, $codeType) {
        $map = array(
            'mobile'      => $mobile,
            'verifyCode'  => $verifyCode,
            'codeType'    => $codeType,
            'channelType' => self::CHANNEL_TYPE_VERIFY,
        );
        if (empty($verifyCode)) {
            return false;
        }
        //有效时间
        $timeout           = C('VERIFY_TIMEOUT') ? C('VERIFY_TIMEOUT') : 60*5;
        $startOffset       = time() - $timeout;
        $map['createTime'] = array('GT', $startOffset);
        $found             = $this->where($map)->find();
        if ($found) {
            $id = $found['id'];
            if ($found['verifyResult'] == self::VERIFY_RESULT_SUCC) {
                return true;
            }
            $updateData = array(
                'verifyResult' => self::VERIFY_RESULT_SUCC,
                'updateTime'   => time(),
            );
            return $this->where(array('id' => $id))->save($updateData);
        } else {
            $this->error = '没有找到匹配的验证码！';
            return false;
        }
    }
}
