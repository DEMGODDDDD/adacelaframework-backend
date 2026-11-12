<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_the_shop_page(): void
    {
        Vite::useHotFile(storage_path('framework/testing/no-vite-hot-file'));

        $this->get('/shop')
            ->assertOk()
            ->assertSee('id="root"', false)
            ->assertDontSee('127.0.0.1:5173', false)
            ->assertDontSee('http://localhost:8000/build/', false)
            ->assertDontSee('ocean-background-optimized.jpg', false);
    }

    public function test_guest_sign_in_page_preloads_its_background_and_welcome_chunk(): void
    {
        Vite::useHotFile(storage_path('framework/testing/no-vite-hot-file'));

        $this->get('/')
            ->assertOk()
            ->assertSee('rel="preload" as="image" href="/images/ocean-background-optimized.jpg"', false)
            ->assertSee('rel="modulepreload"', false)
            ->assertSee('WelcomePage', false)
            ->assertSee('src="/build/assets/app-', false)
            ->assertDontSee('127.0.0.1:5173', false)
            ->assertDontSee('http://localhost:8000/build/', false);
    }

    public function test_catalog_hides_inactive_products_and_categories(): void
    {
        $activeCategory = Category::create([
            'name' => 'Freshwater Fish',
            'slug' => 'freshwater-fish',
            'status' => true,
        ]);
        $inactiveCategory = Category::create([
            'name' => 'Hidden Category',
            'slug' => 'hidden-category',
            'status' => false,
        ]);

        $visibleProduct = Product::create([
            'name' => 'Blue Betta',
            'sku' => 'BETTA-001',
            'category_id' => $activeCategory->id,
            'price' => 450,
            'stock_quantity' => 8,
            'status' => true,
        ]);
        Product::create([
            'name' => 'Inactive Listing',
            'sku' => 'BETTA-002',
            'category_id' => $activeCategory->id,
            'price' => 500,
            'stock_quantity' => 8,
            'status' => false,
        ]);
        Product::create([
            'name' => 'Hidden Category Listing',
            'sku' => 'BETTA-003',
            'category_id' => $inactiveCategory->id,
            'price' => 500,
            'stock_quantity' => 8,
            'status' => true,
        ]);

        $this->getJson('/api/products?per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visibleProduct->id)
            ->assertJsonPath('data.0.stock_quantity', 8);

        $this->getJson('/api/categories?per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeCategory->id);
    }

    public function test_product_catalog_paginates_and_filters_server_side(): void
    {
        $category = Category::create([
            'name' => 'Freshwater Fish',
            'slug' => 'catalog-pagination',
            'status' => true,
        ]);

        foreach (range(1, 10) as $number) {
            Product::create([
                'name' => "Blue Fish {$number}",
                'sku' => "BLUE-FISH-{$number}",
                'category_id' => $category->id,
                'price' => $number * 100,
                'stock_quantity' => 10,
                'status' => true,
            ]);
        }

        $this->getJson('/api/products?per_page=3&page=2&sort=latest&min_price=0&max_price=5000&min_rating=0')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 4)
            ->assertJsonPath('meta.total', 10);

        $this->getJson('/api/products?per_page=8&search=Fish')
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonPath('meta.total', 10);

        $priceResponse = $this->getJson('/api/products?per_page=8&min_price=500');
        $priceResponse
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('meta.total', 6);

        $this->getJson('/api/products?per_page=8&min_rating=4')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_product_catalog_can_filter_to_the_authenticated_users_wishlist(): void
    {
        $user = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'catalog-wishlist@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        $category = Category::create([
            'name' => 'Equipment',
            'slug' => 'wishlist-catalog-equipment',
            'status' => true,
        ]);
        $wishedProduct = Product::create([
            'name' => 'Wished Filter',
            'sku' => 'WISHED-FILTER',
            'category_id' => $category->id,
            'price' => 1000,
            'stock_quantity' => 5,
            'status' => true,
        ]);
        Product::create([
            'name' => 'Other Filter',
            'sku' => 'OTHER-FILTER',
            'category_id' => $category->id,
            'price' => 1200,
            'stock_quantity' => 5,
            'status' => true,
        ]);
        $user->wishlists()->create(['product_id' => $wishedProduct->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/products?wishlist_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wishedProduct->id);
    }

    public function test_authenticated_cart_additions_accumulate_quantity(): void
    {
        $user = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'aqua@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        $category = Category::create([
            'name' => 'Equipment',
            'slug' => 'equipment',
            'status' => true,
        ]);
        $product = Product::create([
            'name' => 'Canister Filter',
            'sku' => 'FILTER-001',
            'category_id' => $category->id,
            'price' => 1200,
            'stock_quantity' => 5,
            'status' => true,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 1])->assertCreated();
        $this->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 2])->assertOk();

        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.items_count', 3);

        $this->deleteJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('message', 'Cart cleared');
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_item_quantity_update_rejects_unavailable_stock(): void
    {
        $user = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'cart-update@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        $category = Category::create([
            'name' => 'Equipment',
            'slug' => 'cart-update-equipment',
            'status' => true,
        ]);
        $product = Product::create([
            'name' => 'Canister Filter',
            'sku' => 'CART-UPDATE-FILTER',
            'category_id' => $category->id,
            'price' => 1200,
            'stock_quantity' => 2,
            'status' => true,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $cartItem = $user->cart->items()->firstOrFail();
        $this->putJson("/api/cart/{$cartItem->id}", ['quantity' => 3])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Insufficient stock');

        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'quantity' => 1,
        ]);
    }

    public function test_checkout_creates_order_calculates_totals_and_decrements_stock(): void
    {
        $user = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'checkout@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        $category = Category::create([
            'name' => 'Equipment',
            'slug' => 'checkout-equipment',
            'status' => true,
        ]);
        $product = Product::create([
            'name' => 'Canister Filter',
            'sku' => 'CHECKOUT-FILTER',
            'category_id' => $category->id,
            'price' => 1200,
            'stock_quantity' => 5,
            'status' => true,
        ]);
        Sanctum::actingAs($user);
        $this->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 2])->assertCreated();

        $this->postJson('/api/orders', [
            'shipping_name' => 'Aqua Customer',
            'shipping_phone' => '09171234567',
            'shipping_address' => '12 Reef Street',
            'payment_method' => 'cod',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.subtotal', 2400)
            ->assertJsonPath('data.shipping_fee', 0)
            ->assertJsonPath('data.tax', 288)
            ->assertJsonPath('data.total', 2688);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 3]);
        $this->assertDatabaseHas('inventories', ['product_id' => $product->id, 'stock_quantity' => 3]);
        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'status' => 'pending', 'total' => 2688]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_registration_returns_a_customer_token_for_shop_redirect_flow(): void
    {
        $this->postJson('/api/auth/register', [
            'first_name' => 'New',
            'last_name' => 'Aquarist',
            'email' => 'new-aquarist@example.test',
            'password' => 'Aquarium123',
            'password_confirmation' => 'Aquarium123',
        ])
            ->assertCreated()
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonStructure(['user' => ['id', 'first_name', 'last_name', 'role'], 'token']);
    }

    public function test_customer_cannot_manage_catalog_or_user_records(): void
    {
        $user = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'authorization@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/categories')->assertForbidden();
        $this->postJson('/api/products')->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_admin_can_manage_inventory_and_product_stock_stays_synchronized(): void
    {
        $admin = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Admin',
            'email' => 'inventory-admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $category = Category::create([
            'name' => 'Equipment',
            'slug' => 'inventory-equipment',
            'status' => true,
        ]);
        $product = Product::create([
            'name' => 'Canister Filter',
            'sku' => 'INVENTORY-FILTER',
            'category_id' => $category->id,
            'price' => 1200,
            'stock_quantity' => 5,
            'status' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/inventories', [
            'product_id' => $product->id,
            'stock_quantity' => 12,
        ])
            ->assertCreated()
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.stock_quantity', 12);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 12]);
        $this->assertDatabaseHas('inventories', ['product_id' => $product->id, 'stock_quantity' => 12]);

        $inventory = $product->inventory()->firstOrFail();
        $this->putJson("/api/inventories/{$inventory->id}", ['stock_quantity' => 3])
            ->assertOk()
            ->assertJsonPath('data.stock_quantity', 3);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 3]);
        $this->assertDatabaseHas('inventories', ['product_id' => $product->id, 'stock_quantity' => 3]);
    }

    public function test_customers_cannot_access_inventory_management(): void
    {
        $customer = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'inventory-customer@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        Sanctum::actingAs($customer);

        $this->getJson('/api/inventories')->assertForbidden();
        $this->postJson('/api/inventories', ['product_id' => 1, 'stock_quantity' => 1])->assertForbidden();
    }

    public function test_order_item_endpoints_limit_customers_to_their_own_orders(): void
    {
        $owner = User::create([
            'first_name' => 'Order',
            'last_name' => 'Owner',
            'email' => 'order-owner@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        $otherCustomer = User::create([
            'first_name' => 'Other',
            'last_name' => 'Customer',
            'email' => 'other-customer@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        $category = Category::create([
            'name' => 'Equipment',
            'slug' => 'order-item-equipment',
            'status' => true,
        ]);
        $product = Product::create([
            'name' => 'Canister Filter',
            'sku' => 'ORDER-ITEM-FILTER',
            'category_id' => $category->id,
            'price' => 1200,
            'stock_quantity' => 5,
            'status' => true,
        ]);
        $order = Order::create([
            'order_number' => 'ORD-ITEM-OWNER',
            'user_id' => $owner->id,
            'subtotal' => 1200,
            'shipping_fee' => 0,
            'tax' => 144,
            'total' => 1344,
            'status' => 'pending',
            'payment_method' => 'cod',
            'shipping_name' => 'Order Owner',
            'shipping_phone' => '09171234567',
            'shipping_address' => '12 Reef Street',
        ]);
        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 1200,
            'total' => 1200,
        ]);
        Sanctum::actingAs($owner);

        $this->getJson('/api/order-items')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $orderItem->id);
        $this->getJson("/api/order-items/{$orderItem->id}")->assertOk();

        Sanctum::actingAs($otherCustomer);
        $this->getJson('/api/order-items')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson("/api/order-items/{$orderItem->id}")->assertForbidden();
    }

    public function test_admin_and_customer_routes_enforce_role_boundaries(): void
    {
        $admin = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Admin',
            'email' => 'role-admin@example.test',
            'password' => 'password',
            'role' => 'admin',
        ]);
        $customer = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'role-customer@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/users')->assertOk();
        $this->getJson('/api/cart')->assertForbidden();
        $this->postJson('/api/orders')->assertForbidden();

        Sanctum::actingAs($customer);
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/categories')->assertForbidden();
        $this->getJson('/api/cart')->assertSuccessful();
    }

    public function test_order_list_validates_filters_and_pagination_size(): void
    {
        $customer = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'order-list@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        Sanctum::actingAs($customer);

        $this->getJson('/api/orders?per_page=5&status=pending')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5);

        $this->getJson('/api/orders?per_page=invalid')->assertUnprocessable();
        $this->getJson('/api/orders?status=unknown')->assertUnprocessable();
    }

    public function test_customer_can_update_own_account_profile(): void
    {
        $customer = User::create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'profile-customer@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/auth/profile', [
            'first_name' => 'Updated',
            'last_name' => 'Aquarist',
            'email' => 'updated-profile@example.test',
            'phone' => '09171234567',
        ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Updated')
            ->assertJsonPath('data.last_name', 'Aquarist')
            ->assertJsonPath('data.email', 'updated-profile@example.test')
            ->assertJsonPath('data.phone', '09171234567')
            ->assertJsonPath('data.role', 'customer');

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'first_name' => 'Updated',
            'email' => 'updated-profile@example.test',
            'role' => 'customer',
        ]);
    }

    public function test_customer_can_upload_a_png_profile_picture(): void
    {
        Storage::fake('public');
        $customer = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'profile-avatar@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        Sanctum::actingAs($customer);

        $this->post('/api/auth/profile', [
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'profile-avatar@example.test',
            'phone' => '',
            'avatar' => UploadedFile::fake()->image('profile.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.role', 'customer');

        $avatarPath = $customer->fresh()->avatar;
        $this->assertStringStartsWith("avatars/{$customer->id}/", $avatarPath);
        Storage::disk('public')->assertExists($avatarPath);
    }

    public function test_customer_can_upload_a_jpg_profile_picture(): void
    {
        Storage::fake('public');
        $customer = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'profile-jpg@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        Sanctum::actingAs($customer);

        $jpegContent = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/AI//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AT//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AT//2Q==', true);
        $this->assertNotFalse($jpegContent);

        $this->post('/api/auth/profile', [
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'profile-jpg@example.test',
            'avatar' => UploadedFile::fake()->createWithContent('profile.jpg', $jpegContent),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.role', 'customer');

        $avatarPath = $customer->fresh()->avatar;
        $this->assertStringEndsWith('.jpg', $avatarPath);
        Storage::disk('public')->assertExists($avatarPath);
    }

    public function test_customer_can_remove_their_profile_picture(): void
    {
        Storage::fake('public');
        $customer = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'profile-remove@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        $avatarPath = "avatars/{$customer->id}/existing.png";
        Storage::disk('public')->put($avatarPath, 'existing avatar');
        $customer->update(['avatar' => $avatarPath]);
        Sanctum::actingAs($customer);

        $this->postJson('/api/auth/profile', [
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'profile-remove@example.test',
            'remove_avatar' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.avatar', null);

        $this->assertNull($customer->fresh()->avatar);
        Storage::disk('public')->assertMissing($avatarPath);
    }

    public function test_profile_update_requires_authentication_and_supported_avatar_type(): void
    {
        $this->postJson('/api/auth/profile', [])->assertUnauthorized();

        $customer = User::create([
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'profile-validation@example.test',
            'password' => 'password',
            'role' => 'customer',
        ]);
        Sanctum::actingAs($customer);

        $this->post('/api/auth/profile', [
            'first_name' => 'Aqua',
            'last_name' => 'Customer',
            'email' => 'profile-validation@example.test',
            'avatar' => UploadedFile::fake()->create('profile.gif', 100, 'image/gif'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }
}
