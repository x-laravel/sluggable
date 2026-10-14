<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->getConnection())->create($this->table(), function (Blueprint $table) {
            $table->id();
            $table->morphs('sluggable');
            $table->string('slug');
            $table->timestamp('created_at')->nullable();

            $table->unique(['sluggable_type', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists($this->table());
    }

    public function getConnection(): ?string
    {
        $connection = Config::get('sluggable.history.connection');

        return is_string($connection) ? $connection : null;
    }

    private function table(): string
    {
        return Config::string('sluggable.history.table', 'slug_history');
    }
};
