<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_admin_can_sign_in_by_username_without_exposing_the_password(): void
    {
        config([
            'admin.username' => 'demgod',
            'admin.email' => 'admin@example.test',
            'admin.password' => 'a-strong-admin-password',
        ]);

        $this->seed(AdminUserSeeder::class);
        $admin = User::where('username', 'demgod')->firstOrFail();

        $this->assertTrue(Hash::check('a-strong-admin-password', $admin->password));

        $this->postJson('/api/auth/login', [
            'username' => 'demgod',
            'password' => 'a-strong-admin-password',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonMissingPath('user.password');

        $this->postJson('/api/auth/login', [
            'username' => 'demgod',
            'password' => 'incorrect-password',
        ])->assertUnprocessable();
    }

    public function test_admin_apis_reject_guests_and_customer_accounts(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
        $this->getJson('/api/admin/reports')->assertUnauthorized();
        $this->getJson('/api/admin/reviews')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/dashboard')->assertForbidden();
        $this->getJson('/api/admin/reports')->assertForbidden();
        $this->getJson('/api/admin/reviews')->assertForbidden();
    }

    public function test_admin_dashboard_inventory_adjustment_order_status_and_review_moderation_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        Sanctum::actingAs($admin);

        $category = Category::create([
            'name' => 'Aquatic Plants',
            'slug' => 'admin-aquatic-plants',
            'status' => true,
        ]);
        $product = Product::create([
            'name' => 'Java Fern',
            'sku' => 'ADMIN-JAVA-FERN',
            'category_id' => $category->id,
            'price' => 150,
            'stock_quantity' => 3,
            'status' => true,
        ]);
        $inventory = $product->inventory()->firstOrFail();
        $inventory->update(['reorder_level' => 5]);

        $order = Order::create([
            'order_number' => 'ORD-ADMIN-TEST-1',
            'user_id' => $customer->id,
            'subtotal' => 150,
            'shipping_fee' => 0,
            'tax' => 18,
            'total' => 168,
            'status' => 'pending',
            'payment_method' => 'gcash',
            'shipping_name' => $customer->full_name,
            'shipping_phone' => '09123456789',
            'shipping_address' => '1 Test Street',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 150,
            'total' => 150,
        ]);
        $review = Review::create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'rating' => 5,
            'comment' => 'Healthy plant',
        ]);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.total_orders', 1)
            ->assertJsonPath('metrics.registered_users', 2)
            ->assertJsonPath('metrics.active_products', 1)
            ->assertJsonPath('metrics.low_stock_alerts', 1);

        $this->getJson('/api/admin/reports?range=daily')
            ->assertOk()
            ->assertJsonPath('totals.orders', 1)
            ->assertJsonPath('top_products.0.name', 'Java Fern');

        $this->patchJson("/api/inventories/{$inventory->id}/adjust", ['delta' => 4])
            ->assertOk()
            ->assertJsonPath('data.stock_quantity', 7);
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->patchJson("/api/inventories/{$inventory->id}/adjust", ['delta' => -100])
            ->assertUnprocessable();

        $this->patchJson("/api/orders/{$order->id}", ['status' => 'return_refund'])
            ->assertOk()
            ->assertJsonPath('data.status', 'return_refund');

        $this->patchJson("/api/reviews/{$review->id}", [
            'moderation_status' => 'flagged',
            'admin_reply' => 'We have contacted you about this review.',
        ])
            ->assertOk()
            ->assertJsonPath('data.moderation_status', 'flagged')
            ->assertJsonPath('data.admin_reply', 'We have contacted you about this review.');

        $this->getJson('/api/products?per_page=100')->assertOk();
    }

    public function test_report_end_date_anchors_the_default_start_date(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $customer = User::factory()->create();

        foreach ([
            ['number' => 'ORD-REPORT-IN-RANGE', 'date' => '2026-10-22 12:00:00'],
            ['number' => 'ORD-REPORT-OUT-OF-RANGE', 'date' => '2026-10-10 12:00:00'],
        ] as $fixture) {
            $order = Order::create([
                'order_number' => $fixture['number'],
                'user_id' => $customer->id,
                'subtotal' => 100,
                'shipping_fee' => 0,
                'tax' => 0,
                'total' => 100,
                'status' => 'delivered',
                'payment_method' => 'cod',
                'shipping_name' => $customer->full_name,
                'shipping_phone' => '09123456789',
                'shipping_address' => '1 Test Street',
            ]);
            Order::whereKey($order->id)->update(['created_at' => $fixture['date']]);
        }

        $this->getJson('/api/admin/reports?range=daily&end_date=2026-10-23')
            ->assertOk()
            ->assertJsonPath('start_date', '2026-10-17')
            ->assertJsonPath('end_date', '2026-10-23')
            ->assertJsonPath('totals.orders', 1)
            ->assertJsonPath('totals.revenue', 100);
    }

    public function test_admin_can_deactivate_customer_and_inactive_user_cannot_sign_in(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$customer->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->postJson('/api/auth/login', [
            'email' => $customer->email,
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_admin_can_manage_product_crud_and_only_an_admin_can_list_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create();
        $category = Category::create([
            'name' => 'Admin Test Category',
            'slug' => 'admin-test-category',
            'status' => true,
        ]);

        Sanctum::actingAs($customer);
        $this->getJson('/api/users')->assertForbidden();

        Sanctum::actingAs($admin);
        $this->get('/admin/products')->assertOk()->assertSee('id="root"', false);

        $this->postJson('/api/products', [
            'name' => 'Admin Created Fish',
            'sku' => 'ADMIN-CREATED-FISH',
            'category_id' => $category->id,
            'price' => 299,
            'stock_quantity' => 12,
            'description' => 'Created from the management API.',
            'image_url' => 'https://example.test/fish.jpg',
            'featured' => true,
            'status' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Admin Created Fish')
            ->assertJsonPath('data.featured', true)
            ->assertJsonPath('data.status', false);

        $product = Product::where('sku', 'ADMIN-CREATED-FISH')->firstOrFail();
        $this->getJson('/api/products?status=0&per_page=100')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id);

        $this->patchJson("/api/products/{$product->id}", ['status' => true, 'price' => 349])
            ->assertOk()
            ->assertJsonPath('data.price', 349);

        $this->deleteJson("/api/products/{$product->id}")->assertOk();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
