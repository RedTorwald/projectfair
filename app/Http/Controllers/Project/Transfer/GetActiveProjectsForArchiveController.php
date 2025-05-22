<?php

namespace App\Http\Controllers\Project\Transfer;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Illuminate\Support\Facades\Storage;


class GetActiveProjectsForArchiveController extends Controller
{
    public function __invoke()
    {
        $stateId = 2;  // состояние активные
        

        $projects = $this->getProjectsWithState($stateId);

        $formattedProjects = $this->formatProjectsData($projects);
        $this->saveProjectsToJson($formattedProjects);

        return response()->json($formattedProjects);
    }

    private function getProjectsWithState(int $stateId)
    {
        $currentTimeBound = now()->subMonth(5); 
        return Project::with(['department', 'supervisors', 'type', 'themeSource', 'projectSpecialities', 'participation'])
            ->where('state_id', $stateId)
            ->where('updated_at', '>=', $currentTimeBound)
            ->get();
    }


    private function formatProjectsData($projects)
    {
        return $projects->map(function ($project) {
            return [
                'id' => $project->id,
                'state_id' => $project->state_id, 
                'title' => $project->title,               
            ];
        })->toArray();
    }

    private function saveProjectsToJson($projects)
    {
        Storage::put('projects_archive_state_2.json', json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}