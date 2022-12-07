<?php

require_once '../../include/api.php';
require_once '../../include/user.php';
require_once '../../include/city.php';
require_once '../../include/country.php';
require_once '../../include/image.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// login
	//-------------------------------------------------------------------------------------------------------
	private static function _login($user_id)
	{
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
	
	function login_op()
	{
		if (!isset($_REQUEST['username']))
		{
			db_log(LOG_DETAILS_LOGIN, 'no user name');
			throw new Exc(get_label('Login attempt failed'));
		}
		$user_name = $_REQUEST['username'];
		$user_id = NULL;
		
		$log_message = 'user not found';
		if (isset($_REQUEST['proof']))
		{
			if (!isset($_SESSION['login_token']))
			{
				db_log(LOG_OBJECT_LOGIN, 'no token', NULL, $user_id);
				throw new Exc(get_label('Login attempt failed'), 'No token');
			}
			$proof = $_REQUEST['proof'];
			
			$query = new DbQuery('SELECT id, password FROM users WHERE name = ? OR email = ? ORDER BY games DESC, id', $user_name, $user_name);
			while ($row = $query->next())
			{
				list ($user_id, $password_hash) = $row;
				
				/*throw new Exc(
					'password: ' . $password_hash .
					'; token: ' . $_SESSION['login_token'] .	
					'; proof: ' . md5($password_hash . $_SESSION['login_token'] . $user_name) .
					'; clientProof: ' . $proof);*/
				
				if (md5($password_hash . $_SESSION['login_token'] . $user_name) == $proof)
				{
					ApiPage::_login($user_id);
					return;
				}
				else
				{
					$log_message = 'invalid password';
				}
			}
		}
		else if (isset($_POST['password']))
		{
			$password = $_POST['password'];
			
			$query = new DbQuery('SELECT id, password FROM users WHERE name = ? OR email = ? ORDER BY games DESC, id', $user_name, $user_name);
			while ($row = $query->next())
			{
				list ($user_id, $password_hash) = $row;
				if (md5($password) == $password_hash)
				{
					ApiPage::_login($user_id);
					return;
				}
				else
				{
					$log_message = 'invalid password';
				}
			}
		}
		else
		{
			db_log(LOG_OBJECT_LOGIN, 'no proof, no password', NULL, $user_id);
			throw new Exc(get_label('Login attempt failed'), 'No proof, no password');
		}
		
		$log_details = new stdClass();
		$log_details->name = $user_name;
		db_log(LOG_OBJECT_LOGIN, $log_message, $log_details);
		throw new Exc(get_label('Login attempt failed'), $log_message);
	}
	
	function login_op_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE, 'Log in. Session id is returned in a cookie <q>auth_key</q>. Use it in all the next requests.');
		$help->request_param('username', 'User name or email.');
		$help->request_param('proof', 'Security proof generated by formula: md5(md5(password) + token + user_name). Where user_name and password are obvious. Token is a token returned by <a href="account.php?help&op=get_token">get_token</a> request.', '<q>password</q> must be set');
		$help->request_param('password', 'Raw user password. It is used only when <q>proof</q> is not set. Use it only using https and only using post method. This is a simplified login but it is absolutely not secure using either http or get method.', '<q>proof</q> must be set');
		$help->request_param('remember', 'Set it to 1 for making session cookie permanent. Set it to 0 to make session cookie temporary.', 'everything as it was in the last session.');
		return $help;
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
		$help = new ApiHelp(PERMISSION_USER, 'Log out.');
		return $help;
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
		$help = new ApiHelp(PERMISSION_EVERYONE,
			'<p>Return login token. Client combines this token with the username and password: md5(md5(password) + token + user_name) and send this value in <q>id</q> paramerer of <q>login</q> request.</p>
			<p>Another method for logging-in is just setting http headers <q>username</q> and <q>password</q> to the apropriate values. But this is not safe for http - the password is not encrypted.</p>');
		$help->response_param('token', 'The token.');
		return $help;
	}
	
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
		$help = new ApiHelp(PERMISSION_EVERYONE, 'Create new user account.');
		
		$help->request_param('address_id', 'Address id.');
		$help->request_param('name', 'Account login name. This name is also used in the scoring tables. The name should be unique in <?php echo PRODUCT_NAME; ?>');
		$help->request_param('email', 'Account email. Currently it does not have to be unique.');

		$help->response_param('message', 'Localized user message sayings that the account is created.');
		return $help;
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
			db_log(LOG_OBJECT_USER, 'reset password', NULL, $id);
		}
		Db::commit();
		
		$body = get_label('Your password at') . ' <a href="' . PRODUCT_URL . '">' . PRODUCT_NAME .'</a> ' . get_label('has been reset to') . ' <b>' . $password . '</b>';
		$text_body = get_label('Your password at') . ' ' . PRODUCT_URL . ' ' . get_label('has been reset to') . ' ' . $password . "\r\n\r\n";
		send_email($email, $body, $text_body, 'Mafia');
		echo  get_label('Your password has been reset. Please check your email for the new password.');
	}
	
	function password_reset_op_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE, 'Reset user password.');
		$help->request_param('name', 'User name.');
		$help->request_param('email', 'User email.');
		$help->response_param('message', 'Localized user message sayings that the password is reset, and the email with it is sent.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// password_change
	//-------------------------------------------------------------------------------------------------------
	function password_change_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		
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
		db_log(LOG_OBJECT_USER, 'changed password', NULL, $_profile->user_id);
		Db::commit();
		
		echo get_label('Your password has been changed.');
	}
	
	function password_change_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Change user password.');
		$help->request_param('old_pwd', 'Current password.');
		$help->request_param('pwd1', 'New password.');
		$help->request_param('pwd2', 'New password confirmation.');
		$help->response_param('message', 'Localized user message sayings that the password is changed.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// suggest_club
	//-------------------------------------------------------------------------------------------------------
	function suggest_club_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
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
			$query = new DbQuery('SELECT c.id, c.country_id, c.area_id FROM cities c JOIN names n ON n.id = c.name_id WHERE n.name', $city_name);
			// $this->response['sql-01'] = $query->get_parsed_sql();
			if ($row = $query->next())
			{
				list($city_id, $country_id, $area_id) = $row;
			}
		}
		
		if ($country_id < 0 && isset($_REQUEST['country']))
		{
			$country_name = $_REQUEST['country'];
			$query = new DbQuery('SELECT DISTINCT c.id FROM countries c JOIN names n ON n.id = c.name_id WHERE n.name = ?', $country_name);
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
		$help = new ApiHelp(PERMISSION_USER, 'Suggest a club for the current user account, considering user city/country and the languages he/she knows.');
		$help->request_param('langs', 'User languages. A bit mask of language ids.' . valid_langs_help(), 'profile languages are used.');
		$help->request_param('city', 'User city name.', 'profile city is used.');
		$help->request_param('country', 'User country name.', 'profile country is used.');
		$help->response_param('club_id', 'The most sutable club for the current user account.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// browser_lang
	//-------------------------------------------------------------------------------------------------------
	function browser_lang_op()
	{
		global $_profile, $_lang;
		
		$lang_code = get_required_param('lang');
		$_SESSION['lang_code'] = $lang_code;
		$_lang = get_lang_by_code($lang_code);
		if (isset($_profile) && $_profile != NULL)
		{
			Db::begin();
			Db::exec(get_label('user'), 'UPDATE users SET def_lang = ? WHERE id = ?', $_lang, $_profile->user_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				$log_details->def_lang = $_lang;
				db_log(LOG_OBJECT_USER, 'changed', $log_details, $_profile->user_id);
			}
			Db::commit();
			$_profile->update();
		}
	}
	
	function browser_lang_op_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE, 'Set preferable language for viewing ' . PRODUCT_NAME . '.');
		$help->request_param('lang', 'Language id.' . valid_langs_help());
		return $help;
	}
}

$page = new ApiPage();
$page->run('Account Operations', CURRENT_VERSION, PERM_ALL);

?>