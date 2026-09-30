<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\UrlInspectionClient;
use App\Exceptions\SeoGoogleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UrlInspectionClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_inspects_a_url_and_returns_the_inspection_result(): void
    {
        Http::fake([
            'searchconsole.googleapis.com/v1/urlInspection/index:inspect' => Http::response([
                'inspectionResult' => [
                    'indexStatusResult' => [
                        'verdict' => 'PASS',
                        'coverageState' => 'Submitted and indexed',
                    ],
                ],
            ]),
        ]);

        $result = (new UrlInspectionClient('token-123'))->inspect(
            'https://ganjnameh.example.com/telecabin',
            'https://ganjnameh.example.com/',
        );

        $this->assertSame('PASS', $result['indexStatusResult']['verdict']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token-123')
            && $request['inspectionUrl'] === 'https://ganjnameh.example.com/telecabin'
            && $request['siteUrl'] === 'https://ganjnameh.example.com/');
    }

    public function test_it_throws_with_googles_error_message_on_failure(): void
    {
        Http::fake([
            'searchconsole.googleapis.com/*' => Http::response(['error' => ['message' => 'دسترسی رد شد.']], 403),
        ]);

        $this->expectException(SeoGoogleException::class);
        $this->expectExceptionMessage('دسترسی رد شد.');

        (new UrlInspectionClient('token-123'))->inspect('https://ganjnameh.example.com/telecabin', 'https://ganjnameh.example.com/');
    }
}
