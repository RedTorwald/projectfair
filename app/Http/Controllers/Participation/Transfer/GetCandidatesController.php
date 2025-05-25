<?php


namespace App\Http\Controllers\Participation\Transfer;

use App\Http\Controllers\Controller;

use App\Models\Candidate;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Get(
 *     path="/transfer/students",
 *     summary="Получить список кандидатов возможных проектов",
 *     description="Возвращает список кандидатов, которые могут участвовать в проектной деятельности",
 *     operationId="getCandidatesAndProjects",
 *     tags={"Participation Transfer"},
 *     @OA\Response(
 *         response=200,
 *         description="Успешный ответ",
 *         @OA\JsonContent(
 *             @OA\Property(
 *                 property="candidates",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="fio", type="string", example="Иванов Иван Иванович"),
 *                     @OA\Property(property="numz", type="string", example="123456"),
 *                     @OA\Property(property="course", type="integer", example=3),
 *                     @OA\Property(property="training_group", type="string", example="ИСТб-21-1")
 *                 )
 *             ),
 *             @OA\Property(
 *                 property="projects",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", example=5),
 *                     @OA\Property(property="title", type="string", example="Проект"),
 *                     @OA\Property(property="department_id", type="integer", example=2)
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class GetCandidatesController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $candidates = Candidate::where('can_send_participations', 1)
            ->get(['id', 'fio', 'numz', 'course', 'training_group']); 

        $projects = Project::whereIn('state_id', [1, 2, 3])
            ->get(['id', 'title', 'department_id',]); 

        return response()->json([
            'candidates' => $candidates,
            'projects' => $projects,
        ]);
    }
}