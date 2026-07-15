<?php

namespace Tests\Feature;

use Tests\TestCase;

class SuspiciousProbeRequestsTest extends TestCase
{
    public function test_login_route_stays_available(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_php_probe_paths_return_not_found(): void
    {
        $this->get('/setup.php')->assertNotFound();
        $this->get('/wp-login.php')->assertNotFound();
        $this->get('/dropdown.php')->assertNotFound();
    }

    public function test_hidden_or_probe_prefixes_return_not_found(): void
    {
        $this->get('/.env')->assertNotFound();
        $this->get('/wp-admin/install.php')->assertNotFound();
        $this->get('/vendor/phpunit/eval-stdin.php')->assertNotFound();
    }
}
