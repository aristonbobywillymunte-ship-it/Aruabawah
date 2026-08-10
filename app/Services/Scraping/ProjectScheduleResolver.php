<?php

namespace App\Services\Scraping;

use App\Models\Package;
use App\Models\Project;

class ProjectScheduleResolver
{
    public function resolvePortal(Project $project): array
    {
        return $this->resolve(
            $project,
            'news_run_times_override',
            'news_runs_per_day',
            'news_run_times',
            fn (Package $package): bool => (bool) $package->use_portal
        );
    }

    public function resolveSocial(Project $project): array
    {
        return $this->resolve(
            $project,
            'social_run_times_override',
            'social_runs_per_day',
            'social_run_times',
            fn (Package $package): bool => $package->enabledActors()->exists()
        );
    }

    protected function resolve(
        Project $project,
        string $overrideField,
        string $runsPerDayField,
        string $packageTimesField,
        callable $featureEnabled
    ): array {
        $package = $project->package;

        if (! $package) {
            return $this->empty('package_missing');
        }

        if (! $featureEnabled($package)) {
            return $this->empty('feature_disabled');
        }

        $override = $this->normalizeTimes(data_get($project, $overrideField), allowEmpty: true);

        if ($override !== []) {
            $projectRunsPerDay = (int) ($package->$runsPerDayField ?? 0);
            if ($projectRunsPerDay < 1 || count($override) !== $projectRunsPerDay) {
                return $this->empty('invalid_project_override');
            }

            return [
                'times' => $override,
                'source' => 'project',
                'reason' => null,
            ];
        }

        $packageRunsPerDay = $package->$runsPerDayField ?? null;
        $packageTimes = $this->normalizeTimes($package->$packageTimesField ?? null, allowEmpty: false);

        if (blank($packageRunsPerDay) || $packageTimes === []) {
            return $this->empty('package_schedule_not_configured');
        }

        if (count($packageTimes) !== (int) $packageRunsPerDay) {
            return $this->empty('invalid_package_schedule');
        }

        return [
            'times' => $packageTimes,
            'source' => 'package',
            'reason' => null,
        ];
    }

    protected function normalizeTimes(mixed $times, bool $allowEmpty): array
    {
        $times = is_array($times) ? array_values($times) : [];
        $normalized = [];
        $seen = [];

        foreach ($times as $time) {
            $time = is_string($time) ? trim($time) : '';

            if ($time === '') {
                if ($allowEmpty) {
                    continue;
                }

                return [];
            }

            if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
                return [];
            }

            if (isset($seen[$time])) {
                return [];
            }

            $seen[$time] = true;
            $normalized[] = $time;
        }

        sort($normalized);

        return $normalized;
    }

    protected function empty(string $reason): array
    {
        return [
            'times' => [],
            'source' => 'none',
            'reason' => $reason,
        ];
    }
}
