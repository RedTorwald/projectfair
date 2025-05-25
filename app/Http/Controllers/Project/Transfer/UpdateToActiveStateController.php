<?php

namespace App\Http\Controllers\Project\Transfer;

use App\Http\Controllers\Controller;

use App\Models\Project;
use App\Models\Participation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Post(
 *     path="/transfer/active/update",
 *     summary="Обновить проекты и участников в состояние 'Активное'",
 *     description="Этот метод обновляет проекты и их участников, переводя проекты в состояние 'Активное' (state_id = 2). Участники, которые находятся в состоянии 'Подано' (state_id = 1), изменяются на состояние 'Отказано' (state_id = 4). Удаляются все заявки с состоянием 'Подано' (state_id = 1), которые не имеют альтернативных заявок на тот же проект с состоянием 'Отказано' (state_id = 4).",
 *     operationId="updateToActiveState",
 *     tags={"Projects Transfer"},
 *     @OA\Response(
 *         response=200,
 *         description="Проекты и участники успешно обновлены",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="updated_projects", type="array", description="Обновленные проекты",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", description="ID проекта"),
 *                     @OA\Property(property="state_id", type="integer", description="Состояние проекта"),
 *                     @OA\Property(property="title", type="string", description="Название проекта")
 *                 )
 *             ),
 *             @OA\Property(property="updated_count", type="integer", description="Количество обновленных проектов")
 *         )
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
