<?php

$tournamentid = (int)$_GET["tournamentid"];

include_once("modules/tournament2/class_tournament.php");
$tfunc = new tfunc;


function WriteGame() {
	global $spieler1, $gameid1, $score1, $spieler1_id, $i, $tournamentid, $game, $dsp, $auth, $lang;

	if ($spieler1 == "") {
		$spieler1 = $game['name'];
		$gameid1 = $game['gameid'];
		$score1 = $game['score'];
		$spieler1_id = $game['leaderid'];

	} else {
		$i++;
		$spieler2 = $game['name'];
		$gameid2 = $game['gameid'];
		$score2 = $game['score'];
		$spieler2_id = $game['leaderid'];

		// Set Colour
		if (($spieler1_id == 0) && ($spieler2_id != 0)) $spieler2 = "<font color=\"#006600\">$spieler2</font>";
		elseif (($spieler2_id == 0) && ($spieler1_id != 0)) $spieler1 = "<font color=\"#006600\">$spieler1</font>";
		elseif (($score1 > 0) || ($score2 > 0)) {
			if ($score1 > $score2) {
				$spieler1 = "<font color=\"#006600\">$spieler1</font>";
				$spieler2 = "<font color=\"#660000\">$spieler2</font>";
			}
			elseif ($score1 < $score2) {
				$spieler1 = "<font color=\"#660000\">$spieler1</font>";
				$spieler2 = "<font color=\"#006600\">$spieler2</font>";
			}
		}

		// Mark own team
		if ($spieler1_id == $auth["userid"]) $spieler1 = "<b>$spieler1</b>";
		if ($spieler2_id == $auth["userid"]) $spieler2 = "<b>$spieler2</b>";

		$score_output = "";
		if (($spieler1_id != 0) && ($spieler2_id != 0)) {
			if (($score1 == 0) && ($score2 == 0)) $score_output = "- : - ";
			else $score_output = "$score1 : $score2 ";
			$score_output .= $dsp->FetchButton("index.php?mod=tournament2&action=submit_result&step=1&tournamentid=$tournamentid&gameid1=$gameid1&gameid2=$gameid2", "details");
		}

		$dsp->AddDoubleRow(t('Paarung')." $i", "$spieler1 vs $spieler2&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$score_output");
		$spieler1 = "";
	}
}


function WriteRoundHeadline($headline, $akt_round){
	global $tournament, $dsp, $func, $lang, $map, $tfunc;

	$round_start = $func->unixstamp2date($tfunc->GetGameStart($tournament, $akt_round), "time");
	$round_end = $func->unixstamp2date($tfunc->GetGameEnd($tournament, $akt_round), "time");

	$dsp->AddSingleRow("<b>$headline ".t('Runde')." ". abs($akt_round) ."</b>"
		.HTML_NEWLINE. t('Zeit') .": ". $round_start ." - ". $round_end
		.HTML_NEWLINE. t('Map') .": ". $map[(abs(floor($akt_round)) % count($map))]
		);
}


