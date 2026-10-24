<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('record_lists', 'public_id')) {
            Schema::table('record_lists', function (Blueprint $table) {
                $table->uuid('public_id')->nullable()->unique();
            });
        }

        DB::table('record_lists')->whereNull('public_id')->select('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('record_lists')->where('id', $row->id)->whereNull('public_id')->update([
                    'public_id' => (string) Str::uuid(),
                ]);
            }
        });

        Schema::table('record_lists', function (Blueprint $table) {
            $table->uuid('public_id')->nullable(false)->change();
        });
    }
};
