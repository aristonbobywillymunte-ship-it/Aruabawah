<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Project;
use App\Models\AiAnalysisResult;

class NotificationDropdown extends Component
{
    public $notifications = [];
    public $unreadCount = 0;
    public $projectId = null;

    public function mount($projectId = null)
    {
        $this->projectId = $projectId;
        $this->loadNotifications();
    }

    public function loadNotifications()
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        // Determine which project IDs to filter
        if ($this->projectId) {
            $projectIds = collect([$this->projectId]);
        } else {
            // Fallback (fallback if no project is active, though page filters usually active)
            $projectIds = Project::accessibleBy($user)->pluck('id');
        }

        // Get read notification IDs from Session
        $readNotificationIds = session()->get('user_' . $user->id . '_read_notifications', []);

        // Get negative sentiments associated with those projects
        $results = AiAnalysisResult::where('sentiment', 'negative')
            ->whereNotIn('id', $readNotificationIds)
            ->where(function ($query) use ($projectIds) {
                $query->whereHas('article.projects', function ($q) use ($projectIds) {
                    $q->whereIn('projects.id', $projectIds);
                })->orWhereHas('socialMediaItem.projects', function ($q) use ($projectIds) {
                    $q->whereIn('projects.id', $projectIds);
                });
            })
            ->with(['article', 'socialMediaItem'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        $this->notifications = $results->map(function ($result) {
            $source = $result->article ?? $result->socialMediaItem;
            
            // Terjemahkan Tingkat Risiko ke Bahasa Indonesia
            $riskRaw = strtolower((string) $result->risk_level);
            if ($riskRaw === 'critical') {
                $riskIndo = 'Resiko Kritis';
            } elseif ($riskRaw === 'high') {
                $riskIndo = 'Resiko Tinggi';
            } elseif ($riskRaw === 'medium') {
                $riskIndo = 'Resiko Sedang';
            } else {
                $riskIndo = 'Resiko Rendah';
            }

            // Terjemahkan Tingkat Jangkauan (Reach) ke Bahasa Indonesia
            $reachRaw = strtoupper((string) ($result->project_reach_level ?? $result->potential_reach_level ?? $result->reach_level));
            if (str_contains($reachRaw, 'NASIONAL') || str_contains($reachRaw, 'LUAR BIASA')) {
                $reachIndo = 'Jangkauan Nasional';
            } elseif (str_contains($reachRaw, 'REGIONAL')) {
                $reachIndo = 'Jangkauan Regional';
            } elseif (str_contains($reachRaw, 'LOKAL')) {
                $reachIndo = 'Jangkauan Lokal';
            } else {
                $reachIndo = 'Jangkauan Rendah';
            }

            // Tentukan tipe sumber
            $sourceType = $result->article ? 'Portal Berita' : 'Media Sosial';

            return [
                'id' => $result->id,
                'title' => $source ? ($source->title ?? $source->content ?? 'Komentar Negatif') : 'Konten Dihapus',
                'url' => $source ? ($source->url ?? '#') : '#',
                'risk_level' => $result->risk_level, // tetap kirim raw untuk class styling
                'risk_label' => $riskIndo,
                'reach_label' => $reachIndo,
                'source_type' => $sourceType,
                'time' => $result->created_at->diffForHumans(),
            ];
        })->toArray();

        $this->unreadCount = count($this->notifications);
    }

    public function markAllAsRead()
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        $notificationIds = collect($this->notifications)->pluck('id')->toArray();
        if (empty($notificationIds)) {
            return;
        }

        $readNotificationIds = session()->get('user_' . $user->id . '_read_notifications', []);
        $newReadIds = array_unique(array_merge($readNotificationIds, $notificationIds));

        session()->put('user_' . $user->id . '_read_notifications', $newReadIds);

        $this->loadNotifications();
    }

    public function render()
    {
        return view('livewire.notification-dropdown');
    }
}
