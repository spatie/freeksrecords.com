<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_records', function (Blueprint $table): void {
            $table->text('shared_note')->nullable();
            $table->text('private_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('collection_records', function (Blueprint $table): void {
            $table->dropColumn(['shared_note', 'private_note']);
        });
    }
};
