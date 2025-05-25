<?php

namespace App\Http\Controllers\Project\Transfer;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Illuminate\Support\Facades\Storage;

class GetRecruitmentProjectsController extends Controller
{
    public function __invoke()
    {
        $stateId = 1; // состояние идет набор
        $projects = $this->getProjectsWithState($stateId);

        $formattedProjects = $this->formatProjectsData($projects);
        $this->saveProjectsToJson($formattedProjects);

        return response()->json($formattedProjects);
    }

    private function getProjectsWithState(int $stateId)
    {
        return Project::with(['department', 'supervisors', 'type', 'themeSource', 'projectSpecialities', 'participation'])
            ->where('state_id', $stateId)
            ->get();
    }

    private function formatProjectsData($projects)
    {
        return $projects->map(function ($project) {
            return [
                'id' => $project->id,
                'state_id' => $project->state_id,
                'title' => $project->title,
                'participations' => $project->participation->map(function ($participation) {
                    return [
                        'candidate_id' => $participation->candidate_id,
                        'state_id' => $participation->state_id,
                    ];
                })->values()->toArray(),
            ];
        })->toArray();
    }

    private function saveProjectsToJson($projects)
    {
        Storage::put('projects_processing_state_1.json', json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}