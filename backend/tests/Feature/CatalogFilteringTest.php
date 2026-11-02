<?php
namespace Tests\Feature;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CatalogFilteringTest extends TestCase {
    use RefreshDatabase;
    public function test_public_catalog_filters_by_name_description_and_price_range(): void {
        $match = Service::create(['code' => 'CAT-' . \Illuminate\Support\Str::random(8), 'name' => 'Kunjungan Ibu Nifas',
            'description' => 'Layanan pendampingan ibu', 'price_amount' => 35000, 'active' => true]);
        Service::create(['code' => 'CAT-' . \Illuminate\Support\Str::random(8), 'name' => 'Konsultasi Umum',
            'description' => 'Layanan kesehatan umum', 'price_amount' => 90000, 'active' => true]);
        Service::create(['code' => 'CAT-' . \Illuminate\Support\Str::random(8), 'name' => 'Ibu Tidak Aktif',
            'description' => 'Tidak ditampilkan', 'price_amount' => 20000, 'active' => false]);
        $this->getJson('/api/v1/services?search=ibu&min_price=30000&max_price=50000')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        $this->getJson('/api/v1/services?search=pendampingan')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }
    public function test_invalid_catalog_price_range_is_rejected(): void {
        $this->getJson('/api/v1/services?min_price=50000&max_price=10000')
            ->assertStatus(422)->assertJsonValidationErrors('max_price');
    }
}
