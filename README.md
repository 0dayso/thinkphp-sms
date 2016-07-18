# thinkphp-sms
用于thinkphp (3.x) 的通用短信发送接口


放在thinkphp源码的
Library/Ihacklog/Utils 下面.


simple usage:

demo 配置：
```php
    'SMS' => array(
        'VERIFY_TIMEOUT' => 300, //认证超时时间(单位：秒)
        'MIN_TIME_SPAN'   => 60, //最小重复发送间隔，单位秒,不限制则设置为0
        'SINGLE_IP_LIMIT' => 30, //单个IP 在SINGLE_IP_LIMIT_TIME规定的时间内允许发送的最多条数,不限制则设置为0
        'SINGLE_IP_LIMIT_TIME' => 3600, //单个IP灌水检测时间限制，单位秒
        //主短信服务商设置（名称为ClassName), 通道为验证码通道
        'SP' => 'Montnets',
        //备用短信服务商设置（名称为ClassName), 通道为验证码通道
        'SP_BAK' => '',
        //短信服务商设置（名称为ClassName), 通知类通道
        'SP_NOTICE' => '',
        //备用短信服务商设置（名称为ClassName), 通知类通道
        'SP_NOTICE_BAK' => '',
        'Montnets' => array(
            'API_URL'  => 'http://61.145.229.29:9006/MWGate/wmgw.asmx/',
            'USERNAME' => '用户名',
            'PASSWORD' => '密码',
        ),
        'Oxiyuan' => array(
            'USERID' => '用户id',
            'ACCOUNT' => '账户名',
            'PASSWORD' => '密码',
        )
    ),
```   

    test 
```php
//----------------
use Ihacklog\Utils\Sms\Sms;
//----------------

        $mobile = '你自己的手机号';
        $content = 'this is demo content.'  . ' [for test] ' . date('Y-m-d H:i:s');

        $sms = Sms::instance('Montnets', C('SMS.Montnets'));
        $smsSendRs = $sms->send($mobile, $content . '[Montnets]');
        var_dump($smsSendRs, $sms->get_errors());

        $sms = Sms::instance('Oxiyuan', C('SMS.Oxiyuan'));
        $smsSendRs = $sms->send($mobile, $content. '[Oxiyuan]');
        var_dump($smsSendRs, $sms->get_errors());
```
        
        
complex demo:
```php
use Common\Model\SmsModel;
use Ihacklog\Utils\Sms\Sms;

echo "<h1>succ test:</h1>";
$verifyCode = mt_rand(1000, 9999);
var_dump('code to send: '. $verifyCode);
var_dump(D('Sms')->sendVerify($mobile, $verifyCode, '', SmsModel::CODE_TYPE_REG, 'web'));
var_dump(D('Sms')->verify($mobile, $verifyCode, SmsModel::CODE_TYPE_REG));
var_dump(D('Sms')->verify($mobile, '8833', SmsModel::CODE_TYPE_REG));
var_dump(D('Sms')->getDbError());
```

```php
use Common\Model\SmsModel;
use Ihacklog\Utils\Sms\Sms;

echo "<h1>fail test:</h1>";
$verifyCode = mt_rand(1000, 9999);
var_dump('code to send: '. $verifyCode);
var_dump(D('Sms')->sendVerify($mobile, $verifyCode, '', SmsModel::CODE_TYPE_REG, 'web'));
var_dump(D('Sms')->verify($mobile, '8833', SmsModel::CODE_TYPE_REG));
var_dump(D('Sms')->getDbError());
```