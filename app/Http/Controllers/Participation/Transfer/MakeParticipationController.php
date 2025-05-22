<?php

namespace App\Http\Controllers\Participation\Transfer;

use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use App\Models\Participation;
use Illuminate\Http\JsonResponse;

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