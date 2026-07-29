<?php

namespace App\Console\Commands;

use App\Models\SocialMediaComment;
use App\Models\SocialMediaItem;
use Illuminate\Console\Command;

class BackfillSocialMediaComments extends Command
{
    protected $signature = 'social:backfill-comments {--platform=} {--limit=500}';

    protected $description = 'Backfill social media comments table from legacy raw_json.comments payloads';

    public function handle(): int
    {
        $platform = trim((string) $this->option('platform'));
        $limit = max(1, (int) $this->option('limit'));

        $query = SocialMediaItem::query()->whereNotNull('raw_json');
        if ($platform !== '') {
            $query->where('platform', $platform);
        }

        $items = $query->orderBy('id')->limit($limit)->get();
        $inserted = 0;

        foreach ($items as $item) {
            $payload = json_decode((string) $item->raw_json, true);
            if (! is_array($payload)) {
                continue;
            }

            $comments = $payload['comments'] ?? null;
            if (! is_array($comments) || $comments === []) {
                continue;
            }

            foreach ($comments as $comment) {
                if (! is_array($comment)) {
                    continue;
                }

                $commentId = (string) ($comment['cid'] ?? $comment['id'] ?? $comment['commentId'] ?? md5(json_encode($comment)));
                if ($commentId === '') {
                    continue;
                }

                SocialMediaComment::updateOrCreate(
                    [
                        'social_media_item_id' => $item->id,
                        'comment_id' => $commentId,
                    ],
                    [
                        'platform' => $item->platform,
                        'parent_comment_id' => (string) ($comment['parentCommentId'] ?? $comment['parentId'] ?? '') ?: null,
                        'author_name' => data_get($comment, 'author.name')
                            ?? data_get($comment, 'user.nickname')
                            ?? data_get($comment, 'username')
                            ?? data_get($comment, 'authorName')
                            ?? null,
                        'author_url' => data_get($comment, 'author.url')
                            ?? data_get($comment, 'profileUrl')
                            ?? data_get($comment, 'ownerProfileUrl')
                            ?? null,
                        'avatar_url' => data_get($comment, 'author.avatarThumb')
                            ?? data_get($comment, 'author.avatar')
                            ?? data_get($comment, 'avatar_url')
                            ?? data_get($comment, 'ownerProfilePicUrl')
                            ?? null,
                        'content' => data_get($comment, 'text')
                            ?? data_get($comment, 'content')
                            ?? data_get($comment, 'commentText')
                            ?? data_get($comment, 'message')
                            ?? 'Tidak ada teks komentar.',
                        'like_count' => (int) (
                            data_get($comment, 'diggCount')
                            ?: data_get($comment, 'likeCount')
                            ?: data_get($comment, 'likesCount')
                            ?: data_get($comment, 'likes')
                            ?: data_get($comment, 'like_count')
                            ?: 0
                        ),
                        'posted_at' => null,
                        'raw_json' => json_encode($comment),
                    ]
                );

                $inserted++;
            }
        }

        $this->info("Backfill selesai. Komentar diproses: {$inserted}");

        return self::SUCCESS;
    }
}
