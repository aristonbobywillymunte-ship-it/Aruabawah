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

        $override = $this->parseTimes(data_get($project, $overrideField), allowBlankEntries: true);

        if ($override['has_non_empty']) {
            $projectRunsPerDay = (int) ($package->$runsPerDayField ?? 0);
            if ($override['invalid'] || $projectRunsPerDay < 1 || count($override['times']) !== $projectRunsPerDay) {
                return $this->empty('invalid_project_override');
            }

            return [
                'times' => $override['times'],
                'source' => 'project',
                'reason' => null,
            ];
        }

        $packageRunsPerDay = $package->$runsPerDayField ?? null;
        $packageTimes = $this->parseTimes($package->$packageTimesField ?? null, allowBlankEntries: false);

        if (blank($packageRunsPerDay)) {
            return $packageTimes['has_non_empty']
                ? $this->empty('invalid_package_schedule')
                : $this->empty('package_schedule_not_configured');
        }

        if (! $packageTimes['has_non_empty']) {
            return $this->empty('package_schedule_not_configured');
        }

        if ($packageTimes['invalid'] || count($packageTimes['times']) !== (int) $packageRunsPerDay) {
            return $this->empty('invalid_package_schedule');
        }

        return [
            'times' => $packageTimes['times'],
            'source' => 'package',
            'reason' => null,
        ];
    }

    protected function parseTimes(mixed $times, bool $allowBlankEntries): array
    {
        $times = is_array($times) ? array_values($times) : [];
        $normalized = [];
        $seen = [];
        $hasNonEmpty = false;
        $invalid = false;

        foreach ($times as $time) {
            $time = is_string($time) ? trim($time) : '';

            if ($time === '') {
                if ($allowBlankEntries) {
                    continue;
                }

                $invalid = true;
                break;
            }

            $hasNonEmpty = true;

            if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
                $invalid = true;
                break;
            }

            if (isset($seen[$time])) {
                $invalid = true;
                break;
            }

            $seen[$time] = true;
            $normalized[] = $time;
        }

        sort($normalized);

        return [
            'times' => $invalid ? [] : $normalized,
            'has_non_empty' => $hasNonEmpty,
            'invalid' => $invalid,
        ];
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
