<?php

namespace FPL;

class TeamBuilderConfig
{
    private string $bootstrapFile;
    private string $dreamTeamsDir;
    private int $defaultGw;
    private array $positionMap;
    private int $maxSquadSize = 15;
    private int $maxBudget = 1000; // 100.0 Millionen
    
    public function __construct()
    {
        $this->bootstrapFile = __DIR__ . '/../data/bootstrap-static.json';
        $this->dreamTeamsDir = __DIR__ . '/../dream_teams';
        $this->defaultGw = 12;
        $this->positionMap = [
            1 => 'GK',
            2 => 'DEF',
            3 => 'MID',
            4 => 'FWD'
        ];
    }
    
    public function getBootstrapFile(): string
    {
        return $this->bootstrapFile;
    }
    
    public function getDreamTeamsDir(): string
    {
        return $this->dreamTeamsDir;
    }
    
    public function getDefaultGw(): int
    {
        return $this->defaultGw;
    }
    
    public function getPositionMap(): array
    {
        return $this->positionMap;
    }
    
    public function getMaxSquadSize(): int
    {
        return $this->maxSquadSize;
    }
    
    public function getMaxBudget(): int
    {
        return $this->maxBudget;
    }
    
    public function ensureDreamTeamsDir(): void
    {
        if (!is_dir($this->dreamTeamsDir)) {
            mkdir($this->dreamTeamsDir, 0777, true);
        }
    }
}
