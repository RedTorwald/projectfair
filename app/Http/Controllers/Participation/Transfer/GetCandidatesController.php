<?php


namespace App\Http\Controllers\Participation\Transfer;

use App\Http\Controllers\Controller;



use App\Models\Candidate;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class GetCandidatesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $candidates = Candidate::where('can_send_participations', 1)
            ->get(['id', 'fio', 'numz', 'course', 'training_group']); 

        $projects = Project::whereIn('state_id', [1, 2, 3])
            ->get(['id', 'title', 'department_id',]); 

        return response()->json([
            'candidates' => $candidates,
            'projects' => $projects,
        ]);
    }
}