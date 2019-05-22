<?php

require_once 'include/session.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/tournament.php';
require_once 'include/timespan.php';
require_once 'include/scoring.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('tournament')));
	
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	$tournament_id = (int)$_REQUEST['id'];
	
	list ($club_id, $request_league_id, $league_id, $name, $start_time, $duration, $timezone, $stars) = 
		Db::record(get_label('tournament'), 'SELECT t.club_id, t.request_league_id, t.league_id, t.name, t.start_time, t.duration, ct.timezone, t.stars FROM tournaments t' . 
		' JOIN addresses a ON a.id = t.address_id' .
		' JOIN cities ct ON ct.id = a.city_id' .
		' WHERE t.id = ?', $tournament_id);
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">' . get_label('Tournament name') . ':</td><td><input id="form-name" value="' . $name . '"></td></tr>';
	
	echo '<tr><td>' . get_label('League') . ':</td><td><select id="form-league">';
	show_option(0, $request_league_id, '');
	$query = new DbQuery('SELECT l.id, l.name FROM league_clubs c JOIN leagues l ON l.id = c.league_id WHERE c.club_id = ? ORDER by l.name', $club_id);
	while ($row = $query->next())
	{
		list($lid, $lname) = $row;
		show_option($lid, $request_league_id, $lname);
	}
	echo '</select></td></tr>';
	
	echo '<tr><td>' . get_label('Stars') . ':</td><td><div id="form-stars" class="stars"></div></td></tr>';
	
	
	$end_time = $start_time + $duration - 24*60*60;
	if ($end_time < $start_time)
	{
		$end_time = $start_time;
	}
	date_default_timezone_set($timezone);
	$start_date = date('Y-m-d', $start_time);
	$end_date = date('Y-m-d', $end_time);
	
	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="text" id="form-start" value="' . $start_date . '">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="text" id="form-end" value="' . $end_date . '">';
	echo '</td></tr>';
	echo '</td></tr>';
	
	// $addr_id = -1;
	// $scoring_id = -1;
	// $query = new DbQuery('SELECT address_id, scoring_id FROM tournaments WHERE club_id = ? ORDER BY start_time DESC LIMIT 1', $club_id);
	// $row = $query->next();
	// if ($row = $query->next())
	// {
		// list($addr_id, $scoring_id) = $row;
		// if ($scoring_id == NULL)
		// {
			// $scoring_id = -1;
		// }
	// }
	// else
	// {
		// $query = new DbQuery('SELECT address_id, scoring_id FROM events WHERE club_id = ? ORDER BY start_time DESC LIMIT 1', $club_id);
		// if ($row = $query->next())
		// {
			// list($addr_id, $scoring_id) = $row;
		// }
	// }
	
	// $query = new DbQuery('SELECT id, name FROM addresses WHERE club_id = ? AND (flags & ' . ADDR_FLAG_NOT_USED . ') = 0 ORDER BY name', $club_id);
	// echo '<tr><td>'.get_label('Address').':</td><td>';
	// echo '<select id="form-addr_id" onChange="addressClick()">';
	// show_option(-1, $addr_id, get_label('New address'));
	// $selected_address = '';
	// while ($row = $query->next())
	// {
		// if (show_option($row[0], $addr_id, $row[1]))
		// {
			// $selected_address = $row[1];
		// }
	// }
	// echo '</select><div id="form-new_addr_div">';
