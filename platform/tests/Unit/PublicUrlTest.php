<?php

namespace Tests\Unit;

use App\Support\PublicUrl;
use PHPUnit\Framework\TestCase;

class PublicUrlTest extends TestCase
{
    public function test_internal_and_non_https_urls_are_refused(): void
    {
        foreach ([
            'http://8.8.8.8/feed.json',
            'https://127.0.0.1/feed.json',
            'https://10.0.0.5/x',
            'https://192.168.1.1/x',
            'https://169.254.169.254/latest/meta-data',
            'https://[::1]/x',
            'https://user:pw@8.8.8.8/x',
            'https://no-such-host.invalid/x',
        ] as $url) {
            $this->assertFalse(PublicUrl::allowed($url), $url);
        }
    }

    public function test_public_https_address_is_allowed(): void
    {
        $this->assertTrue(PublicUrl::allowed('https://8.8.8.8/feed.json'));
        $this->assertTrue(PublicUrl::isPublicIp('2606:4700:4700::1111'));
        $this->assertFalse(PublicUrl::isPublicIp('fd00::1'));
    }
}
