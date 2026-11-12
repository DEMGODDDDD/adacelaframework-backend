<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable()->unique();
            $table->boolean('is_active')->default(true);
        });

        Schema::table('inventories', function (Blueprint $table): void {
            $table->unsignedInteger('reorder_level')->default(5);
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->string('moderation_status')->default('approved');
            $table->text('admin_reply')->nullable();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE orders MODIFY status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'return_refund') NOT NULL DEFAULT 'pending'"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('orders')->where('status', 'return_refund')->update(['status' => 'cancelled']);
            DB::statement(
                "ALTER TABLE orders MODIFY status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'"
            );
        }

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropColumn(['moderation_status', 'admin_reply']);
        });

        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropColumn('reorder_level');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'is_active']);
        });
    }
};
