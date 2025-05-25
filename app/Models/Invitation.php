<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $table = 'invitations';

    protected $fillable = [
        'created_time',
        'expires_time',
        'status',
        'candidate_id',
        'project_id',
        'supervisor_id',
        'responded_time',
        'supervisor_notified',
        'student_notified',
    ];

    public $timestamps = false;

    // Связи
    public function candidate() {
        return $this->belongsTo(Candidate::class);
    }

    public function project() {
        return $this->belongsTo(Project::class);
    }

    public function supervisor() {
        return $this->belongsTo(Supervisor::class);
    }
}
