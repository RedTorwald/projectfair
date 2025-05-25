<?php

namespace App\Http\Controllers\Project\Transfer;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectSpeciality;
use App\Models\Participation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * @OA\Post(
 *     path="/transfer/approved/update",
 *     summary="Обновить активные проекты в состояние одобрены",
 *     description="Этот метод обновляет состояние проектов, клонирует их, сохраняет новые проекты в базу данных и изменяет состояние оригинальных проектов.",
 *     operationId="updateToApprovedProjects",
 *     tags={"Projects Transfer"},
 *     @OA\Response(
 *         response=200,
 *         description="Проекты успешно обновлены, клонированы и сохранены"
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Ошибка при обновлении проектов"
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Ошибка сервера"
 *     )
 * )
 */

class UpdateToApprovedController extends Controller
{
    public function __invoke()
    {
       

        $projects = $this->loadProjectsFromJson();

        $clonedProjects = $this->prepareProjectsForCloning($projects);

        $this->saveClonedProjectsToJson($clonedProjects);

        $this->insertProjectsIntoDatabase($clonedProjects);

        $this->updateOriginalProjectsState($projects);

        

        //return response()->json($clonedProjects);

        return response()->json([
            'updated_original_project_ids' => array_column($projects, 'id'),
            'updated_original_projects_count' => count($projects),
            'cloned_projects' => $clonedProjects,
            'cloned_count' => count($clonedProjects),
        ]);
    }


    private function loadProjectsFromJson()
    {
        $json = Storage::get('active_projects_state_2.json');
        return json_decode($json, true);
    }
   


    private function prepareProjectsForCloning($projects)
    {
        $lastProjectId = Project::max('id');
        $newId = $lastProjectId + 1;
        $currentTimestamp = Carbon::now();

        return array_values(array_filter(array_map(function ($project) use (&$newId, $currentTimestamp) {
            $start = Carbon::parse($project['date_start']);
            $end = Carbon::parse($project['date_end']);

            if ($start->diffInMonths($end) <= 5) {
                return false;
            }

            $project['prev_project_id'] = $project['id'];
            $project['id'] = $newId++;
            $project['state_id'] = 9;
            $project['created_at'] = $currentTimestamp;
            $project['updated_at'] = $currentTimestamp;

            // обновляем состояние и приоритет у кандидатов
            if (!empty($project['participations'])) {
                $project['participations'] = array_map(function ($participation) use ($project, $currentTimestamp) {
                    return [
                        'candidate_id' => $participation['candidate_id'],
                        'project_id' => $project['id'],
                        'state_id' => 1,
                        'priority' => 4,
                        'review' => "   ",
                        'mark' => "   ",
                        'additional_information' =>"   ",
                        'created_at' => $currentTimestamp,
                        'updated_at' => $currentTimestamp,
                    ];
                }, $project['participations']);
            }

            return $project;
        }, $projects)));
    }


    private function saveClonedProjectsToJson($projects)
    {
        Storage::put('active_projects_state_9.json', json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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

                foreach ($projectData['supervisors'] as $supervisor) {
                    DB::table('project_supervisor')->insert([
                        'project_id' => $project->id,
                        'supervisor_id' => $supervisor['id'],
                    ]);
                }
            }
        });
    }
   

    private function updateOriginalProjectsState($originalProjects)
    {
        $originalProjectIds = array_column($originalProjects, 'id'); // id всех полученных проектов
        
        Project::whereIn('id', $originalProjectIds)->update(['state_id' => 4]);
    }
    
}