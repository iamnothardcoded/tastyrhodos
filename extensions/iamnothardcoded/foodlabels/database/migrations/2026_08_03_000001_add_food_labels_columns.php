<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('menus', 'diet_labels')) {
            Schema::table('menus', function(Blueprint $table): void {
                $table->text('diet_labels')->nullable();
            });
        }

        if (!Schema::hasColumn('menus', 'additives')) {
            Schema::table('menus', function(Blueprint $table): void {
                $table->text('additives')->nullable();
            });
        }

        if (!Schema::hasColumn('menus', 'food_info_status')) {
            Schema::table('menus', function(Blueprint $table): void {
                $table->string('food_info_status', 16)->default('unknown');
            });
        }
    }

    public function down(): void
    {
        foreach (['diet_labels', 'additives', 'food_info_status'] as $column) {
            if (Schema::hasColumn('menus', $column)) {
                Schema::table('menus', function(Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
