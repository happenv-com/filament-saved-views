<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('filament-happenv-saved-views.table', 'saved_views'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->text('label')->nullable();
            $table->text('class')->nullable();
            $table->json('saved_data')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('submenu_visible')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-happenv-saved-views.table', 'saved_views'));
    }
};
