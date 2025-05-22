<?php

namespace App\Http\Controllers\Participation\Transfer;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;

class GetCandidateParticipationsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $candidateId = $request->input('candidate_id'); 
        $currentTimeBound = now()->subMonth(30); 

        $participations = Participation::where('candidate_id', $candidateId)           
            ->where('created_at', '>=', $currentTimeBound)
            ->get(['id','project_id', 'candidate_id', 'priority', 'state_id']);

        return response()->json([
            'candidate_id' => $candidateId,
            'participations' => $participations,            
        ]);
    }
}