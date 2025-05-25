<?php

namespace App\Http\Controllers\Project\Transfer;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Get(
 *     path="/transfer/active",
 *     summary="Получить проекты в состоянии 'Обработка'",
 *     description="Этот метод позволяет получить проекты, находящиеся в состоянии 'Обработка' (state_id = 5), с информацией о проекте и его участниках.",
 *     operationId="getProcessingProjects",
 *     tags={"Projects Transfer"},
 *     @OA\Response(
 *         response=200,
 *         description="Проекты в состоянии 'Обработка' успешно получены",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", description="ID проекта"),
 *                 @OA\Property(property="state_id", type="integer", description="ID состояния проекта"),
 *                 @OA\Property(property="title", type="string", description="Название проекта"),
 *                 @OA\Property(
 *                     property="participations",
 *                     type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="candidate_id", type="integer", description="ID кандидата"),
 *                         @OA\Property(property="state_id", type="integer", description="Состояние участия кандидата"),
 *                         @OA\Property(property="project_id", type="integer", description="ID проекта")
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Ошибка при получении данных"
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Ошибка сервера"
 *     )
 * )
 */

class GetProcessingProjectsController extends Controller
{
    public function __invoke()
    {
        $stateId = 5;  // состояние обработка заявок
        $projects = $this->getProjectsWithState($stateId);

        $formattedProjects = $this->formatProjectsData($projects);
        $this->saveProjectsToJson($formattedProjects);

        return response()->json($formattedProjects);
    }

    private function getProjectsWithState(int $stateId)
    {
        return Project::with(['department', 'supervisors', 'type', 'themeSource', 'projectSpecialities', 'participation'])
            ->where('state_id', $stateId)
            ->get();
    }

    private function formatProjectsData($projects)
    {
        return $projects->map(function ($project) {
            return [
                'id' => $project->id,
                'state_id' => $project->state_id,
                'title' => $project->title,
                'participations' => $project->participation->map(function ($participation) {
                    return [
                        'candidate_id' => $participation->candidate_id,
                        'state_id' => $participation->state_id,
                        'project_id' => $participation->project_id,                        
                    ];
                })->values()->toArray(),
            ];
        })->toArray();
    }

    private function saveProjectsToJson($projects)
    {
        Storage::put('projects_active_state_5.json', json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}