<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_time')->useCurrent();
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->unsignedBigInteger('candidate_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('supervisor_id');
            $table->timestamp('responded_time')->nullable();
            $table->boolean('supervisor_notified')->default(false);
            $table->boolean('student_notified')->default(false); 

            // Внешние ключи
            $table->foreign('candidate_id')->references('id')->on('candidates')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('supervisor_id')->references('id')->on('supervisors')->onDelete('cascade');

            // Уникальное ограничение. Только одно активное принятое приглашение для студента
            $table->unique(['candidate_id'], 'unique_candidate_accepted_invitation')
                  ->where('status', '=', 'accepted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
