<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationAndAuthRateLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_registration_logs_in_limited_customer_and_queues_verification_notification(): void
    {
        Queue::fake();

        $response = $this->postRegistration([
            'nama' => 'Pelanggan Baru',
            'email' => 'baru@example.com',
            'no_hp' => '081234567890',
            'alamat' => 'Jl. Sinar Manis No. 1',
            'password' => 'password',
            'konfirmasi' => 'password',
        ]);

        $user = User::where('email', 'baru@example.com')->firstOrFail();

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('users', [
            'id_user' => $user->id_user,
            'email_verified_at' => null,
        ]);

        Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job) use ($user): bool {
            return $job->notification instanceof VerifyEmailNotification
                && $job->notifiables->first()->is($user);
        });
    }

    public function test_registration_validation_and_duplicate_input_are_rejected(): void
    {
        User::factory()->create(['email' => 'duplikat@example.com']);

        $this->postRegistration([
            'nama' => '',
            'email' => 'bukan-email',
            'no_hp' => '',
            'alamat' => '',
            'password' => 'password',
            'konfirmasi' => 'berbeda',
        ])->assertRedirect()->assertSessionHasErrors(['nama', 'email', 'no_hp', 'alamat', 'konfirmasi']);

        $this->postRegistration([
            'nama' => 'Pelanggan Duplikat',
            'email' => 'duplikat@example.com',
            'no_hp' => '081234567890',
            'alamat' => 'Alamat',
            'password' => 'password',
            'konfirmasi' => 'password',
        ])->assertRedirect()->assertSessionHasErrors('email');
    }

    public function test_valid_signed_verification_marks_email_verified_emits_event_and_is_idempotent(): void
    {
        $customer = User::factory()->unverified()->create();
        $url = $this->verificationUrl($customer);
        Event::fake([Verified::class]);

        $this->actingAs($customer)
            ->get($url)
            ->assertRedirect(route('produk'))
            ->assertSessionHas('success');

        $this->assertNotNull($customer->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class, fn (Verified $event): bool => $event->user->is($customer));

        $this->get($url)->assertRedirect(route('produk'));
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_guest_verification_redirects_to_login_and_returns_to_signed_flow(): void
    {
        $customer = User::factory()->unverified()->create(['password' => Hash::make('password')]);
        $url = $this->verificationUrl($customer);

        $this->get($url)->assertRedirect(route('login'));

        $this->post(route('login.post'), [
            'email' => $customer->email,
            'password' => 'password',
            'role' => 'pelanggan',
        ])->assertRedirect($url);

        $this->get($url)->assertRedirect(route('produk'));
        $this->assertNotNull($customer->fresh()->email_verified_at);
    }

    public function test_expired_tampered_and_matching_account_links_are_actionable_and_do_not_verify(): void
    {
        $customer = User::factory()->unverified()->create();
        $otherCustomer = User::factory()->unverified()->create();
        $url = $this->verificationUrl($customer);

        $this->actingAs($otherCustomer)
            ->get($url)
            ->assertStatus(403)
            ->assertViewIs('auth.verification-invalid')
            ->assertSee('akun yang sesuai');

        $this->assertNull($customer->fresh()->email_verified_at);

        $this->actingAs($customer)
            ->get($url . '&tainted=1')
            ->assertStatus(403)
            ->assertSee('kedaluwarsa atau tidak valid')
            ->assertSee('Minta Tautan Baru');

        $createdAt = now();
        $expiredUrl = URL::temporarySignedRoute(
            'verification.verify',
            $createdAt->copy()->addMinutes(60),
            ['id' => $customer->id_user, 'hash' => sha1($customer->email)]
        );
        $this->travelTo($createdAt->copy()->addMinutes(61));

        $this->get($expiredUrl)
            ->assertStatus(403)
            ->assertSee('kedaluwarsa atau tidak valid');

        $this->travelBack();
        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_verification_notice_and_resend_are_localized_and_verified_customer_is_not_resent(): void
    {
        $customer = User::factory()->unverified()->create();
        Queue::fake();

        $this->actingAs($customer)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Verifikasi Email Anda')
            ->assertSee('60 menit');

        $this->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status');

        Queue::assertPushed(SendQueuedNotifications::class);

        Queue::fake();
        $verifiedCustomer = User::factory()->verified()->create();
        $this->actingAs($verifiedCustomer)
            ->post(route('verification.send'))
            ->assertRedirect(route('produk'))
            ->assertSessionHas('status');

        Queue::assertNothingPushed();
    }

    public function test_verification_notification_has_brand_indonesian_copy_and_sixty_minute_signed_url(): void
    {
        $customer = User::factory()->unverified()->create(['email' => 'mail@example.com']);
        $mail = (new VerifyEmailNotification)->toMail($customer);

        $this->assertSame('Verifikasi Email Pelanggan — Toko Sinar Manis', $mail->subject);
        $this->assertSame('Verifikasi Email', $mail->actionText);
        $this->assertSame('Toko Sinar Manis', $mail->from[1]);
        $this->assertStringContainsString('Toko Sinar Manis', implode(' ', $mail->introLines));
        $this->assertStringContainsString('berlaku selama 60 menit', implode(' ', $mail->outroLines));

        parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);
        $this->assertStringContainsString('/email/verify/' . $customer->id_user . '/', (string) parse_url($mail->actionUrl, PHP_URL_PATH));
        $this->assertArrayHasKey('expires', $query);
        $this->assertArrayHasKey('signature', $query);
        $this->assertEqualsWithDelta(now()->addMinutes(60)->timestamp, (int) $query['expires'], 5);
    }

    public function test_resend_has_one_per_minute_and_five_per_hour_limits_with_customer_isolation(): void
    {
        $customer = User::factory()->unverified()->create();
        $otherCustomer = User::factory()->unverified()->create();

        $this->actingAs($customer)->post(route('verification.send'))->assertRedirect();
        $this->post(route('verification.send'))->assertStatus(429)->assertSee('Terlalu banyak permintaan');

        $this->travel(61)->seconds();
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post(route('verification.send'))->assertRedirect();
            $this->travel(61)->seconds();
        }

        $this->post(route('verification.send'))->assertStatus(429)->assertSee('Terlalu banyak permintaan');

        $this->actingAs($otherCustomer)
            ->post(route('verification.send'))
            ->assertRedirect(route('verification.notice'));

        $this->travelBack();
    }

    public function test_unverified_customers_keep_public_access_but_protected_features_use_verification_notice(): void
    {
        $catalog = $this->seedCatalog();
        $customer = User::factory()->unverified()->create();

        $this->actingAs($customer)
            ->get(route('home'))
            ->assertOk();
        $this->get(route('produk'))->assertOk();
        $this->get(route('produk.detail', $catalog['A']))->assertOk();
        $this->get(route('keranjang'))->assertOk();
        $this->get(route('rekomendasi'))->assertOk();

        $this->get(route('checkout'))->assertRedirect(route('verification.notice'));
        $this->get(route('riwayat'))->assertRedirect(route('verification.notice'));
        $this->postJson(route('api.review.store'), [
            'id_product' => $catalog['A'],
            'rating' => 5,
        ])->assertForbidden();
        $this->postJson('/api/checkout')->assertForbidden();
        $this->postJson(route('verify.payment'), ['order_id' => 'missing'])->assertForbidden();
    }

    public function test_verified_customer_and_admin_can_use_protected_entry_points(): void
    {
        $catalog = $this->seedCatalog();
        $customer = User::factory()->verified()->create();

        $this->actingAs($customer)
            ->withSession(['cart' => [$catalog['A'] => 1]])
            ->get(route('checkout'))
            ->assertOk();
        $this->get(route('riwayat'))->assertOk();
        $this->postJson(route('api.review.store'), [
            'id_product' => $catalog['A'],
            'rating' => 5,
            'komentar' => 'Bagus',
        ])->assertOk()->assertJson(['status' => true]);
        $this->postJson(route('verify.payment'), ['order_id' => 'missing'])->assertStatus(404);

        $admin = User::factory()->admin()->unverified()->create();
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
        $this->postJson(route('verify.payment'), ['order_id' => 'missing'])->assertStatus(404);
    }

    public function test_unverified_recommendations_are_public_and_do_not_create_identity_logs(): void
    {
        $this->seedCatalog();
        $customer = User::factory()->unverified()->create();

        $this->actingAs($customer)
            ->get(route('rekomendasi'))
            ->assertOk()
            ->assertViewHas('isVerifiedCustomer', false);
        $this->getJson('/api/rekomendasi?action=personal')
            ->assertOk()
            ->assertJsonPath('method', 'Cold Start - Popularitas/Rating (bukan CF)');

        $this->assertDatabaseCount('recommendation_logs', 0);

        $this->getJson('/api/rekomendasi?action=similar&id_product=1')->assertOk();
        $this->assertDatabaseCount('recommendation_logs', 0);
    }

    public function test_verified_recommendations_can_use_identity_and_log_results(): void
    {
        $this->seedCatalog();
        $customer = User::factory()->verified()->create();

        $this->actingAs($customer)
            ->getJson('/api/rekomendasi?action=personal')
            ->assertOk();

        $this->assertDatabaseHas('recommendation_logs', ['id_user' => $customer->id_user]);
    }

    public function test_registration_limits_count_invalid_requests_by_ip_and_recover_after_ten_minutes(): void
    {
        $payload = [
            'nama' => '',
            'email' => 'invalid',
            'no_hp' => '',
            'alamat' => '',
            'password' => 'x',
            'konfirmasi' => 'y',
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postRegistration($payload, '192.0.2.10')->assertStatus(302);
        }

        $this->postRegistration($payload, '192.0.2.10')
            ->assertStatus(429)
            ->assertSee('Terlalu banyak pendaftaran')
            ->assertHeader('Retry-After');

        $this->postRegistration($payload, '192.0.2.11')->assertStatus(302);

        $this->travel(601)->seconds();
        $this->postRegistration($payload, '192.0.2.10')->assertStatus(302);
        $this->travelBack();
    }

    public function test_registration_daily_limit_is_independent_from_ten_minute_limit(): void
    {
        $payload = [
            'nama' => '',
            'email' => 'invalid',
            'no_hp' => '',
            'alamat' => '',
            'password' => 'x',
            'konfirmasi' => 'y',
        ];

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postRegistration($payload, '198.51.100.10')->assertStatus(302);
            $this->travel(601)->seconds();
        }

        $this->postRegistration($payload, '198.51.100.10')
            ->assertStatus(429)
            ->assertSee('Terlalu banyak pendaftaran');

        $this->travelBack();
    }

    public function test_failed_login_limit_uses_normalized_email_and_ip_and_success_clears_counter(): void
    {
        $customer = User::factory()->verified()->create([
            'email' => 'login@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postLogin('LOGIN@EXAMPLE.COM', 'wrong-password', '192.0.2.20')->assertStatus(302);
        }

        $this->postLogin('login@example.com', 'wrong-password', '192.0.2.20')
            ->assertStatus(429)
            ->assertSee('Terlalu banyak percobaan login');
        $this->postLogin('login@example.com', 'wrong-password', '192.0.2.21')->assertStatus(302);

        Cache::flush();
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postLogin('login@example.com', 'wrong-password', '192.0.2.22')->assertStatus(302);
        }

        $this->postLogin('login@example.com', 'correct-password', '192.0.2.22')
            ->assertRedirect(route('produk'));
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->postLogin('login@example.com', 'wrong-password', '192.0.2.22')->assertStatus(302);
    }

    public function test_failed_login_counts_role_mismatch_and_inactive_account_and_recovers_after_five_minutes(): void
    {
        $customer = User::factory()->verified()->create(['email' => 'role@example.com']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postLogin('role@example.com', 'password', '198.51.100.20', 'admin')->assertStatus(302);
        }

        $this->postLogin('role@example.com', 'password', '198.51.100.20', 'admin')->assertStatus(429);

        Cache::flush();
        $customer->update(['status' => 'nonaktif']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postLogin('role@example.com', 'password', '198.51.100.21')->assertStatus(302);
        }
        $this->postLogin('role@example.com', 'password', '198.51.100.21')->assertStatus(429);

        Cache::flush();
        $this->travel(301)->seconds();
        $this->postLogin('role@example.com', 'password', '198.51.100.21')->assertStatus(302);
        $this->travelBack();
    }

    public function test_validation_failures_do_not_consume_failed_login_counter(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('login.post'), [
                'email' => 'not-an-email',
                'password' => '',
                'role' => 'pelanggan',
            ])->assertStatus(302)->assertSessionHasErrors();
        }

        User::factory()->verified()->create([
            'email' => 'validation@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->postLogin('validation@example.com', 'wrong-password', '203.0.113.30')->assertStatus(302);
    }

    public function test_email_verification_migration_backfills_existing_customers_and_keeps_column_nullable(): void
    {
        $migration = require base_path('database/migrations/2026_08_13_000000_add_email_verified_at_to_users_table.php');
        $migration->down();

        $customerId = DB::table('users')->insertGetId([
            'nama' => 'Pelanggan Lama',
            'email' => 'lama@example.com',
            'password' => Hash::make('password'),
            'role' => 'pelanggan',
            'status' => 'aktif',
            'created_at' => now(),
        ], 'id_user');
        $adminId = DB::table('users')->insertGetId([
            'nama' => 'Admin Lama',
            'email' => 'admin-lama@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'aktif',
            'created_at' => now(),
        ], 'id_user');

        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'email_verified_at'));
        $this->assertNotNull(DB::table('users')->where('id_user', $customerId)->value('email_verified_at'));
        $this->assertNull(DB::table('users')->where('id_user', $adminId)->value('email_verified_at'));

        $futureId = DB::table('users')->insertGetId([
            'nama' => 'Pelanggan Baru',
            'email' => 'future@example.com',
            'password' => Hash::make('password'),
            'role' => 'pelanggan',
            'status' => 'aktif',
            'email_verified_at' => null,
            'created_at' => now(),
        ], 'id_user');

        $this->assertNull(DB::table('users')->where('id_user', $futureId)->value('email_verified_at'));
    }

    private function verificationUrl(User $customer): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $customer->id_user, 'hash' => sha1($customer->email)]
        );
    }

    private function postRegistration(array $payload, string $ip = '192.0.2.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('register.post'), $payload);
    }

    private function postLogin(string $email, string $password, string $ip, string $role = 'pelanggan')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login.post'), compact('email', 'password', 'role'));
    }

    private function seedCatalog(): array
    {
        $categoryId = DB::table('categories')->insertGetId(['nama_category' => 'Sembako']);
        $products = [];

        foreach (['A', 'B', 'C'] as $index => $name) {
            $products[$name] = DB::table('products')->insertGetId([
                'id_category' => $categoryId,
                'nama_product' => 'Produk ' . $name,
                'deskripsi' => 'Deskripsi ' . $name,
                'harga' => 10000 + $index * 1000,
                'stok' => 10,
                'terjual' => $index,
                'status' => 'aktif',
                'created_at' => now(),
            ]);
        }

        return $products;
    }
}
