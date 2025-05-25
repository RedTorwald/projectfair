<?php

namespace App\Http\Controllers\Participation\Transfer;

use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Post(
 *     path="/transfer/students/participations",
 *     summary="Создание или перевод заявки кандидата на проект",
 *     description="Этот метод позволяет создать новую заявку кандидата на проект или обновить существующую заявку, в зависимости от текущего состояния.",
 *     operationId="makeParticipation",
 *     tags={"Participation Transfer"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             required={"candidate_id", "project_id", "reason_message"},
 *             @OA\Property(property="candidate_id", type="integer", example=123),
 *             @OA\Property(property="project_id", type="integer", example=1001),
 *             @OA\Property(property="reason_message", type="string", example="Причина изменения заявки")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Заявка успешно создана или обновлена",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="message", type="string", example="Новая заявка успешно создана"),
 *             @OA\Property(property="new_participation", type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="candidate_id", type="integer", example=123),
 *                 @OA\Property(property="project_id", type="integer", example=1001),
 *                 @OA\Property(property="state_id", type="integer", example=1),
 *                 @OA\Property(property="priority", type="integer", example=1),
 *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-22T10:00:00Z"),
 *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-22T10:00:00Z")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Ошибка в запросе"
 *     ) 
 * )
 */

class MakeParticipationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $candidateId = $request->input('candidate_id');
        $projectId = $request->input('project_id');
        $reasonMessage = $request->input('reason_message');

        $currentTimeBound = now()->subMonth(3); 
        
        // 0. Получаем заявку с первым приоритетом со статусом 3
        $existsTeam = Participation::where('candidate_id', $candidateId)            
            ->where('priority', 1)
            ->where('state_id', 3)
            ->where('created_at', '>=', $currentTimeBound)
            ->exists();

        if (!$existsTeam) {
            // 1. Удаляем заявки со статусом 4 (для конкретного проекта)
            Participation::where('candidate_id', $candidateId)
                ->where('project_id', $projectId)
                ->where('priority', 1)
                ->where('state_id', 4)
                ->delete();

            // 2. Переводим все заявки со статусом 1 и приоритетом 1 в архив (на любом проекте)
            Participation::where('candidate_id', $candidateId)
                ->where('priority', 1)
                ->where('state_id', 1)
                ->update([
                    'state_id' => 4,
                    'additional_information' => $reasonMessage, 
                ]);

            // 3.Проверка на наличие активной заявки на этот проект
            $exists = Participation::where('candidate_id', $candidateId)
            ->where('project_id', $projectId)
            ->where('priority', 1)
            ->where('state_id', 1)
            ->exists();

            if ($exists) {
            return response()->json([
                'message' => 'Активная заявка на этот проект уже существует',
            ], 409);
            }

            // 4. Создаём новую заявку
            $newParticipation = Participation::create([
                'candidate_id' => $candidateId,
                'project_id' => $projectId,
                'state_id' => 1,
                'priority' => 1,
            ]);

            return response()->json([
                'message' => 'Новая заявка успешно создана',
                'new_participation' => $newParticipation
            ]);
        }


        Participation::where('candidate_id', $candidateId)
            ->where('priority', 1)
            ->where('state_id', 3)
            ->where('created_at', '>=', $currentTimeBound)
            ->delete();

          // 4. Создаём новую заявку
        $newParticipation = Participation::create([
            'candidate_id' => $candidateId,
            'project_id' => $projectId,
            'state_id' => 3,
            'priority' => 1,
        ]);
            

        return response()->json([
            'message' => 'Новая заявка успешно создана',
            'new_participation' => $newParticipation
        ]);
    }
}