<?php

namespace TournamentGenerator;

/**
 *
 */
class Group
{

	private $generator = null;
	private $teams = []; // ARRAY OF TEAMS
	private $progressed = []; // ARRAY OF TEAMS ALREADY PROGRESSED FROM THIS GROUP
	public $name = ''; // DISPLAYABLE NAME
	private $ordering = \POINTS; // WHAT TO DECIDE ON WHEN ORDERING TEAMS
	private $progressions = []; // ARRAY OF PROGRESSION CONDITION OBJECTS
	private $games = []; // ARRAY OF GAME OBJECTS
	public $id = ''; // UNIQID OF GROUP FOR IDENTIFICATIONT
	public $winPoints = 3; // POINTS AQUIRED FROM WINNING
	public $drawPoints = 1; // POINTS AQUIRED FROM DRAW
	public $lostPoints = 0; // POINTS AQUIRED FROM LOOSING
	public $secondPoints = 2; // POINTS AQUIRED FROM BEING SECOND (APPLIES ONLY FOR 3 OR 4 INGAME VALUE)
	public $thirdPoints = 1; // POINTS AQUIRED FROM BEING THIRD (APPLIES ONLY FOR 4 INGAME VALUE)
	public $progressPoints = 50; // POINTS AQUIRED FROM PROGRESSING TO THE NEXT ROUND
	public $order = 0; // ORDER OF GROUPS IN ROUND

	function __construct(array $settings = []) {
		$this->id = uniqid();
		$this->generator = new Utilis\Generator($this);
		foreach ($settings as $key => $value) {
			switch ($key) {
				case 'name':
					$this->name = (string) $value;
					break;
				case 'type':
					$this->generator->setType($value);
					break;
				case 'ordering':
					$this->setOrdering($value);
					break;
				case 'inGame':
					$this->generator->setInGame((int) $value);
					break;
				case 'maxSize':
					$this->generator->setMaxSize((int) $value);
					break;
				case 'order':
					$this->order = (int) $value;
					break;
			}
		}
	}
	public function __toString() {
		return 'Group '.$this->name;
	}

	public function allowSkip(){
		$this->generator->allowSkip();
		return $this;
	}
	public function disallowSkip(){
		$this->generator->disallowSkip();
		return $this;
	}
	public function setSkip(bool $skip) {
		$this->generator->setSkip($skip);
		return $this;
	}
	public function getSkip() {
		return $this->generator->getSkip();
	}

	public function addTeam(...$teams) {
		foreach ($teams as $team) {
			if (gettype($team) === 'array') {
				foreach ($team as $team2) {
					$this->setTeam($team2);
				}
				continue;
			}
			$this->setTeam($team);
		}
		return $this;
	}
	private function setTeam(Team $team) {
		$this->teams[] = $team;
		$team->groupResults[$this->id] = [
			'group' => $this,
			'points' => 0,
			'score'  => 0,
			'wins'   => 0,
			'draws'  => 0,
			'losses' => 0,
			'second' => 0,
			'third'  => 0
		];
		return $this;
	}
	public function getTeams($filters = []) {
		$teams = $this->teams;

		if (gettype($filters) !== 'array' && $filters instanceof TeamFilter) $filters = [$filters];
		elseif (gettype($filters) !== 'array') $filters = [];

		// APPLY FILTERS
		$filter = new Filter($this, $filters);
		$filter->filter($teams);

		return $teams;
	}

	public function team(string $name = '') {
		$t = new Team($name);
		$this->teams[] = $t;
		$t->groupResults[$this->id] = [
			'group' => $this,
			'points' => 0,
			'score'  => 0,
			'wins'   => 0,
			'draws'  => 0,
			'losses' => 0,
			'second' => 0,
			'third'  => 0
		];
		return $t;
	}
	public function sortTeams($filters = [], $ordering = null) {
		if (!isset($ordering)) $ordering = $this->ordering;
		Utilis\Sorter\Teams::sortGroup($this->teams, $this, $ordering);
		return $this->getTeams($filters);
	}

	public function setType(string $type = \R_R) {
		$this->generator->setType($type);
		return $this;
	}
	public function getType() {
		return $this->generator->getType();
	}

	public function setOrdering(string $ordering = \POINTS) {
		if (!in_array($ordering, orderingTypes)) throw new \Exception('Unknown group ordering: '.$ordering);
		$this->ordering = $ordering;
		return $this;
	}
	public function getOrdering() {
		return $this->ordering;
	}

	public function setInGame(int $inGame) {
		$this->generator->setInGame($inGame);
		return $this;
	}
	public function getInGame() {
		return $this->generator->getInGame();
	}

	public function addProgression(Progression $progression) {
		$this->progressions[] = $progression;
		return $this;
	}
	public function progression(Group $to, int $start = 0, int $len = null) {
		$p = new Progression($this, $to, $start, $len);
		$this->progressions[] = $p;
		return $p;
	}
	public function progress(bool $blank = false) {
		foreach ($this->progressions as $progression) {
			$progression->progress($blank);
		}
	}
	public function addProgressed(...$teams) {
		foreach ($teams as $team) {
			if ($team instanceOf Team) $this->progressed[] = $team->id;
			elseif (gettype($team) === 'array') {
				$this->progressed = array_merge($this->progressed, array_filter($team, function($a) {
					return ($a instanceof Team);
				}));
			}
		}
		return $this;
	}
	public function isProgressed(Team $team) {
		return in_array($team->id, $this->progressed);
	}

	public function genGames() {
		$this->generator->genGames();
		return $this->games;
	}

	public function game(array $teams = []) {
		$g = new Game($teams, $this);
		$this->games[] = $g;
		return $g;
	}
	public function addGame(...$games){
		foreach ($games as $key => $game) {
			if (gettype($game) === 'array') {
				unset($games[$key]);
				$games = array_merge($games, array_filter($game, function($a){ return ($a instanceof Game); }));
				continue;
			}
			if (!$game instanceof Game) throw new \Exception('Trying to add game which is not instance of Game object.');
			$this->games[] = $game;
		}
		return $this;
	}
	public function getGames() {
		return $this->games;
	}
	public function orderGames() {
		if (count($this->games) <= 4) return $this->games;
		$this->games = $this->generator->orderGames();
		return $this->games;
	}

	public function simulate(array $filters = [], bool $reset = true) {
		return Utilis\Simulator::simulateGroup($this, $filters, $reset);
	}
	public function resetGames() {
		foreach ($this->getGames() as $game) {
			$game->resetResults();
		}
		return $this;
	}
	public function isPlayed(){
		foreach ($this->games as $game) {
			if (!$game->isPlayed()) return false;
		}
		return true;
	}

}