function WritePairs ($bracket, $max_pos) {
	global $config, $dsp, $db, $tournamentid, $tfunc, $akt_round, $lang, $func, $map, $tournament, $i, $game;

	WriteRoundHeadline("$bracket-Bracket - ", $akt_round);

	$spieler1 = "";
	$i = 0;

	for ($akt_pos = 0; $akt_pos <= $max_pos-1; $akt_pos++) {
		$game = $db->query_first("SELECT teams.name, teams.teamid, games.leaderid, games.gameid, games.score
				FROM {$config["tables"]["t2_games"]} AS games
				LEFT JOIN {$config["tables"]["t2_teams"]} AS teams ON (games.tournamentid = teams.tournamentid) AND (games.leaderid = teams.leaderid)
				WHERE (games.tournamentid = '$tournamentid')
				AND (games.group_nr = 0) AND (games.round = $akt_round) AND (games.position = $akt_pos)
				GROUP BY games.gameid
				");

		// Set Playernames
		if ($game == 0) $game['name'] = "<i>".t('Noch Unbekannt')."</i>";
		elseif ($game['leaderid'] == 0) $game['name'] = "<i><font color=\"#000088\">".t('Freilos')."</font></i>";
		else $game['name'] .= $tfunc->button_team_details($game['teamid'], $tournamentid);

		WriteGame();
	}
}


if (!$tournamentid) $func->error(t('Sie haben kein Turnier ausgewählt!'), '');
else {
  switch($_GET["step"]) {
  case 1:
    include_once('modules/tournament2/search.inc.php');
  break;
  
  
  default:
  	// Check if roundtime has exceeded and set awaiting scores randomly
  	$tfunc->CheckTimeExceed($tournamentid);
  
  	$tournament = $db->query_first("SELECT *, UNIX_TIMESTAMP(starttime) AS starttime FROM {$config["tables"]["tournament_tournaments"]} WHERE tournamentid = '$tournamentid'");
  
  	// Get Maparray
  	$map = explode("\r\n", $func->db2text($tournament["mapcycle"]));
  	if ($map[0] == "") $map[0] = t('unbekannt');
  
  	// Check Errors
  	if ($tournament["mode"] == "open") {
  		$func->error(t('Dieses Turnier wurde noch nicht generiert. Die Paarungen sind noch nicht bekannt.'), "index.php?mod=tournament2&action=games&step=1]");
  		break;
  	}
  
  	// Set Modename
  	if ($tournament['mode'] == "single") $modus = t('Single-Elimination');
  	if ($tournament['mode'] == "double") $modus = t('Double-Elimination');
  	if ($tournament['mode'] == "liga") $modus = t('Liga');
  	if ($tournament['mode'] == "groups") $modus = t('Gruppenspiele + KO');
  	if ($tournament['mode'] == "all") $modus = t('Alle in einem');
  
  	// Start Output
  	$dsp->NewContent(t('Turnier %1 (%2) - Paarungen', $tournament['name'], $modus), t('Hier sehen Sie eine Liste aller Paarungen dieses Turniers'));
  
  
  
  	switch ($tournament['mode']){
  	case 'all':
  		// Update score, if submitted
  		if ($_GET['step'] == 10) {
  			foreach ($_POST['team_score'] as $gameid => $team_score) if ($gameid) {
  				if (!is_numeric($team_score)) $team_score_error[$gameid] = t('Bitte geben Sie eine Zahl ein');
  				else $db->query("UPDATE {$config["tables"]["t2_games"]}
  					SET score = {$team_score}
  					WHERE gameid = $gameid");
  			}
  		}
  
  		// Finish tournament
  		if ($_GET['step'] == 11) {
			$db->query("UPDATE {$config["tables"]["tournament_tournaments"]} SET status='closed' WHERE tournamentid = $tournamentid");
			$func->confirmation(t('Turnier wurde beendet'), '');
		}
		// Unfinish tournament
		if ($_GET['step'] == 12) {
			$db->query("UPDATE {$config["tables"]["tournament_tournaments"]} SET status='process' WHERE tournamentid = $tournamentid");
			$func->confirmation(t('Turnier wurde wiedereröffnet'), '');
		}
		
  		// Show players and scores
  		$games = $db->query("SELECT teams.name, teams.teamid, games.leaderid, games.score, games.gameid
  				FROM {$config["tables"]["t2_games"]} AS games
  				LEFT JOIN {$config["tables"]["t2_teams"]} AS teams ON (games.tournamentid = teams.tournamentid) AND (games.leaderid = teams.leaderid)
  				WHERE games.tournamentid = '$tournamentid'
  				ORDER BY games.position
  				");
  		$dsp->SetForm("index.php?mod=tournament2&action=games&step=10&tournamentid=$tournamentid");
  		while ($game = $db->fetch_array($games)) {
  			$dsp->AddTextFieldRow('team_score['. $game['gameid'] .']', $game['name'] .' '. $tfunc->button_team_details($game['teamid'], $tournamentid), $game['score'], $team_score_error[$game['gameid']]);
  		}
  		$db->free_result($games);
  		$dsp->AddFormSubmitRow("save");
  		if ($tournament['status'] == 'process') $dsp->AddDoubleRow('', $dsp->FetchButton("index.php?mod=tournament2&action=games&step=11&tournamentid=$tournamentid", 'finish'));
		elseif ($tournament['status'] == 'closed') $dsp->AddDoubleRow('', $dsp->FetchSpanButton(t('Beenden rückgängig'), "index.php?mod=tournament2&action=games&step=12&tournamentid=$tournamentid"));
  	break;
  	case "liga":
  	case "groups":
  		$games = $db->query("SELECT teams.name, teams.teamid, games.leaderid, games.gameid, games.score, games.group_nr, games.round, games.position, games.leaderid
  				FROM {$config["tables"]["t2_games"]} AS games
  				LEFT JOIN {$config["tables"]["t2_teams"]} AS teams ON (games.tournamentid = teams.tournamentid) AND (games.leaderid = teams.leaderid)
  				WHERE (games.tournamentid = '$tournamentid') AND (games.group_nr > 0)
  				GROUP BY games.gameid
  				ORDER BY games.group_nr, games.round, games.position
  				");
  		$last_round = -1;
  		while ($game = $db->fetch_array($games)){
  
  			// Write Round Headline
  			if ($last_round != $game['round']) {
  				($tournament['mode'] == "groups")? $group_out = t('Gruppe')." {$game['group_nr']},"
  					: $group_out = '';
  
  				WriteRoundHeadline($group_out, $game['round']);
  
  				$spieler1 = "";
  				$i = 0;
  			}
  			$last_round = $game['round'];
  
  			// Set Playernames
  			if ($game['leaderid'] == 0) $game['name'] = "<i>".t('Spielfrei')."</i>";
  			else $game['name'] .= $tfunc->button_team_details($game['teamid'], $tournamentid);
  
  			WriteGame();
  		}
  		$db->free_result($games);
  	break;
  	} // END: Switch $tournament['mode']
  
  
  
  	switch ($tournament['mode']){
  	case "single":
  	case "double":
  	case "groups":
  		// Get $team_anz
  		if ($tournament['mode'] == "groups") {
  			$games = $db->query("SELECT gameid
  					FROM {$config["tables"]["t2_games"]}
  					WHERE (tournamentid = $tournamentid) AND (group_nr > 0)
  					GROUP BY group_nr
  					");
  			$team_anz = 2 * $db->num_rows($games);
  			$db->free_result($games);
  		} else {
  			$games = $db->query_first("SELECT COUNT(*) AS anz
  					FROM {$config["tables"]["t2_games"]}
  					WHERE (tournamentid = $tournamentid) AND (round = 0) AND (group_nr = 0)
  					GROUP BY round
  					");
  			$team_anz = $games["anz"];
  		}
  
  		$akt_round = 0;
  		WritePairs("Winner", $team_anz);
  
  		$akt_round = 1;
  		if ($tournament['mode'] == "double") $limit_round = 2;
  		else $limit_round = 4;
  		for ($z = $team_anz/2; $z >= $limit_round; $z/=2) {
  			WritePairs("Winner", $z);
  			if ($tournament['mode'] == "double") {
  				$akt_round*=-1;
  				$akt_round+=0.5;
  				WritePairs("Loser", $z);
  				$akt_round-=0.5;
  				WritePairs("Loser", $z);
  				$akt_round*=-1;
  			}
  			$akt_round++;
  		}
  
  		WritePairs("Winner", 2);
  
  		// Write Winner
  		$akt_round++;
  		$dsp->AddSingleRow("<b>". t('Turnier-Sieger') ."</b>");
  		$game = $db->query_first("SELECT teams.name, teams.teamid
  				FROM {$config["tables"]["t2_games"]} AS games
  				LEFT JOIN {$config["tables"]["t2_teams"]} AS teams ON (games.tournamentid = teams.tournamentid) AND (games.leaderid = teams.leaderid)
  				WHERE (games.tournamentid = '$tournamentid') AND (games.round = $akt_round) AND (games.position = 0) AND (games.group_nr = 0)
  				GROUP BY games.gameid
  				");
  		if ($game == 0) $game['name'] = "<i>".t('Noch Unbekannt')."</i>";
  		else $game['name'] .= $tfunc->button_team_details($game['teamid'], $tournamentid);
  		$dsp->AddDoubleRow(t('Sieger'), $game['name']);
  	break;
  	} // END: Switch $tournament['mode']
  
  
  	// Finalize Output
  	if ($func->internal_referer) $dsp->AddBackButton($func->internal_referer, "tournament2/games");
  	else $dsp->AddBackButton("index.php?mod=tournament2&action=games&step=1", "tournament2/games");
  	$dsp->AddContent();
  break;
  } // END: Switch Step
}
?>
