<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/languages.php';
require_once __DIR__ . '/image.php';
require_once __DIR__ . '/names.php';
require_once __DIR__ . '/address.php';
require_once __DIR__ . '/league.php';
require_once __DIR__ . '/city.php';
require_once __DIR__ . '/country.php';
require_once __DIR__ . '/user.php';

function show_series_buttons($id, $start_time, $duration, $flags, $league_id, $league_flags, $league_id)
{
	global $_profile;

	$now = time();
	if (($league_flags & LEAGUE_FLAG_RETIRED) == 0 && is_permitted(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, $league_id,  $id))
	{
		echo '<button class="icon" onclick="mr.editSeries(' . $id . ')" title="' . get_label('Edit the tournament series') . '"><img src="images/edit.png" border="0"></button>';
		if ($start_time >= $now)
		{
			if (($flags & SERIES_FLAG_CANCELED) != 0)
			{
				echo '<button class="icon" onclick="mr.restoreSeries(' . $id . ')"><img src="images/undelete.png" border="0"></button>';
			}
			else
			{
				echo '<button class="icon" onclick="mr.cancelSeries(' . $id . ', \'' . get_label('Are you sure you want to cancel the tournament series?') . '\')" title="' . get_label('Cancel the tournament series') . '"><img src="images/delete.png" border="0"></button>';
			}
		}
	}
}


class SeriesPageBase extends PageBase
{
	protected $id;
	protected $name;
	protected $league_id;
	protected $league_name;
	protected $league_flags;
	protected $start_time;
	protected $duration;
	protected $langs;
	protected $notes;
	protected $flags;
	
