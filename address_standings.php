<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/address.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

define("PAGE_SIZE",15);
define('ROLES_COUNT', 7);

class Page extends AddressPageBase
{
	private $roles;
	
	private $user_id;
	private $user_name;
	private $user_club_id;
	private $user_city_id;
	private $user_country_id;

	protected function prepare()
	{
		global $_profile, $_page;
	
		parent::prepare();

		$this->roles = POINTS_ALL;
		if (isset($_REQUEST['roles']))
		{
			$this->roles = $_REQUEST['roles'];
		}
		
		if (isset($_REQUEST['scoring']))
		{
			$this->scoring_id = $_REQUEST['scoring'];
		}
		
		$this->user_id = 0;
		if ($_page < 0)
		{
			$this->user_id = -$_page;
			$_page = 0;
			$query = new DbQuery('SELECT u.name, u.club_id, u.city_id, c.country_id FROM users u JOIN cities c ON c.id = u.city_id WHERE u.id = ?', $this->user_id);
			if ($row = $query->next())
			{
				list($this->user_name, $this->user_club_id, $this->user_city_id, $this->user_country_id) = $row;
				$this->_title .= ' ' . get_label('Following [0].', $this->user_name);
			}
			else
			{
				$this->errorMessage(get_label('Player not found.'));
			}
		}
	}
	
	protected function show_body()
	{
		global $_page, $_lang_code;
		
		$condition = new SQL(' AND g.event_id IN (SELECT id FROM events WHERE address_id = ?)', $this->id);
		
		echo '<form method="get" name="viewForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		show_scoring_select($this->club_id, $this->scoring_id, 'document.viewForm.submit()', get_label('Scoring system'));
		echo ' ';
		show_roles_select($this->roles, 'document.viewForm.submit()', get_label('Use only the points earned in a specific role.'));
		echo '</td><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'club=' . $this->club_id, get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></form>';
		
		$scoring_system = new ScoringSystem($this->scoring_id);
		$scores = new Scores($scoring_system, $condition, get_roles_condition($this->roles));
		$players_count = count($scores->players);
		if ($this->user_id > 0)
		{
			$_page = $scores->get_user_page($this->user_id, PAGE_SIZE);
			if ($_page < 0)
			{
				$_page = 0;
				$this->no_user_error();
			}
		}

		show_pages_navigation(PAGE_SIZE, $players_count);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40" rowspan="2">&nbsp;</td>';
		echo '<td colspan="3" rowspan="2">'.get_label('Player').'</td>';
		echo '<td width="36" align="center" colspan="6">'.get_label('Points').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Games played').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Wins').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Winning %').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Points per game').'</td>';
		echo '</tr>';
		echo '<tr class="th darker" align="center"><td width="36">' . get_label('Sum') . '</td><td width="36">' . get_label('Main') . '</td><td width="36">' . get_label('Guess') . '</td><td width="36">' . get_label('Extra') . '</td><td width="36">' . get_label('Penlt') . '</td><td width="36">' . get_label('Other') . '</td></tr>';

		$page_start = $_page * PAGE_SIZE;
		if ($players_count > $page_start + PAGE_SIZE)
		{
			$players_count = $page_start + PAGE_SIZE;
		}
		for ($number = $page_start; $number < $players_count; ++$number)
		{
			$score = $scores->players[$number];

			if ($score->id == $this->user_id)
			{
				echo '<tr class="darker">';
				$highlight = 'darker';
			}
			else
			{
				echo '<tr>';
				$highlight = 'dark';
			}
			echo '<td align="center" class="' . $highlight . '">' . $number . '</td>';
			echo '<td width="50"><a href="user_info.php?id=' . $score->id . '&bck=1">';
			$this->user_pic->set($score->id, $score->name, $score->flags);
			$this->user_pic->show(ICONS_DIR, 50);
			echo '</a></td><td><a href="user_info.php?id=' . $score->id . '&bck=1">' . cut_long_name($score->name, 45) . '</a></td>';
			echo '<td width="50" align="center">';
			if (!is_null($score->club_id) && $score->club_id > 0)
			{
				$this->club_pic->set($score->club_id, $score->club_name, $score->club_flags);
				$this->club_pic->show(ICONS_DIR, 40);
			}
			echo '</td>';
			echo '<td align="center" class="' . $highlight . '">' . $score->sum_points_str() . '</td>';
			echo '<td align="center">' . $score->main_points_str() . '</td>';
			echo '<td align="center">' . $score->prima_nocta_points_str() . '</td>';
			echo '<td align="center">' . $score->extra_points_str() . '</td>';
			echo '<td align="center">' . $score->penalty_points_str() . '</td>';
			echo '<td align="center">' . $score->other_points_str() . '</td>';
			echo '<td align="center">' . $score->games_played . '</td>';
			echo '<td align="center">' . $score->games_won . '</td>';
			if ($score->games_played != 0)
			{
				echo '<td align="center">' . number_format(($score->games_won * 100.0) / $score->games_played, 1) . '%</td>';
				echo '<td align="center">' . $score->points_per_game_str() . '</td>';
			}
			else
			{
				echo '<td>&nbsp;</td><td>&nbsp;</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
	
	private function no_user_error()
	{
		if ($this->roles == POINTS_ALL)
		{
			$message = get_label('[0] played no games.', $this->user_name);
		}
		else
		{
			$message = get_label('[0] played no games as [1].', $this->user_name, get_role_name($this->roles, ROLE_NAME_FLAG_SINGLE | ROLE_NAME_FLAG_LOWERCASE));
		}
		$this->errorMessage($message);
	}
}

$page = new Page();
$page->run(get_label('Standings'));

?>