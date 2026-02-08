<?php

return [
    'manager_id' => 12206460,
    'default_history_count' => 3,
    'default_fixture_count' => 10,
    'default_min_points' => 10,
    'default_limit' => 50,
    'cache_time' => 3600,
    
    // Heimvorteil in Punkten
    'home_advantage' => 0,
    
    // Farbskala
    'color_steps_per_side' => 50,
    'colors' => [
        'green' => ['r' => 0, 'g' => 255, 'b' => 0],
        'gray' => ['r' => 185, 'g' => 190, 'b' => 195],
        'red' => ['r' => 255, 'g' => 0, 'b' => 0],
    ],
    
    // Dateipfade
    'paths' => [
        'data_dir' => __DIR__ . '/data/',
        'bootstrap' => __DIR__ . '/data/bootstrap-static.json',
        'fixtures' => __DIR__ . '/data/fixtures.json',
        'my_team' => __DIR__ . '/data/my-team.json',
        'all_histories' => __DIR__ . '/data/all_histories.json',
        'watchlist' => __DIR__ . '/watchlist.php',
        'dream_team' => __DIR__ . '/dream_team.json',
        'dream_teams_dir' => __DIR__ . '/dream_teams/',
        'badges_dir' => __DIR__ . '/badges/',
    ],
    
    // Position Mapping
    'positions' => [
        1 => 'GK',
        2 => 'DEF',
        3 => 'MID',
        4 => 'FWD',
    ],
    
    // Spalten-Definition
    'columns' => [
        'player' => 'Player',
        'club' => 'Club',
        'position' => 'Pos.',
        'price' => '£',
        'form' => 'F',
        'points_per_game' => 'P/G',
        'total_points' => 'P',
        'points_home' => 'H',
        'points_away' => 'A',
        'starts' => 'S',
        'minutes' => 'Min',
        'minutes_per_point' => 'M/P',
        'yellow_cards' => 'YC',
        'yellow_cards_total' => 'TYC',
        'red_cards' => 'RC',
        'goals_scored' => 'G',
        'assists' => 'A',
        'clean_sheets' => 'C',
        'selected_by_percent' => '%',
        'id' => 'ID',
    ],
];