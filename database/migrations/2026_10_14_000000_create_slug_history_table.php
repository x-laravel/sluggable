<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use XLaravel\Sluggable\Support\SluggableConfig;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->getConnection())->create(SluggableConfig::historyTable(), function (Blueprint $table) {
            $table->id();
            $table->morphs('sluggable');
            $table->string('slug');
            $table->timestamp('created_at')->nullable();

            $table->unique(['sluggable_type', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists(SluggableConfig::historyTable());
    }

    public function getConnection(): ?string
    {
        return SluggableConfig::connection();
    }
};
