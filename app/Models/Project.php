<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'topics',
        'context_keywords',
        'exclude_keywords',
        'is_active',
    ];

    protected $casts = [
        'topics' => 'array',
        'context_keywords' => 'array',
        'exclude_keywords' => 'array',
        'is_active' => 'boolean',
        'first_news_scrape_attempt_at' => 'datetime',
    ];

    // ─── Relations ───────────────────────────────────────────────────────

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'project_articles')
            ->withTimestamps();
    }

    public function socialMediaItems(): BelongsToMany
    {
        return $this->belongsToMany(SocialMediaItem::class, 'project_social_media_items')
            ->withTimestamps();
    }



    /**
     * User yang punya akses ke project ini.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'project_user')
                    ->withTimestamps();
    }

    // ─── Scopes ──────────────────────────────────────────────────────────

    /**
     * Scope: hanya project yang bisa diakses oleh user tertentu.
     * Admin → semua project.
     * User  → hanya yang di-assign pada relasi project-user.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('users', fn($q) => $q->where('users.id', $user->id));
    }

    public function deactivate(): void
    {
        $this->forceFill(['is_active' => false])->save();
    }

    public function scrapeKeywords(): array
    {
        $raw = $this->normalizeKeywordList($this->topics ?? []);
        $seen = [];
        $result = [];
        
        foreach ($raw as $keyword) {
            $trimmed = trim((string) $keyword);
            if ($trimmed === '') continue;
            
            $lower = strtolower($trimmed);
            if (!isset($seen[$lower])) {
                $seen[$lower] = true;
                $result[] = $trimmed;
            }
        }
        
        return $result;
    }

    public function scrapeContextKeywords(): array
    {
        return $this->normalizeKeywordList($this->context_keywords ?? []);
    }

    public function scrapeExcludeKeywords(): array
    {
        return $this->normalizeKeywordList($this->exclude_keywords ?? []);
    }

    public function scrapeKeywordVariants(): array
    {
        $variants = [];

        foreach ($this->scrapeKeywords() as $keyword) {
            $variants[] = $keyword;

            $hashtag = $this->toHashtagVariant($keyword);
            if ($hashtag !== '') {
                $variants[] = $hashtag;
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    public function scrapeKeywordPlainVariants(): array
    {
        return $this->scrapeKeywords();
    }

    public function scrapeKeywordHashtagVariants(): array
    {
        $variants = [];

        foreach ($this->scrapeKeywords() as $keyword) {
            $hashtag = $this->toHashtagVariant($keyword);
            if ($hashtag !== '') {
                $variants[] = $hashtag;
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    public function scrapeContextKeywordVariants(): array
    {
        $variants = [];

        foreach ($this->scrapeContextKeywords() as $keyword) {
            $variants[] = $keyword;

            $hashtag = $this->toHashtagVariant($keyword);
            if ($hashtag !== '') {
                $variants[] = $hashtag;
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    protected function normalizeKeywordList(mixed $raw): array
    {
        $raw = Arr::wrap($raw);
        $seen = [];
        $result = [];

        foreach ($raw as $keyword) {
            $trimmed = trim((string) $keyword);
            if ($trimmed === '') {
                continue;
            }

            $lower = strtolower($trimmed);
            if (! isset($seen[$lower])) {
                $seen[$lower] = true;
                $result[] = $trimmed;
            }
        }

        return $result;
    }

    protected function toHashtagVariant(string $keyword): string
    {
        $keyword = trim($keyword);
        $keyword = preg_replace('/\s+/u', ' ', $keyword) ?? $keyword;
        $keyword = str_replace(["'", "’", "‘", "`"], '', $keyword);
        $keyword = trim($keyword, " \t\n\r\0\x0B#");
        $keyword = preg_replace('/[^\p{L}\p{N}\s_]+/u', '', $keyword) ?? $keyword;
        $keyword = preg_replace('/\s+/u', '', $keyword) ?? $keyword;

        return $keyword === '' ? '' : '#' . $keyword;
    }

    public function hasScrapeKeywords(): bool
    {
        return ! empty($this->scrapeKeywords());
    }
}
