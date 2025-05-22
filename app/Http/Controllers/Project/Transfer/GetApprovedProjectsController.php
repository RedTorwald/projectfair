<?php

namespace App\Http\Controllers\Project\Transfer;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Illuminate\Support\Facades\Storage;


class GetApprovedProjectsController extends Controller
{
    public function __invoke()
    {
        $stateId = 9; // состояние одобрено
        $projects = $this->getProjectsWithState($stateId);

        $formattedProjects = $this->formatProjectsData($projects);
        $this->saveProjectsToJson($formattedProjects);

        return response()->json($formattedProjects);
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
        Storage::put('projects_recruitment_state_9.json', json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}