	protected function prepare()
	{
		global $_lang_code, $_profile;
		
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('tournament sеriеs')));
		}
		$this->id = (int)$_REQUEST['id'];
		list(
			$this->name, $this->league_id, $this->league_name, $this->league_flags,
			$this->start_time, $this->duration, $this->langs, $this->notes, $this->flags) =
		Db::record(
			get_label('tournament sеriеs'),
			'SELECT s.name, l.id, l.name, l.flags, s.start_time, s.duration, s.langs, s.notes, s.flags FROM series s' .
				' JOIN leagues l ON l.id = s.league_id' .
				' WHERE s.id = ?',
			$this->id);
	}
	
	protected function show_title()
	{
		echo '<table class="head" width="100%">';

		$is_manager = is_permitted(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, $this->league_id, $this->id);
		$menu = array
		(
			new MenuItem('series_info.php?id=' . $this->id, get_label('Tournament sеriеs'), get_label('General tournament series information')),
			new MenuItem('series_tournaments.php?id=' . $this->id, get_label('Tournaments'), get_label('Tournaments of this series')),
			new MenuItem('series_standings.php?id=' . $this->id, get_label('Standings'), get_label('Series standings')),
			new MenuItem('series_competition.php?id=' . $this->id, get_label('Competition chart'), get_label('How players were competing on this tournament series.')),
			new MenuItem('series_games.php?id=' . $this->id, get_label('Games'), get_label('Games list of the tournament series')),
			new MenuItem('#stats', get_label('Reports'), NULL, array
			(
				new MenuItem('series_stats.php?id=' . $this->id, get_label('General stats'), get_label('General statistics. How many games played, mafia winning percentage, how many players, etc.', PRODUCT_NAME)),
				new MenuItem('series_by_numbers.php?id=' . $this->id, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.')),
				new MenuItem('series_nominations.php?id=' . $this->id, get_label('Nomination winners'), get_label('Custom nomination winners. For example who had most warnings, or who was checked by sheriff most often.')),
				new MenuItem('series_referees.php?id=' . $this->id, get_label('Referees'), get_label('Statistics of the tournament series referees')),
			)),
			new MenuItem('#resources', get_label('Resources'), NULL, array
			(
				new MenuItem('series_rules.php?id=' . $this->id, get_label('Rulebook'), get_label('Rules of the game in [0]', $this->name)),
				new MenuItem('series_albums.php?id=' . $this->id, get_label('Photos'), get_label('Tournament series photo albums')),
				new MenuItem('series_videos.php?id=' . $this->id, get_label('Videos'), get_label('Videos from the tournament series.')),
				// new MenuItem('series_tasks.php?id=' . $this->id, get_label('Tasks'), get_label('Learning tasks and puzzles.')),
				// new MenuItem('series_articles.php?id=' . $this->id, get_label('Articles'), get_label('Books and articles.')),
				// new MenuItem('series_links.php?id=' . $this->id, get_label('Links'), get_label('Links to custom mafia web sites.')),
			)),
		);
		if ($is_manager)
		{
			$manager_menu = array
			(
				new MenuItem('series_users.php?id=' . $this->id, get_label('Registrations'), get_label('Manage registrations for [0]', $this->name)),
				new MenuItem('series_extra_points.php?id=' . $this->id, get_label('Extra points'), get_label('Add/remove extra points for players of [0]', $this->name)),
			);
			$menu[] = new MenuItem('#management', get_label('Management'), NULL, $manager_menu);
		}
		
		echo '<tr><td colspan="4">';
		PageBase::show_menu($menu);
		echo '</td></tr>';
		
		echo '<tr><td rowspan="2" valign="top" align="left" width="1">';
		echo '<table class="bordered ';
		if (($this->flags & SERIES_FLAG_CANCELED) != 0)
		{
			echo 'dark';
		}
		else
		{
			echo 'light';
		}
		echo '"><tr><td width="1" valign="top" style="padding:4px;" class="dark">';
		show_series_buttons(
			$this->id,
			$this->start_time,
			$this->duration,
			$this->flags,
			$this->league_id,
			$this->league_flags,
			$this->league_id);
		echo '</td><td width="' . ICON_WIDTH . '" style="padding: 4px;">';
		$series_pic = new Picture(SERIES_PICTURE);
		$series_pic->set($this->id, $this->name, $this->flags);
		if ($this->address_url != '')
		{
			echo '<a href="address_info.php?bck=1&id=' . $this->address_id . '">';
			$series_pic->show(TNAILS_DIR, false);
			echo '</a>';
		}
		else
		{
			$series_pic->show(TNAILS_DIR, false);
		}
		echo '</td></tr></table></td>';
		$title = get_label('Tournament series [0]', $this->_title);
		
		echo '<td rowspan="2" valign="top"><h2 class="series">' . $title . '</h2><br><h3>' . $this->name;
		$time = time();
		echo '</h3><p class="subtitle">' . format_date('l, F d, Y, H:i', $this->start_time, $this->timezone) . '</p>';
		if (!empty($this->price))
		{
			echo '<p class="subtitle"><b>' . get_label('Participation fee: [0]', $this->price) . '</b></p>';
		}
		echo '</td>';
		
		echo '<td valign="top" align="right">';
		show_back_button();
		echo '</td></tr><tr><td align="right" valign="bottom"><table><tr><td align="center">';
		
		$this->league_pic->set($this->league_id, $this->league_name, $this->league_flags);
		$this->league_pic->show(ICONS_DIR, true, 48);
		
		if (!is_null($this->league_id))
		{
			$league_pic = new Picture(LEAGUE_PICTURE);
			$league_pic->set($this->league_id, $this->league_name, $this->league_flags);
			$league_pic->show(ICONS_DIR, true, 48);
		}
		
		echo '</td></tr><tr><td>';
		for ($i = 0; $i < floor($this->stars); ++$i)
		{
			echo '<img src="images/star.png">';
		}
		for (; $i < $this->stars; ++$i)
		{
			echo '<img src="images/star-half.png">';
		}
		for (; $i < 5; ++$i)
		{
			echo '<img src="images/star-empty.png">';
		}
		echo '</td></tr></table></td></tr>';
		
		echo '</table>';
	}
}

?>