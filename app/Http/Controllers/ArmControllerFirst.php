<?php

namespace App\Http\Controllers;

use App\Models\Participation;
use App\Http\Services\CandidateDistributionServiceSecond;
use Illuminate\Support\Facades\Storage;

class ArmControllerFirst extends Controller
{
    protected $candidateDistributionService;

    public function __construct(CandidateDistributionServiceSecond $candidateDistributionService)
    {
        $this->candidateDistributionService = $candidateDistributionService;
    }


    public function __invoke()
    {
        // Шаг 1: получение структуры json
        $this->generateProjectStructure();
        $this->processCandidateDistribution();

       
        $filteredFilePath = Storage::exists('8_updated.json') 
        ? '8_updated.json' 
        : '7_without_distribution.json';
        $jsonData = Storage::get($filteredFilePath); 
        $filteredParticipations = json_decode($jsonData, true); 

        // респонс
        return response()->json($filteredParticipations);
    }

    //--------------------------------------------------------------------------------------------------------------

    // шаг 1 получение проектов и студентов на проектах
    public function generateProjectStructure()
    {        
        $currentYear = now()->year; // получение текущего года
        $month = 9; // нужно исправить на получение месяца семестра
    
        // шаг 1 
        // при помощи модели Participation получаем связи candidate, project. Для project получаем институты и скиллы. Принцип "eager loading" 
        $participations = Participation::with(['candidate', 'project.department.institute', 'project.projectSpecialities']) 
            ->whereYear('created_at', $currentYear) // год + ЗАМЕНИТЬ НА UPDATED_AT
            ->whereMonth('created_at', $month) // месяц
            ->where('state_id', 1) // заявки с состоянием 1
            ->get();
    
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
                    'specialities' => $specialities->map(function ($projectSpeciality) {
                        // Получаем коллекцию специальностей Speciality связанных с проектом через связь speciality. "Ленивая загрузка"
                        $speciality = $projectSpeciality->speciality;
                        return [
                            'id' => $speciality->id, 
                            'name' => $speciality->name, 
                            'priority' => $speciality->priority 
                        ];
                    })->toArray(),
                ];
            }
    
            // Добавляем кандидата в проект
            $structure[$institute->id]['departments'][$department->id]['projects'][$project->id]['candidates'][] = [
                'candidate_id' => $candidate->id,
                'fio' => $candidate->fio,
                'priority' => $participation->priority,
                'created_at' => $participation->created_at // ПОМЕНЯТЬ НА UPDATED_AT
            ];
    
            // счётчик++
            $structure[$institute->id]['departments'][$department->id]['projects'][$project->id]['candidates_count']++;
        }
    
        // шаг 4: сортировка кандидатов по приоритету и дате создания в каждом проекте
        foreach ($structure as &$institute) {
            foreach ($institute['departments'] as &$department) {
                foreach ($department['projects'] as &$project) {
                    // сортировка
                    $project['candidates'] = collect($project['candidates'])->sortBy(function ($candidate) {  // массив -> в коллекцию Laravel для кастомной сортировки
                        return [$candidate['priority'], $candidate['created_at']]; //сортировка по priority и created_at
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
        // респонс
        return response()->json($structure);
    }

    public function processCandidateDistribution()
    {
        $filePath = '1_projects_structure.json';
        $data = json_decode(Storage::get($filePath), true);

        // Шаг 2: Удаляем заявки с приоритетом 2 и 3, оставляя только 1-й приоритет
        $data = $this->candidateDistributionService->filterParticipations($data);

        // Шаг 3: Удаляем заявки с приоритетом 3, оставляя 1-й и 2-й приоритеты
        $data = $this->candidateDistributionService->secondFilterParticipations($data);

        // Шаг 4: Сбор лишних заявок и удаление дубликатов
        $data = $this->candidateDistributionService->collectExcessParticipations($data);

        // Шаг 5: Определение кандидатов без заявок
        $data = $this->candidateDistributionService->getCandidatesWithoutParticipation($data);

        // Шаг 6: Распределение лишних кандидатов
        $data = $this->candidateDistributionService->distributeExcessParticipations($data);

        // Шаг 7: Распределение кандидатов без заявок (молчунов)
        $finalResult = $this->candidateDistributionService->distributeWithoutParticipation($data);

        // Запись конечного результата в JSON
        $outputFilePath = '7_without_distribution.json';
        Storage::put($outputFilePath, json_encode($finalResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // возвращаем результат
        return response()->json($finalResult);
    }
      


}
