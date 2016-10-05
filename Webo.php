<?php
/**
 * Created by PhpStorm.
 * User: hayix
 * Date: 2016/10/5
 * Time: 12:36
 */

namespace gmars\weibo;

use yii;
use yii\base\Event;
use yii\base\Component;
use yii\web\HttpException;
use yii\base\InvalidParamException;
use yii\base\InvalidConfigException;

class Webo extends Component
{
    /**
     * @var应用key
     */
    public $appKey;

    /**
     * @var应用密钥
     */
    public $appSecret;

    public function init()
    {
        if ($this->appKey === null) {
            throw new InvalidConfigException('The appKey property must be set.');
        } elseif ($this->appSecret === null) {
            throw new InvalidConfigException('The appSecret property must be set.');
        }
    }

    /**
     * 解析api回调请求
     * 此方法由callmez编写，在yii2-wechat-sdk中
     * 会根据返回结果处理响应的回调结果.如 40001 access_token失效(会强制更新access_token后)重发, 保证请求的的有效
     * @param $url
     * @param $params
     * @param $method
     * @param bool $force 是否强制更新access_token 并再次请求
     * @return bool|mixed
     */
    protected function parseHttpResult($url, $params, $method, $force = true)
    {
        if (stripos($url, 'http://') === false && stripos($url, 'https://') === false) {
            $url = self::WECHAT_BASE_URL . $url;
        }
        $return = $this->http($url, $params, $method);
        $return = json_decode($return, true) ? : $return;
        if (isset($return['error_code']) && $return['error_code']) {
            $this->lastErrorInfo = $return;
            $log = [
                'class' => __METHOD__,
                'arguments' => func_get_args(),
                'result' => $return,
            ];
            /*switch ($return['errcode']) {
                case 40001: //access_token 失效,强制更新access_token, 并更新地址重新执行请求
                    if ($force) {
                        Yii::warning($log, 'wechat.sdk');
                        $url = preg_replace_callback("/access_token=([^&]*)/i", function(){
                            return 'access_token=' . $this->getAccessToken(true);
                        }, $url);
                        $return = $this->parseHttpResult($url, $params, $method, false); // 仅重新获取一次,否则容易死循环
                    }
                    break;
            }*/
            Yii::error($log, 'wechat.sdk');
        }
        return $return;
    }
    /**
     * Http协议调用接口方法
     * 此方法由callmez编写，在yii2-wechat-sdk中
     * @param $url api地址
     * @param $params 参数
     * @param string $type 提交类型
     * @return bool|mixed
     * @throws \yii\base\InvalidParamException
     */
    protected function http($url, $params = null, $type = 'get')
    {
        $curl = curl_init();
        switch ($type) {
            case 'get':
                is_array($params) && $params = http_build_query($params);
                !empty($params) && $url .= (stripos($url, '?') === false ? '?' : '&') . $params;
                break;
            case 'post':
                curl_setopt($curl, CURLOPT_POST, true);
                if (!is_array($params)) {
                    throw new InvalidParamException("Post data must be an array.");
                }
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                break;
            case 'raw':
                curl_setopt($curl, CURLOPT_POST, true);
                if (is_array($params)) {
                    throw new InvalidParamException("Post raw data must not be an array.");
                }
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                break;
            default:
                throw new InvalidParamException("Invalid http type '{$type}.' called.");
        }
        if (stripos($url, "https://") !== false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1); // 微信官方屏蔽了ssl2和ssl3, 启用更高级的ssl
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $content = curl_exec($curl);
        $status = curl_getinfo($curl);
        curl_close($curl);
        if (isset($status['http_code']) && intval($status['http_code']) == 200) {
            return $content;
        }
        return false;
    }

}