<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (config('cache.default') === 'database' || (app()->isProduction() && config('cache.default') !== 'redis')) {
            throw new RuntimeException('Configure a shared Redis/Valkey cache before removing the database cache. Stop old workers and import commands first.');
        }

        if (config('cache.default') === 'redis') {
            Redis::connection(config('cache.stores.redis.connection'))->ping();
        }

        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
