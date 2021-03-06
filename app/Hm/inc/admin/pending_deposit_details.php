<?php

/*
 * This file is part of the entimm/hm.
 *
 * (c) entimm <entimm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

echo '<b>Deposit Details:</b><br><br>
';
  $id = sprintf('%d', app('data')->frm['id']);
  $q = 'select 
          pending_deposits.*,
          date_format(pending_deposits.date + interval '.app('data')->settings['time_dif'].(''.' hour, \'%b-%e-%Y %r\') as d,
          users.username
        from
          pending_deposits,
          users
        where
          pending_deposits.id = '.$id.' and
          users.id = pending_deposits.user_id
       ');
  $sth = db_query($q);
  $row = mysql_fetch_array($sth);
  $q = 'select * from processings where id = '.$row['ec'];
  $sth = db_query($q);
  $processing = mysql_fetch_array($sth);
  $pfields = unserialize($processing['infofields']);
  echo '
<form method=post name=nform>
<input type=hidden name=a value=pending_deposit_details>';
  if ((app('data')->frm['action'] == 'movetodeposit' or app('data')->frm['action'] == 'movetoaccount')) {
      echo '<input type=hidden name=action value="';
      echo app('data')->frm['action'];
      echo '">
<input type=hidden name=confirm value="yes">';
  }

  echo '
<table cellspacing=1 cellpadding=2 border=0 width=500>
<tr>
 <td colspan=2><b>Deposit Information:</td>
</tr><tr>
 <td>Amount:</td>
 <td>';
  if ((app('data')->frm['action'] != 'movetodeposit' and app('data')->frm['action'] != 'movetoaccount')) {
      echo(''.'$').number_format($row['amount'], 2);
  } else {
      echo '<input type=text name=amount value=\''.sprintf('%0.2f', $row['amount']).'\' class=inpts style=\'text-align: right;\'>';
  }

  echo '</td>
</tr>
<tr>
 <td>Currency:</td>
 <td>';
  echo app('data')->exchange_systems[$row['ec']] ? app('data')->exchange_systems[$row['ec']]['name'] : 'Delated';
  echo '</td>
</tr>';
  if (app('data')->frm['action'] != 'movetoaccount') {
      if (0 < $row['compound']) {
          echo '<tr>
 <td>Componding percent:</td>
 <td>';
          echo number_format($row['compound'], 2);
          echo ' %</td>
</tr>';
      }
  }

  echo '<tr>
 <td>Date:</td>
 <td>';
  echo $row['d'];
  echo '</tr><tr>
 <td>User:</td>
 <td>';
  echo $row['username'];
  echo '</td>
</tr>';
  if ((app('data')->frm['action'] != 'movetodeposit' and app('data')->frm['action'] != 'movetoaccount')) {
      echo '<tr>
 <td colspan=2><br><b>Transaction Information:</b></td>
</tr>';
      $infofields = unserialize($row['fields']);
      if (! app('data')->exchange_systems[$row['ec']]) {
          $row['ec'] = 'deleted';
          foreach ($infofields as $id => $name) {
              echo '       <tr>
        <td>&nbsp;</td>
        <td>';
              echo $name;
              echo '</td>
       </tr>';
          }
      } else {
          foreach ($pfields as $id => $name) {
              echo '       <tr>
        <td>';
              echo $name;
              echo ':</td>
        <td>';
              echo stripslashes($infofields[$id]);
              echo '</td>
       </tr>';
          }
      }
  }

  echo '</table>
<br>
';
  if ($row['status'] != 'processed') {
      if (app('data')->frm['action'] == 'movetoaccount') {
          echo '<input type=submit value="Add funds to account" class=sbmt>';
      } else {
          echo '  ';
          if (app('data')->frm['action'] != 'movetodeposit') {
              echo '<input type=button value="Move to deposit" class=sbmt onClick="document.location=\'?a=pending_deposit_details&action=movetodeposit&id=';
              echo $row['id'];
              echo '\';"> &nbsp;
<input type=button value="Move to account" class=sbmt onClick="document.location=\'?a=pending_deposit_details&action=movetoaccount&id=';
              echo $row['id'];
              echo '\';"> &nbsp;
   ';
              if ($row['status'] == 'problem') {
                  echo '<input type=button value="Move to new" class=sbmt onClick="document.location=\'?a=pending_deposit_details&action=movetonew&id=';
                  echo $row['id'];
                  echo '\';"> &nbsp;
   ';
              } else {
                  echo '<input type=button value="Move to problem" class=sbmt onClick="document.location=\'?a=pending_deposit_details&action=movetoproblem&id=';
                  echo $row['id'];
                  echo '\';"> &nbsp;
   ';
              }

              echo '<input type=button value="Delete" class=sbmt onClick="document.location=\'?a=pending_deposit_details&action=delete&id=';
              echo $row['id'];
              echo '&type=';
              echo $row['status'];
              echo '\';">
  ';
          } else {
              echo '<input type=submit value="Create deposit" class=sbmt>
  ';
          }
      }
  }

  echo '<input type="hidden" name="_token" value="'.csrf_token().'"></form>

<br>';
  echo start_info_table('100%');
  if (app('data')->frm['action'] == 'movetodeposit') {
      echo 'You can change the amount before moving this transfer to the deposit ';
  } else {
      echo 'This screen helps you to manage your Wire Transfers.<br>
Move to deposit - you can move this wire to \'processed\' and create a deposit for 
it if you have really received this Wire Transfer,.<br>
Move to problem - move this Wire Transfer to the \'problem\' Wires.<br>
Delete - delete this Wire Transfer if you haven\'t received it. ';
  }

  echo end_info_table();
