<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_records', function (Blueprint $table): void {
            $table->index(['user_id', 'browse_original_year', 'discogs_release_id', 'added_at', 'id'], 'collection_records_decade_index');
        });
    }
};
