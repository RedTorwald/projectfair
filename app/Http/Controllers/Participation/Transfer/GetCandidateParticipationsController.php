<?php

namespace App\Http\Controllers\Participation\Transfer;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Get(
 *     path="/transfer/students/participations",
 *     summary="Получение заявок кандидата в проектах за последние 3 месяца",
 *     description="Этот метод получает все заявки кандидата в проектах за последние 3 месяца.",
 *     operationId="getCandidateParticipations",
 *     tags={"Participation Transfer"},
 *     @OA\Parameter(
 *         name="candidate_id",
 *         in="query",
 *         description="ID кандидата, для которого нужно получить заявки",
 *         required=true,
 *         @OA\Schema(
 *             type="integer",
 *             example=123
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Список заявок кандидата",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="candidate_id", type="integer", example=123),
 *             @OA\Property(
 *                 property="participations",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="project_id", type="integer", example=1001),
 *                     @OA\Property(property="candidate_id", type="integer", example=123),
 *                     @OA\Property(property="priority", type="integer", example=2),
 *                     @OA\Property(property="state_id", type="integer", example=1)
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Ошибка при обработке запроса"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Участие не найдено"
 *     )
 * )
 */

class GetCandidateParticipationsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $candidateId = $request->input('candidate_id'); 
        $currentTimeBound = now()->subMonth(3); 

        $participations = Participation::where('candidate_id', $candidateId)           
            ->where('created_at', '>=', $currentTimeBound)
            ->get(['id','project_id', 'candidate_id', 'priority', 'state_id']);

        return response()->json([
            'candidate_id' => $candidateId,
            'participations' => $participations,            
        ]);
    }
}