<?php

use App\Support\CollectionBrowseIndex;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collection_records', function (Blueprint $table): void {
            $table->text('browse_title')->nullable();
            $table->unsignedSmallInteger('browse_original_year')->nullable();
            $table->date('browse_added_day')->nullable();
            $table->mediumText('browse_search')->nullable();
            $table->index(['user_id', 'browse_added_day', 'added_at', 'id'], 'collection_records_recent_index');
        });

        Schema::create('collection_browse_entries', function (Blueprint $table): void {
            $table->foreignId('collection_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8);
            $name = $table->string('name', 512);
            if (DB::getDriverName() === 'mysql') {
                $name->collation('utf8mb4_bin');
            }
            $table->unique(['collection_record_id', 'kind', 'name'], 'collection_browse_copy_unique');
            $table->index(['user_id', 'kind', 'name'], 'collection_browse_groups_index');
        });

        DB::table('collection_records')->select('id')->chunkById(50, function ($ids): void {
            DB::transaction(function () use ($ids): void {
                // Read the historical payload schema without eager-loading newer model relations.
                $copies = DB::table('collection_records')->whereIn('id', $ids->pluck('id'))->lockForUpdate()
                    ->get(['id', 'user_id', 'payload', 'added_at'])
                    ->map(fn ($row) => (object) [
                        ...(array) $row, 'payload' => json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR),
                    ]);
                foreach ($copies as $copy) {
                    DB::table('collection_records')->where('id', $copy->id)
                        ->update(CollectionBrowseIndex::attributes($copy->payload, CarbonImmutable::parse($copy->added_at)));
                }
                CollectionBrowseIndex::sync($copies);
            });
        });
    }
};
