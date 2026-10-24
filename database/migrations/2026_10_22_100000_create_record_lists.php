<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('visibility')->default('only_me');
            $table->timestamps();
        });

        Schema::create('record_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_list_id')->constrained()->cascadeOnDelete();
            // Keep a tombstone so owners can see that a Discogs removal affected their list.
            $table->foreignId('collection_record_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position');
            $table->unique(['record_list_id', 'collection_record_id']);
        });
    }
};
