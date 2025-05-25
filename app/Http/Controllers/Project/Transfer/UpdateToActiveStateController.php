<?php

namespace App\Http\Controllers\Project\Transfer;

use App\Http\Controllers\Controller;

use App\Models\Project;
use App\Models\Participation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class UpdateToActiveStateController extends Controller

{
    public function __invoke()
    {
        

        $projects = $this->loadProjectsFromJson();
        
        $updatedProjects = $this->updateProjectAndCandidateStates($projects);
        $this->saveUpdatedProjectsToJson($updatedProjects);
        $this->updateDatabaseStates($updatedProjects);
        $this->deleteAllStateOneParticipations($updatedProjects);

        //return response()->json($updatedProjects);
        return response()->json([
            'updated_projects' => $updatedProjects,
            'updated_count' => count($updatedProjects),
        ]);
    }

   

    private function loadProjectsFromJson()
    {
        $json = Storage::get('projects_active_state_5.json');
        return json_decode($json, true);
    }

    
    private function updateProjectAndCandidateStates($projects)
    {
        foreach ($projects as &$project) {
            $project['state_id'] = 2;
            foreach ($project['participations'] as &$participation) {
                if ($participation['state_id'] === 1) {
                    $participation['state_id'] = 4;
                }
            }
        }
        return $projects;
    }


    private function saveUpdatedProjectsToJson($projects)
    {
        Storage::put('projects_active_state_2.json', json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function updateDatabaseStates($projects)
    {
        $projectIds = array_column($projects, 'id');
       
        Project::whereIn('id', $projectIds)->update(['state_id' => 2]);
        
        // только те, у кого state_id = 1 И нет другой заявки на тот же проект с state_id = 4
        Participation::whereIn('project_id', $projectIds)
            ->where('state_id', 1)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('participations as p')
                    ->whereColumn('p.project_id', 'participations.project_id')
                    ->whereColumn('p.candidate_id', 'participations.candidate_id')
                    ->where('p.state_id', 4);
            })
            ->update(['state_id' => 4]);
    }

    private function deleteAllStateOneParticipations(array $projects)
    {
        $projectIds = array_column($projects, 'id');

        Participation::whereIn('project_id', $projectIds)
            ->where('state_id', 1)
            ->delete();
    }
}
