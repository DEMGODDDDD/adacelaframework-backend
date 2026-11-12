<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['customer', 'admin'])->default('customer')->after('password');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('avatar')->nullable()->after('last_name');
            $table->string('phone')->nullable()->after('avatar');
            $table->text('address')->nullable()->after('phone');
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'first_name', 'last_name', 'avatar', 'phone', 'address']);
            $table->string('name')->after('id');
        });
    }
};