// //	echo '<button class="icon" onclick="mr.createAddr(' . $club_id . ')" title="' . get_label('Create [0]', get_label('address')) . '"><img src="images/create.png" border="0"></button>';
	// echo '<input id="form-new_addr" onkeyup="newAddressChange()"> ';
	// show_country_input('form-country', $club->country, 'form-city');
	// echo ' ';
	// show_city_input('form-city', $club->city, 'form-country');
	// echo '</span></td></tr>';
	
	// echo '<tr><td>' . get_label('Admission rate') . ':</td><td><input id="form-price" value=""></td></tr>';
	
	// echo '<tr><td>' . get_label('Scoring system') . ':</td><td>';
	// echo '<select id="form-scoring" onChange="scoringChanged()" title="' . get_label('Scoring system') . '">';
	// $query = new DbQuery('SELECT id, name FROM scorings WHERE club_id = ? OR club_id IS NULL ORDER BY name', $club_id);
	// show_option(-1, $scoring_id, get_label('[The sum of round scores]'));
	// while ($row = $query->next())
	// {
		// list ($sid, $sname) = $row;
		// show_option($sid, $scoring_id, $sname);
	// }
	// echo '</select></td></tr>';
	
	// if (is_valid_lang($club->langs))
	// {
		// echo '<input type="hidden" id="form-langs" value="' . $club->langs . '">';
	// }
	// else
	// {
		// echo '<tr><td>'.get_label('Languages').':</td><td>';
		// langs_checkboxes(LANG_ALL, $club->langs, NULL, '<br>', 'form-');
		// echo '</td></tr>';
	// }
	
	// echo '<tr><td>'.get_label('Notes').':</td><td><textarea id="form-notes" cols="80" rows="4"></textarea></td></tr>';
		
	// echo '<tr><td colspan="2">';
	// echo '<input type="checkbox" id="form-long_term"> '.get_label('long term tournament. Like a seasonal club championship.').'<br>';
	// echo '<input type="checkbox" id="form-single_game"> '.get_label('single games from non-tournament events can be assigned to the tournament.').'<br>';
	// echo '<input type="checkbox" id="form-event_round"> '.get_label('club events can become tournament rounds if needed.').'<br>';
	echo '</table>';
	
?>	

	<script type="text/javascript" src="js/rater.min.js"></script>
	<script>
	
	var dateFormat = "yy-mm-dd";
	var startDate = $('#form-start').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true }).on("change", function() { endDate.datepicker("option", "minDate", this.value); });
	var endDate = $('#form-end').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true });
	
	// var oldAddressValue = "<?php echo '';//$selected_address; ?>";
	// function newAddressChange()
	// {
		// var text = $("#form-new_addr").val();
		// if ($("#form-name").val() == oldAddressValue)
		// {
			// $("#form-name").val(text);
		// }
		// oldAddressValue = text;
	// }
	
	// function addressClick()
	// {
		// var text = '';
		// if ($("#form-addr_id").val() <= 0)
		// {
			// $("#form-new_addr_div").show();
		// }
		// else
		// {
			// $("#form-new_addr_div").hide();
			// text = $("#form-addr_id option:selected").text();
		// }
		
		// if ($("#form-name").val() == oldAddressValue)
		// {
			// $("#form-name").val(text);
		// }
		// oldAddressValue = text;
	// }
	// addressClick();
	
	$("#form-stars").rate(
	{
		max_value: 5,
		step_size: 0.5,
		initial_value: <?php echo $stars; ?>,
	});
	
	function commit(onSuccess)
	{
		console.log(startDate.val());
		var _addr = $("#form-addr_id").val();
		
		var _flags = 0;
		if ($("#form-long_term").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_LONG_TERM; ?>;
		if ($("#form-single_game").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_SINGLE_GAME; ?>;
		if ($("#form-event_round").attr('checked')) _flags |= <?php echo TOURNAMENT_FLAG_EVENT_ROUND; ?>;
		
		var _end = strToDate(endDate.val());
		_end.setDate(_end.getDate() + 1); // inclusive
		
		var params =
		{
			op: "create",
			club_id: <?php echo $club_id; ?>,
			league_id: $("#form-league").val(),
			name: $("#form-name").val(),
			price: $("#form-price").val(),
			address_id: _addr,
			scoring_id: $("#form-scoring").val(),
			notes: $("#form-notes").val(),
			start: startDate.val(),
			end: dateToStr(_end),
			langs: $("#form-langs").val(),
			flags: _flags,
			stars: $("#form-stars").rate("getValue"),
		};
		
		console.log(_addr);
		if (_addr > 0)
		{
			params['address_id'] = _addr;
		}
		else
		{
			params['address'] = $("#form-new_addr").val();
			params['country'] = $("#form-country").val();
			params['city'] = $("#form-city").val();
		}
		
		json.post("api/ops/tournament.php", params, onSuccess);
	}
	
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>