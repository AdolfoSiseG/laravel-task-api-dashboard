<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add the column and the wider index first. The new (project_id, status, position)
        // index keeps project_id as its leftmost column, so it can satisfy the foreign key...
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('priority');
            $table->index(['project_id', 'status', 'position']);
        });

        // ...which lets MySQL drop the now-redundant (project_id, status) index.
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['project_id', 'status']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status', 'position']);
            $table->dropColumn('position');
        });
    }
};
