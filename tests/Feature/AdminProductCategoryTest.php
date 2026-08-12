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
            ->assertSee('category-picker-new-group', false)
            ->assertSee('Pilih kategori produk', false)
            ->assertSee('Tambah kategori baru', false)
            ->assertSee("selectNewCategory('add')", false)
            ->assertSee('id="add_id_category"', false)
            ->assertSee('id="edit_id_category"', false);
    }

    public function test_admin_can_create_a_category_while_creating_a_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.produk.store'), [
            'nama_product' => 'Nastar Premium',
            'id_category' => '__new__',
            'new_category_name' => 'Kue Kering',
            'harga' => 75000,
            'stok' => 12,
        ]);

        $response->assertRedirect(route('admin.produk'));
        $this->assertDatabaseHas('categories', ['nama_category' => 'Kue Kering']);

        $categoryId = DB::table('categories')->where('nama_category', 'Kue Kering')->value('id_category');
        $this->assertDatabaseHas('products', [
            'nama_product' => 'Nastar Premium',
            'id_category' => $categoryId,
        ]);
    }

    public function test_existing_category_is_reused_when_new_category_name_differs_only_by_case(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $categoryId = DB::table('categories')->insertGetId([
            'nama_category' => 'Kue Kering',
        ], 'id_category');

        $this->actingAs($admin)->post(route('admin.produk.store'), [
            'nama_product' => 'Kastengel',
            'id_category' => '__new__',
            'new_category_name' => 'kue kering',
            'harga' => 80000,
            'stok' => 8,
        ])->assertRedirect(route('admin.produk'));

        $this->assertSame(1, DB::table('categories')->count());
        $this->assertDatabaseHas('products', [
            'nama_product' => 'Kastengel',
            'id_category' => $categoryId,
        ]);
    }

    public function test_admin_can_create_a_category_while_updating_a_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $oldCategoryId = DB::table('categories')->insertGetId([
            'nama_category' => 'Lama',
        ], 'id_category');
        $productId = DB::table('products')->insertGetId([
            'id_category' => $oldCategoryId,
            'nama_product' => 'Produk Lama',
            'harga' => 10000,
            'stok' => 2,
            'status' => 'aktif',
        ], 'id_product');

        $this->actingAs($admin)->put(route('admin.produk.update', $productId), [
            'nama_product' => 'Produk Baru',
            'id_category' => '__new__',
            'new_category_name' => 'Kategori Baru',
            'harga' => 15000,
            'stok' => 5,
            'status' => 'aktif',
        ])->assertRedirect(route('admin.produk'));

        $newCategoryId = DB::table('categories')->where('nama_category', 'Kategori Baru')->value('id_category');
        $this->assertDatabaseHas('products', [
            'id_product' => $productId,
            'nama_product' => 'Produk Baru',
            'id_category' => $newCategoryId,
        ]);
    }
}
