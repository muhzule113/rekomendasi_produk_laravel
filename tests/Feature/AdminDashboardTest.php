<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_chart_uses_six_months_ending_at_latest_transaction(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->insertTransactions($admin->id_user, '2025-01-31 12:00:00', 1);
        $this->insertTransactions($admin->id_user, '2025-02-10 12:00:00', 2);
        $this->insertTransactions($admin->id_user, '2025-04-15 12:00:00', 1);
        $this->insertTransactions($admin->id_user, '2025-07-23 12:00:00', 3);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('monthlyTransactions', function (Collection $months): bool {
                return $months->pluck('month_name')->all() === ['Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul']
                    && $months->pluck('count')->all() === [2, 0, 1, 0, 0, 3];
            });
    }

    private function insertTransactions(int $userId, string $date, int $count): void
    {
        foreach (range(1, $count) as $sequence) {
            DB::table('transactions')->insert([
                'id_user' => $userId,
                'kode_transaksi' => sprintf('TRX-%s-%d', str_replace(['-', ' ', ':'], '', $date), $sequence),
                'tanggal' => $date,
                'total' => 10000,
                'status_pembayaran' => 'Dibayar',
            ]);
        }
    }
}
