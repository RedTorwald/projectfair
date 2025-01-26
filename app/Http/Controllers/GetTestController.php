<?php

namespace App\Http\Controllers;

use App\Models\Participation;
use App\Http\Services\CandidateDistributionServiceThird;
use Illuminate\Support\Facades\Storage;

class GetTestController extends Controller
{
    protected $candidateDistributionService;

    public function __construct(CandidateDistributionServiceThird $candidateDistributionService)
    {
        $this->candidateDistributionService = $candidateDistributionService;
    }


    public function __invoke()
    {
        $this->findDuplicateParticipations();
        // Шаг 1: получение структуры json
        $this->generateProjectStructure();

        $this->removeDuplicatesFromStructure();
     
        // Шаг 2: удаление заявок с приоритетом 2 и 3. Формирование команды из приоритета 1 
        $this->filterParticipations();
   
        // Шаг 3: формирование команды из 1 и 2 приоритета
        $this->secondFilterParticipations();

        // Шаг 4: получение лишних кандидатов. Удаление дубликатов. оставляем уникальные заявки лишних кандидатов
        $this->collectExcessParticipations();
 
        // Шаг 5: получение кандидатов без заявок
        $this->getCandidatesWithoutParticipation();

        // Шаг 6: распределение лишних кандидатов 
        $this->distributeExcessParticipations();

        // Шаг 7: распределение молчунов
        $this->distributeWithoutParticipation();

        // Шаг 8
        $this->groupProjects();
        
/* 
        

      //  $filteredFilePath = '7_without_distribution.json';

      /*
        $filteredFilePath = Storage::exists('6_manual.json') 
        ? '6_manual.json' 
        : (Storage::exists('3_updated.json') 
            ? '3_updated.json' 
            : '2_distribution.json');*/
       
            /*
        $filteredFilePath = Storage::exists('3_updated.json') 
        ? '3_updated.json' 
        : '2_distribution.json';*/
        $filteredFilePath = Storage::exists('11.json') 
        ? '11.json' 
        : '2_distribution.json';


        $jsonData = Storage::get($filteredFilePath); 
        $filteredParticipations = json_decode($jsonData, true); 

        // респонс
        return response()->json($filteredParticipations);
    }

    //--------------------------------------------------------------------------------------------------------------
    // шаг 0 получение дубликатов
    public function findDuplicateParticipations()
    {
        $currentYear = now()->year;
        $currentMonth = now()->month;
    
        // Определяем ближайший месяц семестра
       // $semesterMonth = ($currentMonth > 1 && $currentMonth < 9) ? 2 : 9;
       $currentYear = 2024;

       $semesterMonth = 9;
    
        // Шаг 1: загружаем необходимые данные через "eager loading"/жадную загрузку
        $participations = Participation::with(['candidate', 'project.department.institute', 'project.projectSpecialities'])
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $semesterMonth)
            ->where('state_id', 1)
            ->get();
    
        // Шаг 2: создаём массив для дубликатов
        $duplicates = [];
    
        // Массив для хранения уникальных сочетаний (candidate_id, priority)
        $uniqueCheck = [];
    
        foreach ($participations as $participation) {
            // Получаем связанные данные
            $candidate = $participation->candidate;
            
            $uniqueKey = $candidate->id . '-' . $participation->priority; // создаем ключ из candidate_id и priority
    
            // Проверяем, есть ли такой ключ уже (при совпадени ключений -> найден дубликат)
            if (isset($uniqueCheck[$uniqueKey])) {
                // Если такой ключ есть, то добавляем дубликат в массив
                $duplicates[] = [
                    'candidate_id' => $candidate->id,
                    'priority' => $participation->priority,
                    'created_at' => $participation->created_at,
                    'updated_at' => $participation->updated_at,
                    'fio' => $candidate->fio,
                    'project_id' => $participation->project_id,
                ];
            } else {
                // Если такого ключа нет, добавляем его в массив уникальных значений
                $uniqueCheck[$uniqueKey] = true;
            }
        }
    
        // Шаг 3: сохраняем дубликаты в файл JSON
        $jsonFilePath = 'duplicates.json';
        Storage::put($jsonFilePath, json_encode($duplicates, JSON_PRETTY_PRINT));
    
