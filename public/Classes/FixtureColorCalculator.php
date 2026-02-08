<?php

namespace FPL;

class FixtureColorCalculator
{
    private Config $config;
    private array $pointsMap;
    private array $positionMap;
    private array $idByShort;
    private array $colors;
    private int $maxAbsDiff;

    public function __construct(Config $config, array $pointsMap, array $positionMap, array $idByShort)
    {
        $this->config = $config;
        $this->pointsMap = $pointsMap;
        $this->positionMap = $positionMap;
        $this->idByShort = $idByShort;
        
        $this->calculateMaxDiff();
        $this->generateColorScale();
    }

    private function calculateMaxDiff(): void
    {
        $maxPoints = max($this->pointsMap);
        $minPoints = min($this->pointsMap);
        $realMaxDiff = $maxPoints - $minPoints;
        
        $this->maxAbsDiff = $realMaxDiff > 0 ? $realMaxDiff : 30;
    }

    private function generateColorScale(): void
    {
        $stepsPerSide = $this->config->get('color_steps_per_side');
        $colorConfig = $this->config->get('colors');
        
        $greenRgb = $colorConfig['green'];
        $grayRgb = $colorConfig['gray'];
        $redRgb = $colorConfig['red'];
        
        $this->colors = [];
        
        // Rot → Grau
        for ($i = 0; $i <= $stepsPerSide; $i++) {
            $f = $i / $stepsPerSide;
            $this->colors[$i] = $this->rgbToHex($this->interpolateColor($redRgb, $grayRgb, $f));
        }
        
        // Grau → Grün
        for ($i = 0; $i <= $stepsPerSide; $i++) {
            $f = $i / $stepsPerSide;
            $this->colors[$stepsPerSide + 1 + $i] = $this->rgbToHex($this->interpolateColor($grayRgb, $greenRgb, $f));
        }
        
        // Mitte exakt Grau
        $this->colors[$stepsPerSide] = $this->rgbToHex($grayRgb);
    }

    private function interpolateColor(array $c1, array $c2, float $f): array
    {
        return [
            'r' => round($c1['r'] + $f * ($c2['r'] - $c1['r'])),
            'g' => round($c1['g'] + $f * ($c2['g'] - $c1['g'])),
            'b' => round($c1['b'] + $f * ($c2['b'] - $c1['b']))
        ];
    }

    private function rgbToHex(array $rgb): string
    {
        return sprintf("#%02x%02x%02x", $rgb['r'], $rgb['g'], $rgb['b']);
    }

    public function getFixtureInfo(string $fixtureText, int $teamId): array
    {
        if ($fixtureText === 'BYE') {
            return ['color' => '#FFFFFF', 'diff' => '', 'win_prob' => ''];
        }

        if (!preg_match('/^([A-Z]+) \((H|A)\)$/', $fixtureText, $m)) {
            return ['color' => '#FFFFFF', 'diff' => '', 'win_prob' => ''];
        }

        $oppShort = $m[1];
        $isHome = $m[2] === 'H';
        $oppId = $this->idByShort[$oppShort] ?? null;

        if (!$oppId || !isset($this->pointsMap[$teamId]) || !isset($this->pointsMap[$oppId])) {
            return ['color' => '#c8c8c8', 'diff' => 0, 'win_prob' => '50%'];
        }

        $adjustment = $this->config->get('home_advantage');
        
        // Angepasste Punkte mit Heimvorteil
        $ownAdj = $this->pointsMap[$teamId] + ($isHome ? $adjustment : -$adjustment);
        $oppAdj = $this->pointsMap[$oppId] + ($isHome ? -$adjustment : $adjustment);

        // Differenz
        $diff = $ownAdj - $oppAdj;

        // Skalierung
        $stepsPerSide = $this->config->get('color_steps_per_side');
        $norm = $diff / $this->maxAbsDiff;
        $index = round(($norm + 1) / 2 * ($stepsPerSide * 2));
        $index = max(0, min(100, $index));

        $color = $this->colors[$index];

        // Win Probability
        $wpRaw = 50 + ($diff / $this->maxAbsDiff) * 45;
        $winProb = round(max(5, min(95, $wpRaw))) . '%';

        return [
            'color' => $color,
            'diff' => round($diff, 1),
            'win_prob' => $winProb
        ];
    }
}