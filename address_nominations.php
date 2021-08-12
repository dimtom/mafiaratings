<?php

require_once 'include/address.php';
require_once 'include/game_player.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/checkbox_filter.php';

define('FLAG_FILTER_TOURNAMENT', 0x0001);
define('FLAG_FILTER_NO_TOURNAMENT', 0x0002);
define('FLAG_FILTER_RATING', 0x0004);
define('FLAG_FILTER_NO_RATING', 0x0008);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_RATING);

class Page extends AddressPageBase
{
	private $min_games;
	private $games_count;

	protected function prepare()
	{
		parent::prepare();
		
		list($timezone) = Db::record(get_label('address'), 'SELECT c.timezone FROM addresses a JOIN cities c ON a.city_id = c.id WHERE a.id = ?', $this->id);
		date_default_timezone_set($timezone);
		
		list($this->games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN events e ON g.event_id = e.id WHERE e.address_id = ? AND g.canceled = FALSE AND g.result > 0', $this->id);
		if (isset($_REQUEST['min']))
		{
			$this->min_games = $_REQUEST['min'];
		}
		else
		{
			$this->min_games = round($this->games_count / 100) * 10;
			$this->min_games -= $this->min_games % 10;
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_lang_code;
		
		$noms = array(
			array(get_label('Ratings'), 'SUM(p.rating_earned)', 'count(*)', 0),
			array(get_label('Number of wins'), 'SUM(p.won)', 'count(*)', 1),
			array(get_label('Voted against civilians'), 'SUM(p.voted_civil)', 'SUM(p.voted_civil + p.voted_mafia + p.voted_sheriff)', 1),
			array(get_label('Voted against mafia'), 'SUM(p.voted_mafia)', 'SUM(p.voted_civil + p.voted_mafia + p.voted_sheriff)', 1),
			array(get_label('Voted against sheriff'), 'SUM(p.voted_sheriff)', 'SUM(p.voted_civil + p.voted_mafia + p.voted_sheriff)', 1),
			array(get_label('Voted by civilians'), 'SUM(p.voted_by_civil)', 'SUM(p.voted_by_civil + p.voted_by_mafia + p.voted_by_sheriff)', 1),
			array(get_label('Voted by mafia'), 'SUM(p.voted_by_mafia)', 'SUM(p.voted_by_civil + p.voted_by_mafia + p.voted_by_sheriff)', 1),
			array(get_label('Voted by sheriff'), 'SUM(p.voted_by_sheriff)', 'SUM(p.voted_by_civil + p.voted_by_mafia + p.voted_by_sheriff)', 1),
			array(get_label('Nominated civilians'), 'SUM(p.nominated_civil)', 'SUM(p.nominated_civil + p.nominated_mafia + p.nominated_sheriff)', 1),
			array(get_label('Nominated mafia'), 'SUM(p.nominated_mafia)', 'SUM(p.nominated_civil + p.nominated_mafia + p.nominated_sheriff)', 1),
			array(get_label('Nominated sheriff'), 'SUM(p.nominated_sheriff)', 'SUM(p.nominated_civil + p.nominated_mafia + p.nominated_sheriff)', 1),
			array(get_label('Nominated by civilians'), 'SUM(p.nominated_by_civil)', 'SUM(p.nominated_by_civil + p.nominated_by_mafia + p.nominated_by_sheriff)', 1),
			array(get_label('Nominated by mafia'), 'SUM(p.nominated_by_mafia)', 'SUM(p.nominated_by_civil + p.nominated_by_mafia + p.nominated_by_sheriff)', 1),
			array(get_label('Nominated by sheriff'), 'SUM(p.nominated_by_sheriff)', 'SUM(p.nominated_by_civil + p.nominated_by_mafia + p.nominated_by_sheriff)', 1),
			array(get_label('Survived'), 'SUM(IF(p.kill_type = 0, 1, 0))', 'count(*)', 1),
			array(get_label('Killed in day'), 'SUM(IF(p.kill_type = 1, 1, 0))', 'count(*)', 1),
			array(get_label('Killed in night'), 'SUM(IF(p.kill_type = 2, 1, 0))', 'count(*)', 1),
			array(get_label('Killed first night'), 'SUM(IF(p.kill_type = 2 AND p.kill_round = 0, 1, 0))', 'count(*)', 1),
			array(get_label('Warnings'), 'SUM(p.warns)', 'count(*)', 0),
			array(get_label('Arranged'), 'SUM(IF(p.was_arranged >= 0, 1, 0))', 'count(*)', 1),
			array(get_label('Arranged first night'), 'SUM(IF(p.was_arranged = 0, 1, 0))', 'count(*)', 1),
			array(get_label('Checked by don'), 'SUM(IF(p.checked_by_don >= 0, 1, 0))', 'count(*)', 1),
			array(get_label('Checked by sheriff'), 'SUM(IF(p.checked_by_sheriff >= 0, 1, 0))', 'count(*)', 1),
		);
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$nom = 0;
		if (isset($_REQUEST['nom']))
		{
			$nom = $_REQUEST['nom'];
		}
		if ($nom >= count($noms))
		{
			$nom = 0;
		}
		
		$roles = POINTS_ALL;
		if (isset($_REQUEST['roles']))
		{
			$roles = (int)$_REQUEST['roles'];
		}
		
		$sort = 0;
		if (isset($_REQUEST['sort']))
		{
			$sort = $_REQUEST['sort'];
		}
		
		if (isset($_REQUEST['min']))
		{
			$min_games = $_REQUEST['min'];
		}
		else
		{
			$min_games = round($this->games_count / 100) * 10;
			$min_games -= $min_games % 10;
		}
	
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_roles_select($roles, 'filterChanged()', get_label('Use only the stats of a specific role.'));
		
		echo ' <select id="min" onchange="filterChanged()" title="' . get_label('Show only players who played not less than a specific number of games.') . '">';
		$max_option = round($this->games_count / 20) * 10;
		for ($i = 0; $i <= $max_option; $i += 10)
		{
			if ($i == 0)
			{
				show_option($i, $this->min_games, get_label('All players'));
			}
			else
			{
				show_option($i, $this->min_games, get_label('[0] or more games', $i));
			}
		}
		echo '</select> ';
		show_checkbox_filter(array(get_label('tournament games'), get_label('rating games')), $filter, 'filterChanged');
		echo '</td><td align="right">';
		echo '<select id="nom" onchange="filterChanged()">';
		for ($i = 0; $i < count($noms); ++$i)
		{
			show_option($i, $nom, $noms[$i][0]);
		}
		echo '</select>';
		echo '</td></tr></table></p>';
		
		$condition = get_roles_condition($roles);
		if ($filter & FLAG_FILTER_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NOT NULL');
		}
		if ($filter & FLAG_FILTER_NO_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NULL');
		}
		if ($filter & FLAG_FILTER_RATING)
		{
			$condition->add(' AND (g.flags & ' . GAME_FLAG_FUN . ') = 0');
		}
		if ($filter & FLAG_FILTER_NO_RATING)
		{
			$condition->add(' AND (g.flags & ' . GAME_FLAG_FUN . ') <> 0');
		}
		
		$query = new DbQuery(
			'SELECT p.user_id, u.name, u.flags, count(*) as cnt, (' . $noms[$nom][1] . ') as abs, (' . $noms[$nom][1] . ') / (' . $noms[$nom][2] . ') as val, c.id, c.name, c.flags' .
				' FROM players p' .
				' JOIN games g ON p.game_id = g.id' .
				' JOIN users u ON u.id = p.user_id' .
				' JOIN events e ON g.event_id = e.id' .
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
				' WHERE e.address_id = ? AND g.canceled = FALSE AND g.result > 0',
			$this->id, $condition);
		$query->add(' GROUP BY p.user_id HAVING cnt > ?', $min_games);
		
		if ($sort & 2)
		{
			if ($sort & 1)
			{
				$query->add(' ORDER BY abs, val, cnt DESC LIMIT 10');
			}
			else
			{
				$query->add(' ORDER BY abs DESC, val DESC, cnt DESC LIMIT 10');
			}
		}
		else if ($sort & 1)
		{
			$query->add(' ORDER BY val, abs, cnt DESC LIMIT 10');
		}
		else
		{
			$query->add(' ORDER BY val DESC, abs DESC, cnt DESC LIMIT 10');
		}
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="3">' . get_label('Player') . '</td>';
		echo '<td width="100" align="center">' . get_label('Games played') . '</td>';
		echo '<td width="100" align="center">';
		if ($sort & 2)
		{
			if ($sort & 1)
			{
				echo '&#x25B2; <a href="javascript:goTo({sort:2})">';
			}
			else
			{
				echo '&#x25BC; <a href="javascript:goTo({sort:3})">';
			}
		}
		else
		{
			echo '<a href="javascript:goTo({sort:2})">';
		}
		echo get_label('Absolute') . '</a></td>';
		echo '<td width="100" align="center">';
		if (($sort & 2) == 0)
		{
			if ($sort & 1)
			{
				echo '&#x25B2; <a href="javascript:goTo({sort:0})">';
			}
			else
			{
				echo '&#x25BC; <a href="javascript:goTo({sort:1})">';
			}
		}
		else
		{
			echo '<a href="javascript:goTo({sort:0})">';
		}
		if ($noms[$nom][3])
		{
			echo '%';
		}
		else
		{
			echo get_label('Per game');
		}
		echo '</a></td></tr>';
		
		$number = 0;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $flags, $games_played, $abs, $val, $club_id, $club_name, $club_flags) = $row;

			echo '<tr class="light"><td align="center" class="dark">' . $number . '</td>';
			echo '<td width="50">';
			$this->user_pic->set($id, $name, $flags);
			$this->user_pic->show(ICONS_DIR, true, 50);
			echo '</td><td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
			echo '<td width="50" align="center">';
			if (!is_null($club_id))
			{
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, true, 40);
			}
			echo '</td>';
			echo '<td align="center">' . $games_played . '</td>';
			echo '<td width="100" align="center">' . number_format($abs, 0) . '</td>';
			echo '<td width="100" align="center">';
			if ($noms[$nom][3])
			{
				echo number_format($val * 100, 1) . '%';
			}
			else
			{
				echo number_format($val, 2);
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
?>
		function filterChanged()
		{
			goTo({roles: $('#roles').val(), filter: checkboxFilterFlags(), min: $('#min').val(), nom: $('#nom').val() });
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Nomination Winners'));

?>
