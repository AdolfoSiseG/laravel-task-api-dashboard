<?php

use App\Enums\ProjectStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // owner
            $table->string('name');
            $table->text('description')->nullable();
            // Stored as a string and cast to App\Enums\ProjectStatus: engine-portable
            // (works on MySQL and SQLite alike) and keeps the values in PHP, not the schema.
            $table->string('status', 20)->default(ProjectStatus::Active->value);
            $table->timestamps();
            $table->softDeletes();

            // Owner dashboards always list their projects filtered by status.
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
