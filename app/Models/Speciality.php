<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Speciality extends Model
{
    use HasFactory;
    protected $guarded = false;
    public $timestamps = false;

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    public function department(): BelongsTo | null
    {
        return $this->belongsTo(Department::class);
    }
    
    /*
    public function institute(): HasOneThrough | null
    {
    	
        return $this->hasOneThrough(Institute::class,Department::class);
    }
    */

    public function getInstitute(): Institute | null
    {
        return $this->department?->institute;
    }
}
