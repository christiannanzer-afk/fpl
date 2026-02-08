<?php

namespace FPL;

class WatchlistGenerator
{
    private array $players;
    private array $teams;
    private string $outputFile;

    public function __construct(array $players, array $teams, string $outputFile)
    {
        $this->players = $players;
        $this->teams = $teams;
        $this->outputFile = $outputFile;
    }

    public function generate(): void
    {
        echo "Generiere Watchlist...\n";

        $positions = [1 => 'GK', 2 => 'DEF', 3 => 'MID', 4 => 'FWD'];

        // Team-Namen Mapping
        $teamMap = [];
        foreach ($this->teams as $team) {
            $teamMap[$team['id']] = $team['name'];
        }

        // Spieler gruppieren
        $playersByTeamAndPos = [];
        foreach ($this->players as $player) {
            $teamId = $player['team'] ?? 0;
            $pos = $player['element_type'] ?? 0;
            $minutes = $player['minutes'] ?? 0;

            if ($teamId === 0 || $pos === 0 || $minutes === 0) {
                continue;
            }

            $teamName = $teamMap[$teamId] ?? "Team $teamId";

            if (!isset($playersByTeamAndPos[$teamId])) {
                $playersByTeamAndPos[$teamId] = [
                    'name' => $teamName,
                    'byPos' => [1 => [], 2 => [], 3 => [], 4 => []]
                ];
            }

            $playersByTeamAndPos[$teamId]['byPos'][$pos][] = [
                'id' => $player['id'],
                'web_name' => $player['web_name'] ?? trim($player['first_name'] . ' ' . $player['second_name']),
                'points' => $player['total_points'] ?? 0,
                'cost' => number_format($player['now_cost'] / 10, 1),
                'minutes' => $minutes,
            ];
        }

        // Beste Spieler pro Team & Position auswählen
        $watchlist = [];
        foreach ($playersByTeamAndPos as $teamId => $data) {
            $teamName = $data['name'];
            $selected = [];

            foreach ([1, 2, 3, 4] as $pos) {
                $candidates = $data['byPos'][$pos] ?? [];

                if (empty($candidates)) {
                    $selected[] = null;
                    continue;
                }

                usort($candidates, fn($a, $b) => $b['points'] <=> $a['points']);
                $selected[] = $candidates[0];
            }

            if (count(array_filter($selected)) > 0) {
                $watchlist[$teamName] = $selected;
            }
        }

        // PHP-Datei schreiben
        $this->writePhpFile($watchlist, $positions);
    }

    private function writePhpFile(array $watchlist, array $positions): void
    {
        $content = "<?php\n\nreturn [\n\n";

        foreach ($watchlist as $teamName => $picks) {
            $content .= "    // $teamName\n";

            $posOrder = ['GK', 'DEF', 'MID', 'FWD'];
            $i = 0;
            foreach ($picks as $player) {
                if ($player === null) {
                    $content .= "    // Kein " . $posOrder[$i] . " verfügbar\n";
                } else {
                    $content .= "    {$player['id']},        // {$player['web_name']} ({$posOrder[$i]}, {$player['points']} pts, £{$player['cost']})\n";
                }
                $i++;
            }
            $content .= "\n";
        }

        $content .= "];\n";

        if (file_put_contents($this->outputFile, $content) !== false) {
            echo "Erfolgreich erstellt: $this->outputFile\n";
        } else {
            throw new \RuntimeException("Fehler beim Schreiben von $this->outputFile");
        }
    }
}