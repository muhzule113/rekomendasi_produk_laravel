<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUploadHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_history_pages_are_scoped_to_their_upload_source(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $transactionUpload = $this->createUpload($admin->id_user, 'transaksi', 'transaksi.csv');
        $productUpload = $this->createUpload($admin->id_user, 'produk', 'produk.csv');

        $this->actingAs($admin)
            ->get(route('admin.upload-history.transaksi'))
            ->assertOk()
            ->assertSee('Riwayat Upload Transaksi')
            ->assertSee('transaksi.csv')
            ->assertDontSee('produk.csv')
            ->assertSee(route('admin.upload-history.transaksi', ['id' => $transactionUpload]), false);

        $this->actingAs($admin)
            ->get(route('admin.upload-history.produk'))
            ->assertOk()
            ->assertSee('Riwayat Upload Produk')
            ->assertSee('produk.csv')
            ->assertDontSee('transaksi.csv')
            ->assertSee(route('admin.upload-history.produk', ['id' => $productUpload]), false);
    }

    public function test_detail_page_cannot_show_an_upload_from_the_other_source(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $transactionUpload = $this->createUpload($admin->id_user, 'transaksi', 'transaksi.csv');
        $productUpload = $this->createUpload($admin->id_user, 'produk', 'produk.csv');

        $this->actingAs($admin)
            ->get(route('admin.upload-history.produk', ['id' => $transactionUpload]))
            ->assertOk()
            ->assertSee('Data Tidak Ditemukan')
            ->assertDontSee('transaksi.csv');

        $this->actingAs($admin)
            ->get(route('admin.upload-history.transaksi', ['id' => $productUpload]))
            ->assertOk()
            ->assertSee('Data Tidak Ditemukan')
            ->assertDontSee('produk.csv');
    }

    public function test_legacy_history_url_redirects_to_the_matching_source_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $productUpload = $this->createUpload($admin->id_user, 'produk', 'produk.csv');

        $this->actingAs($admin)
            ->get(route('admin.upload-history'))
            ->assertRedirect(route('admin.upload-history.transaksi'));

        $this->actingAs($admin)
            ->get(route('admin.upload-history') . '?id=' . $productUpload)
            ->assertRedirect(route('admin.upload-history.produk', ['id' => $productUpload]));
    }

    public function test_sidebar_hides_history_and_upload_modals_link_to_the_matching_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.transaksi'))
            ->assertOk()
            ->assertSee(route('admin.upload-history.transaksi'), false)
            ->assertSee('btn-upload', false)
            ->assertDontSee('btn-sm btn-gold', false)
            ->assertDontSee('> Riwayat Upload<', false);

        $this->actingAs($admin)
            ->get(route('admin.produk'))
            ->assertOk()
            ->assertSee(route('admin.upload-history.produk'), false)
            ->assertSee('btn-upload', false)
            ->assertDontSee('btn-sm btn-gold', false)
            ->assertDontSee('> Riwayat Upload<', false);

        $this->actingAs($admin)
            ->get(route('admin.upload-history.transaksi'))
            ->assertOk()
            ->assertSee('btn-upload', false)
            ->assertDontSee('btn-gold', false);
    }

    public function test_deleting_upload_redirects_to_its_source_history_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $productUpload = $this->createUpload($admin->id_user, 'produk', 'produk.csv');

        $this->actingAs($admin)
            ->delete(route('admin.upload-history.destroy', ['id' => $productUpload]))
            ->assertRedirect(route('admin.upload-history.produk'));

        $this->assertDatabaseMissing('data_uploads', ['id_upload' => $productUpload]);
    }

    private function createUpload(int $userId, string $source, string $filename, string $status = 'selesai'): int
    {
        return (int) DB::table('data_uploads')->insertGetId([
            'id_user' => $userId,
            'sumber' => $source,
            'nama_file_asli' => $filename,
            'nama_file_disk' => $filename,
            'tipe_file' => 'csv',
            'ukuran_kb' => 1,
            'path_file' => storage_path('app/uploads/processed/' . $filename),
            'status' => $status,
            'total_baris' => 2,
            'baris_valid' => 2,
            'baris_invalid' => 0,
            'baris_duplikat' => 0,
            'baris_diimport' => 2,
        ], 'id_upload');
    }
}
