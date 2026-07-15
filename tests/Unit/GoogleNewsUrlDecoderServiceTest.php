<?php

namespace Tests\Unit;

use App\Services\News\GoogleNewsUrlDecoderService;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GoogleNewsUrlDecoderServiceTest extends TestCase
{
    public function test_rejects_non_google_host(): void
    {
        $service = new GoogleNewsUrlDecoderService(
            scriptPath: '/tmp/fake.py',
            runner: fn () => throw new \RuntimeException('Runner should not be called')
        );

        $result = $service->decode('https://example.com/articles/abc');

        $this->assertFalse($result['success']);
        $this->assertSame('Google News host must be news.google.com', $result['error']);
    }

    public function test_rejects_too_long_url(): void
    {
        $service = new GoogleNewsUrlDecoderService(
            scriptPath: '/tmp/fake.py',
            runner: fn () => throw new \RuntimeException('Runner should not be called')
        );

        $tooLong = 'https://news.google.com/articles/' . str_repeat('a', 17000);
        $result = $service->decode($tooLong);

        $this->assertFalse($result['success']);
        $this->assertSame('Google News URL exceeds maximum length', $result['error']);
    }

    public function test_rejects_disallowed_path(): void
    {
        $service = new GoogleNewsUrlDecoderService(
            scriptPath: '/tmp/fake.py',
            runner: fn () => throw new \RuntimeException('Runner should not be called')
        );

        $result = $service->decode('https://news.google.com/search?q=wagub');

        $this->assertFalse($result['success']);
        $this->assertSame('Google News path is not allowed', $result['error']);
    }

    public function test_rejects_decoded_url_that_is_still_google(): void
    {
        $service = new GoogleNewsUrlDecoderService(
            scriptPath: '/tmp/fake.py',
            runner: fn () => json_encode([
                'success' => true,
                'original_url' => 'https://news.google.com/articles/abc',
                'method' => 'googlenewsdecoder_0.1.7',
                'error' => null,
            ], JSON_UNESCAPED_SLASHES)
        );

        $result = $service->decode('https://news.google.com/articles/abc');

        $this->assertFalse($result['success']);
        $this->assertSame('Decoded URL is still a Google URL', $result['error']);
    }

    public function test_rejects_localhost_or_private_ip(): void
    {
        $service = new GoogleNewsUrlDecoderService(
            scriptPath: '/tmp/fake.py',
            runner: fn () => json_encode([
                'success' => true,
                'original_url' => 'http://127.0.0.1/internal',
                'method' => 'googlenewsdecoder_0.1.7',
                'error' => null,
            ], JSON_UNESCAPED_SLASHES)
        );

        $result = $service->decode('https://news.google.com/articles/abc');

        $this->assertFalse($result['success']);
        $this->assertSame('Decoded URL must not target localhost', $result['error']);
    }

    public function test_accepts_valid_portal_url(): void
    {
        $service = new GoogleNewsUrlDecoderService(
            scriptPath: '/tmp/fake.py',
            runner: fn () => json_encode([
                'success' => true,
                'original_url' => 'https://www.detik.com/kalimantan/berita/d-8554828/kebut-jalan-perbatasan-kaltara-kaltim-agar-denyut-nadi-ekonomi-lebih-kencang',
                'method' => 'googlenewsdecoder_0.1.7',
                'error' => null,
            ], JSON_UNESCAPED_SLASHES)
        );

        $result = $service->decode('https://news.google.com/articles/abc');

        $this->assertTrue($result['success']);
        $this->assertSame('https://www.detik.com/kalimantan/berita/d-8554828/kebut-jalan-perbatasan-kaltara-kaltim-agar-denyut-nadi-ekonomi-lebih-kencang', $result['original_url']);
        $this->assertSame('googlenewsdecoder_0.1.7', $result['method']);
    }

    public function test_process_timeout_returns_safe_failure(): void
    {
        $service = new GoogleNewsUrlDecoderService(
            scriptPath: '/tmp/fake.py',
            runner: function () {
                $process = new Process(['sleep', '1']);
                throw new ProcessTimedOutException($process, ProcessTimedOutException::TYPE_GENERAL);
            }
        );

        $result = $service->decode('https://news.google.com/articles/abc');

        $this->assertFalse($result['success']);
        $this->assertSame('Google decoder process timed out', $result['error']);
    }
}
