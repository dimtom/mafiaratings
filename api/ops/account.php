<?php

require_once '../../include/api.php';
require_once '../../include/user.php';
require_once '../../include/city.php';
require_once '../../include/country.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		$name = trim(get_required_param('name'));
		$email = trim(get_required_param('email'));
		if ($email == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('email address')));
		}
		
		create_user($name, $email);
		
		echo
			'<p>' . get_label('Thank you for signing up on [0]!', PRODUCT_NAME) .
			'<br>' . get_label('We have sent you a confirmation email to [0].', $email) .
			'</p><p>' . get_label('Click on the confirmation link in the email to complete your sign up.') . '</p>';
	}
	
	function create_op_help()
	{
		$help = new ApiHelp('Create new user account.');
		
		$help->request_param('address_id', 'Address id.');
		$help->request_param('name', 'Account login name. This name is also used in the scoring tables. The name should be unique in <?php echo PRODUCT_NAME; ?>');
		$help->request_param('email', 'Account email. Currently it does not have to be unique.');

		$help->response_param('message', 'Localized user message sayings that the account is created.');
		return $help;
	}
	
	function create_op_permissions()
	{
		return API_PERM_FLAG_EVERYONE;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// edit
	//-------------------------------------------------------------------------------------------------------
	function edit_op()
	{
		global $_profile, $_lang_code;
		
		$name = $_profile->user_name;
		if (isset($_REQUEST['name']))
		{
			$name = trim($_REQUEST['name']);
			if ($name != $_profile->user_name)
			{
				check_user_name($name);
			}
		}
		
		if (isset($_REQUEST['email']))
		{
			$email = trim($_REQUEST['email']);
			if ($email != $_profile->user_email)
			{
				if (empty($email))
				{
					throw new Exc(get_label('Please enter [0].', get_label('email address')));
				}
				else if (!is_email($email))
				{
					throw new Exc(get_label('[0] is not a valid email address.', $email));
				}
				send_activation_email($_profile->user_id, $_profile->user_name, $email);
				echo get_label('You are trying to change your email address. Please check your email and click a link in it to finalize the change.');
			}
		}
		
		$club_id = $_profile->user_club_id;
		if (isset($_REQUEST['club_id']))
		{
			$club_id = (int)$_REQUEST['club_id'];
			if ($club_id <= 0)
			{
				$club_id = NULL;
			}
		}
		
		$country_id = $_profile->country_id;
		if (isset($_REQUEST['country_id']))
		{
			$country_id = (int)$_REQUEST['country_id'];
		}
		else if (isset($_REQUEST['country']))
		{
			$country_id = retrieve_country_id($_REQUEST['country']);
		}
		
		$city_id = $_profile->city_id;
		if (isset($_REQUEST['city_id']))
		{
			$city_id = (int)$_REQUEST['city_id'];
		}
		else if (isset($_REQUEST['city']))
		{
			$city_id = retrieve_city_id($_REQUEST['city'], $country_id, get_timezone());
		}
		
		$langs = $_profile->user_langs;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
		}
		
		$phone = $_profile->user_phone;
		if (isset($_REQUEST['phone']))
		{
			$phone = $_REQUEST['phone'];
		}
		
		$flags = $_profile->user_flags;
		if (isset($_REQUEST['message_notify']))
		{
			if ($_REQUEST['message_notify'])
			{
				$flags |= U_FLAG_MESSAGE_NOTIFY;
			}
			else
			{
				$flags &= ~U_FLAG_MESSAGE_NOTIFY;
			}
		}
		
		if (isset($_REQUEST['photo_notify']))
		{
			if ($_REQUEST['photo_notify'])
			{
				$flags |= U_FLAG_PHOTO_NOTIFY;
			}
			else
			{
				$flags &= ~U_FLAG_PHOTO_NOTIFY;
			}
		}
		
		if (isset($_REQUEST['male']))
		{
			if ($_REQUEST['male'])
			{
				$flags |= U_FLAG_MALE;
			}
			else
			{
				$flags &= ~U_FLAG_MALE;
			}
		}
		
		Db::begin();
		if (isset($_REQUEST['pwd1']))
		{
			$password1 = $_REQUEST['pwd1'];
			$password2 = get_required_param('pwd2');
			check_password($password1, $password2);
			Db::exec(get_label('user'), 'UPDATE users SET password = ? WHERE id = ?', md5($password1), $_profile->user_id);
			if ($flags & U_FLAG_NO_PASSWORD)
			{
				$flags = $flags & ~U_FLAG_NO_PASSWORD;
			}
			else
			{
				db_log('user', 'Changed password', NULL, $_profile->user_id);
			}
		}
		
		$update_clubs = false;
		Db::exec(
			get_label('user'), 
			'UPDATE users SET name = ?, flags = ?, city_id = ?, languages = ?, phone = ?, club_id = ? WHERE id = ?',
			$name, $flags, $city_id, $langs, $phone, $club_id, $_profile->user_id);
		if (Db::affected_rows() > 0)
		{
			if ($club_id != NULL && !isset($_profile->clubs[$club_id]))
			{
				Db::exec(get_label('membership'), 'INSERT INTO user_clubs (user_id, club_id, flags) values (?, ?, ' . UC_NEW_PLAYER_FLAGS . ')', $_profile->user_id, $club_id);
				db_log('user', 'Joined the club', NULL, $_profile->user_id, $club_id);
				$update_clubs = true;
			}
			
			$log_details = '';
			$delim = '';
			if ($_profile->user_flags != $flags)
			{
				$log_details .= $delim . 'flags=' . $flags;
				$delim = '<br>';
			}
			
			if ($_profile->user_name != $name)
			{
				$log_details .= $delim . 'flags=' . $flags;
				$delim = '<br>';
			}
			
			if ($_profile->city_id != $city_id)
			{
				$log_details .= $delim . 'city_id=' . $city_id;
				$delim = '<br>';
			}
			
			if ($_profile->user_langs != $langs)
			{
				$log_details .= $delim . 'langs=' . $langs;
				$delim = '<br>';
			}
				
			if (!is_null($club_id))
			{
				list ($club_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
				$log_details .= $delim . 'club=' . $club_name . ' (' . $club_id . ')';
			}
			db_log('user', 'Changed', $log_details, $_profile->user_id);
		}
		Db::commit();
				
		$_profile->user_name = $name;
		$_profile->user_flags = $flags;
		$_profile->user_langs = $langs;
		$_profile->user_phone = $phone;
		$_profile->user_club_id = $club_id;
		if ($_profile->city_id != $city_id)
		{
			list ($_profile->country_id) = Db::record(get_label('city'), 'SELECT country_id FROM cities WHERE id = ?', $city_id);
			$_profile->city_id = $city_id;
		}
		if ($update_clubs)
		{
			$_profile->update_clubs();
		}
	}
	
	function edit_op_help()
	{
		$help = new ApiHelp('Change account settings.');
		$help->request_param('country_id', 'Country id.', 'remains the same unless <q>country</q> is set');
		$help->request_param('country', 'Country name. An alternative to <q>country_id</q>. It is used only if <q>country_id</q> is not set.', 'remains the same unless <q>country_id</q> is set');
		$help->request_param('city_id', 'City id.', 'remains the same unless <q>city</q> is set');
		$help->request_param('city', 'City name. An alternative to <q>city_id</q>. It is used only if <q>city_id</q> is not set.', 'remains the same unless <q>city_id</q> is not set');
		$help->request_param('club_id', 'User main club. If set to 0 or negative, user main club is set to none.', 'remains the same');
		$help->request_param('phone', 'User phone.', 'remains the same');
		$help->request_param('langs', 'User languages. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'remains the same');
		$help->request_param('male', '1 for male, 0 for female.', 'remains the same');
		$help->request_param('pwd1', 'User password.', 'remains the same');
		$help->request_param('pwd2', 'Password confirmation. Must be the same as <q>pwd1</q>. Must be set when <q>pwd1</q> is set. Ignored when <q>pwd1</q> is not set.', '-');
		$help->request_param('message_notify', '1 to notify user when someone replies to his/her message, 0 to turn notificetions off.', 'remains the same');
		$help->request_param('photo_notify', '1 to notify user when someone comments on his/her photo, 0 to turn notificetions off.', 'remains the same');

		$help->response_param('message', 'Localized user message when there is something to tell user.');
		return $help;
	}
	
	function edit_op_permissions()
	{
		return API_PERM_FLAG_USER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// password_reset
	//-------------------------------------------------------------------------------------------------------
	function password_reset_op()
	{
		$name = get_required_param('name');
		$email = get_required_param('email');
			
		$query = new DbQuery('SELECT id FROM users WHERE name = ? AND email = ?', $name, $email);
		if (!($row = $query->next()))
		{
			throw new Exc(get_label('Your login name and email do not match. You are using different email for this account.') . $sql);
		}
		
		list ($id) = $row;
		$password = rand_string(8);
		Db::begin();
		Db::exec(get_label('user'), 'UPDATE users SET password = ? WHERE id = ?', md5($password), $id);
		if (Db::affected_rows() > 0)
		{
			db_log('user', 'Reset password', NULL, $id);
		}
		Db::commit();
		
		$body = get_label('Your password at') . ' <a href="' . PRODUCT_URL . '">' . PRODUCT_NAME .'</a> ' . get_label('has been reset to') . ' <b>' . $password . '</b>';
		$text_body = get_label('Your password at') . ' ' . PRODUCT_URL . ' ' . get_label('has been reset to') . ' ' . $password . "\r\n\r\n";
		send_email($email, $body, $text_body, 'Mafia');
		echo  get_label('Your password has been reset. Please check your email for the new password.');
	}
	
	function password_reset_op_help()
	{
		$help = new ApiHelp('Reset user password.');
		$help->request_param('name', 'User name.');
		$help->request_param('email', 'User email.');
		$help->response_param('message', 'Localized user message sayings that the password is reset, and the email with it is sent.');
		return $help;
	}
	
	function password_reset_op_permissions()
	{
		return API_PERM_FLAG_EVERYONE;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// password_change
	//-------------------------------------------------------------------------------------------------------
	function password_change_op()
	{
		global $_profile;
		
		$old_pwd = get_required_param('old_pwd');
		$pwd1 = get_required_param('pwd1');
		$pwd2 = get_required_param('pwd2');
		check_password($pwd1, $pwd2);
		
		Db::begin();
		Db::exec(get_label('user'), 'UPDATE users SET password = ? WHERE id = ? AND password = ?', md5($pwd1), $_profile->user_id, md5($old_pwd));
		if (Db::affected_rows() != 1)
		{
			throw new Exc(get_label('Wrong password.'));
		}
		db_log('user', 'Changed password', NULL, $_profile->user_id);
		Db::commit();
		
		echo get_label('Your password has been changed.');
	}
	
	function password_change_op_help()
	{
		$help = new ApiHelp('Change user password.');
		$help->request_param('old_pwd', 'Current password.');
		$help->request_param('pwd1', 'New password.');
		$help->request_param('pwd2', 'New password confirmation.');
		$help->response_param('message', 'Localized user message sayings that the password is changed.');
		return $help;
	}
	
	function password_change_op_permissions()
	{
		return API_PERM_FLAG_USER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// join_club
	//-------------------------------------------------------------------------------------------------------
	function join_club_op()
	{
		global $_profile;
		
		$club_id = get_required_param('club_id');
		list ($count) = Db::record(get_label('membership'), 'SELECT count(*) FROM user_clubs WHERE user_id = ? AND club_id = ?', $_profile->user_id, $club_id);
		if ($count == 0)
		{
			Db::begin();
			Db::exec(get_label('membership'), 'INSERT INTO user_clubs (user_id, club_id, flags) values (?, ?, ' . UC_NEW_PLAYER_FLAGS . ')', $_profile->user_id, $club_id);
			db_log('user', 'Joined the club', NULL, $_profile->user_id, $club_id);
			Db::commit();
			$_profile->update_clubs();
		}
	}
	
	function join_club_op_help()
	{
		$help = new ApiHelp('Make current user a club member.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}
	
	function join_club_op_permissions()
	{
		return API_PERM_FLAG_USER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// quit_club
	//-------------------------------------------------------------------------------------------------------
	function quit_club_op()
	{
		global $_profile;
		
		$club_id = get_required_param('club_id');
		Db::begin();
		
		Db::exec(get_label('membership'), 'DELETE FROM user_clubs WHERE user_id = ? AND club_id = ?', $_profile->user_id, $club_id);
		if (Db::affected_rows() > 0)
		{
			db_log('user', 'Left the club', NULL, $_profile->user_id, $club_id);
		}
		Db::commit();
		$_profile->update_clubs();
	}
	
	function quit_club_op_help()
	{
		$help = new ApiHelp('Exclude current user from the members of the club.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}
	
	function quit_club_op_permissions()
	{
		return API_PERM_FLAG_USER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// suggest_club
	//-------------------------------------------------------------------------------------------------------
	function suggest_club_op()
	{
		global $_profile;
		
		$langs = $_profile->user_langs;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
		}
		
		$city_id = -1;
		$country_id = -1;
		$area_id = -1;
		if (isset($_REQUEST['city']))
		{
			$city_name = $_REQUEST['city'];
			$query = new DbQuery('SELECT id, country_id, area_id FROM cities WHERE name_en = ? OR name_ru = ?', $city_name, $city_name);
			// $this->response['sql-01'] = $query->get_parsed_sql();
			if ($row = $query->next())
			{
				list($city_id, $country_id, $area_id) = $row;
			}
		}
		
		if ($country_id < 0 && isset($_REQUEST['country']))
		{
			$country_name = $_REQUEST['country'];
			$query = new DbQuery('SELECT id FROM countries WHERE name_en = ? OR name_ru = ?', $country_name, $country_name);
			// $this->response['sql-02'] = $query->get_parsed_sql();
			if ($row = $query->next())
			{
				list($country_id) = $row;
			}
		}
		
		$club_id = -1;
		if ($city_id > 0)
		{
			$query = new DbQuery('SELECT c.id FROM clubs c LEFT JOIN games g ON g.club_id = c.id WHERE c.city_id = ? AND c.langs = ? AND (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 GROUP BY c.id ORDER BY count(g.id) DESC', $city_id, $langs);
			// $this->response['sql-03'] = $query->get_parsed_sql();
			if ($row = $query->next())
			{
				list($club_id) = $row;
			}
			
			if ($club_id <= 0)
			{
				$query = new DbQuery('SELECT c.id FROM clubs c LEFT JOIN games g ON g.club_id = c.id WHERE c.city_id = ? AND (c.langs & ?) <> 0 AND (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 GROUP BY c.id ORDER BY count(g.id) DESC', $city_id, $langs);
				// $this->response['sql-04'] = $query->get_parsed_sql();
				if ($row = $query->next())
				{
					list($club_id) = $row;
				}
			}
			
			if ($club_id <= 0)
			{
				$query = new DbQuery('SELECT c.id FROM clubs c LEFT JOIN games g ON g.club_id = c.id WHERE c.city_id = ? AND (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 GROUP BY c.id ORDER BY count(g.id) DESC', $city_id);
				// $this->response['sql-05'] = $query->get_parsed_sql();
				if ($row = $query->next())
				{
					list($club_id) = $row;
				}
			}
			
			if ($area_id != NULL)
			{
				if ($club_id <= 0)
				{
					$query = new DbQuery('SELECT c.id FROM clubs c LEFT JOIN games g ON g.club_id = c.id WHERE (c.city_id = ? OR c.city_id IN (SELECT id FROM cities WHERE area_id = ?)) AND c.langs = ? AND (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 GROUP BY c.id ORDER BY count(g.id) DESC', $area_id, $area_id, $langs);
					// $this->response['sql-06'] = $query->get_parsed_sql();
					if ($row = $query->next())
					{
						list($club_id) = $row;
					}
				}
				
				if ($club_id <= 0)
				{
					$query = new DbQuery('SELECT c.id FROM clubs c LEFT JOIN games g ON g.club_id = c.id WHERE (c.city_id = ? OR c.city_id  IN (SELECT id FROM cities WHERE area_id = ?)) AND (c.langs & ?) <> 0 AND (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 GROUP BY c.id ORDER BY count(g.id) DESC', $area_id, $area_id, $langs);
					// $this->response['sql-07'] = $query->get_parsed_sql();
					if ($row = $query->next())
					{
						list($club_id) = $row;
					}
				}
				
				if ($club_id <= 0)
				{
					$query = new DbQuery('SELECT c.id FROM clubs c LEFT JOIN games g ON g.club_id = c.id WHERE (c.city_id = ? OR c.city_id  IN (SELECT id FROM cities WHERE area_id = ?)) AND (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 GROUP BY c.id ORDER BY count(g.id) DESC', $area_id, $area_id);
					// $this->response['sql-08'] = $query->get_parsed_sql();
					if ($row = $query->next())
					{
						list($club_id) = $row;
					}
				}
			}
		}
		
		if ($club_id <= 0 && $country_id > 0)
		{
			$query = new DbQuery('SELECT c.id FROM clubs c LEFT JOIN games g ON g.club_id = c.id WHERE c.city_id IN (SELECT id FROM cities WHERE country_id = ?) AND c.langs = ? AND (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 GROUP BY c.id ORDER BY count(g.id) DESC', $country_id, $langs);
			// $this->response['sql-09'] = $query->get_parsed_sql();
			if ($row = $query->next())
			{
				list($club_id) = $row;
			}
			
			if ($club_id <= 0)
			{
				$query = new DbQuery('SELECT c.id FROM clubs c LEFT JOIN games g ON g.club_id = c.id WHERE c.city_id IN (SELECT id FROM cities WHERE country_id = ?) AND (c.langs & ?) <> 0 AND (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 GROUP BY c.id ORDER BY count(g.id) DESC', $country_id, $langs);
				// $this->response['sql-10'] = $query->get_parsed_sql();
				if ($row = $query->next())
				{
					list($club_id) = $row;
				}
			}
			
			if ($club_id <= 0)
			{
				$query = new DbQuery('SELECT c.id FROM clubs c LEFT JOIN games g ON g.club_id = c.id WHERE c.city_id IN (SELECT id FROM cities WHERE country_id = ?) AND (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 GROUP BY c.id ORDER BY count(g.id) DESC', $country_id);
				// $this->response['sql-11'] = $query->get_parsed_sql();
				if ($row = $query->next())
				{
					list($club_id) = $row;
				}
			}
		}
		
		if ($club_id <= 0)
		{
			$query = new DbQuery('SELECT c.id FROM clubs c LEFT JOIN games g ON g.club_id = c.id AND c.langs = ? AND (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 GROUP BY c.id ORDER BY count(g.id) DESC', $langs);
			// $this->response['sql-12'] = $query->get_parsed_sql();
			if ($row = $query->next())
			{
				list($club_id) = $row;
			}
		}
		
		if ($club_id <= 0)
		{
			$query = new DbQuery('SELECT c.id FROM clubs c LEFT JOIN games g ON g.club_id = c.id AND (c.langs & ?) <> 0 AND (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 GROUP BY c.id ORDER BY count(g.id) DESC', $langs);
			// $this->response['sql-13'] = $query->get_parsed_sql();
			if ($row = $query->next())
			{
				list($club_id) = $row;
			}
		}
		
		if ($club_id <= 0)
		{
			$query = new DbQuery('SELECT c.id FROM clubs c LEFT JOIN games g ON g.club_id = c.id WHERE (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 GROUP BY c.id ORDER BY count(g.id) DESC');
			// $this->response['sql-14'] = $query->get_parsed_sql();
			if ($row = $query->next())
			{
				list($club_id) = $row;
			}
		}
		$this->response['club_id'] = $club_id;
	}
	
	function suggest_club_op_help()
	{
		$help = new ApiHelp('Suggest a club for the current user account, considering user city/country and the languages he/she knows.');
		$help->request_param('langs', 'User languages. A bit mask where 1 is English; 2 is Russian. Their combination (3 = 1 | 2) means both. Other languages are not supported yet.', 'profile languages are used.');
		$help->request_param('city', 'User city name.', 'profile city is used.');
		$help->request_param('country', 'User country name.', 'profile country is used.');
		$help->response_param('club_id', 'The most sutable club for the current user account.');
		return $help;
	}
	
	function suggest_club_op_permissions()
	{
		return API_PERM_FLAG_USER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// get_token
	//-------------------------------------------------------------------------------------------------------
	function get_token_op()
	{
		$token = md5(rand_string(8));
		$_SESSION['login_token'] = $token;
		$this->response['token'] = $token;
	}
	
	function get_token_op_help()
	{
		$help = new ApiHelp(
			'<p>Return login token. Client combines this token with the username and password: md5(md5(password) + token + user_name) and send this value in <q>id</q> paramerer of <q>login</q> request.</p>
			<p>Another method for logging-in is just setting http headers <q>username</q> and <q>password</q> to the apropriate values. But this is not safe for http - the password is not encrypted.</p>');
		$help->response_param('token', 'The token.');
		return $help;
	}
	
	function get_token_op_permissions()
	{
		return API_PERM_FLAG_EVERYONE;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// login
	//-------------------------------------------------------------------------------------------------------
	function login_op()
	{
		if (!isset($_REQUEST['username']))
		{
			db_log('login', 'No user name', NULL);
			throw new Exc(get_label('Login attempt failed'));
		}
		$user_name = $_REQUEST['username'];
		
		$query = new DbQuery('SELECT id, password FROM users WHERE name = ?', $user_name);
		if ($query->num_rows($query) != 1)
		{
			db_log('login', 'User not found', 'name=' . $user_name);
			throw new Exc(get_label('Login attempt failed'));
		}
		list ($user_id, $password) = $query->next();
		
		if (!isset($_SESSION['login_token']))
		{
			db_log('login', 'No token', NULL, $user_id);
			throw new Exc(get_label('Login attempt failed'));
		}
		
		if (!isset($_REQUEST['id']))
		{
			db_log('login', 'No id', NULL, $user_id);
			throw new Exc(get_label('Login attempt failed'));
		}
		$sec_id = $_REQUEST['id'];
		
		/*throw new Exc(
			'password: ' . $password .
			'; token: ' . $_SESSION['login_token'] .	
			'; secId: ' . md5($password . $_SESSION['login_token'] . $user_name) .
			'; clientSecId: ' . $sec_id);*/
		
		if (md5($password . $_SESSION['login_token'] . $user_name) != $sec_id)
		{
			db_log('login', 'Invalid password', NULL, $user_id);
			//$details = '<br>token = ' . $_SESSION['login_token'] . '<br>raw id = ' . $password . $_SESSION['login_token'] . $user_name . '<br>id = ' . md5($password . $_SESSION['login_token'] . $user_name) . '<br>client id = ' . $sec_id;
			throw new Exc(get_label('Login attempt failed'));
		}
		
		$remember = -1;
		if (isset($_REQUEST['remember']))
		{
			if ($_REQUEST['remember'])
			{
				$remember = 1;
			}
			else
			{
				$remember = 0;
			}
		}
			
		if (!login($user_id, $remember))
		{
			throw new Exc(get_label('Login attempt failed'));
		}
	}
	
	function login_op_help()
	{
		$help = new ApiHelp('Log in.');
		$help->request_param('username', 'User name.');
		$help->request_param('id', 'Security id generated by formula: md5(md5(password) + token + user_name). Where user_name and password are obvious. Token is a token returned by <?php echo $this->title; ?> <q>get_token</q> request.');
		$help->request_param('remember', 'Set it to 1 for making session cookie permanent. Set it to 0 to make session cookie temporary.', 'everything as it was in the last session.');
		return $help;
	}
	
	function login_op_permissions()
	{
		return API_PERM_FLAG_EVERYONE;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// logout
	//-------------------------------------------------------------------------------------------------------
	function logout_op()
	{
		logout();
	}
	
	function logout_op_help()
	{
		$help = new ApiHelp('Log out.');
		return $help;
	}
	
	function logout_op_permissions()
	{
		return API_PERM_FLAG_USER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// site_style
	//-------------------------------------------------------------------------------------------------------
	function site_style_op()
	{
		global $_agent;
		switch ((int)get_required_param('style'))
		{
			case SITE_STYLE_DESKTOP:
				if ($_agent != AGENT_BROWSER || (isset($_SESSION['mobile']) && $_SESSION['mobile']))
				{
					$_SESSION['mobile'] = false;
				}
				break;
			case SITE_STYLE_MOBILE:
				if ($_agent == AGENT_BROWSER || (isset($_SESSION['mobile']) && !$_SESSION['mobile']))
				{
					$_SESSION['mobile'] = true;
				}
				break;
		}
	}
	
	function site_style_op_help()
	{
		$help = new ApiHelp('Set prefered site style for user.');
		$help->request_param('style', '0 for desktop style. 1 for mobile style.');
		return $help;
	}
	
	function site_style_op_permissions()
	{
		return API_PERM_FLAG_USER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// browser_lang
	//-------------------------------------------------------------------------------------------------------
	function browser_lang_op()
	{
		global $_profile, $_lang_code;
		
		$browser_lang = get_required_param('lang');
		$_lang_code = $_SESSION['lang_code'] = $browser_lang;
		if (isset($_profile) && $_profile != NULL)
		{
			$_profile->user_def_lang = get_lang_by_code($browser_lang);
			Db::begin();
			Db::exec(get_label('user'), 'UPDATE users SET def_lang = ? WHERE id = ?', $_profile->user_def_lang, $_profile->user_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = 'def_lang=' . $_profile->user_def_lang;
				db_log('user', 'Changed', $log_details, $_profile->user_id);
			}
			Db::commit();
			$_profile->update();
		}
	}
	
	function browser_lang_op_help()
	{
		$help = new ApiHelp('Set preferable language for viewing ' . PRODUCT_NAME . '.');
		$help->request_param('lang', 'Language code: <q>ru</q> for Russian; <q>en</q> for English; other languages are not supported yet.');
		return $help;
	}
	
	function browser_lang_op_permissions()
	{
		return API_PERM_FLAG_USER;
	}
}

$page = new ApiPage();
$page->run('Account Operations', CURRENT_VERSION, PERM_ALL);

?>