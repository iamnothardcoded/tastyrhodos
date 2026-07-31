<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('menus', 'deposit_class')) {
            Schema::table('menus', function(Blueprint $table): void {
                $table->string('deposit_class', 32)->nullable()->default(null);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('menus', 'deposit_class')) {
            Schema::table('menus', function(Blueprint $table): void {
                $table->dropColumn('deposit_class');
            });
        }
    }
};
