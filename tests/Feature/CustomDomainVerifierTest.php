<?php

namespace Tests\Feature;

use App\Domain\WebApp\CustomDomainVerifier;
use Tests\TestCase;

class CustomDomainVerifierTest extends TestCase
{
    public function test_expected_cname_target_is_this_apps_own_host(): void
    {
        config(['app.url' => 'https://main.example.com']);

        $this->assertSame('main.example.com', app(CustomDomainVerifier::class)->expectedCnameTarget());
    }

    public function test_a_domain_with_no_matching_dns_record_is_not_verified(): void
    {
        config(['app.url' => 'https://main.example.com']);

        $this->assertFalse(app(CustomDomainVerifier::class)->verify('this-domain-does-not-exist.invalid'));
    }
}
