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

namespace Wechat;

use Wechat\Lib\Common;
use Wechat\Lib\Tools;

/**
 * 微信前端 JavaScript 签名SDK
 *
 * @author Anyon <zoujingli@qq.com>
 * @date 2016/06/28 11:24
 */
class WechatScript extends Common
{

    /**
     * JSAPI授权TICKET
     * @var string
     */
    public $jsapi_ticket;

    /**
     * 删除JSAPI授权TICKET
     * @param string $appid
     * @return bool
     */
    public function resetJsTicket($appid = '')
    {
        $this->jsapi_ticket = '';
        $authname = 'wechat_jsapi_ticket_' . empty($appid) ? $this->appid : $appid;
        yield Tools::removeCache($authname);
        yield true;
    }

    /**
     * 获取JSAPI授权TICKET
     * @param string $appid 用于多个appid时使用,可空
     * @param string $jsapi_ticket 手动指定jsapi_ticket，非必要情况不建议用
     * @param string $access_token 获取 jsapi_ticket 指定 access_token
     * @return bool|string
     */
    public function getJsTicket($appid = '', $jsapi_ticket = '', $access_token = '')
    {
        if (empty($access_token)) {
            if (!$this->access_token && !yield $this->getAccessToken()) {
                yield false;
                return;
            }
            $access_token = $this->access_token;
        }
        if (empty($appid)) {
            $appid = $this->appid;
        }
        # 手动指定token，优先使用
        if ($jsapi_ticket) {
            $this->jsapi_ticket = $jsapi_ticket;
            yield $this->jsapi_ticket;
            return;
        }
        # 尝试从缓存中读取
        $cache = 'wechat_jsapi_ticket_' . $appid;
        $jt =yield Tools::getCache($cache);
        if ($jt) {
            $this->jsapi_ticket = $jt;
            yield $this->jsapi_ticket;
            return;
        }
        # 检测事件注册
        if (isset(Loader::$callback[__FUNCTION__])) {
            yield $this->jsapi_ticket = call_user_func_array(Loader::$callback[__FUNCTION__], array(&$this, &$cache));
            return;
        }
        # 调接口获取
        $result =yield Tools::httpGet(self::API_URL_PREFIX . self::GET_TICKET_URL . "access_token={$access_token}" . '&type=jsapi');
        if ($result) {
            $json = json_decode($result, true);
            if (empty($json) || !empty($json['errcode'])) {
                $this->errCode = isset($json['errcode']) ? $json['errcode'] : '505';
                $this->errMsg = isset($json['errmsg']) ? $json['errmsg'] : '无法解析接口返回内容！';
                yield $this->checkRetry(__FUNCTION__, func_get_args());
                return;
            }
            $this->jsapi_ticket = $json['ticket'];
            yield Tools::setCache($cache, $this->jsapi_ticket, $json['expires_in'] ? intval($json['expires_in']) - 100 : 3600);
            yield $this->jsapi_ticket;
            return;
        }
        yield false;
    }

    /**
     * 获取JsApi使用签名
     * @param string $url 网页的URL，自动处理#及其后面部分
     * @param int $timestamp 当前时间戳 (为空则自动生成)
     * @param string $noncestr 随机串 (为空则自动生成)
     * @param string $appid 用于多个appid时使用,可空
     * @param string $access_token 获取 jsapi_ticket 指定 access_token
     * @return array|bool 返回签名字串
     */
    public function getJsSign($url, $timestamp = 0, $noncestr = '', $appid = '', $access_token = '')
    {
        if (!$this->jsapi_ticket && empty(yield $this->getJsTicket($appid, '', $access_token)) || empty($url)) {
            yield false;
            return;
        }
        $data = array(
            "jsapi_ticket" => $this->jsapi_ticket,
            "timestamp"    => empty($timestamp) ? time() : $timestamp,
            "noncestr"     => '' . empty($noncestr) ? Tools::createNoncestr(16) : $noncestr,
            "url"          => trim($url),
        );
        yield array(
            "url"       => $url,
            'debug'     => false,
            "appId"     => empty($appid) ? $this->appid : $appid,
            "nonceStr"  => $data['noncestr'],
            "timestamp" => $data['timestamp'],
            "signature" => Tools::getSignature($data, 'sha1'),
            'jsApiList' => array(
                'onMenuShareTimeline', 'onMenuShareAppMessage', 'onMenuShareQQ', 'onMenuShareWeibo', 'onMenuShareQZone',
                'hideOptionMenu', 'showOptionMenu', 'hideMenuItems', 'showMenuItems', 'hideAllNonBaseMenuItem', 'showAllNonBaseMenuItem',
                'chooseImage', 'previewImage', 'uploadImage', 'downloadImage', 'closeWindow', 'scanQRCode', 'chooseWXPay',
                'translateVoice', 'getNetworkType', 'openLocation', 'getLocation',
                'openProductSpecificView', 'addCard', 'chooseCard', 'openCard',
                'startRecord', 'stopRecord', 'onVoiceRecordEnd', 'playVoice', 'pauseVoice', 'stopVoice', 'onVoicePlayEnd', 'uploadVoice', 'downloadVoice',
                'openWXDeviceLib', 'closeWXDeviceLib', 'getWXDeviceInfos', 'sendDataToWXDevice', 'disconnectWXDevice', 'getWXDeviceTicket', 'connectWXDevice',
                'startScanWXDevice', 'stopScanWXDevice', 'onWXDeviceBindStateChange', 'onScanWXDeviceResult', 'onReceiveDataFromWXDevice',
                'onWXDeviceBluetoothStateChange', 'onWXDeviceStateChange'
            )
        );
    }

}
