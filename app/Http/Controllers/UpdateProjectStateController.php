<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectSpeciality;
use App\Models\Participation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class UpdateProjectStateController extends Controller
{
    public function __invoke()
    {
        $currentMonth = now()->month; // получение текущего месяца для определния типа перевода проекта (из "одобрен" в "активный" или из "активный" в "одобрен")
       // $stateId = ($currentMonth == 9) ? 9 : 2; // 2 - активный, 9 - одобрен

       $stateId = 2;

        $projects = $this->getProjectsWithState($stateId); // получение проектов в нужном состоянии 

        $formattedProjects = $this->formatProjectsData($projects);
        $this->saveProjectsToJson($formattedProjects);

        $clonedProjects = $this->prepareProjectsForCloning($formattedProjects);
        $this->saveClonedProjectsToJson($clonedProjects);

      //  $this->insertProjectsIntoDatabase($clonedProjects);
       // $this->updateOriginalProjectsState($clonedProjects);

        return response()->json(['message' => 'Проекты успешно экспортированы и подготовлены для клонирования']);
    }


    //получение проектов и необходимых моделей с нужным состоянием (2 - активный)
    private function getProjectsWithState(int $stateId)
    {
        return Project::with(['department', 'supervisors', 'type', 'themeSource', 'projectSpecialities'])
            ->where('state_id', $stateId)
            ->get();
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
                'specialities' => $project->projectSpecialities->map(function ($speciality) {
                    return [
                        'speciality_id' => $speciality->speciality_id,
                        'course' => $speciality->course,
                        'priority' => $speciality->priority,
                    ];
                })->toArray(),
                'participations' => $project->participation->where('state_id', 3)->map(function ($participation) {
                    return [
                        'created_at' => $participation->created_at,
                        'updated_at' => $participation->updated_at,
                        'priority' => $participation->priority,
                        'review' => $participation->review,
                        'project_id' => $participation->project_id,
                        'candidate_id' => $participation->candidate_id,
                        'state_id' => $participation->state_id,
                        'mark' => $participation->mark,
                        'additional_information' => $participation->additional_information,
                    ];
                })->values()->toArray(),
            ];
        })->toArray();
    }
    
    private function saveProjectsToJson($projects)
    {
        Storage::put('projects_state_2.json', json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }


    private function prepareProjectsForCloning($projects)
    {
        $lastProjectId = Project::max('id');
        $newId = $lastProjectId + 1;
        $currentTimestamp = Carbon::now()->toDateTimeString();

        return array_map(function ($project) use (&$newId, $currentTimestamp) {
            $project['prev_project_id'] = $project['id'];
            $project['id'] = $newId++;
            $project['state_id'] = 9;
            $project['created_at'] = $currentTimestamp;
            $project['updated_at'] = $currentTimestamp;

            if (isset($project['participations'])) {
                $project['participations'] = array_map(function ($participation) use ($project, $currentTimestamp) {
                    $participation['project_id'] = $project['id'];
                    $participation['state_id'] = 1;
                    $participation['priority'] = 4;
                    $participation['mark'] = null;  // Используем null вместо пустой строки
                    $participation['review'] = '';
                    $participation['created_at'] = $currentTimestamp;
                    $participation['updated_at'] = $currentTimestamp;
                    return $participation;
                }, $project['participations']);
            }

            return $project;
        }, $projects);
    }

    private function saveClonedProjectsToJson($projects)
    {
        Storage::put('cloned_projects.json', json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function updateOriginalProjectsState($projects)
    {
        DB::transaction(function () use ($projects) {
            foreach ($projects as $projectData) {
                // обновление state_id у исходного проекта
                Project::where('id', $projectData['prev_project_id'])->update([
                    'state_id' => 4,
                ]);

            // получение списка candidate_id, у которых уже есть заявки с state_id = 4 на этом проекте
            $candidatesWithState4 = Participation::where('project_id', $projectData['prev_project_id'])
                ->where('state_id', 4)
                ->pluck('candidate_id')
                ->toArray();
/*
            // обновление заявок с state_id = 3, исключая кандидатов, у которых уже есть заявки с state_id = 4
            Participation::where('project_id', $projectData['prev_project_id'])
                ->where('state_id', 3)
                ->whereNotIn('candidate_id', $candidatesWithState4)
                ->update(['state_id' => 4]);*/

            }
        });
    }

    private function insertProjectsIntoDatabase($projects)
    {
        DB::transaction(function () use ($projects) {
            foreach ($projects as $projectData) {
                $project = Project::create([
                    'id' => $projectData['id'],
                    'title' => $projectData['title'],
                    'places' => $projectData['places'],
                    'goal' => $projectData['goal'],
                    'description' => $projectData['description'],
                    'difficulty' => $projectData['difficulty'],
                    'date_start' => $projectData['date_start'],
                    'date_end' => $projectData['date_end'],
                    'requirements' => $projectData['requirements'],
                    'customer' => $projectData['customer'],
                    'additional_inf' => $projectData['additional_inf'],
                    'product_result' => $projectData['product_result'],
                    'study_result' => $projectData['study_result'],
                    'state_id' => $projectData['state_id'],
                    'type_id' => $projectData['type_id'],
                    'prev_project_id' => $projectData['prev_project_id'],
                    'department_id' => $projectData['department_id'],
                    'theme_source_id' => $projectData['theme_source_id'],
                    'rejection_reason' => $projectData['rejection_reason'],
                    'rejection_date' => $projectData['rejection_date'],
                    'project_review' => $projectData['project_review'],
                    'project_goal' => $projectData['project_goal'],
                    'created_at' => $projectData['created_at'],
                    'updated_at' => $projectData['updated_at'],
                ]);

                foreach ($projectData['specialities'] as $specialityData) {
                    ProjectSpeciality::create([
                        'project_id' => $project->id,
                        'speciality_id' => $specialityData['speciality_id'],
                        'course' => $specialityData['course'],
                        'priority' => $specialityData['priority'],
                    ]);
                }

                foreach ($projectData['participations'] as $participationData) {
                    Participation::create([
                        'created_at' => $participationData['created_at'],
                        'updated_at' => $participationData['updated_at'],
                        'priority' => $participationData['priority'],
                        'review' => $participationData['review'],
                        'project_id' => $participationData['project_id'],
                        'candidate_id' => $participationData['candidate_id'],
                        'state_id' => $participationData['state_id'],
                        'mark' => $participationData['mark'],
                        'additional_information' => $participationData['additional_information'],
                    ]);
                }
            }
        });
    }
    
}