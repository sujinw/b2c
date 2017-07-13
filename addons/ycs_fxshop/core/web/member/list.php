<?php
if (!defined('IN_IA')) {
    exit('Access Denied');
}
global $_W, $_GPC;

$op     = $operation = $_GPC['op'] ? $_GPC['op'] : 'display';
$groups = m('member')->getGroups();
$levels = m('member')->getLevels();
$shop   = m('common')->getSysset('shop');
if ($op == 'display') {
    ca('member.member.view');
    $pindex    = max(1, intval($_GPC['page']));
    $psize     = 20;
    $condition = " and dm.uniacid=:uniacid";
    $params    = array(
        ':uniacid' => $_W['uniacid']
    );
    if (!empty($_GPC['mid'])) {
        $condition .= ' and dm.id=:mid';
        $params[':mid'] = intval($_GPC['mid']);
    }
    if (!empty($_GPC['realname'])) {
        $_GPC['realname'] = trim($_GPC['realname']);
        $condition .= ' and ( dm.realname like :realname or dm.nickname like :realname or dm.mobile like :realname)';
        $params[':realname'] = "%{$_GPC['realname']}%";
    }
    if (empty($starttime) || empty($endtime)) {
        $starttime = strtotime('-1 month');
        $endtime   = time();
    }
    if (!empty($_GPC['time'])) {
        $starttime = strtotime($_GPC['time']['start']);
        $endtime   = strtotime($_GPC['time']['end']);
        if ($_GPC['searchtime'] == '1') {
            $condition .= " AND dm.createtime >= :starttime AND dm.createtime <= :endtime ";
            $params[':starttime'] = $starttime;
            $params[':endtime']   = $endtime;
        }
    }
    if ($_GPC['level'] != '') {
        $condition .= ' and dm.level=' . intval($_GPC['level']);
    }
    if ($_GPC['groupid'] != '') {
        $condition .= ' and dm.groupid=' . intval($_GPC['groupid']);
    }
    if ($_GPC['followed'] != '') {
        if ($_GPC['followed'] == 2) {
            $condition .= ' and f.follow=0 and dm.uid<>0';
        } else {
            $condition .= ' and f.follow=' . intval($_GPC['followed']);
        }
    }
    $sql = "select dm.*,l.levelname,g.groupname from " . tablename('ycs_fxshop_member') . " dm " . " left join " . tablename('ycs_fxshop_member_group') . " g on dm.groupid=g.id" . " left join " . tablename('ycs_fxshop_member_level') . " l on dm.level =l.id" . " left join " . tablename('mc_mapping_fans') . "f on f.openid=dm.openid  and f.uniacid={$_W['uniacid']}" . " where 1 {$condition}  ORDER BY dm.id DESC";
    if (empty($_GPC['export'])) {
        $sql .= " limit " . ($pindex - 1) * $psize . ',' . $psize;
    }
    $list = pdo_fetchall($sql, $params);
    foreach ($list as &$row) {
        $row['levelname']  = empty($row['levelname']) ? (empty($shop['levelname']) ? '普通会员' : $shop['levelname']) : $row['levelname'];
        $row['ordercount'] = pdo_fetchcolumn('select count(*) from ' . tablename('ycs_fxshop_order') . ' where uniacid=:uniacid and openid=:openid and status=3', array(
            ':uniacid' => $_W['uniacid'],
            ':openid' => $row['openid']
        ));
        $row['ordermoney'] = pdo_fetchcolumn('select sum(goodsprice) from ' . tablename('ycs_fxshop_order') . ' where uniacid=:uniacid and openid=:openid and status=3', array(
            ':uniacid' => $_W['uniacid'],
            ':openid' => $row['openid']
        ));
        $row['credit1']    = m('member')->getCredit($row['openid'], 'credit1');
        $row['credit2']    = m('member')->getCredit($row['openid'], 'credit2');
        $row['followed']   = m('user')->followed($row['openid']);
    }
    unset($row);
    if ($_GPC['export'] == '1') {
        ca('member.member.export');
        plog('member.member.export', '导出会员数据');
        foreach ($list as &$row) {
            $row['createtime'] = date('Y-m-d H:i', $row['createtime']);
            $row['groupname']  = empty($row['groupname']) ? '无分组' : $row['groupname'];
            $row['levelname']  = empty($row['levelname']) ? '普通会员' : $row['levelname'];
        }
        unset($row);
        m('excel')->export($list, array(
            "title" => "会员数据-" . date('Y-m-d-H-i', time()),
            "columns" => array(
                array(
                    'title' => '昵称',
                    'field' => 'nickname',
                    'width' => 12
                ),
                array(
                    'title' => '姓名',
                    'field' => 'realname',
                    'width' => 12
                ),
                array(
                    'title' => '手机号',
                    'field' => 'mobile',
                    'width' => 12
                ),
                array(
                    'title' => '会员等级',
                    'field' => 'levelname',
                    'width' => 12
                ),
                array(
                    'title' => '会员分组',
                    'field' => 'groupname',
                    'width' => 12
                ),
                array(
                    'title' => '注册时间',
                    'field' => 'createtime',
                    'width' => 12
                ),
                array(
                    'title' => '积分',
                    'field' => 'credit1',
                    'width' => 12
                ),
                array(
                    'title' => '余额',
                    'field' => 'credit2',
                    'width' => 12
                ),
                array(
                    'title' => '成交订单数',
                    'field' => 'ordercount',
                    'width' => 12
                ),
                array(
                    'title' => '成交总金额',
                    'field' => 'ordermoney',
                    'width' => 12
                )
            )
        ));
    }
    $total = pdo_fetchcolumn("select count(*) from" . tablename('ycs_fxshop_member') . " dm " . " left join " . tablename('ycs_fxshop_member_group') . " g on dm.groupid=g.id" . " left join " . tablename('ycs_fxshop_member_level') . " l on dm.level =l.id" . " left join " . tablename('mc_mapping_fans') . "f on f.openid=dm.openid" . " where 1 {$condition} ", $params);
    $pager = pagination($total, $pindex, $psize);
} else if ($op == 'detail') {
    ca('member.member.view');
    $hascommission = false;
    $plugin_com    = p('commission');
    if ($plugin_com) {
        $plugin_com_set = $plugin_com->getSet();
        $hascommission  = !empty($plugin_com_set['level']);
    }
    $id = intval($_GPC['id']);
    if (checksubmit('submit')) {
        ca('member.member.edit');
        $data = is_array($_GPC['data']) ? $_GPC['data'] : array();
        pdo_update('ycs_fxshop_member', $data, array(
            'id' => $id,
            'uniacid' => $_W['uniacid']
        ));

        $member = m('member')->getMember($id);

        plog('member.member.edit', "修改会员资料  ID: {$member['id']} <br/> 会员信息:  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
        if ($hascommission) {
            if (cv('commission.agent.edit|commission.agent.check')) {
                $adata = is_array($_GPC['adata']) ? $_GPC['adata'] : array();
                if (!empty($adata)) {
                    if (empty($_GPC['oldstatus']) && $adata['status'] == 1) {
                        $time               = time();
                        $adata['agenttime'] = time();
                        $plugin_com->sendMessage($member['openid'], array(
                            'nickname' => $member['nickname'],
                            'agenttime' => $time
                        ), TM_COMMISSION_BECOME);
                        plog('commission.agent.check', "审核分销商 <br/>分销商信息:  ID: {$member['id']} /  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
                    }
                    plog('commission.agent.edit', "修改分销商 <br/>分销商信息:  ID: {$member['id']} /  {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
                    pdo_update('ycs_fxshop_member', $adata, array(
                        'id' => $id,
                        'uniacid' => $_W['uniacid']
                    ));
                }
            }
        }
        message('保存成功!', $this->createWebUrl('member/list'), 'success');
    }

    if ($hascommission) {
        $agentlevels = $plugin_com->getLevels();
    }
    $member = m('member')->getInfo($id);

    if ($hascommission) {
        $member = $plugin_com->getInfo($id, array(
            'total',
            'pay'
        ));
    }
    $member['self_ordercount'] = pdo_fetchcolumn('select count(*) from ' . tablename('ycs_fxshop_order') . ' where uniacid=:uniacid and openid=:openid and status=3', array(
        ':uniacid' => $_W['uniacid'],
        ':openid' => $member['openid']
    ));
    $member['self_ordermoney'] = pdo_fetchcolumn('select sum(goodsprice) from ' . tablename('ycs_fxshop_order') . ' where uniacid=:uniacid and openid=:openid and status=3', array(
        ':uniacid' => $_W['uniacid'],
        ':openid' => $member['openid']
    ));
    if (!empty($member['agentid'])) {
        $parentagent = m('member')->getMember($member['agentid']);
    }
    //利用添加数据方式获得积分和余额的方式获得积分和余额
    $profile = m('member')->getInfo($id);
    $profile['credit1'] = m('member')->getCredit($profile['openid'], 'credit1');
    $profile['credit2'] = m('member')->getCredit($profile['openid'], 'credit2');
    //替换积分你和余额
    $member['credit1']=$profile['credit1'];
    $member['credit2']=$profile['credit2'];

} else if ($op == 'delete') {
    ca('member.member.delete');
    $id      = intval($_GPC['id']);
    $isagent = intval($_GPC['isagent']);
    $member  = pdo_fetch("select * from " . tablename('ycs_fxshop_member') . " where uniacid=:uniacid and id=:id limit 1 ", array(
        ':uniacid' => $_W['uniacid'],
        ':id' => $id
    ));
    if (empty($member)) {
        message('会员不存在，无法删除!', $this->createWebUrl('member/list'), 'error');
    }
    if (p('commission')) {
        $agentcount = pdo_fetchcolumn('select count(*) from ' . tablename('ycs_fxshop_member') . ' where  uniacid=:uniacid and agentid=:agentid limit 1 ', array(
            ':uniacid' => $_W['uniacid'],
            ':agentid' => $id
        ));
        if ($agentcount > 0) {
            message('此会员有下线存在，无法删除!', '', 'error');
        }
    }
    pdo_delete('ycs_fxshop_member', array(
        'id' => $_GPC['id']
    ));
    plog('member.member.delete', "删除会员  ID: {$member['id']} <br/>会员信息: {$member['openid']}/{$member['nickname']}/{$member['realname']}/{$member['mobile']}");
    message('删除成功！', $this->createWebUrl('member/list'), 'success');
}
load()->func('tpl');
include $this->template('web/member/list');