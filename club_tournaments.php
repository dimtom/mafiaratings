<?php

require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/tournament.php';
require_once 'include/checkbox_filter.php';

define("CUT_NAME",45);
define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

define('FLAG_FILTER_VIDEOS', 0x0001);
define('FLAG_FILTER_NO_VIDEOS', 0x0002);
define('FLAG_FILTER_EMPTY', 0x0004);
define('FLAG_FILTER_NOT_EMPTY', 0x0008);
define('FLAG_FILTER_CANCELED', 0x0010);
define('FLAG_FILTER_NOT_CANCELED', 0x0020);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NOT_CANCELED | FLAG_FILTER_NOT_EMPTY);

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$future = false;
		if (isset($_REQUEST['future']))
		{
			$future = ((int)$_REQUEST['future'] > 0);
		}
		
		$condition = new SQL(
			' FROM tournaments t ' .
				' JOIN addresses a ON t.address_id = a.id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' LEFT OUTER JOIN leagues l ON l.id = t.league_id' .
				' WHERE t.club_id = ?',
			$this->id);
		if ($future)
		{
			$condition->add(' AND t.start_time + t.duration >= UNIX_TIMESTAMP()');
		}
		else
		{
			$condition->add(' AND t.start_time < UNIX_TIMESTAMP()');
			
			if ($filter & FLAG_FILTER_VIDEOS)
			{
				$condition->add(' AND EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
			}
			if ($filter & FLAG_FILTER_NO_VIDEOS)
			{
				$condition->add(' AND NOT EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
			}
			if ($filter & FLAG_FILTER_EMPTY)
			{
				$condition->add(' AND NOT EXISTS (SELECT g.id FROM games g WHERE g.tournament_id = t.id AND g.result > 0)');
			}
			if ($filter & FLAG_FILTER_NOT_EMPTY)
			{
				$condition->add(' AND EXISTS (SELECT g.id FROM games g WHERE g.tournament_id = t.id AND g.result > 0)');
			}
			if ($filter & FLAG_FILTER_CANCELED)
			{
				$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') <> 0');
			}
			if ($filter & FLAG_FILTER_NOT_CANCELED)
			{
				$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0');
			}
		}
			
		echo '<div class="tab">';
		echo '<button ' . ($future ? '' : 'class="active" ') . 'onclick="goTo({future:0,page:0})">' . get_label('Past') . '</button>';
		echo '<button ' . (!$future ? '' : 'class="active" ') . 'onclick="goTo({future:1,page:0})">' . get_label('Future') . '</button>';
		echo '</div>';
		echo '<div class="tabcontent">';
		
		if (!$future)
		{
			echo '<p><table class="transp" width="100%"><tr><td>';
			show_checkbox_filter(array(get_label('with video'), get_label('unplayed tournaments'), get_label('canceled tournaments')), $filter, 'filterTournaments');
			echo '</td></tr></table></p>';
		}
		
		list ($count) = Db::record(get_label('tournament'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$league_pic = new Picture(LEAGUE_PICTURE);
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.stars, t.start_time, t.duration, ct.timezone, t.langs, a.id, a.address, a.flags, l.id, l.name, l.flags,' .
			' (SELECT count(DISTINCT _p.user_id) FROM players _p JOIN games _g ON _g.id = _p.game_id WHERE _g.tournament_id = t.id AND _g.is_canceled = FALSE AND _g.result > 0) as players,' .
			' (SELECT count(*) FROM games _g JOIN events _e ON _e.id = _g.event_id WHERE _e.tournament_id = t.id AND _g.is_canceled = FALSE AND _g.result > 0) as games,' .
			' (SELECT count(*) FROM events WHERE tournament_id = t.id AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0) as events,' .
			' (SELECT count(*) FROM videos WHERE tournament_id = t.id) as videos',
			$condition);
		if ($future)
		{
			$query->add(' ORDER BY t.start_time, t.id');
		}
		else
		{
			$query->add(' ORDER BY t.start_time DESC, t.id DESC');
		}
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		$now = time();
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="3" align="center">' . get_label('Tournament') . '</td>';
		echo '<td width="60" align="center">' . get_label('Players') . '</td>';
		echo '<td width="60" align="center">' . get_label('Games') . '</td>';
		echo '<td width="60" align="center">' . get_label('Rounds') . '</td></tr>';
		while ($row = $query->next())
		{
			list ($tournament_id, $tournament_name, $tournament_flags, $tournament_stars, $tournament_time, $tournament_duration, $timezone, $languages, $addr_id, $addr, $addr_flags, $league_id, $league_name, $league_flags, $players_count, $games_count, $rounds_count, $videos_count) = $row;

			$playing =($now >= $tournament_time && $now < $tournament_time + $tournament_duration);
			if ($playing)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr>';
			}
			
			echo '<td width="60" class="dark" align="center" valign="center">';
			$tournament_pic->set($tournament_id, $tournament_name, $tournament_flags);
			$tournament_pic->show(ICONS_DIR, true, 60);
			echo '</td>';
			
			echo '<td><table width="100%" class="transp"><tr>';
			echo '<td><b><a href="tournament_standings.php?bck=1&id=' . $tournament_id . '">' . $tournament_name;
			if ($playing)
			{
				echo ' (' . get_label('playing now') . ')';
			}
			echo '</b><br>' . format_date('F d, Y', $tournament_time, $timezone) . '</a></td>';
			if ($videos_count > 0)
			{
				echo '<td align="right"><a href="tournament_videos.php?id=' . $tournament_id . '&bck=1" title="' . get_label('[0] videos from [1]', $videos_count, $tournament_name) . '"><img src="images/video.png" width="40" height="40"></a></td>';
			}
			echo '</tr></table>';
			echo '</td>';
			
			echo '<td width="64" align="center" valign="center">';
			echo '<font style="color:#B8860B; font-size:20px;">' . tournament_stars_str($tournament_stars) . '</font>';
			if ($league_id != NULL)
			{
				echo '<br>';
				$league_pic->set($league_id, $league_name, $league_flags);
				$league_pic->show(ICONS_DIR, false, 32);
			}
			echo '</td>';
			
			echo '<td align="center"><a href="tournament_standings.php?bck=1&id=' . $tournament_id . '">' . $players_count . '</a></td>';
			echo '<td align="center"><a href="tournament_games.php?bck=1&id=' . $tournament_id . '">' . $games_count . '</a></td>';
			echo '<td align="center"><a href="tournament_rounds.php?bck=1&id=' . $tournament_id . '">' . $rounds_count . '</a></td>';
			
			echo '</tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
?>
		function filterTournaments()
		{
			goTo({ filter: checkboxFilterFlags(), page: 0 });
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Tournaments'));

?>