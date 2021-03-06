<?php


if (!defined('IN_IA')) {
    exit('Access Denied');
}
global $_W, $_GPC;
if (!$_W['isfounder']) {
    message('无权访问!');
}
$op = empty($_GPC['op']) ? 'display' : $_GPC['op'];
load()->func('communication');
load()->func('file');
if ($op == 'display') {
    $auth        = $this->getAuthSet();
    $versionfile = IA_ROOT . '/addons/ycs_fxshop/version.php';
    $updatedate  = date('Y-m-d H:i', filemtime($versionfile));
    $version     = YCS_FXSHOP_VERSION;
} else if ($op == 'check') {
    set_time_limit(0);
    $auth = $this->getAuthSet();
    global $my_scenfiles;
    my_scandir(IA_ROOT . '/addons/ycs_fxshop');
    $files = array();
    foreach ($my_scenfiles as $sf) {
        $files[] = array(
            'path' => str_replace(IA_ROOT . "/addons/ycs_fxshop/", "", $sf),
            'md5' => md5_file($sf)
        );
    }
    $files   = base64_encode(json_encode($files));
    $version = defined('YCS_FXSHOP_VERSION') ? YCS_FXSHOP_VERSION : '1.0';
    $resp    = ihttp_post(YCS_FXSHOP_AUTH_URL, array(
        'type' => 'check',
        'ip' => $auth['ip'],
        'id' => $auth['id'],
        'code' => $auth['code'],
        'domain' => $auth['domain'],
        'version' => $version,
        'files' => $files
    ));
    $ret     = @json_decode($resp['content'], true);
    if (is_array($ret)) {
        if ($ret['result'] == 1) {
            $files = array();
            if (!empty($ret['files'])) {
                foreach ($ret['files'] as $file) {
                    $entry = IA_ROOT . "/ycs_fxshop/" . $file['path'];
                    if (!is_file($entry) || md5_file($entry) != $file['md5']) {
                        $files[] = array(
                            'path' => $file['path'],
                            'download' => 0
                        );
                    }
                }
            }
            $tmpdir = IA_ROOT . "/addons/ycs_fxshop/tmp/" . date('ymd');
            if (!is_dir($tmpdir)) {
                mkdirs($tmpdir);
            }
            file_put_contents($tmpdir . "/file.txt", json_encode($ret));
            die(json_encode(array(
                'result' => 1,
                'version' => $ret['version'],
                'filecount' => count($files),
                'upgrade' => !empty($ret['upgrade']),
                'log' => str_replace("\r\n", "<br/>", base64_decode($ret['log']))
            )));
        }
    }
    die(json_encode(array(
        'result' => 0,
        'message' => $resp['content'] . ". "
    )));
} else if ($op == 'download') {
    $tmpdir  = IA_ROOT . "/addons/ycs_fxshop/tmp/" . date('ymd');
    $f       = file_get_contents($tmpdir . "/file.txt");
    $upgrade = json_decode($f, true);
    $files   = $upgrade['files'];
    $auth    = $this->getAuthSet();
    $path    = "";
    foreach ($files as $f) {
        if (empty($f['download'])) {
            $path = $f['path'];
            break;
        }
    }
    if (!empty($path)) {
        $resp = ihttp_post(YCS_FXSHOP_AUTH_URL, array(
            'type' => 'download',
            'ip' => $auth['ip'],
            'id' => $auth['id'],
            'code' => $auth['code'],
            'domain' => $auth['domain'],
            'path' => $path
        ));
        $ret  = @json_decode($resp['content'], true);
        if (is_array($ret)) {
            $path    = $ret['path'];
            $dirpath = dirname($path);
            if (!is_dir(IA_ROOT . '/addons/ycs_fxshop/' . $dirpath)) {
                mkdirs(IA_ROOT . "/addons/ycs_fxshop/" . $dirpath, "0777");
            }
            $content = base64_decode($ret['content']);
            file_put_contents(IA_ROOT . '/addons/ycs_fxshop/' . $path, $content);
            if (isset($ret['path1'])) {
                $path1    = $ret['path1'];
                $dirpath1 = dirname($path1);
                if (!is_dir(IA_ROOT . '/addons/ycs_fxshop/' . $dirpath1)) {
                    mkdirs(IA_ROOT . "/addons/ycs_fxshop/" . $dirpath1, "0777");
                }
                $content1 = base64_decode($ret['content1']);
                file_put_contents(IA_ROOT . '/addons/ycs_fxshop/' . $path1, $content1);
            }
            $success = 0;
            foreach ($files as &$f) {
                if ($f['path'] == $path) {
                    $f['download'] = 1;
                    break;
                }
                if ($f['download']) {
                    $success++;
                }
            }
            unset($f);
            $upgrade['files'] = $files;
            $tmpdir           = IA_ROOT . "/addons/ycs_fxshop/tmp/" . date('ymd');
            if (!is_dir($tmpdir)) {
                mkdirs($tmpdir);
            }
            file_put_contents($tmpdir . "/file.txt", json_encode($upgrade));
            die(json_encode(array(
                'result' => 1,
                'total' => count($files),
                'success' => $success
            )));
        }
    } else {
        if (!empty($upgrade['upgrade'])) {
            $updatefile = IA_ROOT . "/addons/ycs_fxshop/upgrade.php";
            file_put_contents($updatefile, base64_decode($upgrade['upgrade']));
            require $updatefile;
            @unlink($updatefile);
        }
        file_put_contents(IA_ROOT . '/addons/ycs_fxshop/version.php', "<?php if(!defined('IN_IA')) {exit('Access Denied');}if(!defined('YCS_FXSHOP_VERSION')) {define('YCS_FXSHOP_VERSION', '" . $upgrade['version'] . "');}");
        $tmpdir = IA_ROOT . "/addons/ycs_fxshop/tmp";
        @rmdirs($tmpdir);
        $time = time();
        global $my_scenfiles;
        my_scandir(IA_ROOT . '/addons/ycs_fxshop');
        foreach ($my_scenfiles as $file) {
            if (!strexists($file, '/ycs_fxshop/data/') && !strexists($file, 'version.php')) {
                @touch($file, $time);
            }
        }
        die(json_encode(array(
            'result' => 2
        )));
    }
} else if ($op == 'checkversion') {
    file_put_contents(IA_ROOT . "/addons/ycs_fxshop/version.php", "<?php if(!defined('IN_IA')) {exit('Access Denied');}if(!defined('YCS_FXSHOP_VERSION')) {define('YCS_FXSHOP_VERSION', '1.0');}");
    header('location: ' . $this->createWebUrl('upgrade'));
    exit;
}
include $this->template('web/sysset/upgrade');