<?php

namespace App\Http\Controllers\ArmDistribution;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UpdateLastManualActionController extends Controller
{
   
    public function __invoke()
    {
        $logFilePath = '7_log.json';
        $manualFilePath = '3_updated.json';

        // проверяем наличие файла лога
        if (!Storage::exists($logFilePath)) {
            return response()->json(['error' => 'Отсутствует ручное распределение'], 404);
        }

        // загружаем данные из лога
        $logData = json_decode(Storage::get($logFilePath), true);

        // удаляем пустые массивы из лога
        $logData = array_filter($logData, function ($entry) {
            return !empty($entry);
        });

        // если после фильтрации лог остался пустым, сохраняем очищенный файл и возвращаем ошибку
        if (empty($logData)) {
            Storage::put($logFilePath, json_encode($logData, JSON_PRETTY_PRINT));
            return response()->json(['error' => 'Отсутствуют последние действия'], 404);
        }

        // последняя запись из лога
        $lastIndex = max(array_keys($logData));
        $lastCandidates = $logData[$lastIndex];

        // загружаем данные из файла 3_updated.json
        if (!Storage::exists($manualFilePath)) {
            return response()->json(['error' => 'Файл распределения не найден'], 404);
        }

        $manualData = json_decode(Storage::get($manualFilePath), true);

        $projects = $manualData['projects'];
        $excessParticipations = $manualData['excess_participations'];
        $withoutParticipation = $manualData['without_participation'];

        foreach ($lastCandidates as $candidate) {
            $candidateId = $candidate['candidate_id'];
            $candidateInstitute = $candidate['institute_id'];
            $candidateDepartment = $candidate['department_id'];
            $selectedProjectId = $candidate['selected_project'];

            // удаляем кандидата из проекта
            foreach ($projects as &$institute) {
                if ($institute['institute_id'] === $candidateInstitute) {
                    foreach ($institute['departments'] as &$department) {
                        if ($department['department_id'] === $candidateDepartment) {
                            foreach ($department['projects'] as &$project) {
                                if ($project['project_id'] === $selectedProjectId) {
                                    $project['candidates'] = array_filter($project['candidates'], function ($projCandidate) use ($candidateId) {
                                        return $projCandidate['candidate_id'] !== $candidateId;
                                    });

                                    break 3;
                                }
                            }
                        }
                    }
                }
            }

            // готовим данные для добавления в списки лишних или без участия
            $candidateLog = [
                'candidate_id' => $candidate['candidate_id'],
                'fio' => $candidate['fio'],
                'course' => $candidate['course'],
                'training_group' => $candidate['training_group'],
                'priority' => $candidate['priority'],
                'state_id' => $candidate['priority'],
                'institute_id' => $candidate['institute_id'],
                'institute_name' => $candidate['institute_name'],
                'department_id' => $candidate['department_id'],
                'department_name' => $candidate['department_name'],
                'speciality_id' => $candidate['speciality_id'],
                'speciality_name' => $candidate['speciality_name'],
            ];

            if ($candidate['priority'] === 4) {
                $excessParticipations[] = $candidateLog;
            } else {
                $withoutParticipation[] = $candidateLog;
            }
        }

        // обновляем данные в 3_updated.json
        $manualData['projects'] = $projects;
        $manualData['excess_participations'] = array_values($excessParticipations);
        $manualData['without_participation'] = array_values($withoutParticipation);

        Storage::put($manualFilePath, json_encode($manualData, JSON_PRETTY_PRINT));

        // удаляем последнюю запись из лога
        unset($logData[$lastIndex]);
        Storage::put($logFilePath, json_encode($logData, JSON_PRETTY_PRINT));

        return response()->json([
            'updated_data' => $manualData,
        ]);
    }
   
   /*
    public function __invoke()
    {
        $logFilePath = '7_log.json';
        //$manualFilePath = '6_manual.json';
        $manualFilePath = '3_updated.json';
        
        if (!Storage::exists($logFilePath)) {
            return response()->json(['error' => 'Отсутствует ручное распределение'], 404);
        }
        
        $logData = json_decode(Storage::get($logFilePath), true);

        if (empty($logData)) {
            return response()->json(['error' => 'Отсутсвуют последние действия'], 404);
        }

        // последняя запись из лога
        $lastIndex = max(array_keys($logData));
        $lastCandidates = $logData[$lastIndex];
      
        $manualData = json_decode(Storage::get($manualFilePath), true);

        $projects = $manualData['projects'];
        $excessParticipations = $manualData['excess_participations'];
        $withoutParticipation = $manualData['without_participation'];

        foreach ($lastCandidates as $candidate) {
            $candidateId = $candidate['candidate_id'];
            $candidateInstitute = $candidate['institute_id'];
            $candidateDepartment = $candidate['department_id'];
            $selectedProjectId = $candidate['selected_project'];
           
            foreach ($projects as &$institute) {
                if ($institute['institute_id'] === $candidateInstitute) {
                    foreach ($institute['departments'] as &$department) {
                        if ($department['department_id'] === $candidateDepartment) {
                            foreach ($department['projects'] as &$project) {
                                if ($project['project_id'] === $selectedProjectId) {
                                    // удаляем кандидата из списка проекта
                                    $project['candidates'] = array_filter($project['candidates'], function ($projCandidate) use ($candidateId) {
                                        return $projCandidate['candidate_id'] !== $candidateId;
                                    });                                 
                                    
                                    break 3; 
                                }
                            }
                        }
                    }
                }
            }

            
            $candidateLog = [
                'candidate_id' => $candidate['candidate_id'],
                'fio' => $candidate['fio'],
                'course' => $candidate['course'],
                'training_group' => $candidate['training_group'],
                'priority' => $candidate['priority'],
                'state_id' => $candidate['priority'],               
                'institute_id' => $candidate['institute_id'],
                'institute_name' => $candidate['institute_name'],
                'department_id' => $candidate['department_id'],
                'department_name' => $candidate['department_name'],
                'speciality_id' => $candidate['speciality_id'],
                'speciality_name' => $candidate['speciality_name'],
            ];

            if ($candidate['priority'] === 4) {
                $excessParticipations[] = $candidateLog;
            } else {
                $withoutParticipation[] = $candidateLog;
            }
        }
        
        $manualData['projects'] = $projects;
        $manualData['excess_participations'] = $excessParticipations;
        $manualData['without_participation'] = $withoutParticipation;

        Storage::put($manualFilePath, json_encode($manualData, JSON_PRETTY_PRINT));

       
        unset($logData[$lastIndex]);
        Storage::put($logFilePath, json_encode($logData, JSON_PRETTY_PRINT));

        return response()->json([            
            'updated_data' => $manualData,
        ]);
    }*/
}