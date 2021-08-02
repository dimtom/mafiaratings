<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/address.php';
require_once 'include/pages.php';
require_once 'include/event.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);
define('ETYPE_WITH_GAMES', 0);
define('ETYPE_NOT_CANCELED', 1);
define('ETYPE_ALL', 2);

class Page extends AddressPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		$events_type = ETYPE_WITH_GAMES;
		if (isset($_REQUEST['etype']))
		{
			$events_type = (int)$_REQUEST['etype'];
		}
		
		echo '<form method="get" name="clubForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%"><tr><td>';
		echo ' <select name="etype" onchange="document.clubForm.submit()">';
		show_option(ETYPE_WITH_GAMES, $events_type, get_label('Events'));
		show_option(ETYPE_NOT_CANCELED, $events_type, get_label('Events including empty'));
		show_option(ETYPE_ALL, $events_type, get_label('Events including canceled'));
		echo '</select>';
		echo '</td></tr></table></form>';
		
		$condition = new SQL(' FROM events e LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id WHERE e.address_id = ? AND e.start_time < UNIX_TIMESTAMP()', $this->id);
		switch ($events_type)
		{
			case ETYPE_NOT_CANCELED:
				$condition->add(' AND (e.flags & ' . (EVENT_FLAG_CANCELED | EVENT_FLAG_HIDDEN_AFTER) . ') = 0');
				break;
			case ETYPE_ALL:
				$condition->add(' AND (e.flags & ' . EVENT_FLAG_HIDDEN_AFTER . ') = 0');
				break;
			default:
				$condition->add(' AND (e.flags & ' . EVENT_FLAG_HIDDEN_AFTER . ') = 0 AND EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id AND g.result > 0)');
				break;
		}
		
		list ($count) = Db::record(get_label('event'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$query = new DbQuery(
			'SELECT e.id, e.name, e.start_time, e.flags, t.id, t.name, t.flags, ' .
				' (SELECT count(*) FROM games WHERE event_id = e.id AND result IN (1, 2)) as games,' .
				' (SELECT count(*) FROM registrations WHERE event_id = e.id) as users',
			$condition);
		$query->add(' ORDER BY e.start_time DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="2">' . get_label('Event') . '</td>';
		echo '<td>' . get_label('Address') . '</td>';
		echo '<td width="60" align="center">' . get_label('Games played') . '</td>';
		echo '<td width="60" align="center">' . get_label('Players attended') . '</td></tr>';
		
		$event_pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE));
		while ($row = $query->next())
		{
			list ($event_id, $event_name, $event_time, $event_flags, $tournament_id, $tournament_name, $tournament_flags, $games_count, $users_count) = $row;
			
			if ($event_flags & EVENT_FLAG_CANCELED)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr>';
			}
			
			echo '<td width="50" class="dark">';
			$event_pic->
				set($event_id, $event_name, $event_flags)->
				set($tournament_id, $tournament_name, $tournament_flags);
			$event_pic->show(ICONS_DIR, true, 50);
			echo '</td>';
			echo '<td width="180">' . $event_name . '<br><b>' . format_date('l, F d, Y', $event_time, $this->timezone) . '</b></td>';
			
			echo '<td>' . $this->address . '</td>';
			
			echo '<td align="center"><a href="event_games.php?bck=1&id=' . $event_id . '">' . $games_count . '</a></td>';
			echo '<td align="center"><a href="event_standings.php?bck=1&id=' . $event_id . '">' . $users_count . '</a></td>';
			
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Events history'));

?>