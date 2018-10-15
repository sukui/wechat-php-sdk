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

use CURLFile;
use ZanPHP\HttpClient\HttpClient;

/**
 * 微信接口通用类
 *
 * @category WechatSDK
 * @subpackage library
 * @author Anyon <zoujingli@qq.com>
 * @date 2016/05/28 11:55
 */
class Tools
{

    /**
     * 产生随机字符串
     * @param int $length 指定字符长度
     * @param string $str 字符串前缀
     * @return string
     */
    static public function createNoncestr($length = 32, $str = "")
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * 数据生成签名
     * @param array $data 签名数组
     * @param string $method 签名方法
     * @return bool|string 签名值
     */
    static public function getSignature($data, $method = "sha1")
    {
        if (!function_exists($method)) {
            return false;
        }
        ksort($data);
        $params = array();
        foreach ($data as $key => $value) {
            $params[] = "{$key}={$value}";
        }
        return $method(join('&', $params));
    }

    /**
     * 生成支付签名
     * @param array $option
     * @param string $partnerKey
     * @return string
     */
    static public function getPaySign($option, $partnerKey)
    {
        ksort($option);
        $buff = '';
        foreach ($option as $k => $v) {
            $buff .= "{$k}={$v}&";
        }
        return strtoupper(md5("{$buff}key={$partnerKey}"));
    }

    /**
     * XML编码
     * @param mixed $data 数据
     * @param string $root 根节点名
     * @param string $item 数字索引的子节点名
     * @param string $id 数字索引子节点key转换的属性名
     * @return string
     */
    static public function arr2xml($data, $root = 'xml', $item = 'item', $id = 'id')
    {
        return "<{$root}>" . self::_data_to_xml($data, $item, $id) . "</{$root}>";
    }

    /**
     * XML内容生成
     * @param array $data 数据
     * @param string $item 子节点
     * @param string $id 节点ID
     * @param string $content 节点内容
     * @return string
     */
    static private function _data_to_xml($data, $item = 'item', $id = 'id', $content = '')
    {
        foreach ($data as $key => $val) {
            is_numeric($key) && $key = "{$item} {$id}=\"{$key}\"";
            $content .= "<{$key}>";
            if (is_array($val) || is_object($val)) {
                $content .= self::_data_to_xml($val);
            } elseif (is_numeric($val)) {
                $content .= $val;
            } else {
                $content .= '<![CDATA[' . preg_replace("/[\\x00-\\x08\\x0b-\\x0c\\x0e-\\x1f]/", '', $val) . ']]>';
            }
            list($_key,) = explode(' ', $key . ' ');
            $content .= "</$_key>";
        }
        return $content;
    }


    /**
     * 将xml转为array
     * @param string $xml
     * @return array
     */
    static public function xml2arr($xml)
    {
        return json_decode(Tools::json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }

    /**
     * 生成安全JSON数据
     * @param array $array
     * @return string
     */
    static public function json_encode($array)
    {
        return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', function ($matches) {
            return mb_convert_encoding(pack("H*", $matches[1]), "UTF-8", "UCS-2BE");
        }, json_encode($array));
    }

    /**
     * 以get方式提交请求
     * @param $url
     * @param int $timeout
     * @return bool|mixed
     */
    static public function httpGet($url,$timeout=5000)
    {
        $httpClient = new HttpClient();
        $response = yield $httpClient->getByURL($url,[],$timeout);
        yield (intval($response->getStatusCode()) === 200) ? $response->getBody() : false;
    }

    /**
     * 以post方式提交请求
     * @param string $url
     * @param array|string $data
     * @param int $timeout
     * @return bool|mixed
     */
    static public function httpPost($url, $data, $timeout=5000)
    {

        $httpClient = new HttpClient();
        $response = yield $httpClient->postByURL($url,self::_buildPost($data),$timeout);
        yield (intval($response->getStatusCode()) === 200) ? $response->getBody() : false;
    }

    /**
     * 使用证书，以post方式提交xml到对应的接口url
     * @param string $url POST提交的内容
     * @param array $data 请求的地址
     * @param string $ssl_cer 证书Cer路径 | 证书内容
     * @param string $ssl_key 证书Key路径 | 证书内容
     * @param int $timeout 设置请求超时时间
     * @return bool|mixed
     */
    static public function httpsPost($url, $data, $ssl_cert = null, $ssl_key = null, $timeout = 30000)
    {
        $httpClient = new HttpClient();
        $options = [];
        if (!is_null($ssl_cert) && file_exists($ssl_cert) && is_file($ssl_cert)) {
            $options['ssl_cert_file']    = $ssl_cert;
        }
        if (!is_null($ssl_key) && file_exists($ssl_key) && is_file($ssl_key)) {
            $options['ssl_key_file'] = $ssl_key;
        }
        $httpClient->set($options);
        $response = yield $httpClient->postByURL($url,self::_buildPost($data),$timeout);
        yield (intval($response->getStatusCode()) === 200) ? $response->getBody() : false;
    }

    /**
     * POST数据过滤处理
     * @param array $data
     * @return array
     */
    static private function _buildPost(&$data)
    {
        if (is_array($data)) {
            foreach ($data as &$value) {
                if (is_string($value) && $value[0] === '@' && class_exists('CURLFile', false)) {
                    $filename = realpath(trim($value, '@'));
                    file_exists($filename) && $value = new CURLFile($filename);
                }
            }
        }
        return $data;
    }

    /**
     * 读取微信客户端IP
     * @return null|string
     */
    static public function getAddress()
    {
        $ip = yield getClientIp();
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            yield $ip;
        } else {
            yield '0.0.0.0';
        }
    }

    /**
     * 设置缓存，按需重载
     * @param string $name
     * @param mixed $value
     * @param int $expired
     * @return bool
     */
    static public function setCache($name, $value, $expired = 0)
    {
        yield Cache::set($name, $value, $expired);
    }

    /**
     * 获取缓存，按需重载
     * @param string $name
     * @return mixed
     */
    static public function getCache($name)
    {
        yield Cache::get($name);
    }

    /**
     * 清除缓存，按需重载
     * @param string $name
     * @return bool
     */
    static public function removeCache($name)
    {
        yield Cache::del($name);
    }

    /**
     * SDK日志处理方法
     * @param string $msg 日志行内容
     * @return \Generator
     */
    static public function log($msg)
    {
        yield Cache::put($msg);
    }

    /**
     * 银行卡相关加密
     * @param $str
     * @param $public_key
     * @return string
     */
    static public function getBankSign($str,$public_key){
        openssl_sign($str, $sign, $public_key, OPENSSL_PKCS1_OAEP_PADDING);
        return base64_encode($sign);
    }

}
