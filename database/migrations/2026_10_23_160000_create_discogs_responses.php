<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discogs_responses', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 16);
            $table->unsignedBigInteger('discogs_id');
            $table->json('response');
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at')->index();
            $table->unique(['resource_type', 'discogs_id']);
        });

        if (! Schema::hasTable('cache')) {
            return;
        }

        $expirations = [];
        DB::table('cache')
            ->where('expiration', '>', now()->timestamp)
            ->where('key', 'like', '%discogs-public-v1:%')
            ->chunkById(100, function ($entries) use (&$expirations): void {
                $responses = [];

                foreach ($entries as $entry) {
                    if (! preg_match('#discogs-public-v1:(releases|masters|artists)/([0-9]+)$#', $entry->key, $matches)) {
                        continue;
                    }

                    $resource = $matches[1].':'.(int) $matches[2];
                    if (($expirations[$resource] ?? 0) >= $entry->expiration) {
                        continue;
                    }

                    $response = $this->responseJson($entry->value);
                    if ($response === null) {
                        continue;
                    }

                    $expirations[$resource] = $entry->expiration;
                    $expiresAt = CarbonImmutable::createFromTimestamp($entry->expiration);
                    $responses[$resource] = [
                        'resource_type' => $matches[1],
                        'discogs_id' => (int) $matches[2],
                        'response' => $response,
                        'fetched_at' => $expiresAt->subWeek(),
                        'expires_at' => $expiresAt,
                    ];
                }

                DB::table('discogs_responses')->upsert(array_values($responses),
                    ['resource_type', 'discogs_id'], ['response', 'fetched_at', 'expires_at']);
            }, 'key');
    }

    protected function responseJson(string $value): ?string
    {
        if (! Str::contains($value, [':', ';'])) {
            $value = base64_decode($value, strict: true);
            if ($value === false) {
                return null;
            }
        }

        $response = @unserialize($value, ['allowed_classes' => false]);
        if (! is_array($response)) {
            return null;
        }

        $json = json_encode($response);
        if ($json === false) {
            return null;
        }

        $containsObjects = false;
        array_walk_recursive($response, function (mixed $item) use (&$containsObjects): void {
            if (is_object($item)) {
                $containsObjects = true;
            }
        });

        if ($containsObjects) {
            return null;
        }

        return $json;
    }
};
