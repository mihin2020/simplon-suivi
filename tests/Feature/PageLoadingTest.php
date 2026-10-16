<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PageLoadingTest extends TestCase
{
    public function test_the_whatsapp_page_no_longer_waits_for_the_whatsapp_service(): void
    {
        Http::fake();

        $this->actingAsSuperAdmin()
            ->get('/communication/whatsapp')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Communication/WhatsApp')->where('initialWaStatus', null));

        // The status is fetched by the page afterwards, not while building it.
        // (Only the dev server-side-rendering probe of Inertia may show up.)
        Http::assertNotSent(fn ($request) => ! str_contains($request->url(), '__inertia_ssr'));
    }

    public function test_reference_data_urls_without_a_list_page_no_longer_crash(): void
    {
        $user = $this->createSuperAdmin();

        foreach (['/age-ranges', '/education-levels', '/vulnerabilities', '/last-diplomas'] as $uri) {
            $this->actingAs($user)->get($uri)->assertStatus(405);
        }
    }
}
