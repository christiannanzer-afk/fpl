<?php

namespace FPL;

class FormGenerator
{
    private Config $config;
    private array $params;
    private array $teams;

    public function __construct(Config $config, array $params, array $teams)
    {
        $this->config = $config;
        $this->params = $params;
        $this->teams = $teams;
    }

    public function generate(): string
    {
        ob_start();
        ?>
        <form id="filter-form" hx-get="<?php echo $this->buildBaseUrl(); ?>" hx-target="#content" hx-swap="outerHTML" hx-trigger="change from:form, submit">
            <div class="row g-3 align-items-center">
                <div class="col-auto">
                    <label for="filter_name" class="form-label">Player Name</label>
                    <input type="text" name="filter_name" id="filter_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($this->params['filterName']); ?>">
                </div>
                <div class="col-auto">
                    <label for="filter_team" class="form-label">Team</label>
                    <select name="filter_team" id="filter_team" class="form-select form-select-sm">
                        <option value="0" <?php echo $this->params['filterTeam'] == 0 ? 'selected' : ''; ?>>All</option>
                        <?php foreach ($this->teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>" <?php echo $this->params['filterTeam'] == $team['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($team['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <label for="filter_position" class="form-label">Position</label>
                    <select name="filter_position" id="filter_position" class="form-select form-select-sm">
                        <option value="0" <?php echo $this->params['filterPosition'] == 0 ? 'selected' : ''; ?>>All</option>
                        <option value="1" <?php echo $this->params['filterPosition'] == 1 ? 'selected' : ''; ?>>GK</option>
                        <option value="2" <?php echo $this->params['filterPosition'] == 2 ? 'selected' : ''; ?>>DEF</option>
                        <option value="3" <?php echo $this->params['filterPosition'] == 3 ? 'selected' : ''; ?>>MID</option>
                        <option value="4" <?php echo $this->params['filterPosition'] == 4 ? 'selected' : ''; ?>>FWD</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label for="hist_count" class="form-label">History</label>
                    <select name="hist_count" id="hist_count" class="form-select form-select-sm">
                        <option value="0" <?php echo $this->params['histCountGet'] === '0' ? 'selected' : ''; ?>>0</option>
                        <option value="3" <?php echo $this->params['histCountGet'] === '3' ? 'selected' : ''; ?>>3</option>
                        <option value="5" <?php echo $this->params['histCountGet'] === '5' ? 'selected' : ''; ?>>5</option>
                        <option value="10" <?php echo $this->params['histCountGet'] === '10' ? 'selected' : ''; ?>>10</option>
                        <option value="all" <?php echo $this->params['histCountGet'] === 'all' ? 'selected' : ''; ?>>All</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label for="fixture_count" class="form-label">Fixtures</label>
                    <select name="fixture_count" id="fixture_count" class="form-select form-select-sm">
                        <option value="0" <?php echo $this->params['fixtureCountGet'] === '0' ? 'selected' : ''; ?>>0</option>
                        <option value="3" <?php echo $this->params['fixtureCountGet'] === '3' ? 'selected' : ''; ?>>3</option>
                        <option value="5" <?php echo $this->params['fixtureCountGet'] === '5' ? 'selected' : ''; ?>>5</option>
                        <option value="10" <?php echo $this->params['fixtureCountGet'] === '10' ? 'selected' : ''; ?>>10</option>
                        <option value="all" <?php echo $this->params['fixtureCountGet'] === 'all' ? 'selected' : ''; ?>>All</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label for="limit" class="form-label">Limit</label>
                    <select name="limit" id="limit" class="form-select form-select-sm">
                        <option value="25" <?php echo $this->params['limit'] == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $this->params['limit'] == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $this->params['limit'] == 100 ? 'selected' : ''; ?>>100</option>
                        <option value="200" <?php echo $this->params['limit'] == 200 ? 'selected' : ''; ?>>200</option>
                        <option value="0" <?php echo $this->params['limit'] == 0 ? 'selected' : ''; ?>>All</option>
                    </select>
                </div>
                <div class="col-auto">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="filter_watchlist" name="filter_watchlist" value="1" <?php echo $this->params['filterWatchlist'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="filter_watchlist">Watchlist</label>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="filter_dreamteam" name="filter_dreamteam" value="1" <?php echo $this->params['filterDreamTeam'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="filter_dreamteam">Dream Team</label>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="player_details" name="player_details" value="1" <?php echo $this->params['playerDetails'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="player_details">Player Details</label>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="fixture_details" name="fixture_details" value="1" <?php echo $this->params['fixtureDetails'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="fixture_details">Fixture Details</label>
                    </div>
                </div>
            </div>
            <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($this->params['sortBy']); ?>">
            <input type="hidden" name="sort_dir" value="<?php echo htmlspecialchars($this->params['sortDir']); ?>">
            <input type="hidden" name="gw_dream" value="<?php echo $this->params['gwDream']; ?>">
        </form>
        <?php
        return ob_get_clean();
    }

    private function buildBaseUrl(): string
    {
        return sprintf(
            '?min_points=%d&amp;hist_count=%s&amp;fixture_count=%s&amp;limit=%d&amp;sort_by=%s&amp;sort_dir=%s&amp;hist_highlight=%d',
            $this->params['minPoints'],
            $this->params['histCountGet'],
            $this->params['fixtureCountGet'],
            $this->params['limit'],
            htmlspecialchars($this->params['sortBy']),
            htmlspecialchars($this->params['sortDir']),
            $this->params['histHighlight']
        );
    }
}