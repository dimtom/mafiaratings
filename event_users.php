<?php

require_once 'include/event.php';
require_once 'include/pages.php';

class Page extends EventPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $this->event->club_id, $this->event->id, $this->event->tournament_id);
		$can_edit = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $this->event->club_id, $this->event->id, $this->event->tournament_id);
		
		$event_user_pic =
			new Picture(USER_EVENT_PICTURE, 
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic)));

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="87">';
		if ($can_edit)
		{
			echo '<button class="icon" onclick="mr.addEventUser(' . $this->event->id . ')" title="' . get_label('Add registration to [0].', $this->event->name) . '"><img src="images/create.png" border="0"></button>';
		}
		echo '</td>';
		echo '<td colspan="4">' . get_label('User') . '</td><td width="130">' . get_label('Permissions') . '</td></tr>';
		
		$query = new DbQuery(
			'SELECT u.id, u.name, u.email, u.flags, eu.nickname, eu.flags, tu.tournament_id, tu.flags, c.id, c.name, c.flags, cu.club_id, cu.flags' .
			' FROM event_users eu' .
			' JOIN users u ON eu.user_id = u.id' .
			' JOIN events e ON e.id = eu.event_id' .
			' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
			' LEFT OUTER JOIN tournament_users tu ON tu.tournament_id = e.tournament_id AND tu.user_id = eu.user_id' .
			' LEFT OUTER JOIN club_users cu ON cu.club_id = e.club_id AND cu.user_id = eu.user_id' .
			' WHERE eu.event_id = ?' .
			' ORDER BY u.name',
			$this->event->id);
		while ($row = $query->next())
		{
			list($id, $name, $email, $user_flags, $user_nickname, $event_user_flags, $tournament_id, $tournament_user_flags, $club_id, $club_name, $club_flags, $user_club_id, $club_user_flags) = $row;
		
			echo '<tr class="light"><td class="dark">';
			if ($can_edit)
			{
				echo '<button class="icon" onclick="mr.removeEventUser(' . $id . ', ' . $this->event->id . ')" title="' . get_label('Unregister [0].', $name) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.editEventAccess(' . $id . ', ' . $this->event->id . ')" title="' . get_label('Set [0] permissions.', $name) . '"><img src="images/access.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.eventUserPhoto(' . $id . ', ' . $this->event->id . ')" title="' . get_label('Set [0] photo for [1].', $name, $this->event->name) . '"><img src="images/photo.png" border="0"></button>';
			}
			else
			{
				echo '<img src="images/transp.png" height="32" border="0">';
			}
			echo '</td>';
			
			echo '<td width="60" align="center">';
			$event_user_pic->
				set($id, $user_nickname, $event_user_flags, 'e' . $this->event->id)->
				set($id, $name, $tournament_user_flags, 't' . $tournament_id)->
				set($id, $name, $club_user_flags, 'c' . $user_club_id)->
				set($id, $name, $user_flags);
			$event_user_pic->show(ICONS_DIR, true, 50);
			echo '</td>';
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 56) . '</a></td>';
			echo '<td width="200">';
			if ($_profile->is_club_manager($club_id))
			{
				echo $email;
			}
			echo '</td>';
			echo '<td width="50" align="center">';
			if (!is_null($club_id))
			{
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, true, 40);
			}
			echo '</td>';
			
			echo '<td>';
			if ($event_user_flags & USER_PERM_PLAYER)
			{
				echo '<img src="images/player.png" width="32" title="' . get_label('Player') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			if ($event_user_flags & USER_PERM_REFEREE)
			{
				echo '<img src="images/referee.png" width="32" title="' . get_label('Referee') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			if ($event_user_flags & USER_PERM_MANAGER)
			{
				echo '<img src="images/manager.png" width="32" title="' . get_label('Manager') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Registrations'));

?>