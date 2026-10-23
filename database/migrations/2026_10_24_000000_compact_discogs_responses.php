<?php

use App\Models\DiscogsResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $lastId = 0;

        do {
            $count = DB::transaction(function () use (&$lastId): int {
                $responses = DB::table('discogs_responses')->where('id', '>', $lastId)
                    ->orderBy('id')->limit(100)->lockForUpdate()->get();

                if ($responses->isEmpty()) {
                    return 0;
                }

                $updates = [];

                foreach ($responses as $response) {
                    $updates[] = [
                        'id' => $response->id,
                        'resource_type' => $response->resource_type,
                        'discogs_id' => $response->discogs_id,
                        'response' => json_encode(DiscogsResponse::compactResponse(
                            $response->resource_type,
                            json_decode($response->response, true, 512, JSON_THROW_ON_ERROR),
                        ), JSON_THROW_ON_ERROR),
                        'fetched_at' => $response->fetched_at,
                        'expires_at' => $response->expires_at,
                    ];
                    $lastId = $response->id;
                }

                DB::table('discogs_responses')->upsert($updates, ['id'], ['response']);

                return $responses->count();
            });
        } while ($count === 100);
    }
};
