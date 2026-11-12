<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('products')
            ->select(['id', 'stock_quantity', 'updated_at'])
            ->orderBy('id')
            ->chunk(500, function ($products): void {
                DB::table('inventories')->insert(
                    $products->map(fn ($product) => [
                        'product_id' => $product->id,
                        'stock_quantity' => $product->stock_quantity,
                        'updated_at' => $product->updated_at,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
