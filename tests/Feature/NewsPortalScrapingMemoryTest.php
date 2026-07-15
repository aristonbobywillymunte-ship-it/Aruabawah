<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Project;
use ReflectionClass;

class NewsPortalScrapingMemoryTest extends TestCase
{
    private function getScraperInstance()
    {
        $class = new ReflectionClass(\App\Console\Commands\RunNewsPortalScraping::class);
        return $class->newInstanceWithoutConstructor();
    }

    private function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }

    public function test_safe_keyword_text_limits_memory_and_truncates()
    {
        $scraper = $this->getScraperInstance();
        
        $title = 'Judul Artikel Utama';
        // Generate 10MB of content
        $largeDescription = str_repeat('A', 10 * 1024 * 1024); // 10MB
        
        $initialMemory = memory_get_usage();
        $safeText = $this->invokeMethod($scraper, 'safeKeywordText', [$title, $largeDescription]);
        $finalMemory = memory_get_usage();

        // Memory difference should not spike by another 10MB for the output
        $this->assertLessThan(1024 * 1024, $finalMemory - $initialMemory);
        // Result length should be title (19 char) + space (1) + truncated desc (8192 char) = 8212
        $this->assertEquals(8212, mb_strlen($safeText));
    }

    public function test_keyword_matching_behavior()
    {
        $scraper = $this->getScraperInstance();
        
        $project = new Project([
            'topics' => ['samarinda', 'walikota samarinda']
        ]);

        // 1. Keyword in title matches
        $this->assertTrue(
            $this->invokeMethod($scraper, 'articleMatchesProjectKeywords', [$project, 'Banjir melanda kota Samarinda', 'Konten berita biasa'])
        );

        // 2. Keyword at the beginning of content matches
        $this->assertTrue(
            $this->invokeMethod($scraper, 'articleMatchesProjectKeywords', [$project, 'Judul Netral', 'Samarinda hari ini dilaporkan aman'])
        );

        // 3. Keyword far after 8 KB does NOT match (due to truncation)
        $farDescription = str_repeat('A', 8195) . ' samarinda';
        $this->assertFalse(
            $this->invokeMethod($scraper, 'articleMatchesProjectKeywords', [$project, 'Judul Netral', $farDescription])
        );

        // 4. Keyword just before 8 KB limit matches
        $nearDescription = str_repeat('A', 8180) . ' samarinda';
        $this->assertTrue(
            $this->invokeMethod($scraper, 'articleMatchesProjectKeywords', [$project, 'Judul Netral', $nearDescription])
        );

        // 5. Keyword parts space separated matches (walikota ... samarinda)
        $this->assertTrue(
            $this->invokeMethod($scraper, 'articleMatchesProjectKeywords', [$project, 'Walikota hadir', 'Acara di kota Samarinda'])
        );
    }
}
