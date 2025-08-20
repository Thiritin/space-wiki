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
        // First add the html_content column to existing table
        Schema::table('wiki_pages', function (Blueprint $table) {
            $table->longText('html_content')->nullable()->after('content');
        });
        
        // Then rename the table
        Schema::rename('wiki_pages', 'pages');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the table rename
        Schema::rename('pages', 'wiki_pages');
        
        // Remove the html_content column
        Schema::table('wiki_pages', function (Blueprint $table) {
            $table->dropColumn('html_content');
        });
    }
};
