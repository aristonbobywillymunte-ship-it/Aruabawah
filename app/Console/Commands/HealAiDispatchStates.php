<?php

namespace App\Console\Commands;

use App\Models\AiAnalysisDispatchState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * HealAiDispatchStates
 *
 * Command ini berjalan otomatis untuk mendeteksi dan memperbaiki dua kondisi
 * abnormal yang dapat menghentikan pemrosesan AI secara diam-diam:
 *
 * 1. Item berstatus 'queued' yang ter-soft-delete (deleted_at IS NOT NULL).
 *    Ini menyebabkan Eloquent tidak menemukannya → AI tidak pernah dipicu.
 *    Solusi: hapus deleted_at (restore).
 *
 * 2. Item berstatus 'queued' yang proyeknya sudah soft-deleted.
 *    normalizePayloadContext() selalu gagal → item berputar tanpa hasil.
 *    Solusi: tandai sebagai 'failed' dengan kode project_soft_deleted.
 */
class HealAiDispatchStates extends Command
{
    protected $signature = 'ai:heal-dispatch-states
                            {--dry-run : Tampilkan apa yang akan diperbaiki tanpa mengubah data}';

    protected $description = 'Auto-heal kondisi abnormal di ai_analysis_dispatch_states (soft-delete queued, proyek terhapus).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('[dry-run] Mode preview — tidak ada perubahan yang diterapkan.');
        }

        $totalFixed = 0;
        $totalFixed += $this->restoreSoftDeletedQueued($dryRun);
        $totalFixed += $this->failQueuedFromDeletedProjects($dryRun);
        $totalFixed += $this->healStuckCommentsChecked($dryRun);
        $totalFixed += $this->healMissingAiAnalysisForActiveProjects($dryRun);

        if ($totalFixed > 0) {
            $this->info("Heal selesai: {$totalFixed} item diperbaiki.");
            Log::info("[AI Heal] Selesai. Total diperbaiki: {$totalFixed}", ['dry_run' => $dryRun]);
        }

        return self::SUCCESS;
    }

    /**
     * Fix #1: Restore item 'queued' yang ter-soft-delete.
     *
     * Kondisi ini bisa terjadi jika ada operasi bulk delete/cleanup yang
     * tidak mempertimbangkan dispatch states yang masih aktif.
     */
    private function restoreSoftDeletedQueued(bool $dryRun): int
    {
        // Gunakan withTrashed agar bisa menemukan item yang soft-deleted
        $count = AiAnalysisDispatchState::withTrashed()
            ->where('status', 'queued')
            ->whereNotNull('deleted_at')
            ->count();

        if ($count === 0) {
            return 0;
        }

        $this->warn("[Heal #1] Ditemukan {$count} item 'queued' yang ter-soft-delete. " . ($dryRun ? '(dry-run)' : 'Memulihkan...'));

        Log::warning("[AI Heal] Ditemukan {$count} dispatch state 'queued' dengan deleted_at terisi.", [
            'dry_run' => $dryRun,
        ]);

        if (! $dryRun) {
            $restored = AiAnalysisDispatchState::withTrashed()
                ->where('status', 'queued')
                ->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            $this->info("[Heal #1] {$restored} item berhasil dipulihkan (deleted_at = NULL).");
            Log::info("[AI Heal] Restored {$restored} soft-deleted queued dispatch states.");
            return $restored;
        }

        return $count;
    }

    /**
     * Fix #2: Tandai 'failed' item 'queued' yang proyeknya sudah soft-deleted.
     *
     * normalizePayloadContext() menggunakan Project::query() (dengan SoftDeletes),
     * sehingga proyek yang dihapus dianggap tidak ada → item berputar terus tanpa
     * berhasil diproses, memblokir item lain dalam antrian.
     */
    private function failQueuedFromDeletedProjects(bool $dryRun): int
    {
        // Cari semua project_id yang dipakai di dispatch states queued
        $queuedProjectIds = AiAnalysisDispatchState::query()
            ->where('status', 'queued')
            ->whereNotNull('project_id')
            ->distinct()
            ->pluck('project_id');

        if ($queuedProjectIds->isEmpty()) {
            return 0;
        }

        // Dari project_id tersebut, cari mana yang sudah soft-deleted
        $deletedProjectIds = DB::table('projects')
            ->whereIn('id', $queuedProjectIds)
            ->whereNotNull('deleted_at')
            ->pluck('id');

        if ($deletedProjectIds->isEmpty()) {
            return 0;
        }

        $count = AiAnalysisDispatchState::query()
            ->where('status', 'queued')
            ->whereIn('project_id', $deletedProjectIds)
            ->count();

        $this->warn("[Heal #2] Ditemukan {$count} item 'queued' dari " . count($deletedProjectIds) . " proyek yang sudah dihapus (project_id: " . $deletedProjectIds->implode(', ') . "). " . ($dryRun ? '(dry-run)' : 'Menandai failed...'));

        Log::warning("[AI Heal] Ditemukan {$count} dispatch state 'queued' dari proyek soft-deleted.", [
            'deleted_project_ids' => $deletedProjectIds->toArray(),
            'dry_run'             => $dryRun,
        ]);

        if (! $dryRun) {
            $now = now();
            $marked = AiAnalysisDispatchState::query()
                ->where('status', 'queued')
                ->whereIn('project_id', $deletedProjectIds)
                ->update([
                    'status'           => 'failed',
                    'failure_category' => 'missing_dependency',
                    'last_error_code'  => 'project_soft_deleted',
                    'error_message'    => 'Proyek referensi sudah dihapus (soft-deleted). Item tidak dapat diproses.',
                    'last_failed_at'   => $now,
                    'completed_at'     => $now,
                    'updated_at'       => $now,
                ]);

            $this->info("[Heal #2] {$marked} item ditandai 'failed' (project_soft_deleted).");
            Log::info("[AI Heal] Marked {$marked} queued states as failed due to soft-deleted projects.", [
                 'deleted_project_ids' => $deletedProjectIds->toArray(),
            ]);
            return $marked;
        }

        return $count;
    }

    /**
     * Fix #3: Otomatis membebaskan status comments_checked = false yang menggantung
     * agar data sosial media tetap bisa tampil di dashboard setelah lewat 6 jam.
     */
    private function healStuckCommentsChecked(bool $dryRun): int
    {
        $count = DB::table('social_media_items')
            ->where('comments_checked', false)
            ->where('created_at', '<=', now()->subHours(6))
            ->count();

        if ($count === 0) {
            return 0;
        }

        $this->warn("[Heal #3] Ditemukan {$count} item sosial media dengan comments_checked tertahan FALSE > 6 jam. " . ($dryRun ? '(dry-run)' : 'Membebaskan...'));

        if (!$dryRun) {
            $updated = DB::table('social_media_items')
                ->where('comments_checked', false)
                ->where('created_at', '<=', now()->subHours(6))
                ->update([
                    'comments_checked' => true,
                    'updated_at' => now(),
                ]);

            $this->info("[Heal #3] {$updated} item sosial media berhasil dibebaskan (comments_checked = TRUE).");
            return $updated;
        }

        return $count;
    }

    /**
     * Fix #4: Menjaring data yang terhubung ke project aktif namun tidak memiliki hasil AI
     * dan juga tidak terdaftar di dispatch states antrean AI (terlewat scheduler).
     */
    private function healMissingAiAnalysisForActiveProjects(bool $dryRun): int
    {
        $activeProjectIds = DB::table('projects')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        if (empty($activeProjectIds)) {
            return 0;
        }

        // Cek data sosial media yang terhubung ke project aktif tetapi belum di-analisis AI
        $stuckSocialIds = DB::table('social_media_items as s')
            ->join('project_social_media_items as p', 's.id', '=', 'p.social_media_item_id')
            ->leftJoin('ai_analysis_results as ai', 's.id', '=', 'ai.social_media_item_id')
            ->leftJoin('ai_analysis_dispatch_states as d', function ($join) {
                $join->on('s.id', '=', 'd.analyzable_id')
                     ->where('d.analyzable_type', 'like', '%SocialMediaItem%');
            })
            ->whereIn('p.project_id', $activeProjectIds)
            ->whereNull('ai.id')
            ->whereNull('d.id')
            ->where('s.comments_checked', true)
            ->distinct()
            ->pluck('s.id')
            ->toArray();

        $count = count($stuckSocialIds);
        if ($count === 0) {
            return 0;
        }

        $this->warn("[Heal #4] Ditemukan {$count} postingan sosial media terlewat antrean AI. " . ($dryRun ? '(dry-run)' : 'Memasukkan ke antrean...'));

        if (!$dryRun) {
            $dispatched = 0;
            // Panggil Service untuk mendaftarkan ke antrean
            $dispatchService = app(\App\Services\AiAnalysisDispatchStateService::class);
            
            // Batasi maksimal 20 item per pembersihan agar tidak membebani server
            $itemsToProcess = array_slice($stuckSocialIds, 0, 20);

            foreach ($itemsToProcess as $id) {
                $socialItem = \App\Models\SocialMediaItem::find($id);
                if (!$socialItem) continue;

                $projectId = DB::table('project_social_media_items')
                    ->where('social_media_item_id', $id)
                    ->whereIn('project_id', $activeProjectIds)
                    ->value('project_id');

                if (!$projectId) continue;

                $payload = [
                    'type' => 'social',
                    'id' => $socialItem->id,
                    'project_id' => $projectId,
                    'title' => "Post dari {$socialItem->platform} oleh " . ($socialItem->author_name ?: 'Pengguna'),
                    'url' => $socialItem->post_url,
                    'content' => $socialItem->content,
                    'source_name' => $socialItem->platform,
                    'published_at' => optional($socialItem->posted_at)->toIso8601String(),
                    'no_telegram' => true,
                ];

                $decision = $dispatchService->reserveQueuedStateAndDispatch($payload);
                if ($decision['should_dispatch'] ?? false) {
                    $dispatched++;
                }
            }

            $this->info("[Heal #4] {$dispatched} item sosial media terlewat berhasil dikirim kembali ke antrean AI.");
            return $dispatched;
        }

        return $count;
    }
}
