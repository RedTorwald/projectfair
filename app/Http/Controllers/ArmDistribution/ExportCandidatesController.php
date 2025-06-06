<?php

namespace App\Http\Controllers\ArmDistribution;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use App\Models\Participation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExportCandidatesController extends Controller
{
    public function __invoke()
    {
       
       $filePath = '6_final_distribution.json';
       

        if (!Storage::exists($filePath)) {
            return response()->json(['error' => 'Файл 6_final_distribution.json не найден'], 404);
        }

        $jsonData = json_decode(Storage::get($filePath), true);

        // 2.  массив для заявок
        $applications = [];
        $currentTimestamp = Carbon::now()->toDateTimeString();

        // 3. Проходим по каждому институту, кафедре и проекту
        foreach ($jsonData['projects'] as $institute) {
            foreach ($institute['departments'] as $department) {
                foreach ($department['projects'] as $project) {
                    $projectId = $project['project_id'];

                    // Собираем всех кандидатов из текущего проекта
                    foreach ($project['candidates'] as $candidate) {
                        $applications[] = [
                            'candidate_id' => $candidate['candidate_id'],
                            'priority' => $candidate['priority'],
                            'project_id' => $projectId,
                            'state_id' => 3, // Устанавливаем фиксированный state_id
                            'created_at' => $currentTimestamp,
                            'updated_at' => $currentTimestamp,
                        ];
                    }
                }
            }
        }

        // 4. сохраняем массив заявок в файл 8_final.json
        $outputFilePath = '8_final.json';
        Storage::put($outputFilePath, json_encode($applications, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        
        // 5. вставляем заявки в базу данных
        try {
            DB::beginTransaction();
            Participation::insert($applications); // массовая вставка заявок
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Ошибка при вставке заявок в базу данных', 'details' => $e->getMessage()], 500);
        }

       
        return response()->json([
            'message' => 'Кандидаты успешно экспортированы и сохранены в базе данных',
            'file' => $outputFilePath,
        ]);
    }
}