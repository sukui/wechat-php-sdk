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
use ZanPHP\Log\Log;
use ZanPHP\NoSql\Facade\Cache as RedisCache;
use ZanPHP\Utilities\File\File;
use ZanPHP\Utilities\File\OnceFile;

/**
 * 微信SDK基础缓存类
 *
 * @author Anyon <zoujingli@qq.com>
 * @date 2016-08-20 17:50
 */
class Cache
{

    /**
     * 缓存位置
     * @var string
     */
    static public $appid;

    /**
     * 设置缓存
     * @param string $name
     * @param string $value
     * @param int $expired
     * @return mixed
     */
    static public function set($name, $value, $expired = 0)
    {
        if (isset(Loader::$callback['CacheSet'])) {
            return call_user_func_array(Loader::$callback['CacheSet'], func_get_args());
        }
        yield RedisCache::set('weixin.common.default',$name,$value);
    }

    /**
     * 读取缓存
     * @param string $name
     * @return mixed
     */
    static public function get($name)
    {
        if (isset(Loader::$callback['CacheGet'])) {
            return call_user_func_array(Loader::$callback['CacheGet'], func_get_args());
        }
        yield RedisCache::get('weixin.common.default',$name);
    }

    /**
     * 删除缓存
     * @param string $name
     * @return mixed
     */
    static public function del($name)
    {
        if (isset(Loader::$callback['CacheDel'])) {
            return call_user_func_array(Loader::$callback['CacheDel'], func_get_args());
        }
        yield RedisCache::del('weixin.common.default',$name);
    }

    /**
     * 输出内容到日志
     * @param string $message
     * @return mixed
     */
    static public function put($message)
    {
        if (isset(Loader::$callback['CachePut'])) {
            return call_user_func_array(Loader::$callback['CachePut'], func_get_args());
        }
        yield Log::make('debug')->info($message);
    }

    /**
     * 文件缓存，成功返回文件路径
     * @param string $content 文件内容
     * @param string $filename 文件名称
     * @return bool|string
     */
    static public function file($content, $filename = '')
    {
        if (isset(Loader::$callback['CacheFile'])) {
            return call_user_func_array(Loader::$callback['CacheFile'], func_get_args());
        }
        empty($filename) && $filename = md5($content) . '.' . self::getFileExt($content);
        $path = APP_PATH."/upload/";
        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            return false;
        }
        $fileWriter = new OnceFile();
        $ret = yield $fileWriter->putContents($path.$filename,$content);
        if ($ret) {
            yield $path . $filename;
        }
        yield false;
    }

    /**
     * 根据文件流读取文件后缀
     * @param string $content
     * @return string
     */
    static public function getFileExt($content)
    {
        $types = array(
            255216 => 'jpg', 7173 => 'gif', 6677 => 'bmp', 13780 => 'png',
            7368   => 'mp3', 4838 => 'wma', 7784 => 'mid', 6063 => 'xml',
        );
        $typeInfo = @unpack("C2chars", substr($content, 0, 2));
        $typeCode = intval($typeInfo['chars1'] . $typeInfo['chars2']);
        return isset($types[$typeCode]) ? $types[$typeCode] : 'mp4';
    }

}
