<?php

namespace Tests\Feature;

use App\Models\ActivationToken;
use App\Rules\StrongPassword;
use App\Services\RemoteImageFetcher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The developer's .env may allow private hosts: tests state the protection explicitly.
        config(['services.remote_images.allow_private_hosts' => false]);
    }

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_password_reset_requests_are_limited_to_three_per_ten_minutes(): void
    {
        RateLimiter::clear('reset-email|someone@example.test|127.0.0.1');

        foreach (range(1, 3) as $attempt) {
            $this->post(route('password.email'), ['email' => 'someone@example.test'])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'someone@example.test'])->assertStatus(429);
    }

    public function test_strong_password_rule(): void
    {
        $passes = fn (string $password) => ! Validator::make(['p' => $password], ['p' => [new StrongPassword]])->fails();

        $this->assertTrue($passes('Simplon#2026'));
        $this->assertTrue($passes('Éducation9!'));
        $this->assertTrue($passes('short1!A'));
        $this->assertFalse($passes('Short1!'));
        $this->assertFalse($passes('nouppercase1!'));
        $this->assertFalse($passes('NoDigitHere!'));
        $this->assertFalse($passes('NoSpecial123'));
    }

    public function test_weak_passwords_are_refused_everywhere(): void
    {
        $user = $this->createAdmin();
        $plain = 'reset-token-plain';
        ActivationToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plain),
            'type' => 'password_reset',
            'expires_at' => now()->addHour(),
        ]);

        foreach (['password', 'Password1', 'password1!', 'Ab1!'] as $weak) {
            $this->post(route('password.update', $plain), ['password' => $weak, 'password_confirmation' => $weak])
                ->assertSessionHasErrors('password');
        }

        $this->actingAs($user)
            ->put(route('profile.password'), ['current_password' => 'x', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertSessionHasErrors('password');
    }

    public function test_strong_password_resets_the_account(): void
    {
        $user = $this->createAdmin();
        $plain = 'another-token-plain';
        ActivationToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plain),
            'type' => 'password_reset',
            'expires_at' => now()->addHour(),
        ]);

        $this->post(route('password.update', $plain), ['password' => 'Simplon#2026', 'password_confirmation' => 'Simplon#2026'])
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Simplon#2026', $user->fresh()->password));
    }

    public function test_remote_images_never_reach_private_or_unusual_targets(): void
    {
        Http::fake();
        $fetcher = new RemoteImageFetcher;

        foreach ([
            'http://127.0.0.1/a.png',
            'http://localhost/a.png',
            'http://10.0.0.5/a.png',
            'http://192.168.1.10/a.png',
            'http://169.254.169.254/latest/meta-data',
            'http://[::1]/a.png',
            'http://93.184.216.34:8080/a.png',
            'http://user:pw@93.184.216.34/a.png',
            'file:///etc/passwd',
            'ftp://93.184.216.34/a.png',
            'http://does-not-resolve.invalid/a.png',
        ] as $url) {
            $this->assertNull($fetcher->fetch($url), $url);
        }

        Http::assertNothingSent();
    }

    public function test_a_public_image_is_downloaded_and_identified_by_its_content(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response(base64_decode(self::PNG), 200, ['Content-Type' => 'text/html'])]);

        $image = (new RemoteImageFetcher)->fetch('http://93.184.216.34/photo.jpg');

        $this->assertSame('png', $image['extension']);
    }

    public function test_a_non_image_body_is_refused(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response('<html><script>alert(1)</script></html>', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->assertNull((new RemoteImageFetcher)->fetch('http://93.184.216.34/photo.jpg'));
    }

    public function test_a_redirect_to_a_private_address_is_refused(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data'])]);

        $this->assertNull((new RemoteImageFetcher)->fetch('http://93.184.216.34/photo.jpg'));
        Http::assertSentCount(1);
    }

    public function test_a_redirect_to_a_public_image_is_followed(): void
    {
        Http::fake([
            '93.184.216.34/start' => Http::response('', 302, ['Location' => 'http://93.184.216.35/final.png']),
            '93.184.216.35/*' => Http::response(base64_decode(self::PNG), 200),
        ]);

        $this->assertSame('png', (new RemoteImageFetcher)->fetch('http://93.184.216.34/start')['extension']);
    }

    public function test_private_hosts_can_be_allowed_for_local_development(): void
    {
        config(['services.remote_images.allow_private_hosts' => true]);
        Http::fake(['*' => Http::response(base64_decode(self::PNG), 200)]);

        $fetcher = new RemoteImageFetcher;

        $this->assertSame('png', $fetcher->fetch('http://127.0.0.1:8000/storage/photo.png')['extension']);
        $this->assertSame('png', $fetcher->fetch('http://localhost/photo.png')['extension']);
        // Still http(s) only, even in development.
        $this->assertNull($fetcher->fetch('file:///etc/passwd'));
    }

    private function policy(?string $header = 'Content-Security-Policy'): array
    {
        $directives = [];

        foreach (explode(';', (string) $this->get(route('login'))->headers->get($header)) as $part) {
            $tokens = preg_split('/\s+/', trim($part));

            if ($tokens[0] !== '') {
                $directives[array_shift($tokens)] = $tokens;
            }
        }

        return $directives;
    }

    public function test_content_security_policy_covers_every_external_source_the_app_uses(): void
    {
        $csp = $this->policy();

        $this->assertSame(["'self'"], $csp['default-src']);
        $this->assertContains('https://upload-widget.cloudinary.com', $csp['script-src']);
        $this->assertContains('https://fonts.googleapis.com', $csp['style-src']);
        $this->assertContains('https://fonts.gstatic.com', $csp['font-src']);
        $this->assertContains('https://api.qrserver.com', $csp['connect-src']);
        $this->assertContains('https://*.cloudinary.com', $csp['connect-src']);
        $this->assertContains('https://*.cloudinary.com', $csp['frame-src']);
        $this->assertContains('https:', $csp['img-src']);
        $this->assertContains('blob:', $csp['img-src']);
        $this->assertSame(["'none'"], $csp['object-src']);
        $this->assertSame(["'self'"], $csp['frame-ancestors']);
    }

    public function test_scripts_can_never_be_inline_or_evaluated(): void
    {
        $scripts = $this->policy()['script-src'];

        $this->assertNotContains("'unsafe-inline'", $scripts);
        $this->assertNotContains("'unsafe-eval'", $scripts);
        $this->assertNotContains('https:', $scripts);
        $this->assertNotContains('*', $scripts);
    }

    public function test_the_html_shell_has_no_executable_inline_script(): void
    {
        $html = $this->get(route('login'))->getContent();

        preg_match_all('/<script([^>]*)>/i', $html, $tags);

        foreach ($tags[1] as $attributes) {
            $this->assertTrue(
                str_contains($attributes, 'src=') || str_contains($attributes, 'application/json') || str_contains($attributes, 'application/ld+json'),
                'Inline executable script found, it would be blocked by the CSP: <script'.$attributes.'>',
            );
        }

        $this->assertDoesNotMatchRegularExpression('/\son(click|load|error|mouseover|submit|focus)\s*=/i', $html);
    }

    public function test_csp_is_not_sent_with_non_html_responses(): void
    {
        $response = $this->actingAsSuperAdmin()->getJson(route('chatbot.status'));

        $response->assertOk();
        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_csp_mode_can_be_switched_to_report_only_or_off(): void
    {
        config(['security.csp_mode' => 'report-only']);
        $response = $this->get(route('login'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));

        config(['security.csp_mode' => 'off']);
        $response = $this->get(route('login'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_vite_dev_server_is_allowed_only_while_it_runs(): void
    {
        // The real hot file exists whenever `npm run dev` is running on the machine.
        Vite::useHotFile(sys_get_temp_dir().'/no-vite-dev-server-'.uniqid());

        $this->assertNotContains('http://localhost:5173', $this->policy()['script-src']);

        $hot = tempnam(sys_get_temp_dir(), 'hot');
        file_put_contents($hot, 'http://localhost:5173');
        Vite::useHotFile($hot);

        $csp = $this->policy();
        unlink($hot);

        $this->assertContains('http://localhost:5173', $csp['script-src']);
        $this->assertContains('http://localhost:5173', $csp['style-src']);
        $this->assertContains('ws://localhost:5173', $csp['connect-src']);
    }

    /** Vite listens on [::1] on many machines; a CSP source cannot hold an IPv6 literal, which blanked the login page. */
    public function test_vite_dev_server_on_ipv6_is_allowed_without_an_invalid_source(): void
    {
        $hot = tempnam(sys_get_temp_dir(), 'hot');
        file_put_contents($hot, 'http://[::1]:5173');
        Vite::useHotFile($hot);

        $csp = $this->policy();
        unlink($hot);

        foreach (['script-src', 'style-src', 'font-src'] as $directive) {
            $this->assertContains('http://*:5173', $csp[$directive]);
            $this->assertNotContains('http://[::1]:5173', $csp[$directive]);
        }

        $this->assertContains('ws://*:5173', $csp['connect-src']);
        $this->assertNotContains('ws://[::1]:5173', $csp['connect-src']);
    }

    public function test_camera_is_delegated_to_the_cloudinary_widget_only(): void
    {
        $policy = $this->get(route('login'))->headers->get('Permissions-Policy');

        $this->assertStringContainsString('camera=(self "https://upload-widget.cloudinary.com"', $policy);
        $this->assertStringContainsString('geolocation=()', $policy);
    }
}
