<?php

include_once("modules/tournament2/class_tournament.php");
$tfunc = new tfunc;

$tournamentid 	= $vars["tournamentid"];
$teamid 	= $vars["teamid"];
$step 	= $vars["step"];
$headermenuitem	= $vars["headermenuitem"];

if ($headermenuitem == "") $headermenuitem = 1;

$tournament = $db->query_first_rows("SELECT *, UNIX_TIMESTAMP(starttime) AS starttime FROM {$config["tables"]["tournament_tournaments"]} WHERE tournamentid = '$tournamentid'");

if($tournament["number"] == 0) $func->error(t('Das ausgewählte Turnier existiert nicht'), "index.php?mod=tournament2");
else {

	switch ($step){
    // Shuffle maps
    case 20:
      if ($auth['type'] <= 1) $func->error('ACCESS_DENIED');
      else {
        $maps = explode("\n", $tournament["mapcycle"]);
        shuffle($maps);
        $tournament["mapcycle"] = implode("\n", $maps);
        $db->query("UPDATE {$config["tables"]["tournament_tournaments"]} SET mapcycle = '{$tournament['mapcycle']}' WHERE tournamentid = '$tournamentid'");
      }
    break;
  }
  
	switch ($step){
		case 10:	// Activate Seeding
			$seeded = $db->query_first("SELECT COUNT(*) AS anz FROM {$config["tables"]["t2_teams"]} WHERE (tournamentid = '$tournamentid') AND (seeding_mark = '1') GROUP BY tournamentid");
			$team = $db->query_first("SELECT COUNT(*) AS anz FROM {$config["tables"]["t2_teams"]} WHERE (tournamentid = '$tournamentid') GROUP BY tournamentid");

			if (($seeded['anz']+1) > ($team['anz'] / 2)){
				$func->information(t('Es wurde bereits die Hälfte der fest angemeldeten Teams markiert! Demarkieren Sie zuerst ein Team, bevor Sie ein weiteres markieren'), "index.php?mod=tournament2&action=details&tournamentid=$tournamentid&headermenuitem=2");
			} else {
				$db->query("UPDATE {$config["tables"]["t2_teams"]} SET seeding_mark = '1' WHERE (teamid = $teamid)");
				$func->confirmation(t('Das Team wurde zum Setzen markiert.HTML_NEWLINEAlle markierten Teams werden beim Generieren so gesetzt, dass sie möglichst spät im Turnierbaum aufeinander treffen werden.'), "index.php?mod=tournament2&action=details&tournamentid=$tournamentid&headermenuitem=2");
			}
		break;

		case 11:	// Deaktivate Seeding
			$db->query("UPDATE {$config["tables"]["t2_teams"]} SET seeding_mark = '0' WHERE (teamid = $teamid)");
			$func->confirmation(t('Das Team wurde demarkiert.'), "index.php?mod=tournament2&action=details&tournamentid=$tournamentid&headermenuitem=2");
		break;

		default:	// Show details
			$dsp->NewContent(t('Turnier %1', $tournament['name']), t('Hier finden Sie Informationen zu diesem Turnier und können sich anmelden'));

			$menunames[1] = t('Turnierinfos');
			$menunames[2] = t('Angemeldete Teams');
			$dsp->AddHeaderMenu($menunames, "index.php?mod=tournament2&action=details&tournamentid=$tournamentid", $headermenuitem);

			switch ($headermenuitem) {
				case 1:
					$dsp->AddDoubleRow(t('Turniername'), $tournament['name']);

					if (($tournament['icon']) && ($tournament['icon'] != "none")) $icon = "<img src=\"ext_inc/tournament_icons/{$tournament['icon']}\" alt=\"Icon\"> ";
					if ($tournament['version'] == "") $tournament['version'] = "<i>".t('unbekannt')."</i>";
					$dsp->AddDoubleRow(t('Spiel'), $icon . $tournament['game'] ." (".t('Version').": ". $tournament['version'] .")");

					$league = "";
					if ($tournament['wwcl_gameid'] != 0) $league .= ", <img src=\"ext_inc/tournament_icons/leagues/wwcl.png\" alt=\"WWCL\">";
					if ($tournament['ngl_gamename'] != "") $league .= ", <img src=\"ext_inc/tournament_icons/leagues/ngl.png\" alt=\"NGL\">";
					if ($tournament['mode'] == "single") $modus = t('Single-Elimination');
					if ($tournament['mode'] == "double") $modus = t('Double-Elimination');
					if ($tournament['mode'] == "liga") $modus = t('Liga');
					if ($tournament['mode'] == "groups") $modus = t('Gruppenspiele + KO');
					if ($tournament['mode'] == "all") $modus = t('Alle in einem');
					if ($tournament['blind_draw']) $blind_draw = " (Blind Draw)";
					else $blind_draw = "";
					$dsp->AddDoubleRow(t('Spiel-Modus'), $modus .", ". $tournament['teamplayer'] ." ".t('gegen')." ". $tournament['teamplayer'] . $blind_draw . $league);

          $sponsor_banners = '';
          $sponsor = $db->query("SELECT * FROM {$config['tables']['sponsor']} WHERE tournamentid = ". (int)$tournamentid);
      		while($sponsor_row = $db->fetch_array($sponsor)) {
            $sponsor_banner = '<img src="'. $sponsor_row['pic_path'] .'" border="1" class="img_border" title="'. $sponsor_row['name'] .'" alt="Sponsor Banner"/>';
            if ($cfg['sys_internet']) $sponsor_banner = '<a href="index.php?mod=sponsor&action=bannerclick&design=base&type=banner&sponsorid='. $sponsor_row["sponsorid"] .'" target="_blank">'. $sponsor_banner .'</a><br>';
            $sponsor_banners .= $sponsor_banner;
          }
          $db->free_result($sponsor);
					if ($sponsor_banners) $dsp->AddDoubleRow('Sponsored by', $sponsor_banners);

          $dsp->AddFieldsetStart(t('Anmeldeeinschränkungen'));
					if ($tournament['status'] == "invisible") $status = t('Unsichtbar');
					if ($tournament['status'] == "open") $status = t('Anmeldung offen');
					if ($tournament['status'] == "locked") $status = t('Anmeldung geschlossen');
					if ($tournament['status'] == "closed") $status = "<div class=\"tbl_error\">".t('Turnier beendet')."</div>";
					if ($tournament['status'] == "process") $status = "<div class=\"tbl_error\">".t('Partien werden gespielt')."</div>";
					$dsp->AddDoubleRow(t('Status'), $status);

					($tournament['groupid'] == 0) ?
						$dsp->AddDoubleRow(t('Turniergruppe'), t('Dieses Turnier wurde keiner Gruppe zugeordnet. Jeder darf teilnehmen.'))
						: $dsp->AddDoubleRow(t('Turniergruppe'), t('Dieses Turnier wurde der Gruppe %1 zugeordnet. Es düfen sich nur Spieler anmelden, welche nicht bereits zu einem anderen Turnier der Gruppe %1 angemeldet sind.', $tournament['groupid']));

          if ($auth['userid'] != '') {
  					if ($tournament['coins'] == 0) $dsp->AddDoubleRow(t('Coin-Kosten'), t('Für dieses Turnier werden keine Coins benötigt'));
  					else {
  						$team_coin = $db->query_first("SELECT SUM(t.coins) AS t_coins
  							FROM {$config["tables"]["tournament_tournaments"]} AS t
  							INNER JOIN {$config["tables"]["t2_teams"]} AS teams ON t.tournamentid = teams.tournamentid
  							WHERE (teams.leaderid = '{$auth["userid"]}')
  							AND t.party_id='$party->party_id' 
  							GROUP BY teams.leaderid
  							");
  						$member_coin = $db->query_first("SELECT SUM(t.coins) AS t_coins
  							FROM {$config["tables"]["tournament_tournaments"]} AS t
  							INNER JOIN {$config["tables"]["t2_teammembers"]} AS members ON t.tournamentid = members.tournamentid
  							WHERE (members.userid = '{$auth["userid"]}')
  							AND t.party_id='$party->party_id' 
  							GROUP BY members.userid
  							");
  						(($cfg['t_coins'] - $team_coin['t_coins'] - $member_coin['t_coins']) < $tournament['coins']) ?
  							$coin_out = t('Das Anmelden kostet %COST% Coins, Sie besitzen jedoch nur %IS% Coins!')
  							: $coin_out = t('Das Anmelden kostet %COST% Coins. Sie besitzen noch: %IS% Coins');
  						
  						$dsp->AddDoubleRow(t('Coin-Kosten'), "<div class=\"tbl_error\">". str_replace("%IS%", ($cfg['t_coins'] - $team_coin['t_coins'] - $member_coin['t_coins']), str_replace("%COST%", $tournament['coins'], $coin_out)) ."</div>");
  					}
          }

					($tournament['over18']) ?
						$dsp->AddDoubleRow(t('U18-Sperre'), t('Nur zugänglich für Spieler aus Über-18-Blöcken'))
						: $dsp->AddDoubleRow(t('U18-Sperre'), t('Keine Sperre'));
          $dsp->AddFieldsetEnd();


					($tournament["defwin_on_time_exceed"] == "1")? $defwin_warning = "<div class=\"tbl_error\">".t('ACHTUNG: Bei Zeitüberschreitung wird das Ergebnis automatisch gelost!')."</div> ".t('Wir bitten euch daher die Spiele direkt zu beginnen und das Ergebnis umgehend zu melden!')."" : $defwin_warning = "";
          $dsp->AddFieldsetStart(t('Zeiten') . $defwin_warning);
					$dsp->AddDoubleRow(t('Turnier beginnt um'), $func->unixstamp2date($tournament["starttime"], "datetime"));

					$dsp->AddDoubleRow(t('Dauer einer Runde'), t('Maximal %1 Spiel(e) pro Runde (je %2) + %3 Pause -> %4', $tournament["max_games"], $tournament["game_duration"] ."min", $tournament["break_duration"] ."min", $tournament["max_games"] * $tournament["game_duration"] + $tournament["break_duration"] ."min"));
;
          $dsp->AddFieldsetEnd();


          $dsp->AddFieldsetStart(t('Regeln und Sonstiges'));
					if ($tournament['rules_ext']) $dsp->AddDoubleRow(t('Regelwerk'), "<a href=\"./ext_inc/tournament_rules/{$tournament['rules_ext']}\" target=\"_blank\">".t('Regelwerk öffnen')."({$tournament['rules_ext']})</a>");

					$dsp->AddDoubleRow(t('Bemerkung'), $func->db2text2html($tournament["comment"]));

          $maps = explode("\n", $tournament["mapcycle"]);
          $map_str = '';
          foreach ($maps as $key => $val) $map_str .= t('Runde')." $key: $val \n";
          $mapcycle = t('Mapcycle'). HTML_NEWLINE . HTML_NEWLINE;
          if ($auth['type'] > 1) $mapcycle .= '<a href="index.php?mod=tournament2&action=details&tournamentid='. $_GET['tournamentid'] .'&step=20">'. t('Maps neu mischen') .'</a>';
					$dsp->AddDoubleRow($mapcycle, $func->db2text2html($map_str));
          $dsp->AddFieldsetEnd();
				break;

				case 2:
					$waiting_teams = "";
					$completed_teams = "";
					$teams = $db->query("SELECT name, teamid, seeding_mark, disqualified FROM {$config["tables"]["t2_teams"]} WHERE (tournamentid = $tournamentid)");
					while($team = $db->fetch_array($teams)) {
						$members = $db->query_first("SELECT COUNT(*) AS members
							FROM {$config["tables"]["t2_teammembers"]}
							WHERE (teamid = {$team['teamid']})
							GROUP BY teamid
							");
						$team_out = $team["name"] . $tfunc->button_team_details($team['teamid'], $tournamentid);
						if (($tournament['mode'] == "single") or ($tournament['mode'] == "double")){
							if ($team["seeding_mark"]) $team_out .= " ". t('Dieses Team wird beim Generieren gesetzt');
							if (($auth["type"] > 1) && ($tournament['status'] == "open")) {
								if ($team["seeding_mark"]) $team_out .= " <a href=\"index.php?mod=tournament2&action=details&step=11&tournamentid=$tournamentid&teamid={$team['teamid']}\">".t('demarkieren')."</a>";
								else $team_out .= " <a href=\"index.php?mod=tournament2&action=details&step=10&tournamentid=$tournamentid&teamid={$team['teamid']}\">".t('Team setzen')."</a>";
							}
						}
/*  // Disquallifiy droped, due to errors
						if ($auth["type"] > 1 and $tournament['status'] == "process") {
							if ($team['disqualified']) $team_out .= " <font color=\"#ff0000\">".t('Disqualifiziert')."</font> ". $dsp->FetchButton("index.php?mod=tournament2&action=disqualify&teamid={$team['teamid']}&step=10", "undisqualify");
							else $team_out .= " ". $dsp->FetchButton("index.php?mod=tournament2&action=disqualify&teamid={$team['teamid']}", "disqualify");
						}
*/
						$team_out .= HTML_NEWLINE;
						if(($members["members"] + 1) < $tournament['teamplayer'])  {
							$teamcount[0]++;
							$waiting_teams .= $team_out;
						} else {
							$teamcount[1]++;
							$completed_teams .= $team_out;
						}
					}
					$db->free_result($teams);

					$dsp->AddSingleRow(t('Es sind %1 von maximal %2 Teams zu diesem Turnier angemeldet.',($teamcount[0] + $teamcount[1]), $tournament['maxteams']));

					if ($completed_teams == "") $completed_teams = "<i>".t('Keine')."</i>";
					$dsp->AddDoubleRow(t('Teamnamen'), $completed_teams);

					if (($tournament['teamplayer'] > 1) && ($waiting_teams != "")){
						$dsp->AddSingleRow(t('Folgende %1 Teams sind noch unvollständig', ($teamcount[0] + 0)));
						$dsp->AddDoubleRow(t('Teamnamen'), $waiting_teams);
					}
				break;
			} // END Switch($headermenuitem)

			$buttons="";
			switch($tournament["status"]) {
				case "open":
					$buttons .= $dsp->FetchButton("index.php?mod=tournament2&action=join&tournamentid=$tournamentid&step=2", "join"). " ";
					if ($auth["type"] > 1) $buttons .= $dsp->FetchButton("index.php?mod=tournament2&action=generate_pairs&step=2&tournamentid=$tournamentid", "generate"). " ";
				break;
				case "process":
					$buttons .= $dsp->FetchButton("index.php?mod=tournament2&action=games&step=2&tournamentid=$tournamentid", "games"). " ";
					$buttons .= $dsp->FetchButton("index.php?mod=tournament2&action=tree&step=2&tournamentid=$tournamentid", "tree"). " ";
					if ($auth["type"] > 1) $buttons .= $dsp->FetchButton("index.php?mod=tournament2&action=undo_generate&tournamentid=$tournamentid", "undo_generate"). " ";
				break;
				case "closed":
					$buttons .= $dsp->FetchButton("index.php?mod=tournament2&action=games&step=2&tournamentid=$tournamentid", "games"). " ";
					$buttons .= $dsp->FetchButton("index.php?mod=tournament2&action=tree&step=2&tournamentid=$tournamentid", "tree"). " ";
					if ($auth["type"] > 1) $buttons .= $dsp->FetchButton("index.php?mod=tournament2&action=undo_close&tournamentid=$tournamentid", "undo_close"). " ";
				break;
			} // END: switch status
			$dsp->AddDoubleRow("", $buttons);
			$dsp->AddBackButton("index.php?mod=tournament2", "tournament2/details"); 

			$dsp->AddContent();
		break;
	} // END: Switch Step

} // else
?>
