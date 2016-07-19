# thinkphp-sms
用于thinkphp (3.x) 的通用短信发送接口

## 0.简介
当前支持的短信接口：
* lmobile
* sms.cn （云信）
* montnets 梦网短信接口
* Oxiyuan 熹源短信接口

========================

当前实现的功能

1. 统一的短信发送和验证接口。不管后期换了哪家的接口，只需要更换配置和添加新的Provider文件即可。不需要修改代码。
2. 发送操作(可以直接调用此api发送短信） 和 发送业务逻辑分离（也可以用Model发送）
3. 短信模板支持
4. 认证超时功能支持
5. 最小重复发送间隔功能支持
6. 单个IP灌水检测时间限制设定
7. 单个IP 在 单个IP灌水检测时间限制 规定的时间内允许发送的最多条数
8. 基于天朝国情设计，区别验证码通道（极速） 和 通知类通道。¶

========================

## 1.安装
假设think-sms库文件放在项目根目录下的
`Libs/Service/Ihacklog/Utils/Sms` 下面.

```bash
cd projectRootDir
mkdir -p Libs/Service/Ihacklog/Utils
cd Libs/Service/Ihacklog/Utils
git clone https://github.com/ihacklog/thinkphp-sms.git Sms
```

则在主配置文件中写上：
```php
    return array(
        /* 命名空间 */
        'AUTOLOAD_NAMESPACE' => array(
            'Ihacklog' => rtrim(APP_PATH, '/')  . '/../Libs/Service/Ihacklog',
        ),
    );
```

## 2.配置
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

## 3.使用举例

### 简单例子
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


### 复杂例子（使用Model)
```php
use Common\Model\SmsModel;
use Ihacklog\Utils\Sms\Sms;

echo "<h1>succ test:</h1>";
$verifyCode = mt_rand(1000, 9999);
var_dump('code to send: '. $verifyCode);
var_dump(D('Sms')->sendVerify($mobile, $verifyCode, '', SmsModel::CODE_TYPE_REG, 'web'));
var_dump(D('Sms')->verify($mobile, $verifyCode, SmsModel::CODE_TYPE_REG));
var_dump(D('Sms')->verify($mobile, '8833', SmsModel::CODE_TYPE_REG));
var_dump(D('Sms')->getError());
```

```php
use Common\Model\SmsModel;
use Ihacklog\Utils\Sms\Sms;

echo "<h1>fail test:</h1>";
$verifyCode = mt_rand(1000, 9999);
var_dump('code to send: '. $verifyCode);
var_dump(D('Sms')->sendVerify($mobile, $verifyCode, '', SmsModel::CODE_TYPE_REG, 'web'));
var_dump(D('Sms')->verify($mobile, '8833', SmsModel::CODE_TYPE_REG));
var_dump(D('Sms')->getError());
```

## 4.如何添加自己的短信服务提供商？
假设有短信服务提供商 `Foo`
在 `Provider` 目录添加 `Foo.class.php`
```php
namespace Ihacklog\Utils\Sms\Provider;

use Ihacklog\Utils\Sms\Sms;

class Foo extends Sms
{
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
     * 必须自己实现的方法： 发送短信
     */
    public function send($mobile, $content, $extra_params = array())
    {
        //YOUR CODE HERE
    }


    /**
     * 必须自己实现的方法： 获取账户余额
     * @return mixed
     */
    public function get_balance()
    {
        //YOUR CODE HERE
    }

    /**
     * 必须自己实现的方法： 获取已发送总条数
     * @return mixed
     */
    public function get_sent_count()
    {
        //YOUR CODE HERE
    }
}
```
