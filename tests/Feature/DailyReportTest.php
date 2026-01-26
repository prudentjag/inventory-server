<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Inventory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockRequest;
use App\Models\DailyReport;
use App\Models\DailyReportItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $unit;
    protected $brand;
    protected $category;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create(['role' => 'staff']);
        $this->unit = Unit::create(['name' => 'Main Store']);
        $this->category = Category::create(['name' => 'Beverages']);
        $this->brand = Brand::create(['name' => 'Coca-Cola', 'category_id' => $this->category->id]);
        $this->product = Product::create([
            'name' => 'Coke',
            'brand_id' => $this->brand->id,
            'sku' => 'COKE-001',
            'unit_of_measurement' => 'bottle',
            'cost_price' => 100,
            'selling_price' => 150,
            'product_type' => 'individual',
        ]);

        // Add product to unit inventory
        Inventory::create([
            'unit_id' => $this->unit->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
        ]);

        // Assign user to the unit (required by unit_access middleware)
        $this->user->units()->attach($this->unit->id);
    }

    public function test_user_can_generate_daily_report()
    {
        $response = $this->actingAs($this->user)->postJson('/api/daily-reports/generate', [
            'unit_id' => $this->unit->id,
            'remark' => 'All items accounted for',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.user_id', $this->user->id);
        $response->assertJsonPath('data.unit_id', $this->unit->id);
        $response->assertJsonPath('data.remark', 'All items accounted for');
        $response->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('daily_reports', [
            'user_id' => $this->user->id,
            'unit_id' => $this->unit->id,
        ]);
    }

    public function test_report_calculates_sales_correctly()
    {
        // Create a sale for today
        $sale = Sale::create([
            'unit_id' => $this->unit->id,
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-TEST001',
            'total_amount' => 750,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 150,
            'total_price' => 750,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/daily-reports/generate', [
            'unit_id' => $this->unit->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.total_sales_amount', '750.00');
        $response->assertJsonPath('data.total_items_sold', 5);

        // Check item-level data
        $report = DailyReport::first();
        $item = $report->items->first();
        $this->assertEquals(5, $item->quantity_sold);
    }

    public function test_report_includes_damages()
    {
        $response = $this->actingAs($this->user)->postJson('/api/daily-reports/generate', [
            'unit_id' => $this->unit->id,
            'damages' => [
                $this->product->id => 3,
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.total_damages', 3);

        $report = DailyReport::first();
        $item = $report->items->first();
        $this->assertEquals(3, $item->damages);
    }

    public function test_user_cannot_generate_duplicate_report_same_day()
    {
        // Generate first report
        $this->actingAs($this->user)->postJson('/api/daily-reports/generate', [
            'unit_id' => $this->unit->id,
        ]);

        // Try to generate second report
        $response = $this->actingAs($this->user)->postJson('/api/daily-reports/generate', [
            'unit_id' => $this->unit->id,
        ]);

        $response->assertStatus(400);
        // Either our custom message or SQL constraint violation (both indicate duplicate prevention)
        $this->assertTrue(
            str_contains($response->json('message'), 'already been generated') ||
            str_contains($response->json('message'), 'UNIQUE constraint')
        );
    }

    public function test_user_can_add_remark_to_report()
    {
        // Generate report
        $this->actingAs($this->user)->postJson('/api/daily-reports/generate', [
            'unit_id' => $this->unit->id,
        ]);

        $report = DailyReport::first();

        // Update remark
        $response = $this->actingAs($this->user)->patchJson("/api/daily-reports/{$report->id}/remark", [
            'remark' => 'Updated remark after review',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.remark', 'Updated remark after review');
    }

    public function test_user_can_list_their_reports()
    {
        // Generate a report
        $this->actingAs($this->user)->postJson('/api/daily-reports/generate', [
            'unit_id' => $this->unit->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/daily-reports?unit_id={$this->unit->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.data');
    }

    public function test_user_can_view_report_details()
    {
        // Generate a report
        $this->actingAs($this->user)->postJson('/api/daily-reports/generate', [
            'unit_id' => $this->unit->id,
        ]);

        $report = DailyReport::first();

        $response = $this->actingAs($this->user)->getJson("/api/daily-reports/{$report->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'user_id',
                'unit_id',
                'report_date',
                'total_sales_amount',
                'total_items_sold',
                'total_damages',
                'remark',
                'status',
                'items' => [
                    '*' => [
                        'product_id',
                        'opening_stock',
                        'stock_received',
                        'quantity_sold',
                        'damages',
                        'closing_stock',
                        'product',
                    ]
                ],
                'user',
                'unit',
            ]
        ]);
    }

    public function test_closing_stock_reflects_current_inventory()
    {
        $response = $this->actingAs($this->user)->postJson('/api/daily-reports/generate', [
            'unit_id' => $this->unit->id,
        ]);

        $response->assertStatus(201);

        $report = DailyReport::first();
        $item = $report->items->first();

        // Closing stock should match current inventory
        $this->assertEquals(50, $item->closing_stock);
    }
}
