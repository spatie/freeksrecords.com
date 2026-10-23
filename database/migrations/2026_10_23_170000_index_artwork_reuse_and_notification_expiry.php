<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX releases_cover_index ON releases (cover(191))');
        } else {
            Schema::table('releases', fn (Blueprint $table) => $table->index('cover'));
        }

        Schema::table('social_notifications', fn (Blueprint $table) => $table->index('created_at'));
    }
};
