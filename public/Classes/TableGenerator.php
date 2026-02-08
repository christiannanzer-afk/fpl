<?php

namespace FPL;

class TableGenerator
{
    private Config $config;
    private DataLoader $dataLoader;
    private array $params;

    public function __construct(Config $config, DataLoader $dataLoader, array $params)
    {
        $this->config = $config;
        $this->dataLoader = $dataLoader;
        $this->params = $params;
    }

    public function generate(): string
    {
        // Berechnungen
        $gwCalc = new GameweekCalculator($this->dataLoader->getEvents());
        $currentGw = $gwCalc->getCurrentGameweek();
        $nextGW = $gwCalc->getNextGameweek();

        $leagueCalc = new LeagueTableCalculator($this->dataLoader->getTeams(), $this->dataLoader->getFixtures());

        // Team-Namen und Codes
        $teamNames = [];
        $teamCodes = [];
        foreach ($this->dataLoader->getTeams() as $team) {
            $teamNames[$team['id']] = $team['short_name'];
            $teamCodes[$team['id']] = $team['code'];
        }

        // Fixtures verarbeiten
        $fixtureProcessor = new FixtureProcessor(
            $this->dataLoader->getFixtures(),
            $teamNames,
            $nextGW
        );

        // Recent Results für alle Teams
        $recentResults = [];
        for ($teamId = 1; $teamId <= count($this->dataLoader->getTeams()); $teamId++) {
            $recentResults[$teamId] = $fixtureProcessor->getRecentResults($teamId);
        }

        // Next Fixtures für alle Teams
        $nextFixtures = [];
        for ($teamId = 1; $teamId <= count($this->dataLoader->getTeams()); $teamId++) {
            $nextFixtures[$teamId] = $fixtureProcessor->getNextFixtures(
                $teamId,
                $teamCodes,
                $this->params['fixtureCount']
            );
        }

        // Position Map für Farb-Berechnung
        $positionMap = [];
        $teamShortNames = [];
        foreach ($this->dataLoader->getTeams() as $team) {
            $positionMap[$team['id']] = $team['position'] ?? 0;
            $teamShortNames[$team['id']] = $team['short_name'];
        }
        $idByShort = array_flip($teamShortNames);

        // Color Calculator
        $colorCalc = new FixtureColorCalculator(
            $this->config,
            $leagueCalc->getPointsMap(),
            $positionMap,
            $idByShort
        );

        // Filter & Sort
        $filter = new PlayerFilter(
            $this->params['minPoints'],
            $this->params['filterTeam'],
            $this->params['filterPosition'],
            $this->params['filterWatchlist'],
            $this->params['filterDreamTeam'],
            $this->params['filterName'],
            $this->dataLoader->getWatchlist(),
            $this->dataLoader->getDreamTeam()
        );
        $filteredPlayers = $filter->filter($this->dataLoader->getPlayers());

        $sorter = new PlayerSorter(
            $this->params['sortBy'],
            $this->params['sortDir'],
            $teamNames,
            $this->config->get('positions'),
            $this->params['filterDreamTeam'],
            $this->dataLoader->getDreamTeam()
        );

        $sorter->sort($filteredPlayers);

        // Limit
        if ($this->params['limit'] > 0) {
            $filteredPlayers = array_slice($filteredPlayers, 0, $this->params['limit']);
        }

        // Histories für gefilterte Spieler
        $histories = [];
        $allHistories = $this->dataLoader->getHistories();
        foreach ($filteredPlayers as $player) {
            $histories[$player['id']] = $allHistories[$player['id']] ?? [];
        }

        // Update params mit aktuellen GWs
        $this->params['currentGw'] = $currentGw;
        $this->params['nextGW'] = $nextGW;

        // Thead Generator
        $theadGen = new TheadGenerator($this->config, $this->params);
        $thead = $theadGen->generate($nextFixtures);

        // Tbody Generator
       $tbodyGen = new TbodyGenerator(
            $this->config,
            $this->params,
            $teamNames,
            $teamCodes,
            $nextFixtures,
            $recentResults,
            $leagueCalc->getTable(),
            $leagueCalc->getRanks(),
            $histories,
            $this->dataLoader->getWatchlist(),
            $this->dataLoader->getDreamTeam(),
            $leagueCalc->getPointsMap(),
            $colorCalc
        );

        $tbody = $tbodyGen->generate($filteredPlayers);

        return '<table id="player-table" class="table table">' . $thead . $tbody . '</table>';
    }
}