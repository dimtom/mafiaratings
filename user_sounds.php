<?php

require_once 'include/user.php';

class Page extends UserPageBase
{
	protected function show_body()
	{
		global $_profile;
		
		check_permissions(PERMISSION_OWNER, $this->id);
		
		$sounds = array();
		$query = new DbQuery('SELECT id, name FROM sounds WHERE user_id = ? ORDER BY name', $this->id);
		while ($row = $query->next())
		{
			$sounds[] = $row;
		}
		
		$global_sounds = array();
		$query = new DbQuery('SELECT id, name FROM sounds WHERE club_id IS NULL AND user_id IS NULL ORDER BY id');
		while ($row = $query->next())
		{
			$global_sounds[] = $row;
		}
		
		$def_prompt_sound = 0;
		$def_end_sound = 0;
		$query = new DbQuery('SELECT prompt_sound_id, end_sound_id FROM game_settings WHERE user_id = ?', $this->id);
		if ($row = $query->next())
		{
			list($def_prompt_sound, $def_end_sound) = $row;
		}
		
		echo '<p>';
		echo get_label('Default 10 sec prompt sound') . ': <select id="def-prompt" onchange="promptSoundChanged()">';
		show_option(0, $def_prompt_sound, get_label('[use club sound]'));
		foreach ($global_sounds as $row)
		{
			list ($id, $name) = $row;
			show_option($id, $def_prompt_sound, $name);
		}
		foreach ($sounds as $row)
		{
			list ($id, $name) = $row;
			show_option($id, $def_prompt_sound, $name);
		}
		echo '</select>   ';
		
		echo get_label('Default end of speech sound') . ': <select id="def-end" onchange="endSoundChanged()">';
		show_option(0, $def_end_sound, get_label('[use club sound]'));
		foreach ($global_sounds as $row)
		{
			list ($id, $name) = $row;
			show_option($id, $def_end_sound, $name);
		}
		foreach ($sounds as $row)
		{
			list ($id, $name) = $row;
			show_option($id, $def_end_sound, $name);
		}
		echo '</select></p>';
		
		echo '<audio id="snd" preload></audio>';
		echo '<table class="bordered" width="100%">';
		echo '<tr class="darker"><th width="56">';
		echo '<button class="icon" onclick="mr.createSound(undefined, ' . $this->id . ')" title="' . get_label('Create [0]', get_label('sound')) . '"><img src="images/create.png" border="0"></button></th>';
		echo '<th align="left">' . get_label('Name') . '</th></tr>';
		foreach ($sounds as $row)
		{
			list ($id, $name) = $row;

			echo '<tr class="light">';
			echo '<td width="84" valign="top">';
			echo '<button class="icon" onclick="playSound(' . $id . ')" title="' . get_label('Play sound') . '"><img src="images/resume.png" border="0"></button>';
			echo '<button class="icon" onclick="mr.editSound(' . $id . ')" title="' . get_label('Edit [0]', get_label('sound')) . '"><img src="images/edit.png" border="0"></button>';
			echo '<button class="icon" onclick="mr.deleteSound(' . $id . ', \'' . get_label('Are you sure you want to delete the sound?') . '\')" title="' . get_label('Delete [0]', get_label('sound')) . '"><img src="images/delete.png" border="0"></button>';
			echo '</td>';
			echo '<td>' . $name . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
		parent::js();
?>
		function playSound(id)
		{
			var sound = document.getElementById('snd').cloneNode(true);
			var time = new Date();
			sound.src = "sounds/" + id + ".mp3?" + time.getTime();
			sound.play();
		}
		
		function promptSoundChanged()
		{
			var id = $("#def-prompt").val();
			playSound(id)
			json.post("api/ops/sound.php",
			{
				op: 'set_def_sound'
				, prompt_sound_id: id
			});
		}
		
		function endSoundChanged()
		{
			var id = $("#def-end").val();
			playSound(id)
			json.post("api/ops/sound.php",
			{
				op: 'set_def_sound'
				, end_sound_id: id
			});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Sounds'));

?>