<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class SslScrapingTest extends TestCase
{
    public function test_valid_https_request_succeeds()
    {
        // Request to a valid HTTPS site like google.com should succeed with verify enabled
        $response = Http::timeout(10)->get('https://www.google.com');
        $this->assertTrue($response->successful());
    }

    public function test_invalid_ssl_request_fails()
    {
        // Request to a bad SSL site should throw a ConnectionException
        $this->expectException(ConnectionException::class);
        
        // Using badssl.com's expired certificate endpoint to test
        Http::timeout(10)->get('https://expired.badssl.com/');
    }

    public function test_no_ssl_bypass_in_code()
    {
        // Scan app/ directory to ensure no one bypassed SSL using withoutVerifying or verify => false
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        $bypassPatterns = ['withoutVerifying', '\'verify\'\s*=>\s*false', '"verify"\s*=>\s*false'];
        
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getRealPath());
                foreach ($bypassPatterns as $pattern) {
                    if (preg_match('/' . $pattern . '/i', $content)) {
                        $this->fail("Found SSL bypass '{$pattern}' in file: " . $file->getRealPath());
                    }
                }
            }
        }
        $this->assertTrue(true);
    }
}
