<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('team_name'); // e.g., 'it', 'accounting'
            $table->string('team_display_name'); // e.g., 'IT', 'Accounting'
            $table->string('team_href'); // e.g., '/wiki/team:it'
            $table->string('team_type')->default('namespace'); // 'namespace' or 'page'
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->unique(['user_id', 'team_name']);
            $table->index(['user_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_teams');
    }
};
