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
            $table->string('author')->nullable()->after('last_modified');
            $table->bigInteger('revision')->nullable()->after('author');
            $table->integer('permission')->nullable()->after('revision');
            $table->string('content_hash')->nullable()->after('permission');
            $table->integer('size_bytes')->nullable()->after('content_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['author', 'revision', 'permission', 'content_hash', 'size_bytes']);
        });
    }
};