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
        Schema::create('wiki_pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_id')->unique();
            $table->string('title');
            $table->longText('content');
            $table->string('namespace')->default('root');
            $table->bigInteger('last_modified')->default(0);
            $table->string('url');
            $table->timestamps();
            
            $table->index(['namespace']);
            $table->index(['last_modified']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wiki_pages');
    }
};
