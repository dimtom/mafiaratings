<?php

require_once '../include/session.php';
require_once '../include/country.php';
require_once '../include/names.php';

initiate_session();

try
{
	dialog_title(get_label('Create country'));

	if ($_profile == NULL || !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	echo '<table class="dialog_form" width="100%">';
	
	echo '<tr><td width="200">'.get_label('Country name').':</td><td>';
	Names::show_control();
	echo '</td></tr>';
	
	echo '<tr><td>'.get_label('Two letter country code').':</td><td><input id="form-code" maxlength="2" size="2"></td></tr>';
	
	echo '<tr><td colspan="2" align="right"><input type="checkbox" id="form-confirm" checked> ' . get_label('confirm') . '</td></tr>';
	echo '</table>';
	
?>
	<script>
	function commit(onSuccess)
	{
		var request =
		{
			op: 'create'
			, code: $("#form-code").val()
			, confirm: ($('#form-confirm').attr('checked') ? 1 : 0)
		};
		nameControl.fillRequest(request);
		
		json.post("api/ops/country.php", request, onSuccess);
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