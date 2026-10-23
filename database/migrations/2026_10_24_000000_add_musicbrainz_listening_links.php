<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table): void {
            $table->text('bandcamp_url')->nullable();
            $table->boolean('bandcamp_search')->nullable();
            $table->timestamp('musicbrainz_checked_at')->nullable()->index();
        });
    }
};
