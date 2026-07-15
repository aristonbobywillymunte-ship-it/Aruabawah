<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Article;
use App\Models\NewsSource;
use App\Models\AiAnalysisDispatchState;
use App\Models\AiAnalysisResult;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use App\Jobs\AiAnalysisJob;
use Tests\TestCase;

class RelevanceFilterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_relevance_filter_logic()
    {
        Queue::fake();

        // 1. Create Projects
        $projectSeno = Project::create([
            'name' => 'Project Seno',
            'topics' => ['seno aji', 'wagub kaltim'],
            'is_active' => true,
        ]);

        $projectRudy = Project::create([
            'name' => 'Project Rudy',
            'topics' => ["rudy mas'ud"],
            'is_active' => true,
        ]);

        // 2. Create Article Klopp (not relevant to either)
        $articleKlopp = Article::create([
            'title' => 'Jadi Kandidat Terdepan Pelatih Jerman, Klopp Didukung Eks Man United',
            'content' => 'Bastian Schweinsteiger mendukung Klopp untuk menjadi pelatih Jerman menggantikan Julian Nagelsmann.',
            'url' => 'https://bola.kompas.com/read/2026/07/02/klopp-didukung-jerman',
            'canonical_url' => 'https://bola.kompas.com/read/2026/07/02/klopp-didukung-jerman',
            'source_name' => 'KOMPAS.com',
        ]);

        // 3. Create Article Rudy Hartono (not relevant to Rudy Mas'ud)
        $articleRudyHartono = Article::create([
            'title' => 'Susy Susanti Pimpin PB Jaya Raya Gantikan Rudy Hartono',
            'content' => 'Susy Susanti resmi menjabat sebagai ketua umum menggantikan Rudy Hartono yang legendaris.',
            'url' => 'https://www.kompas.com/badminton/read/rudy-hartono',
            'canonical_url' => 'https://www.kompas.com/badminton/read/rudy-hartono',
            'source_name' => 'KOMPAS.com',
        ]);

        // 4. Create Relevan Article (contains Seno Aji / Wagub Kaltim)
        $articleRelevance = Article::create([
            'title' => 'Seno Aji Tinjau Abrasi di Pulau Derawan',
            'content' => 'Wagub Kaltim Seno Aji meninjau langsung kondisi pantai yang terkena abrasi di Pulau Derawan.',
            'url' => 'https://arusbawah.co/seno-aji-derawan',
            'canonical_url' => 'https://arusbawah.co/seno-aji-derawan',
            'source_name' => 'Arusbawah',
        ]);

        // Instantiate command & prepare reflection
        $command = app(\App\Console\Commands\RunNewsPortalScraping::class);
        $outputStyle = new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput()
        );
        $command->setOutput($outputStyle);
        
        $reflector = new \ReflectionClass(\App\Console\Commands\RunNewsPortalScraping::class);
        
        $method = $reflector->getMethod('articleMatchesProjectKeywords');
        $method->setAccessible(true);

        // Test 1: Klopp rejected for Seno project
        $matchKloppSeno = $method->invokeArgs($command, [$projectSeno, $articleKlopp->title, $articleKlopp->content]);
        $this->assertFalse($matchKloppSeno, "Klopp article should be rejected for Seno project.");

        // Test 2: Rudy Hartono rejected for Rudy Mas'ud project
        $matchHartonoRudy = $method->invokeArgs($command, [$projectRudy, $articleRudyHartono->title, $articleRudyHartono->content]);
        $this->assertFalse($matchHartonoRudy, "Rudy Hartono article should be rejected for Rudy Mas'ud project.");

        // Test 3: Relevan article accepted
        $matchRelevanSeno = $method->invokeArgs($command, [$projectSeno, $articleRelevance->title, $articleRelevance->content]);
        $this->assertTrue($matchRelevanSeno, "Relevan article containing Seno Aji should be accepted.");

        // Test 4, 5 & 6: Discovery candidates are trusted, then validated by URL/content quality.
        $methodProcess = $reflector->getMethod('processPortalCandidate');
        $methodProcess->setAccessible(true);

        // Set private properties needed for processPortalCandidate
        $runStatsProp = $reflector->getProperty('runStats');
        $runStatsProp->setAccessible(true);
        $runStatsProp->setValue($command, [
            'discovered' => 0,
            'found_count' => 0,
            'rss_item_count' => 0,
            'matched_count' => 0,
            'candidate_seen_count' => 0,
            'candidate_processed_count' => 0,
            'duplicate_candidate_count' => 0,
            'resolved_count' => 0,
            'unresolved_count' => 0,
            'rejected_count' => 0,
            'rejected_keyword' => 0,
            'rejected_short_content' => 0,
            'rejected_invalid_url' => 0,
            'partial_count' => 0,
            'error_count' => 0,
            'scraped_count' => 0,
            'reused_article_count' => 0,
            'new_article_count' => 0,
            'newly_inserted' => 0,
            'reused_existing' => 0,
            'attached_project_count' => 0,
            'processed_success_count' => 0,
            'saved_count' => 0,
            'skipped_count' => 0,
            'manual_saved_count' => 0,
            'google_saved_count' => 0,
            'discovery_source' => 'google_news',
        ]);
        
        $runCandidateLogsProp = $reflector->getProperty('runCandidateLogs');
        $runCandidateLogsProp->setAccessible(true);
        $runCandidateLogsProp->setValue($command, []);

        // Mock Http to return response when fetchFullContent is called inside processPortalCandidate
        Http::fake([
            'https://bola.kompas.com/read/2026/07/02/klopp-didukung-jerman*' => Http::response(
                '<html><head><title>' . $articleKlopp->title . '</title></head><body>' . $articleKlopp->content . '</body></html>',
                200
            ),
        ]);

        // Seed target NewsSource so the fetcher matches it
        NewsSource::create([
            'name' => 'KOMPAS.com',
            'domain' => 'kompas.com',
            'crawling_type' => 'html',
            'is_active' => true,
        ]);

        $res = $methodProcess->invokeArgs($command, [
            $projectSeno,
            $articleKlopp->url,
            $articleKlopp->url,
            $articleKlopp->title,
            $articleKlopp->source_name,
            null,
            'manual_portal',
            true, // suppress telegram
            true, // suppress ai
            true  // suppress reach
        ]);

        // Manual portal and Google News discovery are trusted; this candidate proceeds
        // past keyword filtering but is still held back because the fetched content is too short.
        $this->assertIsArray($res);
        $this->assertSame('partial', $res['status']);
        $this->assertSame(0, $res['newly_inserted']);
        $this->assertSame(0, $res['reused_existing']);
        $this->assertSame(0, $res['rejected']);
        $this->assertSame(1, $res['partial']);

        // Verify Klopp is not attached to Seno project
        $this->assertFalse($projectSeno->articles()->where('articles.id', $articleKlopp->id)->exists());

        // Verify Klopp global article still exists (not deleted)
        $this->assertNotNull(Article::find($articleKlopp->id));
    }
}
