<?php

namespace App\Http\Controllers\Project\Transfer;

use App\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Get(
 *     path="/transfer/approved",
 *     summary="Получить активные проекты",
 *     description="Этот метод возвращает список проектов, находящихся в активном состоянии (state_id = 2), если текущий месяц соответствует критериям.",
 *     operationId="getActiveProjects",
 *     tags={"Projects Transfer"},
 *     @OA\Response(
 *         response=200,
 *         description="Список активных проектов"
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Ошибка в запросе"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Не найдено активных проектов"
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Ошибка сервера"
 *     )
 * )
 */


class GetActiveProjectsController extends Controller
{
    public function __invoke()
    {       

        $stateId = 2; // состояние активные
        $projects = $this->getProjectsWithState($stateId); 

        $formattedProjects = $this->formatProjectsData($projects);
        $this->saveProjectsToJson($formattedProjects);
       
        return response()->json($formattedProjects);       
    }


    //получение проектов и необходимых моделей с нужным состоянием (2 - активный)
    private function getProjectsWithState(int $stateId)
    {
        $currentMonth = now()->month; //определение текущего месяца

        if (in_array($currentMonth, [11, 12, 1, 5])) { // проверка на подходящий месяц
            return Project::with(['department', 'supervisors', 'type', 'themeSource', 'projectSpecialities'])
                ->where('state_id', $stateId)
                ->get();
        }
        return collect(); // в случае несовпадения месяца возвращаем пустую коллекцию
    }
   

    private function formatProjectsData($projects)
    {
        return $projects->map(function ($project) {
            return [
                'id' => $project->id,
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
                'title' => $project->title,
                'places' => $project->places,
                'goal' => $project->goal,
                'description' => $project->description,
                'difficulty' => $project->difficulty,
                'date_start' => $project->date_start,
                'date_end' => $project->date_end,
                'requirements' => $project->requirements,
                'customer' => $project->customer,
                'additional_inf' => $project->additional_inf,
                'product_result' => $project->product_result,
                'study_result' => $project->study_result,
                'supervisors_id' => $project->supervisors->pluck('id')->toArray(),
                'state_id' => $project->state_id,
                'type_id' => $project->type_id,
                'prev_project_id' => $project->prev_project_id,
                'department_id' => $project->department_id,
                'theme_source_id' => $project->theme_source_id,
                'rejection_reason' => $project->rejection_reason,
                'rejection_date' => $project->rejection_date,
                'project_review' => $project->project_review,
                'project_goal' => $project->project_goal,
                'supervisors' => $project->supervisors->map(function ($supervisor) {
                    return [
                        'id' => $supervisor->id,
                        'fio' => $supervisor->fio,
                        'email' => $supervisor->email,
                        'position' => $supervisor->position,
                        'department_id' => $supervisor->department_id,
                        'user_id' => $supervisor->user_id,
                    ];
                })->toArray(),
                'specialities' => $project->projectSpecialities->map(function ($speciality) {
                    return [
                        'speciality_id' => $speciality->speciality_id,
                        'course' => $speciality->course,
                        'priority' => $speciality->priority,
                    ];
                })->toArray(),
                'participations' => $project->participation->where('state_id', 3)->map(function ($participation) {
                    return [
                        'candidate_id' => $participation->candidate_id,
                        'state_id' => $participation->state_id,
                        
                        'created_at' => $participation->created_at,
                        'updated_at' => $participation->updated_at,
                        'priority' => $participation->priority,
                        'review' => $participation->review,
                        'project_id' => $participation->project_id,
                        
                        'mark' => $participation->mark,
                        'additional_information' => $participation->additional_information,
                    ];
                })->values()->toArray(),
            ];
        })->toArray();
    }
    
    private function saveProjectsToJson($projects)
    {
        Storage::put('active_projects_state_2.json', json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
  
    
}