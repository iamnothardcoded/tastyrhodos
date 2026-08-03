<?php

declare(strict_types=1);

use Iamnothardcoded\FoodLabels\Classes\Allergens;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // DB:: (not the model) — no model boot/events in migrations.
        // Idempotent by exact name match; safe under igniter:up --force.
        foreach (Allergens::SEED as $row) {
            if (!DB::table('ingredients')->where('name', $row['name'])->exists()) {
                DB::table('ingredients')->insert([
                    'name' => $row['name'],
                    'status' => 1,
                    'is_allergen' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Deliberate no-op: owners may have attached the seeded allergen rows
        // to dishes — deleting them would silently strip live allergen data.
    }
};
