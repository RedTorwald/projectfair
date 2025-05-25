<?php

namespace App\Http\Controllers\Project\Transfer;

use App\Http\Controllers\Controller;


use App\Models\Project;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Post(
 *     path="/transfer/processing/update",
 *     summary="Обновить проекты в состояние 'Обработка'",
 *     description="Этот метод обновляет проекты, находящиеся в состоянии 'Набор' (state_id = 1), переводя их в состояние 'Обработка' (state_id = 5). Также обновляются соответствующие состояния кандидатов.",
 *     operationId="updateToProcessingState",
 *     tags={"Projects Transfer"},
 *     @OA\Response(
 *         response=200,
 *         description="Проекты успешно обновлены",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="updated_projects", type="array", items=@OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", description="ID обновленного проекта"),
 *                 @OA\Property(property="state_id", type="integer", description="Новый ID состояния проекта"),
 *                 @OA\Property(property="updated_at", type="string", format="date-time", description="Дата последнего обновления проекта")
 *             )),
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
