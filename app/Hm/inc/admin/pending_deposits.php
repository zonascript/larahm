<?php

/*
 * This file is part of the entimm/hm.
 *
 * (c) entimm <entimm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

echo '<form method=post name=nform>
<input type=hidden name=a value=pending_deposits>
<table cellspacing=0 cellpadding=1 border=0 width=100%>
<tr>
<td><b>Pending Deposits:</b></td>
<td align=right>';
  echo '<s';
  echo 'elect name=type class=inpts onchange="document.nform.submit()">
<option value=\'new\'>New</option>
<option value=\'problem\' ';
  echo app('data')->frm['type'] == 'problem' ? 'selected' : '';
  echo '>Problem</option>
<option value=\'processed\' ';
  echo app('data')->frm['type'] == 'processed' ? 'selected' : '';
  echo '>Processed</option>
</select>
<input type=submit value=\'GO\' class=sbmt>
</td>
</tr>
</table>
<input type="hidden" name="_token" value="'.csrf_token().'"></form>
<br>
<table cellspacing=1 cellpadding=2 border=0 width=100%>
<tr>
 <th bgcolor=FFEA00>UserName</th>
 <th bgcolor=FFEA00>Date</th>
 <th bgcolor=FFEA00>Amount</th>
 <th bgcolor=FFEA00>Fields</th>
 <th bgcolor=FFEA00>P.S.</th>
 <th bgcolor=FFEA00>-</th>
</tr>';
  $processings = [];
  $q = 'select * from processings';
  $sth = db_query($q);
  while ($row = mysql_fetch_array($sth)) {
      $processings[$row['id']] = unserialize($row['infofields']);
  }

  $status = (app('data')->frm['type'] == 'problem' ? 'problem' : (app('data')->frm['type'] == 'processed' ? 'processed' : 'new'));
  $q = 'select
          pending_deposits.*,
          date_format(pending_deposits.date + interval '.app('data')->settings['time_dif'].(''.' hour, \'%b-%e-%Y %r\') as d,
          users.username
        from
          pending_deposits,
          users
        where
          pending_deposits.status = \''.$status.'\' and
          users.id = pending_deposits.user_id
        order by date desc
       ');
  $sth = db_query($q);
  $col = 0;
  while ($row = mysql_fetch_array($sth)) {
      $infofields = unserialize($row['fields']);
      $fields = '';
      if (! app('data')->exchange_systems[$row['ec']]) {
          $row['ec'] = 'deleted';
          foreach ($infofields as $id => $name) {
              $fields .= $name.'<br>';
          }
      } else {
          foreach ($processings[$row['ec']] as $id => $name) {
              $fields .= $name.': '.stripslashes($infofields[$id]).'<br>';
          }
      }

      ++$col;
      echo '     <tr onMouseOver="bgColor=\'#FFECB0\';" onMouseOut="bgColor=\'\';">
	<td><b>';
      echo $row['username'];
      echo '</b></td>
	<td align=center>';
      echo $row['d'];
      echo '</td>
	<td align=center>$';
      echo number_format($row['amount'], 2);
      echo '</td>
	<td>';
      echo $fields;
      echo '</td>
	<td align=center><img src="images/';
      echo $row['ec'];
      echo '.gif" height=17 hspace=1 vspace=1></td>
	<td align=center><a href=?a=pending_deposit_details&id=';
      echo $row['id'];
      echo '>[details]</a></td>
     </tr>
    ';
  }

  if ($col == 0) {
      echo '       <tr><td colspan=6 align=center>No records found</td></tr>
    ';
  }

  echo '

</table>';
