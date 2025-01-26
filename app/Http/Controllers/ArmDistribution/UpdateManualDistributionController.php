<?php

namespace App\Http\Controllers\ArmDistribution;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UpdateManualDistributionController extends Controller
{

    public function __invoke(Request $request)
    {
       
        $filePath = Storage::exists('3_updated.json') 
            ? '3_updated.json' 
            : '2_distribution.json';

        
        $jsonData = json_decode(Storage::get($filePath), true);

        // загружаем действия из лога 7_log.json, если он существует
        $logData = Storage::exists('7_log.json')
            ? json_decode(Storage::get('7_log.json'), true)
            : [];

        // применяем все действия из лога к данным
        $jsonData = $this->applyLogToData($jsonData, $logData);

        // фильтруем новых кандидатов из запроса (только тех, у кого есть selected_project)
        $candidatesData = array_filter($request->all(), function ($candidate) {
            return isset($candidate['selected_project']);
        });

        // добавляем новых кандидатов в лог
        $this->logCandidates($candidatesData);

        // применяем новые действия к данным
        $jsonData = $this->applyLogToData($jsonData, [$candidatesData]);

        // сохраняем обновленные данные обратно в 3_updated.json
        Storage::put('3_updated.json', json_encode($jsonData, JSON_PRETTY_PRINT));

        return response()->json($jsonData);
    }

    /**
     * Применяет лог к данным из файла
     */
    private function applyLogToData(array $jsonData, array $logData): array
    {
        $projects = $jsonData['projects'];
        $excessParticipations = $jsonData['excess_participations'];
        $withoutParticipation = $jsonData['without_participation'];

        foreach ($logData as $logEntry) {
            foreach ($logEntry as $candidate) {
                $candidateId = $candidate['candidate_id'];
                $priority = $candidate['priority'];
                $selectedProjectId = $candidate['selected_project'];
                $candidateInstitute = $candidate['institute_id'];
                $candidateDepartment = $candidate['department_id'];

                // удаляем кандидатов из списков лишних и без участия
                if ($priority === 4) {
                    $excessParticipations = array_filter($excessParticipations, function ($item) use ($candidateId) {
                        return $item['candidate_id'] !== $candidateId;
                    });
                } else {
                    $withoutParticipation = array_filter($withoutParticipation, function ($item) use ($candidateId) {
                        return $item['candidate_id'] !== $candidateId;
                    });
                }

                // добавляем кандидата на проект
                foreach ($projects as &$institute) {
                    if ($institute['institute_id'] === $candidateInstitute) {
                        foreach ($institute['departments'] as &$department) {
                            if ($department['department_id'] === $candidateDepartment) {
                                foreach ($department['projects'] as &$project) {
                                    if ($project['project_id'] === $selectedProjectId) {
                                        $project['candidates'][] = [
                                            'candidate_id' => $candidate['candidate_id'],
                                            'fio' => $candidate['fio'],
                                            'priority' => $candidate['priority'],
                                            'state_id' => 1,
                                            'created_at' => now()->toDateTimeString(),
                                        ];
                                        $project['candidates_count']++;
                                        break 3;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // возвращаем обновленные данные
        return [
            'projects' => $projects,
            'excess_participations' => array_values($excessParticipations),
            'without_participation' => array_values($withoutParticipation),
        ];
    }

    /**
     * Лог действий пользователя
     */
    public function logCandidates(array $candidatesData)
    {
        $logFilePath = '7_log.json';

        $existingLog = Storage::exists($logFilePath)
            ? json_decode(Storage::get($logFilePath), true)
            : [];

        $currentIndex = count($existingLog);

        $existingLog[$currentIndex] = $candidatesData;

        Storage::put($logFilePath, json_encode($existingLog, JSON_PRETTY_PRINT));
    }

}