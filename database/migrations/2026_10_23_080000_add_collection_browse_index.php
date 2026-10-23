<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_records', function (Blueprint $table): void {
            $table->index(['user_id', 'added_at', 'id', 'discogs_release_id'], 'collection_records_browse_index');
        });
    }
};
