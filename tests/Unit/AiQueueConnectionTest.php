<?php

namespace Tests\Unit;

use App\Jobs\AiAnalysisJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiQueueConnectionTest extends TestCase
{
    public function test_redis_ai_queue_connection_is_defined_with_long_retry_after(): void
    {
        $redis = config('queue.connections.redis');
        $redisAi = config('queue.connections.redis-ai');

        $this->assertIsArray($redisAi);
        $this->assertSame('redis', $redisAi['driver']);
        $this->assertSame($redis['connection'], $redisAi['connection']);
        $this->assertSame('ai-analysis', $redisAi['queue']);
        $this->assertSame(900, $redisAi['retry_after']);
        $this->assertSame($redis['block_for'], null);
        $this->assertSame($redis['after_commit'], $redisAi['after_commit']);
        $this->assertSame(90, $redis['retry_after']);
    }

    public function test_ai_analysis_job_defaults_to_redis_ai_connection_and_ai_analysis_queue(): void
    {
        Queue::fake();

        AiAnalysisJob::dispatch([
            'type' => 'article',
            'id' => 69,
            'project_id' => 7,
            'title' => 'Test Article',
            'content' => str_repeat('a', 500),
            'url' => 'https://example.com/article',
            'source_name' => 'Example Source',
        ]);

        Queue::assertPushed(AiAnalysisJob::class, function (AiAnalysisJob $job): bool {
            return $job->connection === 'redis-ai'
                && $job->queue === 'ai-analysis';
        });
    }
}
