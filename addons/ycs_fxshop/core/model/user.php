<?php


if (!defined('IN_IA')) {
    exit('Access Denied');
}
class Ycs_DShop_User
{
    private $sessionid;
    public function __construct()
    {
        global $_W;
        $this->sessionid = "__cookie_ycs_fxshop_201507200000_{$_W['uniacid']}";
    }
    function getOpenid()
    {
        $userinfo = $this->getInfo(false, true);
        return $userinfo['openid'];
    }
    function getPerOpenid()
    {
        global $_W, $_GPC;
        $lifeTime = 24 * 3600 * 3;
        session_set_cookie_params($lifeTime);
        @session_start();
        $cookieid = "__cookie_ycs_fxshop_openid_{$_W['uniacid']}";
        $openid   = base64_decode($_COOKIE[$cookieid]);
        if (!empty($openid)) {
            return $openid;
        }
        load()->func('communication');
        $appId        = $_W['account']['key'];
        $appSecret    = $_W['account']['secret'];
        $access_token = "";
        $code         = $_GPC['code'];
        $url          = $_W['siteroot'] . 'app/index.php?' . $_SERVER['QUERY_STRING'];
        if (empty($code)) {
            $authurl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $appId . "&redirect_uri=" . urlencode($url) . "&response_type=code&scope=snsapi_base&state=123#wechat_redirect";
            header('location: ' . $authurl);
            exit();
        } else {
            $tokenurl = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $appId . "&secret=" . $appSecret . "&code=" . $code . "&grant_type=authorization_code";
            $resp     = ihttp_get($tokenurl);
            $token    = @json_decode($resp['content'], true);
            if (!empty($token) && is_array($token) && $token['errmsg'] == 'invalid code') {
                $authurl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $appId . "&redirect_uri=" . urlencode($url) . "&response_type=code&scope=snsapi_base&state=123#wechat_redirect";
                header('location: ' . $authurl);
                exit();
            }
            if (is_array($token) && !empty($token['openid'])) {
                $access_token = $token['access_token'];
                $openid       = $token['openid'];
                setcookie($cookieid, base64_encode($openid));
            } else {
                $querys = explode('&', $_SERVER['QUERY_STRING']);
                $newq   = array();
                foreach ($querys as $q) {
                    if (!strexists($q, 'code=') && !strexists($q, 'state=') && !strexists($q, 'from=') && !strexists($q, 'isappinstalled=')) {
                        $newq[] = $q;
                    }
                }
                $rurl    = $_W['siteroot'] . 'app/index.php?' . implode('&', $newq);
                $authurl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $appId . "&redirect_uri=" . urlencode($rurl) . "&response_type=code&scope=snsapi_base&state=123#wechat_redirect";
                header('location: ' . $authurl);
                exit;
            }
        }
        return $openid;
    }
    function getInfo($base64 = false, $debug = false)
    {
        global $_W, $_GPC;
        $userinfo = array();
        if (YCS_FXSHOP_DEBUG) {
            $userinfo = array(
                'openid' => 'fromUser',
                'nickname' => 'fromUser',
                'headimgurl' => 'http://stc.weixin.mx/assets/mwxv5/images/logo.png',
                'province' => '广东',
                'city' => '深圳'
            );
        } else {
            load()->model('mc');
            if (empty($_GPC['directopenid'])) {
                $userinfo = mc_oauth_userinfo();
            } else {
                $userinfo = array(
                    'openid' => $this->getPerOpenid()
                );
            }
            $need_openid = true;
            if ($_W['container'] != 'wechat') {
                if ($_GPC['do'] == 'order' && $_GPC['p'] == 'pay') {
                    $need_openid = false;
                }
                if ($_GPC['do'] == 'member' && $_GPC['p'] == 'recharge') {
                    $need_openid = false;
                }
            }
            if (empty($userinfo['openid']) && $need_openid) {
                die("<!DOCTYPE html>
                <html>
                    <head>
                        <meta name='viewport' content='width=device-width, initial-scale=1, user-scalable=0'>
                        <title>抱歉，出错了</title><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1, user-scalable=0'><link rel='stylesheet' type='text/css' href='https://res.wx.qq.com/connect/zh_CN/htmledition/style/wap_err1a9853.css'>
                    </head>
                    <body>
                    <div class='page_msg'><div class='inner'><span class='msg_icon_wrp'><i class='icon80_smile'></i></span><div class='msg_content'><h4>请在微信客户端打开链接</h4></div></div></div>
                    </body>
                </html>");
            }
        }
        if ($base64) {
            return urlencode(base64_encode(json_encode($userinfo)));
        }
        return $userinfo;
    }
    function oauth_info()
    {
        global $_W, $_GPC;
        if ($_W['container'] != 'wechat') {
            if ($_GPC['do'] == 'order' && $_GPC['p'] == 'pay') {
                return array();
            }
            if ($_GPC['do'] == 'member' && $_GPC['p'] == 'recharge') {
                return array();
            }
        }
        $lifeTime = 24 * 3600 * 3;
        session_set_cookie_params($lifeTime);
        @session_start();
        $sessionid = "__cookie_ycs_fxshop_201507100000_{$_W['uniacid']}";
        $session   = json_decode(base64_decode($_SESSION[$sessionid]), true);
        $openid    = is_array($session) ? $session['openid'] : '';
        $nickname  = is_array($session) ? $session['openid'] : '';
        if (!empty($openid)) {
            return $session;
        }
        load()->func('communication');
        $appId        = $_W['account']['key'];
        $appSecret    = $_W['account']['secret'];
        $access_token = "";
        $code         = $_GPC['code'];
        $url          = $_W['siteroot'] . 'app/index.php?' . $_SERVER['QUERY_STRING'];
        if (empty($code)) {
            $authurl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $appId . "&redirect_uri=" . urlencode($url) . "&response_type=code&scope=snsapi_userinfo&state=123#wechat_redirect";
            header('location: ' . $authurl);
            exit();
        } else {
            $tokenurl = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . $appId . "&secret=" . $appSecret . "&code=" . $code . "&grant_type=authorization_code";
            $resp     = ihttp_get($tokenurl);
            $token    = @json_decode($resp['content'], true);
            if (!empty($token) && is_array($token) && $token['errmsg'] == 'invalid code') {
                $authurl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $appId . "&redirect_uri=" . urlencode($url) . "&response_type=code&scope=snsapi_userinfo&state=123#wechat_redirect";
                header('location: ' . $authurl);
                exit();
            }
            if (empty($token) || !is_array($token) || empty($token['access_token']) || empty($token['openid'])) {
                die('获取token失败,请重新进入!');
            } else {
                $access_token = $token['access_token'];
                $openid       = $token['openid'];
            }
        }
        $infourl  = "https://api.weixin.qq.com/sns/userinfo?access_token=" . $access_token . "&openid=" . $openid . "&lang=zh_CN";
        $resp     = ihttp_get($infourl);
        $userinfo = @json_decode($resp['content'], true);
        if (isset($userinfo['nickname'])) {
            $_SESSION[$sessionid] = base64_encode(json_encode($userinfo));
            return $userinfo;
        } else {
            die('获取用户信息失败，请重新进入!');
        }
    }
    function followed($openid = '')
    {
        global $_W;
        $followed = !empty($openid);
        if ($followed) {
            $mf       = pdo_fetch("select follow from " . tablename('mc_mapping_fans') . " where openid=:openid and uniacid=:uniacid limit 1", array(
                ":openid" => $openid,
                ':uniacid' => $_W['uniacid']
            ));
            $followed = $mf['follow'] == 1;
        }
        return $followed;
    }
}