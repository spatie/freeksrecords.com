<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('record_lists')->where('visibility', 'collection')->update([
            'visibility' => DB::raw('(select visibility from users where users.id = record_lists.user_id)'),
        ]);
    }
};
