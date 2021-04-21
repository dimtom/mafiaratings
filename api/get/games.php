<?php

require_once '../../include/api.php';
require_once '../../include/game.php';
require_once '../../include/datetime.php';
require_once '../../include/games.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_profile;
		
		$started_before = get_optional_param('started_before');
		$ended_before = get_optional_param('ended_before');
		$started_after = get_optional_param('started_after');
		$ended_after = get_optional_param('ended_after');
		$game_id = (int)get_optional_param('game_id', -1);
		$club_id = (int)get_optional_param('club_id', -1);
		$league_id = (int)get_optional_param('league_id', -1);
		$event_id = (int)get_optional_param('event_id', -1);
		$tournament_id = (int)get_optional_param('tournament_id', -1);
		$address_id = (int)get_optional_param('address_id', -1);
		$city_id = (int)get_optional_param('city_id', -1);
		$area_id = (int)get_optional_param('area_id', -1);
		$country_id = (int)get_optional_param('country_id', -1);
		$user_id = (int)get_optional_param('user_id', -1);
		$langs = (int)get_optional_param('langs', 0);
		$games_filter = (int)get_optional_param('games_filter', GAMES_FILTER_ALL);
		$rules_code = get_optional_param('rules_code');
		$lod = (int)get_optional_param('lod', 0);
		$count_only = isset($_REQUEST['count']);
		$page = (int)get_optional_param('page', 0);
		$page_size = (int)get_optional_param('page_size', DEFAULT_PAGE_SIZE);
		
		$condition = new SQL('');
		if (!empty($started_before))
		{
			$condition->add(' AND g.start_time < ?', get_datetime($started_before, $_profile->timezone)->getTimestamp());
		}

		if (!empty($ended_before))
		{
			$condition->add(' AND g.end_time < ?', get_datetime($ended_before, $_profile->timezone)->getTimestamp());
		}

		if (!empty($started_after))
		{
			$condition->add(' AND g.start_time >= ?', get_datetime($started_after, $_profile->timezone)->getTimestamp());
		}

		if (!empty($ended_after))
		{
			$condition->add(' AND g.end_time >= ?', get_datetime($ended_after, $_profile->timezone)->getTimestamp());
		}

		if ($game_id > 0)
		{
			$condition->add(' AND g.id = ?', $game_id);
		}
		
		if ($club_id > 0)
		{
			$condition->add(' AND g.club_id = ?', $club_id);
		}

		if ($league_id == 0)
		{
			$condition->add(' AND g.event_id IN (SELECT _e.id FROM events _e LEFT OUTER JOIN tournaments _t ON _t.id = _e.tournament_id WHERE _t.id IS NULL OR _t.league_id IS NULL)');
		}
		else if ($league_id > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT _e.id FROM events _e JOIN tournaments _t ON _t.id = _e.tournament_id WHERE _t.league_id = ?)', $league_id);
		}

		if ($event_id > 0)
		{
			$condition->add(' AND g.event_id = ?', $event_id);
		}

		if ($tournament_id == 0)
		{
			$condition->add(' AND g.event_id IN (SELECT id FROM events WHERE tournament_id IS NULL)');
		}
		else if ($tournament_id > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT id FROM events WHERE tournament_id = ?)', $tournament_id);
		}
		
		if ($address_id > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT id FROM events WHERE address_id = ?)', $address_id);
		}
		
		if ($city_id > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id WHERE a1.city_id = ?)', $city_id);
		}
		
		if ($area_id > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.area_id = (SELECT area_id FROM cities WHERE id = ?))', $area_id);
		}
		
		if ($country_id > 0)
		{
			$condition->add(' AND g.event_id IN (SELECT e1.id FROM events e1 JOIN addresses a1 ON e1.address_id = a1.id JOIN cities c1 ON a1.city_id = c1.id WHERE c1.country_id = ?)', $country_id);
		}
		
		if ($langs != 0)
		{
			$condition->add(' AND (g.language & ?) <> 0', $langs);
		}
		
		$condition->add(get_games_filter_condition($games_filter));
		
		if (!empty($rules_code))
		{
			$condition->add(' AND g.rules = ?', $rules_code);
		}
		
		$condition->add(' ORDER BY g.start_time DESC, g.id DESC');
		
		if ($user_id > 0)
		{
			$count_query = new DbQuery('SELECT count(*) FROM players p JOIN games g ON p.game_id = g.id WHERE g.result > 0 AND p.user_id = ?', $user_id, $condition);
			$query = new DbQuery('SELECT g.id, g.json, g.feature_flags, c.timezone FROM players p JOIN games g ON p.game_id = g.id JOIN events e ON g.event_id = e.id JOIN addresses a ON e.address_id = a.id JOIN cities c ON a.city_id = c.id WHERE g.canceled = FALSE AND g.result > 0 AND p.user_id = ?', $user_id, $condition);
		}
		else
		{
			$count_query = new DbQuery('SELECT count(*) FROM games g WHERE g.canceled = FALSE AND g.result > 0', $condition);
			$query = new DbQuery('SELECT g.id, g.json, g.feature_flags, c.timezone FROM games g JOIN events e ON g.event_id = e.id JOIN addresses a ON e.address_id = a.id JOIN cities c ON a.city_id = c.id WHERE g.result > 0', $condition);
		}
		
		list ($count) = $count_query->record('game');
		$this->response['count'] = (int)$count;
		if ($count_only)
		{
			return;
		}
		
		if ($page_size > 0)
		{
			$query->add(' LIMIT ' . ($page * $page_size) . ',' . $page_size);
		}
			
		$games = array();
		$this->show_query($query);
		while ($row = $query->next())
		{
			list ($id, $json, $feature_flags, $timezone) = $row;
			$game = new Game($json, $feature_flags);
			
			date_default_timezone_set($timezone);
			$game->data->startTime = date("Y-m-d\TH:i:sO", $game->data->startTime);
			$game->data->endTime = date("Y-m-d\TH:i:sO", $game->data->endTime);
			$game->data->timezone = $timezone;
			$game->data->features = Game::feature_flags_to_leters($game->flags);
			//$game->data->rules = rules_code_to_object($game->data->rules);
			$games[] = $game->data;
		}
		$this->response['games'] = $games;
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('started_before', 'Unix timestamp, or datetime, or <q>now</q>. Returns games that are started before a certain time. For example: <a href="games.php?started_before=2017-01-01%2000:00">' . PRODUCT_URL . '/api/get/games.php?started_before=2017-01-01%2000:00</a> returns all games started before January 1, 2017. Logged user timezone is used for converting dates.', '-');
		$help->request_param('ended_before', 'Unix timestamp, or datetime, or <q>now</q>. Returns games that are ended before a certain time. For example: <a href="games.php?ended_before=1483228800">' . PRODUCT_URL . '/api/get/games.php?ended_before=1483228800</a> returns all games ended before January 1, 2017; <a href="games.php?ended_before=now">' . PRODUCT_URL . '/api/get/games.php?ended_before=now</a> returns all games that are already ended. Logged user timezone is used for converting dates.', '-');
		$help->request_param('started_after', 'Unix timestamp, or datetime, or <q>now</q>. Returns games that are started after a certain time. For example: <a href="games.php?started_after=2017-01-01%2000:00">' . PRODUCT_URL . '/api/get/games.php?started_after=2017-01-01%2000:00</a> returns all games started after January 1, 2017. Logged user timezone is used for converting dates.', '-');
		$help->request_param('ended_after', 'Unix timestamp, or datetime, or <q>now</q>. Returns games that are ended after a certain time. For example: <a href="games.php?ended_after=1483228800">' . PRODUCT_URL . '/api/get/games.php?ended_after=1483228800</a> returns all games ended after January 1, 2017; <a href="games.php?started_before=now&ended_after=now">' . PRODUCT_URL . '/api/get/games.php?started_before=now&ended_after=now</a> returns all games that happening now. Logged user timezone is used for converting dates.', '-');
		$help->request_param('game_id', 'Game id. For example: <a href="games.php?game_id=1299"><?php echo PRODUCT_URL; ?>/api/get/games.php?game_id=1299</a> returns only one game played in VaWaCa-2017 tournament.', '-');
		$help->request_param('club_id', 'Club id. For example: <a href="games.php?club_id=1"><?php echo PRODUCT_URL; ?>/api/get/games.php?club_id=1</a> returns all games for Vancouver Mafia Club.', '-');
		$help->request_param('league_id', 'League id. For example: <a href="games.php?league_id=2"><?php echo PRODUCT_URL; ?>/api/get/games.php?league_id=2</a> returns all games played in American Mafia League. <a href="games.php?league_id=0"><?php echo PRODUCT_URL; ?>/api/get/games.php?league_id=0</a> returns all games that were played outside of any league.', '-');
		$help->request_param('event_id', 'Event id. For example: <a href="games.php?event_id=7927"><?php echo PRODUCT_URL; ?>/api/get/games.php?event_id=7927</a> returns all games for VaWaCa-2017 tournament.', '-');
		$help->request_param('tournament_id', 'Tournament id. For example: <a href="games.php?tournament_id=1"><?php echo PRODUCT_URL; ?>/api/get/games.php?tournament_id=1</a> returns all games for VaWaCa-2017 tournament. <a href="games.php?tournament_id=0"><?php echo PRODUCT_URL; ?>/api/get/games.php?tournament_id=0</a> returns all non tournament games.', '-');
		$help->request_param('address_id', 'Address id. For example: <a href="games.php?address_id=10"><?php echo PRODUCT_URL; ?>/api/get/games.php?address_id=10</a> returns all games played in Tafs Cafe by Vancouver Mafia Club.', '-');
		$help->request_param('city_id', 'City id. For example: <a href="games.php?city_id=49"><?php echo PRODUCT_URL; ?>/api/get/games.php?city_id=49</a> returns all games played in Seattle. List of the cities and their ids can be obtained using <a href="cities.php?help"><?php echo PRODUCT_URL; ?>/api/get/cities.php</a>.', '-');
		$help->request_param('area_id', 'City id. The difference with city is that when area_id is set, the games from all nearby cities are also returned. For example: <a href="games.php?area_id=1"><?php echo PRODUCT_URL; ?>/api/get/games.php?area_id=1</a> returns all games played in Vancouver and nearby cities. Though <a href="games.php?city_id=1"><?php echo PRODUCT_URL; ?>/api/get/games.php?city_id=1</a> returns only the games played in Vancouver itself.', '-');
		$help->request_param('country_id', 'Country id. For example: <a href="games.php?country_id=2"><?php echo PRODUCT_URL; ?>/api/get/games.php?country_id=2</a> returns all games played in Russia. List of the countries and their ids can be obtained using <a href="countries.php?help"><?php echo PRODUCT_URL; ?>/api/get/countries.php</a>.', '-');
		$help->request_param('rules_code', 'Rules code. For example: <a href="games.php?rules_code=00000000100101010200000000000">' . PRODUCT_URL . '/api/get/games.php?rules_code=00000000100101010200000000000</a> returns all games played by the rules with the code 00000000100101010200000000000 was used. Please check <a href="rules.php?help">' . PRODUCT_URL . '/api/get/rules.php?help</a> for the meaning of rules codes and getting rules list.', '-');
		$help->request_param('user_id', 'User id. For example: <a href="games.php?user_id=25"><?php echo PRODUCT_URL; ?>/api/get/games.php?user_id=25</a> returns all games where Fantomas played. If missing, all games for all users are returned.', '-');
		$help->request_param('langs', 'Languages filter. 1 for English; 2 for Russian. Bit combination - 3 - means both (this is a default value). For example: <a href="games.php?langs=1"><?php echo PRODUCT_URL; ?>/api/get/games.php?langs=1</a> returns all games played in English; <a href="games.php?club=1&langs=3"><?php echo PRODUCT_URL; ?>/api/get/games.php?club=1&langs=3</a> returns all English and Russian games of Vancouver Mafia Club', '-');
		$help->request_param('games_filter', 'Game importance filter. A bit flag of: 1 - include tournament games; 2 - include rating games; 4 - include non-rating games. For example: <a href="games.php?games_filter=3"><?php echo PRODUCT_URL; ?>/api/get/games.php?games_filter=3</a> excludes all non-reting games from the list', '-');
		$help->request_param('count', 'Returns game count but does not return the games. For example: <a href="games.php?user_id=25&count"><?php echo PRODUCT_URL; ?>/api/get/games.php?user_id=25&count</a> returns how many games Fantomas have played; <a href="games.php?event=7927&count"><?php echo PRODUCT_URL; ?>/api/get/games.php?event=7927&count</a> returns how many games were played in VaWaCa-2017 tournament.', '-');
		$help->request_param('page', 'Page number. For example: <a href="games.php?club=1&page=1"><?php echo PRODUCT_URL; ?>/api/get/games.php?club=1&page=1</a> returns the second page for Vancouver Mafia Club.', '-');
		$help->request_param('page_size', 'Page size. Default page_size is ' . DEFAULT_PAGE_SIZE . '. For example: <a href="games.php?club=1&page_size=32"><?php echo PRODUCT_URL; ?>/api/get/games.php?club=1&page_size=32</a> returns last 32 games for Vancouver Mafia Club; <a href="games.php?club=6&page_size=0"><?php echo PRODUCT_URL; ?>/api/get/games.php?club=6&page_size=0</a> returns all games for Empire of Mafia club in one page; <a href="games.php?club=1"><?php echo PRODUCT_URL; ?>/api/get/games.php?club=1</a> returns last ' . DEFAULT_PAGE_SIZE . ' games for Vancouver Mafia Club;', '-');

		$param = $help->response_param('games', 'The array of games. Games are always sorted from latest to oldest. There is no way to change sorting order in the current version of the API.');
		Game::api_help($param);
		$help->response_param('count', 'The total number of games sutisfying the request parameters. It is set only when the parameter <i>count</i> is set.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Games', CURRENT_VERSION);

?>