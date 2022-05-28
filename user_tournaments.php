<?php

require_once 'include/user.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/ccc_filter.php';
require_once 'include/scoring.php';
require_once 'include/tournament.php';
require_once 'include/checkbox_filter.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

define('FLAG_FILTER_VIDEOS', 0x0001);
define('FLAG_FILTER_NO_VIDEOS', 0x0002);

define('FLAG_FILTER_DEFAULT', 0);

class Page extends UserPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		echo '<table class="transp" width="100%"><tr><td>';
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('tournaments')));
		show_checkbox_filter(array(get_label('with video')), $filter, 'filterTournaments');
		echo '</td></tr></table>';
		
		$condition = new SQL(
			' FROM tournaments t' .
			' JOIN games g ON g.tournament_id = t.id' .
			' JOIN players p ON p.game_id = g.id' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN clubs c ON t.club_id = c.id' .
			' JOIN cities ct ON ct.id = a.city_id' . 
			' LEFT OUTER JOIN leagues l ON l.id = t.league_id' . 
			' WHERE p.user_id = ? AND g.is_canceled = FALSE AND g.result > 0 AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0', $this->id);
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND t.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND t.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND a.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND ct.country_id = ?', $ccc_id);
			break;
		}
		if ($filter & FLAG_FILTER_VIDEOS)
		{
			$condition->add(' AND EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
		}
		if ($filter & FLAG_FILTER_NO_VIDEOS)
		{
			$condition->add(' AND NOT EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
		}
		
		list ($count) = Db::record(get_label('tournament'), 'SELECT count(DISTINCT t.id)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time, ct.timezone, t.stars, c.id, c.name, c.flags, l.id, l.name, l.flags, t.langs, a.id, a.address, a.flags, SUM(p.rating_earned), COUNT(g.id), SUM(p.won), ' .
			' (SELECT count(*) FROM videos WHERE tournament_id = t.id) as videos',
			$condition);
		$query->add(' GROUP BY t.id ORDER BY t.start_time DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="3">' . get_label('Tournament') . '</td>';
		echo '<td width="60" align="center">'.get_label('Rating earned').'</td>';
		echo '<td width="60" align="center">'.get_label('Games played').'</td>';
		echo '<td width="60" align="center">'.get_label('Wins').'</td>';
		echo '<td width="60" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="60" align="center">'.get_label('Rating per game').'</td></tr>';

		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		$league_pic = new Picture(LEAGUE_PICTURE);
		while ($row = $query->next())
		{
			list ($tournament_id, $tournament_name, $tournament_flags, $tournament_time, $timezone, $tournament_stars, $club_id, $club_name, $club_flags, $league_id, $league_name, $league_flags, $languages, $address_id, $address, $address_flags, $rating, $games_played, $games_won, $videos_count) = $row;
			
			echo '<tr>';
			
			echo '<td width="60" class="dark" align="center" valign="center">';
			$tournament_pic->set($tournament_id, $tournament_name, $tournament_flags);
			$tournament_pic->show(ICONS_DIR, true, 60);
			echo '</td>';
			
			echo '<td><table width="100%" class="transp"><tr>';
			echo '<td width="60" align="center" valign="center">';
			$club_pic->set($club_id, $club_name, $club_flags);
			$club_pic->show(ICONS_DIR, false, 40);
			echo '</td><td>';
			echo '<b><a href="tournament_standings.php?bck=1&id=' . $tournament_id . '">' . $tournament_name . '</b>';
			echo '<br>' . format_date('F d, Y', $tournament_time, $timezone) . '</a></td>';
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
			
			echo '<td align="center" class="dark">' . number_format($rating, 2) . '</td>';
			echo '<td align="center"><a href="tournament_player_games.php?bck=1&user_id=' . $this->id . '&id=' . $tournament_id . '">' . $games_played . '</a></td>';
			echo '<td align="center">' . $games_won . '</td>';
			if ($games_played != 0)
			{
				echo '<td align="center">' . number_format(($games_won*100.0)/$games_played, 1) . '%</td>';
				echo '<td align="center">' . number_format($rating/$games_played, 2) . '</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td><td width="60">&nbsp;</td>';
			}
			
			echo '</tr>';
		}
		echo '</table>';
	}
	
	function js()
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