        // Возвращаем массив дубликатов
        return $duplicates;
    }

    // шаг 1 получение проектов и студентов на проектах
    public function generateProjectStructure()
    {
        $currentYear = now()->year; 
        $currentMonth = now()->month; 
        
        // Определяем ближайший месяц семестра
        $semesterMonth = ($currentMonth > 1 && $currentMonth < 9) ? 2 : 9;
        $currentYear = 2024; 
        $semesterMonth = 9;
        
        // Шаг 1: загружаем необходимые данные через "eager loading"
        $participations = Participation::with(['candidate', 'project.department.institute', 'project.projectSpecialities'])
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $semesterMonth)
            ->where('state_id', 1)
            ->get();
        
        // Шаг 2: создаём структуру
        $structure = [];
        
        foreach ($participations as $participation) {
            // получаем связанные данные
            $project = $participation->project;
            $candidate = $participation->candidate;
            $department = $project->department;
            $institute = $department->institute;
    
            // проверка на наличие проекта в стрктуре (при отсутствии проекта с таким id -> добавляем в массив)
            if (!isset($structure[$project->id])) {
                $specialities = $project->projectSpecialities;
                
                $structure[$project->id] = [
                    'project_id' => $project->id,
                    'title' => $project->title,
                    'places' => $project->places,
                    'candidates_count' => 0,
                    'institute_id' => $institute->id,
                    'department_id' => $department->id,
                    'specialities' => $specialities
                        ->filter(fn($projectSpeciality) => $projectSpeciality->priority === 1)
                        ->map(fn($projectSpeciality) => [
                            'id' => $projectSpeciality->speciality->id,
                            'name' => $projectSpeciality->speciality->name,
                            'priority' => $projectSpeciality->priority,
                            'course' => $projectSpeciality->course,
                        ])
                        ->values()->toArray(),
                    'candidates' => [],
                ];
            }
    
            // Добавляем заявку (участие) в проект
            $structure[$project->id]['candidates'][] = [
                'candidate_id' => $candidate->id,
                'training_group' => $candidate['training_group'],
                'course' => $candidate->course,
                'fio' => $candidate->fio,
                'priority' => $participation->priority,
                'created_at' => $participation->created_at,
                'updated_at' => $participation->updated_at,
            ];
    
            // Увеличиваем счётчик кандидатов
            $structure[$project->id]['candidates_count']++;
        }
    
        // Шаг 3: сортируем кандидатов по приоритету и дате создания заявки
        foreach ($structure as &$project) {
            $project['candidates'] = collect($project['candidates'])
                ->sortBy(fn($candidate) => [$candidate['priority'], $candidate['updated_at']])
                ->values()
                ->toArray();
        }
    
        // Шаг 4: сохраняем структуру в файл JSON
        $jsonFilePath = '00.json';
        Storage::put($jsonFilePath, json_encode(array_values($structure), JSON_PRETTY_PRINT));
    
        return array_values($structure);
    }

    // шаг 2 удаление дубликатов
    public function removeDuplicatesFromStructure()
    {
        $filePath = '00.json';
        $duplicatesFilePath = 'duplicates.json';
        $result = $this->candidateDistributionService->removeDuplicatesFromStructure($filePath, $duplicatesFilePath);
    
        return $result;
    }
   
    // шаг 2 удаление заявок с приоритетом 2 и 3 для проектных команд
    public function filterParticipations()
    {
        $filePath = '00.json';
        $filteredFilePath = '11.json';
        $filteredProjects = $this->candidateDistributionService->filterParticipations($filePath, $filteredFilePath); 
        
        return response()->json($filteredProjects);
    }
  
    // шаг 3 удаление заявок с приоритетом 3. Формирование команды из 1 и 2 приоритета
    public function secondFilterParticipations()
    {       
        $filePath = '11.json';
        $filteredFilePath = '11.json';                
        $result= $this->candidateDistributionService->secondFilterParticipations($filePath, $filteredFilePath);
                      
        return response()->json($result);
    }


    // шаг 4 получение лишних кандидатов. Удаление дубликатов. оставляем уникальные заявки лишних кандидатов
    public function collectExcessParticipations()
    {
        $filePath = '11.json';
        $filteredFilePath = '11.json';
        $result = $this->candidateDistributionService->collectExcessParticipations($filePath, $filteredFilePath);
    
        return $result;
    }


    // шаг 5 получение кандидатов без заявок.
    public function getCandidatesWithoutParticipation()
    {
        $filePath = '11.json';
        $filteredFilePath = '11.json';
        $result = $this->candidateDistributionService->getCandidatesWithoutParticipation($filePath, $filteredFilePath);
        
        return $result;
    }

    // Шаг 6 распределение лишних кандидатов  
    public function distributeExcessParticipations()
    {
        $filePath = '11.json';
        $filteredFilePath = '11.json';
        $result = $this->candidateDistributionService->distributeExcessParticipations($filePath, $filteredFilePath);
    
        return $result;
    }

    // Шаг 7 распределение молчунов  
    public function distributeWithoutParticipation()
    {
        $filePath = '11.json';
        $filteredFilePath = '11.json';      
        $result = $this->candidateDistributionService->distributeWithoutParticipation($filePath, $filteredFilePath);
    
        return $result;
    }  

    public function groupProjects()
    {
        $filePath = '11.json';
        $filteredFilePath = '11.json';      
        $result = $this->candidateDistributionService->groupProjects($filePath, $filteredFilePath);
    
        return $result;
    }  

}
