<?php

namespace App\Http\Controllers;

use App\Models\Participation;
use App\Http\Services\CandidateDistributionService;
use Illuminate\Support\Facades\Storage;

class GetAutoDistributionController extends Controller
{
    protected $candidateDistributionService;

    public function __construct(CandidateDistributionService $candidateDistributionService)
    {
        $this->candidateDistributionService = $candidateDistributionService;
    }


    public function __invoke()
    {
        // Шаг 1: получение структуры json
        $this->generateProjectStructure();
               
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

      //  $filteredFilePath = '7_without_distribution.json';

      
        $filteredFilePath = Storage::exists('6_manual.json') 
        ? '6_manual.json' 
        : (Storage::exists('3_updated.json') 
            ? '3_updated.json' 
            : '2_distribution.json');
       
            /*
        $filteredFilePath = Storage::exists('3_updated.json') 
        ? '3_updated.json' 
        : '2_distribution.json';*/


        $jsonData = Storage::get($filteredFilePath); 
        $filteredParticipations = json_decode($jsonData, true); 

        // респонс
        return response()->json($filteredParticipations);
    }

    //--------------------------------------------------------------------------------------------------------------

    // шаг 1 получение проектов и студентов на проектах
    public function generateProjectStructure()
    {
       
        $currentYear = now()->year; 
        $currentMonth = now()->month; 
        
        // Определяем ближайший месяц семестра
        $semesterMonth = ($currentMonth > 2 && $currentMonth < 9) ? 2 : 9;
        
        // шаг 1
        // при помощи модели Participation получаем связи candidate, project. Для project получаем институты и скиллы. Принцип "eager loading" 
        $participations = Participation::with(['candidate', 'project.department.institute', 'project.projectSpecialities']) 
            ->whereYear('created_at', $currentYear) // год по updated_at
            ->whereMonth('created_at', $semesterMonth) // ближайший семестр (2 или 9)
            ->where('state_id', 1) // заявки с состоянием 1
            ->get();
            /*
        $currentYear = now()->year; // получение текущего года
        $month = 9; // нужно исправить на получение месяца семестра
    
        // шаг 1 
        // при помощи модели Participation получаем связи candidate, project. Для project получаем институты и скиллы. Принцип "eager loading" 
        $participations = Participation::with(['candidate', 'project.department.institute', 'project.projectSpecialities']) 
            ->whereYear('created_at', $currentYear) // год + ЗАМЕНИТЬ НА UPDATED_AT
            ->whereMonth('created_at', $month) // месяц
            ->where('state_id', 1) // заявки с состоянием 1
            ->get();*/

        // шаг 2: массив для структуры JSON
        $structure = [];
    
        foreach ($participations as $participation) {
            // получаем из переменной институт, кафедру, проект и кандидата
            $institute = $participation->project->department->institute;
            $department = $participation->project->department;
            $project = $participation->project;
            $candidate = $participation->candidate;
    
            // шаг 3: создаем структуру json: projects -> institute -> departments -> department -> project

            // проверка на наличие института в структуре
            if (!isset($structure[$institute->id])) {
                $structure[$institute->id] = [
                    'institute_id' => $institute->id,
                    'institute_name' => $institute->name,
                    'departments' => []
                ];
            }
    
            // проверка на наличие кафедры в институте
            if (!isset($structure[$institute->id]['departments'][$department->id])) {
                $structure[$institute->id]['departments'][$department->id] = [
                    'department_id' => $department->id,
                    'department_name' => $department->name,
                    'projects' => []
                ];
            }    
            
            if (!isset($structure[$institute->id]['departments'][$department->id]['projects'][$project->id])) {
                // получаем специальность проекта методом projectSpecialities
                $specialities = $project->projectSpecialities; //получаем коллекцию связанных моделей из БД. "Ленивая загрузка"
    
                // Добавляем проект со специальностями
                $structure[$institute->id]['departments'][$department->id]['projects'][$project->id] = [
                    'project_id' => $project->id,
                    'places' => $project->places, // количество мест в проекте
                    'candidates_count' => 0, // счётчик кандидатов
                    'title' => $project->title,
                    'candidates' => [],
                    'specialities' => $specialities
                        ->filter(function ($projectSpeciality) {                           
                            return $projectSpeciality->priority === 1;
                        })
                        ->map(function ($projectSpeciality) {                           
                            $speciality = $projectSpeciality->speciality;
                            return [
                                'id' => $speciality->id,
                                'name' => $speciality->name,
                                'priority' => $projectSpeciality->priority,
                                'course' => $projectSpeciality->course
                            ];
                        })->values()->toArray(),
                ];
            }
    
            // добавляем кандидата в проект
            $structure[$institute->id]['departments'][$department->id]['projects'][$project->id]['candidates'][] = [
                'candidate_id' => $candidate->id,
                'training_group' => $candidate['training_group'],
                'course' => $candidate->course,
                'fio' => $candidate->fio,
                'priority' => $participation->priority,
                'created_at' => $participation->created_at, // ПОМЕНЯТЬ НА UPDATED_AT
                'updated_at' => $participation->updated_at
            ];
    
            // счётчик++
            $structure[$institute->id]['departments'][$department->id]['projects'][$project->id]['candidates_count']++;
        }
    
        // шаг 4: сортировка кандидатов по приоритету и дате обновления в каждом проекте
        foreach ($structure as &$institute) {
            foreach ($institute['departments'] as &$department) {
                foreach ($department['projects'] as &$project) {
                    // сортировка
                    $project['candidates'] = collect($project['candidates'])->sortBy(function ($candidate) {  // массив -> в коллекцию Laravel для кастомной сортировки
                        return [$candidate['priority'], $candidate['created_at']]; //сортировка по priority и updated_at
                    })->values()->toArray(); // возвращаем в массив
                }
            }
        }
    
        // шаг 5: преобразуем ассоциативные массивы в индексированные
        $structure = array_values(array_map(function ($institute) {
            $institute['departments'] = array_values(array_map(function ($department) {
                $department['projects'] = array_values($department['projects']);
                return $department;
            }, $institute['departments']));
            return $institute;
        }, $structure));
           

        $jsonFilePath = '1_projects_structure.json';
        Storage::put($jsonFilePath, json_encode($structure, JSON_PRETTY_PRINT));    
        
        return $structure;
    }   
    

    
    // шаг 2 удаление заявок с приоритетом 2 и 3 для проектных команд
    public function filterParticipations()
    {
        $filePath = '1_projects_structure.json';
        $filteredFilePath = '2_distribution.json';
        $filteredProjects = $this->candidateDistributionService->filterParticipations($filePath, $filteredFilePath); 
        
        return response()->json($filteredProjects);
    }
   
