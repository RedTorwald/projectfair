<?php

namespace App\Http\Controllers\ArmDistribution;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use App\Models\Participation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CancelExportController extends Controller
{
    public function __invoke()
    {
        // 1. Путь к файлу с заявками
        $filePath = '8_final.json';

        // 2. Проверяем наличие файла
        if (!Storage::exists($filePath)) {
            return response()->json(['error' => 'Файл 8_final.json не найден'], 404);
        }

        // 3. Читаем данные из файла
        $jsonData = json_decode(Storage::get($filePath), true);

        // Проверяем, есть ли данные для удаления
        if (empty($jsonData)) {
            return response()->json(['error' => 'Файл 8_final.json не содержит заявок'], 404);
        }

        try {
            DB::beginTransaction();

            // 4. Удаляем заявки из базы данных
            foreach ($jsonData as $application) {
                $candidateId = $application['candidate_id'];
                $projectId = $application['project_id'];

                // Удаляем только те заявки, у которых state_id = 10
                DB::table('participations')
                    ->where('candidate_id', $candidateId)
                    ->where('project_id', $projectId)
                    ->where('state_id', 10)
                    ->delete();
            }

            DB::commit();

            return response()->json(['message' => 'Заявки с state_id = 10 успешно удалены из базы данных']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Ошибка при удалении заявок',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}