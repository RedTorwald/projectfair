<?php

namespace App\Http\Controllers\ArmDistribution;


use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class GetManualDistributionController extends Controller
{


    public function __invoke()
    {

        $filteredFilePath = Storage::exists('3_updated.json') 
        ? '3_updated.json' 
        : '2_distribution.json';
        /*
        $filteredFilePath = Storage::exists('6_manual.json') 
        ? '6_manual.json' 
        : (Storage::exists('3_updated.json') 
            ? '3_updated.json' 
            : '2_distribution.json');*/

        $outputFilePath = '5_manual.json';  
        
        $this->findEligibleProjectsForCandidates($filteredFilePath, $outputFilePath); 
        
        $jsonData = Storage::get($outputFilePath); 
        $filteredParticipations = json_decode($jsonData, true); 
        
        return response()->json($filteredParticipations);
    }

    //--------------------------------------------------------------------------------------------------------------

    public function findEligibleProjectsForCandidates(string $filePath, string $outputFilePath)
    {
        // Читаем данные из исходного JSON
        $jsonData = json_decode(Storage::get($filePath), true);
        $candidates = array_merge($jsonData['excess_participations'], $jsonData['without_participation']);
        
        // Инициализация массивов для новых данных
        $candidatesWithProjects = [];
        $projects = $jsonData['projects'];
    
        // Массив для хранения всех проектов
        $allEligibleProjects = [];
    
        // Перебор кандидатов
        foreach ($candidates as $candidate) {
            if (is_null($candidate['institute_id'])) {
                continue;
            }
    
            // Извлекаем данные о кандидате
            $candidateInstitute = $candidate['institute_id'];
            $candidateDepartment = $candidate['department_id'];
            $candidateSpecialityId = $candidate['speciality_id'];
    
            $eligibleProjects = [];
            $eligibleProjectsID = [];
    
            // Перебор проектов
            foreach ($projects as $institute) {
                if ($institute['institute_id'] === $candidateInstitute) {
                    foreach ($institute['departments'] as $department) {
                        if ($department['department_id'] === $candidateDepartment) {
                            foreach ($department['projects'] as $project) {
                                $projectSpecialities = array_column($project['specialities'], 'id');
                                
                                // Проверяем соответствие специальности
                                if (in_array($candidateSpecialityId, $projectSpecialities)) {
                                    $eligibleProjects[] = [
                                        'project_id' => $project['project_id'],
                                        'project_title' => $project['title'],
                                        'places' => $project['places'],
                                        'candidates_count' => $project['candidates_count'],
                                    ];
                                    $eligibleProjectsID[] = $project['project_id'];
    
                                    // Добавляем проект в общий список
                                    if (!isset($allEligibleProjects[$project['project_id']])) {
                                        $allEligibleProjects[$project['project_id']] = [
                                            'project_id' => $project['project_id'],
                                            'project_title' => $project['title'],
                                            'places' => $project['places'],
                                            'candidates_count' => $project['candidates_count'],
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
    
            // Формируем запись для кандидата
            $candidatesWithProjects[] = [
                'candidate_id' => $candidate['candidate_id'],
                'fio' => $candidate['fio'],
                'course' => $candidate['course'],
                'training_group' => $candidate['training_group'],
                'priority' => $candidate['priority'],
                'institute_id' => $candidate['institute_id'],
                'institute_name' => $candidate['institute_name'],
                'department_id' => $candidate['department_id'],
                'department_name' => $candidate['department_name'],
                'speciality_id' => $candidateSpecialityId,
                'speciality_name' => $candidate['speciality_name'],
                'eligible_projects_ids' => $eligibleProjectsID,
            ];
        }
    
        // Формируем список всех проектов
        $eligibleProjects = array_values($allEligibleProjects);
    
        // Структура данных
        $result = [
            'candidates' => $candidatesWithProjects,
            'eligible_projects' => $eligibleProjects,
        ];
    
        // Записываем результат в файл
        Storage::put($outputFilePath, json_encode($result, JSON_PRETTY_PRINT));
    
        return response()->json($result);
    }
    
}
