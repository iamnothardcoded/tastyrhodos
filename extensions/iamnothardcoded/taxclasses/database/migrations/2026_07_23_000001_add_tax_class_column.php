<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('menus', 'tax_class')) {
            Schema::table('menus', function(Blueprint $table): void {
                $table->string('tax_class', 16)->nullable()->default(null);
            });
        }

        if (!Schema::hasColumn('categories', 'tax_class')) {
            Schema::table('categories', function(Blueprint $table): void {
                $table->string('tax_class', 16)->nullable()->default(null);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('menus', 'tax_class')) {
            Schema::table('menus', function(Blueprint $table): void {
                $table->dropColumn('tax_class');
            });
        }

        if (Schema::hasColumn('categories', 'tax_class')) {
            Schema::table('categories', function(Blueprint $table): void {
                $table->dropColumn('tax_class');
            });
        }
    }
};