    // шаг 3 удаление заявок с приоритетом 3. Формирование команды из 1 и 2 приоритета
    public function secondFilterParticipations()
    {       
        $filePath = '2_distribution.json';
        $filteredFilePath = '2_distribution.json';                
        $result= $this->candidateDistributionService->secondFilterParticipations($filePath, $filteredFilePath);
                      
        return response()->json($result);
    }

    // шаг 4 получение лишних кандидатов. Удаление дубликатов. оставляем уникальные заявки лишних кандидатов
    public function collectExcessParticipations()
    {
        $filePath = '2_distribution.json';
        $filteredFilePath = '2_distribution.json';
        $result = $this->candidateDistributionService->collectExcessParticipations($filePath, $filteredFilePath);
    
        return $result;
    }

    // шаг 5 получение кандидатов без заявок.
    public function getCandidatesWithoutParticipation()
    {
        $filePath = '2_distribution.json';
        $filteredFilePath = '2_distribution.json';
        $result = $this->candidateDistributionService->getCandidatesWithoutParticipation($filePath, $filteredFilePath);
        
        return $result;
    }

    // Шаг 6 распределение лишних кандидатов  
    public function distributeExcessParticipations()
    {
        $filePath = '2_distribution.json';
        $filteredFilePath = '2_distribution.json';
        $result = $this->candidateDistributionService->distributeExcessParticipations($filePath, $filteredFilePath);
    
        return $result;
    }
     
    // Шаг 7 распределение молчунов  
    public function distributeWithoutParticipation()
    {
        $filePath = '2_distribution.json';
        $filteredFilePath = '2_distribution.json';      
        $result = $this->candidateDistributionService->distributeWithoutParticipation($filePath, $filteredFilePath);
    
        return $result;
    }  



}
