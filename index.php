<?php

require_once 'include/general_page_base.php';
require_once 'include/user.php';
require_once 'include/languages.php';
require_once 'include/address.php';
require_once 'include/user_location.php';
require_once 'include/club.php';
require_once 'include/event.php';
require_once 'include/snapshot.php';
require_once 'include/scoring.php';
require_once 'include/picture.php';

define('COLUMN_COUNT', 5);
define('ROW_COUNT', 2);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends GeneralPageBase
{
	private $event_pic;
	private $tournament_pic;
	
	private function show_events_list($query, $title)
	{
		$event_count = 0;
		$colunm_count = 0;
		while ($row = $query->next())
		{
			list ($event_id, $event_name, $event_flags, $event_time, $timezone, $tour_id, $tour_name, $tour_flags, $club_id, $club_name, $club_flags, $languages, $addr_id, $addr_flags, $addr, $addr_name) = $row;
			if ($event_name == $addr_name)
			{
				$event_name = $addr;
			}
			if ($colunm_count == 0)
			{
				if ($event_count == 0)
				{
					echo '<table class="bordered light" width="100%">';
					echo '<tr class="darker"><td colspan="' . COLUMN_COUNT . '"><b>' . $title . '</b></td></tr>';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td width="' . COLUMN_WIDTH . '%" align="center">';
			echo $club_name . '<br>';
			echo '<a href="event_info.php?bck=1&id=' . $event_id . '" title="' . get_label('View event details.') . '"><b>';
			echo format_date('l, F d, Y, H:i', $event_time, $timezone) . '</b><br>';
			$this->event_pic->set($event_id, $event_name, $event_flags)->set($tour_id, $tour_name, $tour_flags)->set($club_id, $club_name, $club_flags);
			$this->event_pic->show(ICONS_DIR, 60);
			echo '</a><br>' . $event_name . '</b></td>';
			++$colunm_count;
			++$event_count;
			if ($colunm_count >= COLUMN_COUNT)
			{
				$colunm_count = 0;
			}
		}
		if ($colunm_count > 0)
		{
			echo '<td colspan="' . (COLUMN_COUNT - $colunm_count) . '">&nbsp;</td>';
		}
		if ($event_count > 0)
		{
			echo '</tr></table>';
			return true;
		}
		return false;
	}
	
	private function show_tournaments_list($query, $title)
	{
		$tournament_count = 0;
		$colunm_count = 0;
		while ($row = $query->next())
		{
			list ($tournament_id, $tournament_name, $tournament_flags, $tournament_time, $timezone, $club_id, $club_name, $club_flags, $languages, $addr_id, $addr_flags, $addr, $addr_name) = $row;
			if ($tournament_name == $addr_name)
			{
				$tournament_name = $addr;
			}
			if ($colunm_count == 0)
			{
				if ($tournament_count == 0)
				{
					echo '<table class="bordered light" width="100%">';
					echo '<tr class="darker"><td colspan="' . COLUMN_COUNT . '"><b>' . $title . '</b></td></tr>';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td width="' . COLUMN_WIDTH . '%" align="center">';
			echo $club_name . '<br>';
			echo '<a href="tournament_info.php?bck=1&id=' . $tournament_id . '" title="' . get_label('View tournament details.') . '"><b>';
			echo format_date('l, F d, Y, H:i', $tournament_time, $timezone) . '</b><br>';
			$this->tournament_pic->set($tournament_id, $tournament_name, $tournament_flags)->set($club_id, $club_name, $club_flags);
			$this->tournament_pic->show(ICONS_DIR, 60);
			echo '</a><br>' . $tournament_name . '</b></td>';
			++$colunm_count;
			++$tournament_count;
			if ($colunm_count >= COLUMN_COUNT)
			{
				$colunm_count = 0;
			}
		}
		if ($colunm_count > 0)
		{
			echo '<td colspan="' . (COLUMN_COUNT - $colunm_count) . '">&nbsp;</td>';
		}
		if ($tournament_count > 0)
		{
			echo '</tr></table>';
			return true;
		}
		return false;
	}
	
	private function show_changes($rows, $columns)
	{
		global $_profile;
		
		$snapshot = new Snapshot(time());
		$snapshot->shot();
		$query = new DbQuery('SELECT time, snapshot FROM snapshots ORDER BY time DESC LIMIT 2');
		while ($row = $query->next())
		{
			list($prev_time, $json) = $row;
			$prev_snapshot = new Snapshot($prev_time, $json);
			$prev_snapshot->load_user_details();
			$diff = $snapshot->compare($prev_snapshot);
			if (count($diff) > 0)
			{
				break;
			}
		}
		if (!isset($diff) || count($diff) == 0)
		{
			return;
		}
		
		$timezone = get_timezone();
		$firstDark = false;
		$dark = false;
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td colspan="' . $columns . '"><b>' . get_label('Latest changes in the rating (since [0])', format_date('j M Y', $prev_time, $timezone)) . '</b></a></td></tr><tr>';
		for ($i = 0; $i < $columns; ++$i)
		{
			$dark = $firstDark;
			$firstDark = !$firstDark;
			
			$count = 0;
			$row_count = 0;
			echo '<td width="' . floor(100 / $columns) . '%" valign="top"><table class="transp" width="100%">';
			foreach ($diff as $player)
			{
				if ($count++ % $columns != $i)
				{
					continue;
				}
				
				if ($row_count++ >= $rows)
				{
					break;
				}
				
				if ($dark)
				{
					echo '<tr class="dark">';
				}
				else
				{
					echo '<tr>';
				}
				$dark = !$dark;
				echo '<td width="48" align="center"><a href="user_info.php?id=' . $player->id . '&bck=1">';
				$this->user_pic->set($player->id, $player->user_name, $player->user_flags);
				$this->user_pic->show(ICONS_DIR, 36);
				echo '</a></td><td width="48"><a href="club_main.php?id=' . $player->club_id . '&bck=1">';
				$this->club_pic->set($player->club_id, $player->club_name, $player->club_flags);
				$this->club_pic->show(ICONS_DIR, 36);
				echo '</a></td><td width="30">';
				if (isset($player->src))
				{
					if (isset($player->dst))
					{
						if ($player->src > $player->dst)
						{
							echo '<img src="images/up.png">';
							if ($player->user_flags & USER_FLAG_MALE)
							{
								echo '</td><td>' . get_label('[0] moved up from [1] to [2] place.', '<b>' . $player->user_name . '</b>', $player->src, $player->dst);
							}
							else
							{
								// the space in the end of a string means female gender for the languages where it matters
								echo '</td><td>' . get_label('[0] moved up from [1] to [2] place. ', '<b>' . $player->user_name . '</b>', $player->src, $player->dst);
							}
						}
						else if ($player->src < $player->dst)
						{
							echo '<img src="images/down_red.png">';
							if ($player->user_flags & USER_FLAG_MALE)
							{
								echo '</td><td>' . get_label('[0] moved down from [1] to [2] place.', '<b>' . $player->user_name . '</b>', $player->src, $player->dst);
							}
							else
							{
								// the space in the end of a string means female gender for the languages where it matters
								echo '</td><td>' . get_label('[0] moved down from [1] to [2] place. ', '<b>' . $player->user_name . '</b>', $player->src, $player->dst);
							}
						}
					}
					else
					{
						echo '<img src="images/down_red.png"></td><td>';
						if ($player->user_flags & USER_FLAG_MALE)
						{
							echo get_label('[0] left top 100.', '<b>' . $player->user_name . '</b>', $player->src);
						}
						else
						{
							// the space in the end of a string means female gender for the languages where it matters
							echo get_label('[0] left top 100. ', '<b>' . $player->user_name . '</b>', $player->src);
						}
					}
				}
				else
				{
					echo '<img src="images/up.png"></td><td>';
					if ($player->user_flags & USER_FLAG_MALE)
					{
						echo get_label('[0] entered top 100 and gained [1] place.', '<b>' . $player->user_name . '</b>', $player->dst);
					}
					else
					{
						// the space in the end of a string means female gender for the languages where it matters
						echo get_label('[0] entered top 100 and gained [1] place. ', '<b>' . $player->user_name . '</b>', $player->dst);
					}
				}
			}
			echo '</table></td>';
		}
		echo '</tr></table>';
	}

	protected function show_body()
	{
		global $_profile, $_lang_code;
		
		$this->tournament_pic = new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE));
		$this->event_pic = new Picture(EVENT_PICTURE, $this->tournament_pic);
		
		$condition = new SQL();
		$ccc_id = $this->ccc_filter->get_id();
		$ccc_code = $this->ccc_filter->get_code();
		$ccc_type = $this->ccc_filter->get_type();
		switch($ccc_type)
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND c.id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND c.id IN (SELECT club_id FROM user_clubs WHERE user_id = ?)', $_profile->user_id);
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND c.id IN (SELECT id FROM clubs WHERE city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?))', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND c.id IN (SELECT l.id FROM clubs l JOIN cities i ON i.id = l.city_id WHERE i.country_id = ?)', $ccc_id);
			break;
		}
		
		echo '<table width="100%"><tr><td valign="top">';
		$have_tables = false;
	
		// adverts
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, ct.timezone, n.id, n.timestamp, n.message FROM news n' . 
				' JOIN clubs c ON c.id = n.club_id' .
				' JOIN cities ct ON ct.id = c.city_id WHERE n.expires >= UNIX_TIMESTAMP() AND n.timestamp <= UNIX_TIMESTAMP()', $condition);
		$query->add(' ORDER BY n.timestamp DESC LIMIT 3');
		
		if ($row = $query->next())
		{
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="2"><b>' . get_label('Adverts') . '</b></a></td></tr>';
			
			do
			{
				list ($club_id, $club_name, $club_flags, $timezone, $id, $timestamp, $message) = $row;
				echo '<tr>';
				echo '<td width="100" class="dark" align="center" valign="top"><a href="club_main.php?id=' . $club_id . '&bck=1">' . $club_name . '<br>';
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, 60);
				echo '</a></td><td valign="top"><b>' . format_date('l, F d, Y', $timestamp, $timezone) . ':</b><br>' . $message . '</td></tr>';
				
				
			} while ($row = $query->next());
			echo '</table>';
			$have_tables = true;
		}
		
		$had_tables = $have_tables;
		if ($have_tables)
		{
			echo '<p>';
		}
		// my events
		// if ($_profile != NULL)
		// {
			// $query = new DbQuery(
				// 'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, t.id, t.name, t.flags, c.id, c.name, c.flags, e.languages, a.id, a.flags, a.address, a.name FROM event_users u' .
					// ' JOIN events e ON e.id = u.event_id' .
					// ' JOIN addresses a ON e.address_id = a.id' .
					// ' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
					// ' JOIN clubs c ON e.club_id = c.id' .
					// ' JOIN cities ct ON ct.id = c.city_id' .
					// ' WHERE u.user_id = ? AND u.coming_odds > 0 AND e.start_time + e.duration > UNIX_TIMESTAMP()' .
					// ' ORDER BY e.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT),
				// $_profile->user_id);
			// $have_tables = $this->show_events_list($query, get_label('Your events')) || $have_tables;
		// }
		
		// future tournaments
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time, ct.timezone, c.id, c.name, c.flags, t.langs, a.id, a.flags, a.address, a.name FROM tournaments t' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN clubs c ON t.club_id = c.id' .
			' JOIN cities ct ON ct.id = c.city_id' .
			' WHERE t.start_time > UNIX_TIMESTAMP()' .
			' ORDER BY t.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT));
		$have_tables = $this->show_tournaments_list($query, get_label('Upcoming tournaments')) || $have_tables;
		
		// upcoming
		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, t.id, t.name, t.flags, c.id, c.name, c.flags, e.languages, a.id, a.flags, a.address, a.name FROM events e' .
			' JOIN addresses a ON e.address_id = a.id' .
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
			' JOIN clubs c ON e.club_id = c.id' .
			' JOIN cities ct ON ct.id = c.city_id' .
			' WHERE e.start_time + e.duration > UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_HIDDEN_BEFORE . ') = 0', $condition);
		if ($_profile != NULL)
		{
			$query->add(' AND e.id NOT IN (SELECT event_id FROM event_users WHERE user_id = ? AND coming_odds > 0)', $_profile->user_id);
		}
		$query->add(' ORDER BY e.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT));
		$have_tables = $this->show_events_list($query, get_label('Coming soon')) || $have_tables;
		
		// current tournaments
		// $query = new DbQuery(
			// 'SELECT t.id, t.name, t.flags, t.start_time, ct.timezone, c.id, c.name, c.flags, t.langs, a.id, a.flags, a.address, a.name FROM tournaments t' .
			// ' JOIN addresses a ON t.address_id = a.id' .
			// ' JOIN clubs c ON t.club_id = c.id' .
			// ' JOIN cities ct ON ct.id = c.city_id' .
			// ' WHERE t.start_time + t.duration > UNIX_TIMESTAMP() AND t.start_time <= UNIX_TIMESTAMP()' .
			// ' ORDER BY t.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT));
		// $have_tables = $this->show_tournaments_list($query, get_label('Current tournaments')) || $have_tables;
		
		if ($had_tables)
		{
			echo '</p>';
		}

		$this->show_changes(10, 1);
		
		// ratings
		$query = new DbQuery('SELECT u.id, u.name, u.rating, u.games, u.games_won, u.flags, c.id, c.name, c.flags FROM users u LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE u.games > 0');
		switch($ccc_type)
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$query->add(' AND u.id IN (SELECT user_id FROM user_clubs WHERE club_id = ?)', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$query->add(' AND u.id IN (SELECT user_id FROM user_clubs WHERE club_id IN (' . $_profile->get_comma_sep_clubs() . '))');
			}
			break;
		case CCCF_CITY:
			$query->add(' AND u.city_id = ?', $ccc_id);
			break;
		case CCCF_COUNTRY:
			$query->add(' AND u.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $ccc_id);
			break;
		}
		$query->add(' ORDER BY u.rating DESC, u.games, u.games_won, u.id LIMIT 15');
		
		$number = 1;
		if ($row = $query->next())
		{
			echo '</td><td width="320" valign="top">';
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="5"><b>' . get_label('Best players') . '</b></td></tr>';
			
			do
			{
				list ($id, $name, $rating, $games_played, $games_won, $flags, $club_id, $club_name, $club_flags) = $row;

				echo '<td width="20" class="dark" align="center">' . $number . '</td>';
				echo '<td width="50" valign="top"><a href="user_info.php?id=' . $id . '&bck=1">';
				$this->user_pic->set($id, $name, $flags);
				$this->user_pic->show(ICONS_DIR, 50);
				echo '</a></td><td width="36"><a href="club_main.php?id=' . $club_id . '&bck=1">';
				
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, 36);
				echo '</td><td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
				echo '<td width="60" align="center">' . number_format($rating) . '</td>';
				echo '</tr>';
				
				++$number;
			} while ($row = $query->next());
			
			echo '</table>';
		}
		
		echo '</td></tr></table>';
	}
}

$page = new Page();
$page->run(get_label('Home'));

?>