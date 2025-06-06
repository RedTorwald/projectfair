<?php

namespace App\Http\Controllers\Project\Transfer;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Illuminate\Support\Facades\Storage;
/*
class CancelRecruitmentStateController extends Controller

{
    public function __invoke()
    {
        

        $projects = $this->loadProjectsFromJson();
        
        $updatedProjects = $this->updateProjectAndCandidateStates($projects);
        $this->saveUpdatedProjectsToJson($updatedProjects);
        $this->updateDatabaseStates($updatedProjects);

        return response()->json($updatedProjects);
    }

   

    private function loadProjectsFromJson()
    {
        $json = Storage::get('projects_recruitment_state_1.json');
        return json_decode($json, true);
    }

    private function updateProjectAndCandidateStates($projects)
    {
        foreach ($projects as &$project) {
            $project['state_id'] = 9;
            
        }
        return $projects;
    }

    private function saveUpdatedProjectsToJson($projects)
    {
        Storage::put('projects_recruitment_state_9.json', json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function updateDatabaseStates($projects)
    {
        $projectIds = array_column($projects, 'id');

        Project::whereIn('id', $projectIds)->update(['state_id' => 9]);
        
    }
}*/

class CancelRecruitmentStateController extends Controller
{
    public function __invoke()
    {
        $stateId = 1; // состояние одобрено
        $stateTo = 9;   

        $projects = $this->getProjectsWithState($stateId);

        $formattedProjects = $this->formatProjectsData($projects);
        $this->saveProjectsToJson($formattedProjects);

       $updatedProjects = $this->updateProjectStates($formattedProjects, $stateTo);
       $this->updateDatabaseStates($updatedProjects, $stateTo);
 
        return response()->json($updatedProjects);
    }

    private function getProjectsWithState(int $stateId)
    {
        // получение диапазона месяцев для определения проектов в состояниии "одобрена"(9), подходящих для переноса в состояние идет набор (1)
        // Дата старат должна быть в окрестности +-1 месяц от текущей даты
        $startDate = now()->subMonth(1);       
        $endDate = now()->addMonth(1);         
        return Project::with(['department', 'supervisors', 'type', 'themeSource', 'projectSpecialities', 'participation'])
            ->where('state_id', $stateId)
            ->whereBetween('date_start', [$startDate, $endDate])
            ->get();
    }

    private function updateProjectStates(array $projects, int $newStateId)
    {
        foreach ($projects as &$project) {
            $project['state_id'] = $newStateId;
        }
        return $projects;
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

    private function updateDatabaseStates(array $projects, int $newStateId)
    {
        $projectIds = array_column($projects, 'id');
        Project::whereIn('id', $projectIds)->update(['state_id' => $newStateId]);
    }

    private function saveProjectsToJson($projects)
    {
        Storage::put('projects_recruitment_state_10.json', json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
