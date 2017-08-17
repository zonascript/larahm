<?php

/*
 * This file is part of the entimm/hm.
 *
 * (c) entimm <entimm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use App\Exceptions\EmptyException;
use App\Exceptions\RedirectException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

require app_path('Hm').'/lib/config.inc.php';
require app_path('Hm').'/lib/index.inc.php';

$smarty = app('smarty');
$smarty->default_modifiers = ['escape'];

if (isset(app('data')->frm['ref']) && app('data')->frm['ref'] != '') {
    bind_ref();
}

if (app('data')->frm['a'] == 'run_crontab') {
    count_earning(-2);
    throw new EmptyException();
}

$q = 'delete from hm2_online where ip=\''.app('data')->env['REMOTE_ADDR'].'\' or date + interval 30 minute < now()';
db_query($q);
$q = 'insert into hm2_online set ip=\''.app('data')->env['REMOTE_ADDR'].'\', date = now()';
db_query($q);

$userinfo = [];
$userinfo['logged'] = 0;
if (app('data')->frm['a'] == 'logout') {
    Auth::logout();
}

$stats = [];
if (app('data')->settings['crontab_stats'] == 1) {
    $s = file('stats.php');
    $stats = unserialize($s[0]);
}

show_info_box($stats);

$ref = Cookie::get('referer', '');
if ($ref) {
    $q = 'select * from hm2_users where username = \''.$ref.'\'';
    $sth = db_query($q);
    while ($row = mysql_fetch_array($sth)) {
        view_assign('referer', $row);
    }
}

view_assign('settings', app('data')->settings);

if (app('data')->frm['a'] == 'do_login') {
    do_login($userinfo);
    Auth::loginUsingId($userinfo['id']);
    if (($userinfo['logged'] == 1 and $userinfo['id'] == 1)) {
        add_log('Admin logged', 'Admin entered to admin area ip='.app('data')->env['REMOTE_ADDR']);

        $admin_url = env('ADMIN_URL');
        $html = "<head><title>HYIP Manager</title><meta http-equiv=\"Refresh\" content=\"1; URL={$admin_url}\"></head>";
        $html .= "<body><center><a href=\"{$admin_url}\">Go to admin area</a></center></body>";
        echo $html;
        throw new EmptyException($html);
    }
    throw new RedirectException('/?a=account');
} else {
    do_login_else($userinfo);
}

if (($userinfo['logged'] == 1 and $userinfo['should_count'] == 1)) {
    count_earning($userinfo['id']);
}

if ($userinfo['id'] == 1) {
    $userinfo['logged'] = 0;
}

if ($userinfo['logged'] == 1) {
    $q = 'select type, sum(actual_amount) as s from hm2_history where user_id = '.$userinfo['id'].' group by type';
    $sth = db_query($q);
    $balance = 0;
    while ($row = mysql_fetch_array($sth)) {
        if ($row['type'] == 'deposit') {
            $userinfo['total_deposited'] = number_format(abs($row['s']), 2);
        }

        if ($row['type'] == 'earning') {
            $userinfo['total_earned'] = number_format(abs($row['s']), 2);
        }

        $balance += $row['s'];
    }

    $userinfo['balance'] = number_format(abs($balance), 2);
}

if ((((((app('data')->frm['a'] != 'show_validation_image' and !$userinfo['logged']) and extension_loaded('gd')) and app('data')->settings['graph_validation'] == 1) and 0 < app('data')->settings['graph_max_chars']) and app('data')->frm['action'] != 'signup')) {
    $userinfo['validation_enabled'] = 1;
    $validation_number = gen_confirm_code(app('data')->settings['graph_max_chars'], 0);
    if (app('data')->settings['use_number_validation_number']) {
        $i = 0;
        $validation_number = '';
        while ($i < app('data')->settings['graph_max_chars']) {
            $validation_number .= rand(0, 9);
            ++$i;
        }
    }

    session(['validation_number' => $validation_number]);
}

if ((app('data')->frm['a'] == 'cancelwithdraw' and $userinfo['logged'] == 1)) {
    $id = sprintf('%d', app('data')->frm['id']);
    $q = 'delete from hm2_history where id = '.$id.' and type=\'withdraw_pending\' and user_id = '.$userinfo['id'];
    db_query($q);
    throw new RedirectException('/?a=withdraw_history');
}

view_assign('userinfo', $userinfo);

view_assign('frm', app('data')->frm);

if (isset($userinfo['id']) && $id = $userinfo['id']) {
    $ab = get_user_balance($id);
    $ab_formated = [];
    $ab['deposit'] = 0 - $ab['deposit'];
    $ab['earning'] = $ab['earning'];
    reset($ab);
    while (list($kk, $vv) = each($ab)) {
        $ab_formated[$kk] = number_format(abs($vv), 2);
    }

    view_assign('currency_sign', '$');
    view_assign('ab_formated', $ab_formated);
}

if ((app('data')->frm['a'] == 'signup' and $userinfo['logged'] != 1)) {
    include app_path('Hm').'/inc/signup.inc';
} elseif ((app('data')->frm['a'] == 'forgot_password' and $userinfo['logged'] != 1)) {
    include app_path('Hm').'/inc/forgot_password.inc';
} elseif ((app('data')->frm['a'] == 'confirm_registration' and app('data')->settings['use_opt_in'] == 1)) {
    include app_path('Hm').'/inc/confirm_registration.inc';
} elseif (app('data')->frm['a'] == 'login') {
    include app_path('Hm').'/inc/login.inc';
} elseif (((app('data')->frm['a'] == 'do_login' or app('data')->frm['a'] == 'account') and $userinfo['logged'] == 1)) {
    include app_path('Hm').'/inc/account_main.inc';
} elseif ((app('data')->frm['a'] == 'deposit' and $userinfo['logged'] == 1)) {
    if (substr(app('data')->frm['type'], 0, 8) == 'account_') {
        $ps = substr(app('data')->frm['type'], 8);
        if (app('data')->exchange_systems[$ps]['status'] == 1) {
            include app_path('Hm').'/inc/deposit.account.confirm.inc';
        } else {
            include app_path('Hm').'/inc/deposit.inc';
        }
    } else {
        if (substr(app('data')->frm['type'], 0, 8) == 'process_') {
            $ps = substr(app('data')->frm['type'], 8);
            if (app('data')->exchange_systems[$ps]['status'] == 1) {
                switch ($ps) {
                    case 0:
                        include app_path('Hm').'/inc/deposit.egold.confirm.inc';
                        break;
                    case 1:
                        include app_path('Hm').'/inc/deposit.evocash.confirm.inc';
                        break;
                    case 2:
                        include app_path('Hm').'/inc/deposit.intgold.confirm.inc';
                        break;
                    case 3:
                        include app_path('Hm').'/inc/deposit.perfectmoney.confirm.inc';
                        break;
                    case 4:
                        include app_path('Hm').'/inc/deposit.stormpay.confirm.inc';
                        break;
                    case 5:
                        include app_path('Hm').'/inc/deposit.ebullion.confirm.inc';
                        break;
                    case 6:
                        include app_path('Hm').'/inc/deposit.paypal.confirm.inc';
                        break;
                    case 7:
                        include app_path('Hm').'/inc/deposit.goldmoney.confirm.inc';
                        break;
                    case 8:
                        include app_path('Hm').'/inc/deposit.eeecurrency.confirm.inc';
                        break;
                    case 9:
                        include app_path('Hm').'/inc/deposit.pecunix.confirm.inc';
                        break;
                    case 1:
                        include app_path('Hm').'/inc/deposit.payeer.confirm.inc';
                        break;
                    case 1:
                        include app_path('Hm').'/inc/deposit.bitcoin.confirm.inc';
                        break;
                    default:
                        include app_path('Hm').'/inc/deposit.other.confirm.inc';
                }
            } else {
                include app_path('Hm').'/inc/deposit.inc';
            }
        } else {
            include app_path('Hm').'/inc/deposit.inc';
        }
    }
} else {
    if (((app('data')->frm['a'] == 'add_funds' and app('data')->settings['use_add_funds'] == 1) and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/add_funds.inc';
    } elseif ((app('data')->frm['a'] == 'withdraw' and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/withdrawal.inc';
    } elseif ((app('data')->frm['a'] == 'withdraw_history' and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/withdrawal_history.inc';
    } elseif ((app('data')->frm['a'] == 'deposit_history' and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/deposit_history.inc';
    } elseif ((app('data')->frm['a'] == 'earnings' and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/earning_history.inc';
    } elseif ((app('data')->frm['a'] == 'deposit_list' and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/deposit_list.inc';
    } elseif ((app('data')->frm['a'] == 'edit_account' and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/edit_account.inc';
    } elseif ((app('data')->frm['a'] == 'withdraw_principal' and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/withdraw_principal.inc';
    } elseif ((app('data')->frm['a'] == 'change_compound' and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/change_compound.inc';
    } elseif ((app('data')->frm['a'] == 'internal_transfer' and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/internal_transfer.inc';
    } elseif (app('data')->frm['a'] == 'support') {
        include app_path('Hm').'/inc/support.inc';
    } elseif (app('data')->frm['a'] == 'faq') {
        include app_path('Hm').'/inc/faq.inc';
    } elseif (app('data')->frm['a'] == 'company') {
        include app_path('Hm').'/inc/company.inc';
    } elseif (app('data')->frm['a'] == 'rules') {
        include app_path('Hm').'/inc/rules.inc';
    } elseif (app('data')->frm['a'] == 'show_validation_image') {
        include app_path('Hm').'/inc/show_validation_image.inc';
    } elseif (((app('data')->frm['a'] == 'members_stats' and app('data')->settings['show_stats_box']) and app('data')->settings['show_members_stats'])) {
        include app_path('Hm').'/inc/members_stats.inc';
    } elseif (((app('data')->frm['a'] == 'paidout' and app('data')->settings['show_stats_box']) and app('data')->settings['show_paidout_stats'])) {
        include app_path('Hm').'/inc/paidout.inc';
    } elseif (((app('data')->frm['a'] == 'top10' and app('data')->settings['show_stats_box']) and app('data')->settings['show_top10_stats'])) {
        include app_path('Hm').'/inc/top10.inc';
    } elseif (((app('data')->frm['a'] == 'last10' and app('data')->settings['show_stats_box']) and app('data')->settings['show_last10_stats'])) {
        include app_path('Hm').'/inc/last10.inc';
    } elseif (((app('data')->frm['a'] == 'refs10' and app('data')->settings['show_stats_box']) and app('data')->settings['show_refs10_stats'])) {
        include app_path('Hm').'/inc/refs10.inc';
    } elseif ($_GET['a'] == 'return_egold') {
        include app_path('Hm').'/inc/deposit.egold.status.inc';
    } elseif ($_GET['a'] == 'return_perfectmoney') {
        include app_path('Hm').'/inc/deposit.perfectmoney.status.inc';
    } elseif ($_GET['a'] == 'return_payeer') {
        include app_path('Hm').'/inc/deposit.payeer.status.inc';
    } elseif (((app('data')->frm['a'] == 'referallinks' and app('data')->settings['use_referal_program'] == 1) and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/referal.links.inc';
    } elseif (((app('data')->frm['a'] == 'referals' and app('data')->settings['use_referal_program'] == 1) and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/referals.inc';
    } elseif (app('data')->frm['a'] == 'news') {
        include app_path('Hm').'/inc/news.inc';
    } elseif (app('data')->frm['a'] == 'calendar') {
        include app_path('Hm').'/inc/calendar.inc';
    } elseif ((app('data')->frm['a'] == 'exchange' and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/exchange.inc';
    } elseif ((app('data')->frm['a'] == 'banner' and $userinfo['logged'] == 1)) {
        include app_path('Hm').'/inc/banner.inc';
    } elseif (app('data')->frm['a'] == 'activate') {
        include app_path('Hm').'/inc/activate.inc';
    } elseif (app('data')->frm['a'] == 'show_package_info') {
        include app_path('Hm').'/inc/package_info.inc';
    } elseif (app('data')->frm['a'] == 'ref_plans') {
        include app_path('Hm').'/inc/ref_plans.inc';
    } else {
        if (app('data')->frm['a'] == 'cust') {
            $file = app('data')->frm['page'];
            $file = basename($file);
            if (file_exists(tmpl_path().'/custom/'.$file.'.tpl')) {
                view_execute('custom/'.$file.'.tpl');
            } else {
                include app_path('Hm').'/inc/home.inc';
            }
        } else {
            if (app('data')->frm['a'] == 'invest_page') {
                view_assign('frm', app('data')->frm);
                include app_path('Hm').'/inc/invest_page.inc';
            } else {
                view_assign('frm', app('data')->frm);
                include app_path('Hm').'/inc/home.inc';
            }
        }
    }
}
