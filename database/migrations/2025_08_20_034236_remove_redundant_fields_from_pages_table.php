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
        Schema::table('pages', function (Blueprint $table) {
            // Remove redundant fields that are either always empty or can be generated dynamically
            $table->dropColumn(['author', 'content_hash', 'url', 'subpages']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // Restore the columns if needed
            $table->string('author')->nullable()->after('last_modified');
            $table->string('content_hash')->nullable()->after('permission');
            $table->string('url')->nullable()->after('size_bytes');
            $table->json('subpages')->nullable()->after('html_content');
        });
    }
};