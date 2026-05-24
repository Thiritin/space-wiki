<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wiki_sync_log', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'full' or 'incremental'
            $table->integer('processed')->default(0);
            $table->integer('updated')->default(0);
            $table->integer('errors')->default(0);
            $table->timestamp('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_sync_log');
    }
};
