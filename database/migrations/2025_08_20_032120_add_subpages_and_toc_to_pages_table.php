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
            $table->json('subpages')->nullable()->after('html_content');
            $table->json('table_of_contents')->nullable()->after('subpages');
            $table->text('excerpt')->nullable()->after('table_of_contents');
            $table->integer('depth')->default(0)->after('namespace');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['subpages', 'table_of_contents', 'excerpt', 'depth']);
        });
    }
};
