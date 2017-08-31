<?php

/*
 * This file is part of the entimm/hm.
 *
 * (c) entimm <entimm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

$q = 'select * from types where status = \'on\'';
  $sth = db_query($q);
  $plans = [];
  $total = 0;
  while ($row = mysql_fetch_array($sth)) {
      $row['deposits'] = [];
      $q = 'select * from plans where parent = '.$row['id'].' order by id';
      if (! ($sth1 = db_query($q))) {
      }

      $row['plans'] = [];
      while ($row1 = mysql_fetch_array($sth1)) {
          $row1['deposit'] = '';
          if ($row1['max_deposit'] == 0) {
              $row1['deposit'] = '$'.number_format($row1['min_deposit']).' and more';
          } else {
              $row1['deposit'] = '$'.number_format($row1['min_deposit']).' - $'.number_format($row1['max_deposit']);
          }

          array_push($row['plans'], $row1);
      }

      $periods = ['d' => 'Daily', 'w' => 'Weekly', 'b-w' => 'Bi Weekly', 'm' => 'Monthly', 'y' => 'Yearly'];
      $row['period'] = $periods[$row['period']];
      $q = 'select
                *,
                date_format(deposit_date + interval '.app('data')->settings['time_dif'].' hour, \'%b-%e-%Y %r\') as date,
                (to_days(now()) - to_days(deposit_date)) as duration,
                (to_days(now()) - to_days(deposit_date) - '.$row['withdraw_principal_duration'].') as pending_duration
          from
                deposits
          where
                user_id = '.$userinfo['id'].' and
                status=\'on\' and
                type_id = '.$row['id'].'
          order by
                deposit_date
         ';
      $sth1 = db_query($q);
      $d = [];
      while ($row1 = mysql_fetch_array($sth1)) {
          array_push($d, $row1[id]);
          $row1['can_withdraw'] = 1;
          if ($row['withdraw_principal'] == 0) {
              $row1['can_withdraw'] = 0;
          } else {
              if ($row1['duration'] < $row['withdraw_principal_duration']) {
                  $row1['can_withdraw'] = 0;
              }

              if (($row['withdraw_principal_duration_max'] != 0 and $row['withdraw_principal_duration_max'] <= $row1['duration'])) {
                  $row1['can_withdraw'] = 0;
              }
          }

          $row1['deposit'] = number_format(floor($row1['actual_amount'] * 100) / 100, 2);
          $row1['compound'] = sprintf('%.02f', $row1['compound']);
          $row1['pending_duration'] = 0 - $row1['pending_duration'];
          array_push($row['deposits'], $row1);
          $total += $row1['actual_amount'];
      }

      $q = 'select
            sum(history.actual_amount) as sm
          from
            history, deposits
          where
            history.deposit_id = deposits.id and
            history.user_id = '.$userinfo['id'].' and
            deposits.user_id = '.$userinfo['id'].' and
            history.type=\'deposit\' and
            deposits.type_id = '.$row['id'];
      $sth1 = db_query($q);
      $row1 = mysql_fetch_array($sth1);
      $row['total_deposit'] = number_format(abs($row1['sm']), 2);
      $q = 'select
            sum(history.actual_amount) as sm
          from
            history, deposits
          where
            history.deposit_id = deposits.id and
            history.user_id = '.$userinfo['id'].' and
            deposits.user_id = '.$userinfo['id'].' and
            history.type=\'earning\' and
            to_days(history.date + interval '.app('data')->settings['time_dif'].' hour) = to_days(now()) and
            deposits.type_id = '.$row['id'];
      $sth1 = db_query($q);
      $row1 = mysql_fetch_array($sth1);
      $row['today_profit'] = number_format(abs($row1['sm']), 2);
      $q = 'select
            sum(history.actual_amount) as sm
          from
            history, deposits
          where
            history.deposit_id = deposits.id and
            history.user_id = '.$userinfo['id'].' and
            deposits.user_id = '.$userinfo['id'].' and
            history.type=\'earning\' and
            deposits.type_id = '.$row['id'];
      $sth1 = db_query($q);
      $row1 = mysql_fetch_array($sth1);
      $row['total_profit'] = number_format(abs($row1['sm']), 2);
      if ((! $row['deposits'] and $row['closed'] != 0)) {
          continue;
      }

      array_push($plans, $row);
  }

  view_assign('plans', $plans);
  view_assign('total', number_format($total, 2));
  view_execute('deposit_list.blade.php');
