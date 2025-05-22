<?php

namespace App\Http\Controllers\Project\Transfer;

use App\Http\Controllers\Controller;


use App\Models\Project;
use Illuminate\Support\Facades\Storage;

class UpdateToProcessingStateController extends Controller

{
    public function __invoke()
    {
        

        $projects = $this->loadProjectsFromJson();
        
        $updatedProjects = $this->updateProjectAndCandidateStates($projects);
        $this->saveUpdatedProjectsToJson($updatedProjects);
        $this->updateDatabaseStates($updatedProjects);

        //return response()->json($updatedProjects);
        return response()->json([
            'updated_projects' => $updatedProjects,
            'updated_count' => count($updatedProjects),
        ]);
    }

   

    private function loadProjectsFromJson()
    {
        $json = Storage::get('projects_processing_state_1.json');
        return json_decode($json, true);
    }

    private function updateProjectAndCandidateStates($projects)
    {
        foreach ($projects as &$project) {
            $project['state_id'] = 5;
            
        }
        return $projects;
    }

    private function saveUpdatedProjectsToJson($projects)
    {
        Storage::put('projects_processing_state_5.json', json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function updateDatabaseStates($projects)
    {
        $projectIds = array_column($projects, 'id');

        Project::whereIn('id', $projectIds)->update(['state_id' => 5]);
        
    }
}
