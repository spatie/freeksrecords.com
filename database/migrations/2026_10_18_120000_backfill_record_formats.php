<?php

use App\Support\DiscogsRecordMapper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $mapper = app(DiscogsRecordMapper::class);
        DB::table('collection_records')->orderBy('id')->chunkById(200, function ($records) use ($mapper): void {
            foreach ($records as $record) {
                $payload = json_decode($record->payload, true, flags: JSON_THROW_ON_ERROR);
                if (array_key_exists('mediaFormats', $payload)) {
                    continue;
                }
                $payload['mediaFormats'] = $mapper->mediaFormats($payload['format'] ?? '');
                DB::table('collection_records')->where('id', $record->id)->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
            }
        });
    }

    public function down(): void {}
};
