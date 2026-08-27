<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_product_forms_render_a_styled_category_picker_for_add_and_edit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DB::table('categories')->insert(['nama_category' => 'Kue Kering']);

        $this->actingAs($admin)
            ->get(route('admin.produk'))
            ->assertOk()
            ->assertSee('category-picker', false)
            ->assertSee('category-picker-icon', false)
            ->assertSee('category-picker-select', false)
            ->assertSee('Pilih kategori produk', false)
            ->assertDontSee('category-picker-new-group', false)
            ->assertDontSee('Tambah kategori baru', false)
            ->assertDontSee('new_category_name', false)
            ->assertSee('id="add_id_category"', false)
            ->assertSee('id="edit_id_category"', false);
    }

    public function test_product_creation_rejects_inline_category_creation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.produk.store'), [
            'nama_product' => 'Nastar Premium',
            'id_category' => '__new__',
            'new_category_name' => 'Kue Kering',
            'harga' => 75000,
            'stok' => 12,
        ])->assertSessionHasErrors('id_category');

        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_admin_can_crud_an_unused_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.kategori.store'), ['nama_category' => 'Lama'])
            ->assertRedirect(route('admin.kategori'));

        $categoryId = DB::table('categories')->where('nama_category', 'Lama')->value('id_category');

        $this->actingAs($admin)
            ->get(route('admin.kategori'))
            ->assertOk()
            ->assertSee('Kelola Kategori')
            ->assertSee('Lama');

        $this->actingAs($admin)
            ->put(route('admin.kategori.update', $categoryId), ['nama_category' => 'Baru'])
            ->assertRedirect(route('admin.kategori'));

        $this->assertDatabaseHas('categories', [
            'id_category' => $categoryId,
            'nama_category' => 'Baru',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.kategori.destroy', $categoryId))
            ->assertRedirect(route('admin.kategori'));

        $this->assertDatabaseMissing('categories', ['id_category' => $categoryId]);
    }

    public function test_category_cannot_be_deleted_while_used_by_a_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $categoryId = DB::table('categories')->insertGetId(['nama_category' => 'Dipakai'], 'id_category');
        DB::table('products')->insert([
            'id_category' => $categoryId,
            'nama_product' => 'Produk',
            'harga' => 10000,
            'stok' => 1,
            'status' => 'aktif',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.kategori.destroy', $categoryId))
            ->assertRedirect(route('admin.kategori'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', [
            'id_category' => $categoryId,
            'nama_category' => 'Dipakai',
        ]);
    }

    public function test_category_name_must_be_unique_without_case_difference(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        DB::table('categories')->insert(['nama_category' => 'ATK']);

        $this->actingAs($admin)
            ->post(route('admin.kategori.store'), ['nama_category' => 'atk'])
            ->assertSessionHasErrors('nama_category');

        $this->assertSame(1, DB::table('categories')->count());
    }
}
