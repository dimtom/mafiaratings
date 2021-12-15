<?php

require_once '../../include/api.php';
require_once '../../include/game.php';
require_once '../../include/datetime.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	protected function prepare_response()
	{
		global $_profile;
		
		$token = (int)get_optional_param('token', 0);
		$game_id = (int)get_optional_param('game_id', 0);
		$user_id = (int)get_optional_param('user_id', 0);
		$moderator_id = (int)get_optional_param('moderator_id', 0);
		
		$query = new DbQuery('SELECT g.id, g.log, e.security_token, t.security_token FROM games g JOIN events e ON e.id = g.event_id LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id WHERE g.result = 0');
		if ($game_id > 0)
		{
			$query->add(' AND g.id = ?', $game_id);
		}
		if ($moderator_id  > 0)
		{
			$query->add(' AND g.moderator_id = ?', $moderator_id);
		}
		if ($user_id  > 0)
		{
			$query->add(' AND g.user_id = ?', $user_id);
		}
		$query->add(' ORDER BY id');
		
		$gs = NULL;
		while ($row = $query->next())
		{
			list($game_id, $log, $event_token, $tournament_token) = $row;
			if ((!is_null($event_token) && $event_token == $token) || (!is_null($tournament_token) && $tournament_token == $token))
			{
				$gs = json_decode($log);
				break;
			}
		}
		
		if ($gs != NULL)
		{
			$game = new stdClass();
			if (isset($gs->id))
			{
				$game->id = $gs->id;
				$game->name = get_label('Game #[0]', $gs->id);
			}
			switch ($gs->gamestate)
			{
				case /*GAME_STATE_NOT_STARTED*/0:
				case /*GAME_STATE_VOTING*/8:
				case /*GAME_STATE_VOTING_MULTIPLE_WINNERS*/9:
					$speachPossible = false;
					break;
				default:
					$speachPossible  = true;
					break;
			}
			if (isset($gs->players))
			{
				$user_pic = new Picture(USER_PICTURE);
				$game->players = array();
				foreach ($gs->players as $p)
				{
					$player = new stdClass();
					$player->id = (int)$p->id;
					$player->name = $p->nick;
					$player->number = $p->number + 1;
					$player->isSpeaking = ($speachPossible && $p->number == $gs->player_speaking);
					
					if ($player->id > 0)
					{
						list($player_flags) = Db::record(get_label('user'), 'SELECT flags FROM users WHERE id = ?', $player->id);
						if ($p->is_male)
						{
							$player->gender = 'male';
						}
						else
						{
							$player->gender = 'female';
						}
					}
					else
					{
						$player_flags = 0;
					}
					$user_pic->set($player->id, $player->name, $player_flags);
					$player->photoUrl = get_server_url() . '/' . $user_pic->url(TNAILS_DIR);
					$player->hasPhoto = $user_pic->hasImage();
					
					
					switch ($p->role)
					{
						case 0:
							$player->role = 'town';
							break;
						case 1:
							$player->role = 'sheriff';
							break;
						case 2:
							$player->role = 'maf';
							break;
						case 3:
							$player->role = 'don';
							break;
					}
					$player->warnings = $p->warnings;
					if ($p->don_check >= 0)
					{
						$player->checkedByDon = $p->don_check + 1;
					}
					if ($p->sheriff_check >= 0)
					{
						$player->checkedBySheriff = $p->sheriff_check + 1;
					}
					
					if ($p->state == 0 /*PLAYER_STATE_ALIVE*/)
					{
						$player->state = 'alive';
					}
					else
					{
						$player->state = 'dead';
						$player->deathRound = $p->kill_round;
						switch ($p->kill_reason)
						{
							case 1 /*KILL_REASON_GIVE_UP*/:
								$player->deathType = 'giveUp';
								break;
							case 2 /*KILL_REASON_WARNINGS*/:
								$player->deathType = 'warnings';
								break;
							case 3 /*KILL_REASON_KICK_OUT*/:
								$player->deathType = 'kickOut';
								break;
							case 0 /*KILL_REASON_NORMAL*/:
							default:
								if ($p->state == 1 /*PLAYER_STATE_KILLED_NIGHT*/)
								{
									$player->deathType = 'shooting';
								}
								else
								{
									$player->death->type = 'voting';
								}
								break;
						}
					}
					
					// $player->photoUrl = get_server_url() . '/images/' . TNAILS_DIR . 'user.png';
					$game->players[] = $player;
				}
				
				$game->moderator = new stdClass();
				$game->moderator->id = (int)$gs->moder_id;
				if ($gs->moder_id > 0)
				{
					list($game->moderator->name, $moderator_flags) = Db::record(get_label('user'), 'SELECT name, flags FROM users WHERE id = ?', $game->moderator->id);
					if ($moderator_flags & USER_FLAG_MALE)
					{
						$game->moderator->gender = 'male';
					}
					else
					{
						$game->moderator->gender = 'female';
					}
				}
				else
				{
					$game->moderator->name = '';
					$moderator_flags = 0;
				}
				$user_pic->set($game->moderator->id, $game->moderator->name, $moderator_flags);
				$game->moderator->photoUrl = get_server_url() . '/' . $user_pic->url(TNAILS_DIR);
				$game->moderator->hasPhoto = $user_pic->hasImage();
				
				switch ($gs->gamestate)
				{
					case /*GAME_STATE_NOT_STARTED*/0:
						$game->phase = 'night';
						$game->state = 'notStarted';
						$game->round = 0;
						break;
					case /*GAME_STATE_NIGHT0_START*/1:
						$game->phase = 'night';
						$game->state = 'starting';
						$game->round = 0;
						break;
					case /*GAME_STATE_NIGHT0_ARRANGE*/2:
						$game->phase = 'night';
						$game->state = 'arranging';
						$game->round = 0;
						break;
					case /*GAME_STATE_DAY_START*/3:
						$game->phase = 'day';
						$game->state = 'starting';
						$game->round = $gs->round;
						break;
					case /*GAME_STATE_DAY_PLAYER_SPEAKING*/5:
						$game->phase = 'day';
						$game->state = 'speaking';
						$game->round = $gs->round;
						break;
					case /*GAME_STATE_VOTING_KILLED_SPEAKING*/7:
						$game->phase = 'day';
						$game->state = 'nightKillSpeaking';
						$game->round = $gs->round;
						break;
					case /*GAME_STATE_VOTING*/8:
					case /*GAME_STATE_VOTING_MULTIPLE_WINNERS*/9:
						$game->phase = 'day';
						$game->state = 'voting';
						$game->round = $gs->round;
						break;
					case /*GAME_STATE_VOTING_NOMINANT_SPEAKING*/10:
						$game->phase = 'day';
						$game->state = 'nomineeSpeaking';
						$game->round = $gs->round;
						break;
					case /*GAME_STATE_NIGHT_START*/11:
						$game->phase = 'night';
						$game->state = 'starting';
						$game->round = $gs->round;
						break;
					case /*GAME_STATE_NIGHT_SHOOTING*/12:
						$game->phase = 'night';
						$game->state = 'shooting';
						$game->round = $gs->round;
						break;
					case /*GAME_STATE_NIGHT_DON_CHECK*/13:
						$game->phase = 'night';
						$game->state = 'donChecking';
						$game->round = $gs->round;
						break;
					case /*GAME_STATE_NIGHT_SHERIFF_CHECK*/15:
						$game->phase = 'night';
						$game->state = 'sheriffChecking';
						$game->round = $gs->round;
						break;
					case /*GAME_STATE_MAFIA_WON*/17:
						$game->phase = 'day';
						$game->state = 'mafiaWon';
						$game->round = $gs->round;
						break;
					case /*GAME_STATE_CIVIL_WON*/18:
						$game->phase = 'day';
						$game->state = 'townWon';
						$game->round = $gs->round;
						break;
					default:
						$game->phase = 'day';
						$game->state = 'unknown';
						$game->round = $gs->round;
						break;
				}
				
				if ($game->phase != 'night')
				{
					$voting = NULL;
					foreach ($gs->votings as $v)
					{
						if ($v->round == $gs->round && $v->voting_round == 0)
						{
							$voting = $v;
							break;
						}
					}
					if ($voting != NULL)
					{
						$game->votingCanceled = ($voting->canceled != 0);
						$nominees = array();
						foreach ($voting->nominants as $n)
						{
							$nominees[] = $n->player_num + 1;
						}
						if ($gs->gamestate == 5 /*GAME_DAY_PLAYER_SPEAKING*/ && $gs->current_nominant >= 0)
						{
							$nominees[] = $gs->current_nominant + 1;
						}
						$game->nominees = $nominees;
					}
				}
			}
			$this->response['game'] = $game;
		}
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('token', 'Event or tournament security token. Token can be found by club manager by clicking on token button with this <img scr="../../images/obs.png" width="32" height="32"> for club\'s event/tournament.');
		$help->request_param('game_id', 'Return the specified game.', 'user_id or moderator_id must be set');
		$help->request_param('user_id', 'Return incomplete game for the specified user account.', 'game_id or moderator_id must be set');
		$help->request_param('moderator_id', 'Return incomplete game of the specified moderator.', 'user_id or game_id must be set');
		$param = $help->response_param('game', 'Game state.');
			$param->sub_param('id', 'Game id.');
			$param->sub_param('name', 'Game name.');
			$players = $param->sub_param('players', 'Players.');
				$players->sub_param('id', 'User id. If 0 or lower - the player is unknown.');
				$players->sub_param('name', 'Player nickname.');
				$players->sub_param('number', 'Number in the game.');
				$players->sub_param('photoUrl', 'A link to the user photo. If user is missing - a link to a transparent image.');
				$players->sub_param('hasPhoto', 'True - if a player has custom photo. False - when player did not upload photo, or when id<=0, which means there is no player.');
				$players->sub_param('gender', 'Either "mail" or "female".', 'the gender is unknown.');
				$players->sub_param('role', 'One of: "town", "sheriff", "maf", or "don".');
				$players->sub_param('warnings', 'Number of warnings.');
				$players->sub_param('isSpeaking', 'A boolean which is true when the player is speaking.');
				$players->sub_param('state', 'Player state - "dead" or "alive".');
				$players->sub_param('deathRound', 'If player state is "dead" it is set to the round number when they died.');
				$players->sub_param('deathType', 'If player state is "dead" it is set to the type of their death. One of: "voting", "shooting", "warnings", "giveUp", or "kickOut".');
				$players->sub_param('checkedByDon', 'If a player was checked by the don it contains the round number when it happened.');
				$players->sub_param('checkedBySheriff', 'If a player was checked by the sheriff it contains the round number when it happened.');
			$moderator = $param->sub_param('moderator', 'Moderator.');
				$moderator->sub_param('id', 'User id. If 0 or lower - the player is unknown.');
				$moderator->sub_param('name', 'Moderator nickname.');
				$moderator->sub_param('photoUrl', 'A link to the moderator photo.');
				$moderator->sub_param('hasPhoto', 'True - if a moderator has custom photo. False - when moderator did not upload photo, or when id<=0, which means there is no moderator yet.');
				$moderator->sub_param('gender', 'Either "mail" or "female".', 'the gender is unknown.');
 			$param->sub_param('phase', 'Current game phase - "day" or "night".');
 			$param->sub_param('state', 'Contains more detailed information about the game phase - which part of the day or night. One of:<ul><li>"notStarted" - when the game is not started yet.</li><li>"starting" - night before shooting, or day before any speaches.</li><li>"arranging" - mafia is arranging in night 0.</li><li>"speaking" - normal day speaches.</li><li>"nightKillSpeaking" - a player gives their last speach after being night-shooted.</li><li>"voting" - voting phase.</li><li>"nomineeSpeaking" - 30-sec speach after splitting the table.</li><li>"shooting" - mafia is shooting.</li><li>"donChecking" - don is checking.</li><li>"sheriffChecking" - sheriff is checking.</li><li>"mafiaWon" - game over mafia won.</li><li>"townWon" - game over town won.</li><li>"unknown" - something strange happening.</li></ul>');
 			$param->sub_param('round', 'Current round number. Game starts with night-0; then day-0; then night-1; day-1; etc.');
 			$param->sub_param('nominees', 'Array of players currently nominated. It is set only in the day phase.');
 			$param->sub_param('votingCanceled', 'Boolean which is true when votings were canceled (most likely because someone was mod-killed). It is set only in the day phase.');
				
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Currently Played Game', CURRENT_VERSION);

?>