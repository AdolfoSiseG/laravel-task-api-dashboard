<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            // Unassigning is allowed and assignees can be removed without losing the task.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default(TaskStatus::Todo->value);
            $table->string('priority', 20)->default(TaskPriority::Medium->value);
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']); // board columns within a project
            $table->index('assigned_to');            // "assigned to me" + tasks-by-user analytics
            $table->index('completed_at');           // completion-over-time analytics
            $table->index('due_date');               // upcoming deadlines / overdue scans
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
