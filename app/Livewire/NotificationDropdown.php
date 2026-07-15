<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Project;
use App\Models\AiAnalysisResult;

class NotificationDropdown extends Component
{
    public $notifications = [];
    public $unreadCount = 0;

    public function mount()
    {
        $this->loadNotifications();
    }

    public function loadNotifications()
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        // Get project IDs accessible by user
        $projectIds = Project::accessibleBy($user)->pluck('id');

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
            return [
                'id' => $result->id,
                'title' => $source ? ($source->title ?? $source->content ?? 'Notifikasi Negatif') : 'Item Dihapus',
                'url' => $source ? ($source->url ?? '#') : '#',
                'risk_level' => $result->risk_level,
                'reach_level' => $result->project_reach_level ?? $result->potential_reach_level ?? $result->reach_level,
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
