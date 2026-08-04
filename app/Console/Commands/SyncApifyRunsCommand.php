<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApifySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncApifyRunsCommand extends Command
{
    protected $signature = 'apify:sync-runs {--limit=100}';
    protected $description = 'Sync recent runs from Apify to ensure costs and statuses are up to date.';

    public function handle()
    {
        $limit = $this->option('limit');
        $setting = ApifySetting::first();
        if (!$setting) {
            $this->error('No Apify settings found.');
            return;
        }

        $token = $setting->getActiveToken();
        if (!$token) {
            $this->error('No active Apify token found.');
            return;
        }

        $this->info("Fetching up to {$limit} recent runs from Apify...");
        
        $response = Http::get('https://api.apify.com/v2/actor-runs', [
            'token' => $token,
            'desc' => 'true',
            'limit' => $limit
        ]);

        if (!$response->successful()) {
            $this->error('Failed to fetch runs from Apify: ' . $response->body());
            return;
        }

        $runs = $response->json('data.items') ?? [];
        $this->info("Found " . count($runs) . " runs. Processing...");

        $manualProject = null;

        foreach ($runs as $run) {
            $runId = $run['id'];
            $actId = $run['actId'];
            $status = $this->mapStatus($run['status']);
            $cost = $run['usageTotalUsd'] ?? 0;
            $startedAt = isset($run['startedAt']) ? \Carbon\Carbon::parse($run['startedAt'])->setTimezone(config('app.timezone')) : null;
            $finishedAt = isset($run['finishedAt']) ? \Carbon\Carbon::parse($run['finishedAt'])->setTimezone(config('app.timezone')) : null;
            $duration = null;
            if ($startedAt && $finishedAt) {
                $duration = max(0, (int) $finishedAt->timestamp - (int) $startedAt->timestamp);
            }

            // Cek apakah sudah ada di database
            $existing = DB::table('apify_dispatch_states')->where('run_id', $runId)->first();

            if ($existing) {
                // Update cost dan status jika diperlukan
                if ($existing->status !== 'success' || $existing->actual_cost_usd != $cost || !$existing->completed_at) {
                    DB::table('apify_dispatch_states')->where('id', $existing->id)->update([
                        'status' => $status,
                        'actual_cost_usd' => $cost,
                        'completed_at' => $finishedAt ?? $existing->completed_at,
                        'run_duration_secs' => $duration ?? $existing->run_duration_secs,
                        'updated_at' => now(),
                    ]);
                    $this->info("Updated existing run: {$runId} (Cost: {$cost}, Status: {$status})");
                }
            } else {
                // Manual run dari Apify
                $this->info("Found manual/untracked run: {$runId}. Resolving actor...");
                
                // Fetch actor info
                $actorResponse = Http::get("https://api.apify.com/v2/acts/{$actId}", ['token' => $token]);
                if (!$actorResponse->successful()) continue;
                $actorData = $actorResponse->json('data');
                if (!$actorData) continue;
                
                $actorSlug = ($actorData['username'] ?? '') . '/' . ($actorData['name'] ?? '');
                $localActor = DB::table('apify_actors')->where('actor_slug', $actorSlug)->first();
                
                if (!$localActor) {
                    $this->warn("Local actor not found for slug: {$actorSlug}");
                    continue;
                }

                // Buat dummy project jika belum ada
                if (!$manualProject) {
                    $manualProject = DB::table('projects')->where('name', 'Manual Run (Tanpa Proyek)')->first();
                    if (!$manualProject) {
                        $package = DB::table('packages')->first();
                        $projectId = DB::table('projects')->insertGetId([
                            'name' => 'Manual Run (Tanpa Proyek)',
                            'package_id' => $package ? $package->id : 1,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $manualProject = DB::table('projects')->where('id', $projectId)->first();
                    }
                }

                DB::table('apify_dispatch_states')->insert([
                    'dispatch_key' => 'manual_' . $runId . '_' . Str::random(5),
                    'project_id' => $manualProject->id,
                    'actor_id' => $localActor->id,
                    'platform' => $localActor->platform,
                    'keyword' => 'Manual Run from Apify',
                    'normalized_keyword' => 'manualrunfromapify',
                    'status' => $status,
                    'run_id' => $runId,
                    'attempts' => 1,
                    'started_at' => $startedAt,
                    'completed_at' => $finishedAt,
                    'created_at' => $startedAt ?? now(),
                    'updated_at' => now(),
                    'actual_cost_usd' => $cost,
                    'run_duration_secs' => $duration,
                    'items_collected' => 0, // Manual runs might need dataset fetch to know items, set 0 for simplicity
                ]);
                $this->info("Inserted manual run: {$runId} (Actor: {$actorSlug})");
            }
        }
        
        $this->info('Sync completed!');
    }

    private function mapStatus($apifyStatus)
    {
        return match(strtoupper($apifyStatus)) {
            'SUCCEEDED' => 'success',
            'FAILED', 'ABORTED', 'TIMED-OUT' => 'failed',
            'RUNNING', 'READY' => 'processing',
            default => 'processing',
        };
    }
}
