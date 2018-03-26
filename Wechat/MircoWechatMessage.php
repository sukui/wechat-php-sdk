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
 * 微信模板消息
 *
 * @author Anyon <zoujingli@qq.com>
 * @date 2016/06/28 11:29
 */
class MircoWechatMessage extends Common
{

    /**
     * 获取模板列表
     * @return bool|array
     */
    public function getAllPrivateTemplate()
    {
        if (!$this->access_token && !yield $this->getAccessToken()) {
            yield false;
            return;
        }
        $result = yield Tools::httpPost(self::API_URL_PREFIX . "/wxopen/template/list?access_token={$this->access_token}", ["offset"=>0,"count"=> 20]);
        if ($result) {
            $json = json_decode($result, true);
            if (empty($json) || !empty($json['errcode'])) {
                $this->errCode = isset($json['errcode']) ? $json['errcode'] : '505';
                $this->errMsg = isset($json['errmsg']) ? $json['errmsg'] : '无法解析接口返回内容！';
                yield $this->checkRetry(__FUNCTION__, func_get_args());
                return;
            }
            yield $json;
            return;
        }
        yield false;
    }


    /**
     * 删除模板消息
     * @param string $tpl_id
     * @return bool
     */
    public function delPrivateTemplate($tpl_id)
    {
        if (!$this->access_token && !yield $this->getAccessToken()) {
            yield false;
            return;
        }
        $data = array('template_id' => $tpl_id);
        $result = yield Tools::httpPost(self::API_URL_PREFIX . "/wxopen/template/del?access_token={$this->access_token}", Tools::json_encode($data));
        if ($result) {
            $json = json_decode($result, true);
            if (empty($json) || !empty($json['errcode'])) {
                $this->errCode = isset($json['errcode']) ? $json['errcode'] : '505';
                $this->errMsg = isset($json['errmsg']) ? $json['errmsg'] : '无法解析接口返回内容！';
                yield $this->checkRetry(__FUNCTION__, func_get_args());
                return;
            }
            yield true;
            return;
        }
        yield false;
    }

    /**
     * 发送模板消息
     * @param array $data 消息结构
     * {
     * "touser": "OPENID",
     * "template_id": "TEMPLATE_ID",
     * "page": "index",
     * "form_id": "FORMID",
     * "data": {
     * "keyword1": {
     * "value": "339208499",
     * "color": "#173177"
     * },
     * "keyword2": {
     * "value": "2015年01月05日 12:30",
     * "color": "#173177"
     * },
     * "keyword3": {
     * "value": "粤海喜来登酒店",
     * "color": "#173177"
     * } ,
     * "keyword4": {
     * "value": "广州市天河区天河路208号",
     * "color": "#173177"
     * }
     * },
     * "emphasis_keyword": "keyword1.DATA"
     * }
     * @return bool|array
     */
    public function sendTemplateMessage($data)
    {
        if (!$this->access_token && !yield $this->getAccessToken()) {
            yield false;
            return;
        }
        $result = yield Tools::httpPost(self::API_URL_PREFIX . "/message/wxopen/template/send?access_token={$this->access_token}", Tools::json_encode($data));
        if ($result) {
            $json = json_decode($result, true);
            if (empty($json) || !empty($json['errcode'])) {
                $this->errCode = isset($json['errcode']) ? $json['errcode'] : '505';
                $this->errMsg = isset($json['errmsg']) ? $json['errmsg'] : '无法解析接口返回内容！';
                //yield $this->checkRetry(__FUNCTION__, func_get_args());
                yield false;
                return;
            }
            yield $json;
            return;
        }
        yield false;
    }

}
