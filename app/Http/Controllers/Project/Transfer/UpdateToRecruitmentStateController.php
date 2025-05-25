<?php

namespace App\Http\Controllers\Project\Transfer;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Post(
 *     path="/transfer/recruitment/update",
 *     summary="Обновить состояние проектов на 'Набор'",
 *     description="Этот метод обновляет состояние проектов, которые находятся в состоянии 'Одобрено' (state_id = 9), на состояние 'Набор' (state_id = 1). Он также обновляет состояние кандидатов в соответствующих проектах.",
 *     operationId="updateToRecruitmentState",
 *     tags={"Projects Transfer"},
 *     @OA\Response(
 *         response=200,
 *         description="Успешно обновлены проекты",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="updated_projects", type="array", 
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", description="ID проекта"),
 *                     @OA\Property(property="state_id", type="integer", description="ID состояния проекта")
 *                 )
 *             ),
 *             @OA\Property(property="updated_count", type="integer", description="Количество обновленных проектов")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Ошибка при загрузке или обработке данных"
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Ошибка сервера"
 *     )
 * )
 */


class UpdateToRecruitmentStateController extends Controller
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
        $json = Storage::get('projects_recruitment_state_9.json');
        return json_decode($json, true);
    }

    private function updateProjectAndCandidateStates($projects)
    {
        foreach ($projects as &$project) {
            $project['state_id'] = 1;
            
        }
        return $projects;
    }

    private function saveUpdatedProjectsToJson($projects)
    {
        Storage::put('projects_recruitment_state_1.json', json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function updateDatabaseStates($projects)
    {
        $projectIds = array_column($projects, 'id');

        Project::whereIn('id', $projectIds)->update(['state_id' => 1]);
        
    }
}
