<?php

namespace App\Http\Controllers\ArmDistribution;

use App\Http\Controllers\Controller;
use App\Models\Participation;
use App\Http\Services\CandidateDistributionService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class GetAutoDistributionController extends Controller
{
    protected $candidateDistributionService;

    public function __construct(CandidateDistributionService $candidateDistributionService)
    {
        $this->candidateDistributionService = $candidateDistributionService;
    }


    public function __invoke()
    {
        // отключение лимита по запросу (можно настроить на сервере)
        set_time_limit(0);

        // Шаг 1: получение структуры json
        $this->generateProjectStructure();

        // удаление дубликатов
        $this->getAndSaveDuplicates();
        $this->removeDuplicates();
             
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
        $this->clearCandidates();
   
        $filteredFilePath = Storage::exists('3_1_updated.json') 
        ? '3_1_updated.json' 
        : '2_1_distribution.json';

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
        
        // ближайший месяц семестра
        $semesterMonth = ($currentMonth > 1 && $currentMonth < 9) ? 2 : 9;
        
        // шаг 1: получение заявок с участниками и проектами
        $participations = Participation::with(['candidate', 'project.department.institute', 'project.projectSpecialities']) 
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $semesterMonth)
            ->where('state_id', 1)
            ->get();
        
        // шаг 2: массив для структуры JSON
        $structure = [];

        foreach ($participations as $participation) {
            $institute = $participation->project->department->institute;
            $department = $participation->project->department;
            $project = $participation->project;
            $candidate = $participation->candidate;

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
                $specialities = $project->projectSpecialities;

                $structure[$institute->id]['departments'][$department->id]['projects'][$project->id] = [
                    'project_id' => $project->id,
                    'places' => $project->places,
                    'candidates_count' => 0,
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

            $structure[$institute->id]['departments'][$department->id]['projects'][$project->id]['candidates'][] = [
                'candidate_id' => $candidate->id,
                'training_group' => $candidate['training_group'],
                'course' => $candidate->course,
                'fio' => $candidate->fio,
                'priority' => $participation->priority,
                'created_at' => $participation->created_at,
                'updated_at' => $participation->updated_at
            ];

            $structure[$institute->id]['departments'][$department->id]['projects'][$project->id]['candidates_count']++;
        }

        // шаг 3: получение пустых проектов
        $emptyProjectsData = DB::table('projects')
            ->leftJoin('participations', 'projects.id', '=', 'participations.project_id')
            ->whereNull('participations.project_id')
            ->where('projects.state_id', 1)
            ->join('departments', 'projects.department_id', '=', 'departments.id')
            ->join('institutes', 'departments.institute_id', '=', 'institutes.id')
            ->select(
                'projects.id as project_id',
                'projects.title as project_title',
                'projects.places as places',
                DB::raw('0 as candidates_count'),
                'departments.id as department_id',
                'departments.name as department_name',
                'institutes.id as institute_id',
                'institutes.name as institute_name'
            )
            ->get()
            ->map(function ($project) {
                $specialities = DB::table('project_speciality')
                    ->join('specialities', 'project_speciality.speciality_id', '=', 'specialities.id')
                    ->where('project_speciality.project_id', $project->project_id)
                    ->select('specialities.id', 'specialities.name', 'project_speciality.course')
                    ->get();

                return [
                    'project_id' => $project->project_id,
                    'places' => $project->places,
                    'candidates_count' => $project->candidates_count,
                    'title' => $project->project_title,
                    'specialities' => $specialities,
                    'department_id' => $project->department_id,
                    'department_name' => $project->department_name,
                    'institute_id' => $project->institute_id,
                    'institute_name' => $project->institute_name,
                    'candidates' => []
                ];
            })->toArray();

        // шаг 4: добавление пустых проектов в структуру
        foreach ($emptyProjectsData as $emptyProject) {

            if (!isset($structure[$emptyProject['institute_id']])) { //при добавлении проектов без заявок в общую структуру проверяем наличие института в структуре
                $structure[$emptyProject['institute_id']] = [ // в случае отстутствия института -> добавляем
                    'institute_id' => $emptyProject['institute_id'],
                    'institute_name' => $emptyProject['institute_name'],
                    'departments' => []
                ];
            }

            if (!isset($structure[$emptyProject['institute_id']]['departments'][$emptyProject['department_id']])) {//при добавлении проектов без заявок в общую структуру проверяем наличие кафыедры в структуре
                $structure[$emptyProject['institute_id']]['departments'][$emptyProject['department_id']] = [ // в случае отстутствия кафедры -> добавляем
                    'department_id' => $emptyProject['department_id'],
                    'department_name' => $emptyProject['department_name'],
                    'projects' => []
                ];
            }

            $structure[$emptyProject['institute_id']]['departments'][$emptyProject['department_id']]['projects'][$emptyProject['project_id']] = $emptyProject; //добавление проекта без заявок в общую структуру
        }

        // шаг 5: сортировка кандидатов по приоритету и дате обновления в каждом проекте
        foreach ($structure as &$institute) {
            foreach ($institute['departments'] as &$department) {
                foreach ($department['projects'] as &$project) {
                    $project['candidates'] = collect($project['candidates'])->sortBy(function ($candidate) {
                        return [$candidate['priority'], $candidate['updated_at']];
                    })->values()->toArray();
                }
            }
        }

        // шаг 6: преобразуем ассоциативные массивы в индексированные
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
   
    
    //получение дубликатов
    public function getAndSaveDuplicates()
    {
        $filePath = '1_projects_structure.json';
        $filteredFilePath = 'duplicates.json';
        $filteredProjects = $this->candidateDistributionService->getAndSaveDuplicates($filePath, $filteredFilePath); 
        
        return response()->json($filteredProjects);
    }
    
    //удаление дубликатов
    public function removeDuplicates()
    {
        $filePath = '1_projects_structure.json';
        $duplicatesFilePath = 'duplicates.json';
        $filteredProjects = $this->candidateDistributionService->removeDuplicates($filePath, $duplicatesFilePath); 
        
        return response()->json($filteredProjects);
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

    // Шаг 8 удаление кандидатов из файла для увеличения скорости передачи json  
    public function clearCandidates()
    {
        $filePath = '2_distribution.json';
        $filteredFilePath = '2_1_distribution.json';      
        $result = $this->candidateDistributionService->clearCandidates($filePath, $filteredFilePath);
    
        return $result;
    }  
}
