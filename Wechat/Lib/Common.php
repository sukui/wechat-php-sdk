<?php

// +----------------------------------------------------------------------
// | wechat-php-sdk
// +----------------------------------------------------------------------
// | 版权所有 2014~2017 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方文档: https://www.kancloud.cn/zoujingli/wechat-php-sdk
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/wechat-php-sdk
// +----------------------------------------------------------------------

namespace Wechat\Lib;

use Wechat\Loader;

/**
 * 微信SDK基础类
 *
 * @category WechatSDK
 * @subpackage library
 * @author Anyon <zoujingli@qq.com>
 * @date 2016/05/28 11:55
 */
class Common
{

    /** API接口URL需要使用此前缀 */
    const API_BASE_URL_PREFIX = 'https://api.weixin.qq.com';
    const API_URL_PREFIX = 'https://api.weixin.qq.com/cgi-bin';
    const GET_TICKET_URL = '/ticket/getticket?';
    const AUTH_URL = '/token?grant_type=client_credential&';
    public $token;
    public $encodingAesKey;
    public $encrypt_type;
    public $appid;
    public $appsecret;
    public $access_token;
    public $postxml;
    public $_msg;
    public $errCode = 0;
    public $errMsg = "";
    public $config = array();
    private $_retry = false;

    /**
     * 构造方法
     * @param array $options
     */
    public function __construct($options = array())
    {
        $config = Loader::config($options);
        $this->token = isset($config['token']) ? $config['token'] : '';
        $this->appid = isset($config['appid']) ? $config['appid'] : '';
        $this->appsecret = isset($config['appsecret']) ? $config['appsecret'] : '';
        $this->encodingAesKey = isset($config['encodingaeskey']) ? $config['encodingaeskey'] : '';
        $this->config = $config;
    }

    /**
     * 当前当前错误代码
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errCode;
    }

    /**
     * 获取当前错误内容
     * @return string
     */
    public function getError()
    {
        return $this->errMsg;
    }

    /**
     * 获取当前操作公众号APPID
     * @return string
     */
    public function getAppid()
    {
        return $this->appid;
    }

    /**
     * 获取SDK配置参数
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }


    /**
     * 接口验证
     * @return bool
     */
    public function valid()
    {
        $encryptStr = "";
        $method = yield requestMethod();
        if ($method == "POST") {
            $postStr = yield requestContent();
            $array = (array)simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $this->encrypt_type = yield requestGet('encrypt_type');
            if ($this->encrypt_type == 'aes') {
                $encryptStr = $array['Encrypt'];
                $pc = new Prpcrypt($this->encodingAesKey);
                $array = $pc->decrypt($encryptStr, $this->appid);
                if (!isset($array[0]) || intval($array[0]) > 0) {
                    $this->errCode = $array[0];
                    $this->errMsg = $array[1];
                    yield Tools::log("Interface Authentication Failed. {$this->errMsg}[{$this->errCode}]-{$this->appid}");
                    yield false;
                    return;
                }
                $this->postxml = $array[1];
                empty($this->appid) && $this->appid = $array[2];
            } else {
                $this->postxml = $postStr;
            }
        } elseif (isset($_GET["echostr"])) {
            if (yield $this->checkSignature()) {
                yield true;
                return;
            }
            yield false;
        }
        $ret = yield $this->checkSignature($encryptStr);
        if (!$ret) {
            $this->errMsg = 'Interface authentication failed, please use the correct method to call.';
            yield false;
            return;
        }
        yield true;
    }

    /**
     * 验证来自微信服务器
     * @param string $str
     * @return bool
     */
    private function checkSignature($str = '')
    {
        $get = yield requestGet();
        $signature = isset($get["msg_signature"]) ? $get["msg_signature"] : (isset($get["signature"]) ? $get["signature"] : '');
        $timestamp = isset($get["timestamp"]) ? $get["timestamp"] : '';
        $nonce = isset($get["nonce"]) ? $get["nonce"] : '';
        $tmpArr = array($this->token, $timestamp, $nonce, $str);
        sort($tmpArr, SORT_STRING);
        if (sha1(implode($tmpArr)) == $signature) {
            yield true;
            return;
        }
        yield false;
    }

    /**
     * 获取公众号访问 access_token
     * @param string $appid 如在类初始化时已提供，则可为空
     * @param string $appsecret 如在类初始化时已提供，则可为空
     * @param string $token 手动指定access_token，非必要情况不建议用
     * @return bool|string
     */
    public function getAccessToken($appid = '', $appsecret = '', $token = '')
    {
        if (!$appid || !$appsecret) {
            list($appid, $appsecret) = array($this->appid, $this->appsecret);
        }
        if ($token) {
            yield $this->access_token = $token;
            return;
        }
        $cache = 'wechat_access_token_' . $appid;
        if (($access_token =yield Tools::getCache($cache)) && !empty($access_token)) {
            yield $this->access_token = $access_token;
            return;
        }
        # 检测事件注册
        if (isset(Loader::$callback[__FUNCTION__])) {
            yield $this->access_token = call_user_func_array(Loader::$callback[__FUNCTION__], array(&$this, &$cache));
            return;
        }
        $result =yield Tools::httpGet(self::API_URL_PREFIX . self::AUTH_URL . 'appid=' . $appid . '&secret=' . $appsecret);
        if ($result) {
            $json = json_decode($result, true);
            if (!$json || isset($json['errcode'])) {
                $this->errCode = $json['errcode'];
                $this->errMsg = $json['errmsg'];
                yield Tools::log("Get New AccessToken Error. {$this->errMsg}[{$this->errCode}] - {$this->appid}");
                return false;
            }
            $this->access_token = $json['access_token'];
            yield Tools::log("Get New AccessToken Success. - {$this->appid}");
            yield Tools::setCache($cache, $this->access_token);
            yield $this->access_token;
            return;
        }
        yield false;
    }

    /**
     * 接口失败重试
     * @param string $method SDK方法名称
     * @param array $arguments SDK方法参数
     * @return bool|mixed
     */
    protected function checkRetry($method, $arguments = array())
    {
        yield Tools::log("Run {$method} Faild. {$this->errMsg}[{$this->errCode}] - {$this->appid}");
        if (!$this->_retry && in_array($this->errCode, array('40014', '40001', '41001', '42001'))) {
            ($this->_retry = true) && yield $this->resetAuth();
            $this->errCode = 40001;
            $this->errMsg = 'no access';
            yield Tools::log("Retry Run {$method} ...");
            yield call_user_func_array(array($this, $method), $arguments);
            return;
        }
        yield false;
    }

    /**
     * 删除验证数据
     * @param string $appid 如在类初始化时已提供，则可为空
     * @return bool
     */
    public function resetAuth($appid = '')
    {
        $authname = 'wechat_access_token_' . (empty($appid) ? $this->appid : $appid);
        yield Tools::log("Reset Auth And Remove Old AccessToken.");
        $this->access_token = '';
        yield Tools::removeCache($authname);
        yield true;
    }

}
