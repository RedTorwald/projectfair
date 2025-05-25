<?php

namespace App\Http\Controllers\ArmDistribution;

use App\Http\Services\CandidateDistributionService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Storage;


/**
 * @OA\Patch(
 *     path="/arm/projects/distribution",
 *     summary="Обновление распределения студентов по проектам",
 *     description="Позволяет изменить количество мест в проектах и выполнить перераспределение студентов",
 *     operationId="updateDistribution",
 *     tags={"ARM Distribution"},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Структура с обновленным количеством мест по проектам",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 required={"institute_id", "departments"},
 *                 @OA\Property(property="institute_id", type="integer", example=1),
 *                 @OA\Property(property="departments", type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         required={"department_id", "projects"},
 *                         @OA\Property(property="department_id", type="integer", example=5),
 *                         @OA\Property(property="projects", type="array",
 *                             @OA\Items(
 *                                 type="object",
 *                                 required={"project_id", "places"},
 *                                 @OA\Property(property="project_id", type="integer", example=101),
 *                                 @OA\Property(property="places", type="integer", example=4)
 *                             )
 *                         )
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Успешное обновление распределения и возврат новой структуры",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="institute_id", type="integer", example=1),
 *                 @OA\Property(property="institute_name", type="string", example="Институт Name"),
 *                 @OA\Property(
 *                     property="departments",
 *                     type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="department_id", type="integer", example=5),
 *                         @OA\Property(property="department_name", type="string", example="Кафедра Name"),
 *                         @OA\Property(
 *                             property="projects",
 *                             type="array",
 *                             @OA\Items(
 *                                 type="object",
 *                                 @OA\Property(property="project_id", type="integer", example=123),
 *                                 @OA\Property(property="title", type="string", example="Разработка веб-приложения"),
 *                                 @OA\Property(property="places", type="integer", example=4),
 *                                 @OA\Property(property="candidates_count", type="integer", example=3),
 *                                 @OA\Property(property="specialities", type="array", @OA\Items(type="object")),
 *                                 @OA\Property(property="candidates", type="array", @OA\Items(type="object"))
 *                             )
 *                         )
 *                     )
 *                 )
 *             )
 *         )
 *     )
 * )
 */



class UpdateDistributionController extends Controller
{   
    protected $candidateDistributionService;

    public function __construct(CandidateDistributionService $candidateDistributionService)
    {
        $this->candidateDistributionService = $candidateDistributionService;
    }
    

    public function __invoke(Request $request)
    {        
        
        $this->updateProjectPlaces($request);

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

        // Шаг 8: распределение молчунов
        $this->clearCandidates();


        $filePath = '3_1_updated.json';

        $jsonData = Storage::get($filePath); 
        $filteredParticipations = json_decode($jsonData, true); 

       
        return response()->json($filteredParticipations);
    }

    public function updateProjectPlaces(Request $request)
    {
        
        $filePath = '1_projects_structure.json';    

        $jsonData = Storage::get($filePath);
        $projectsCollection = json_decode($jsonData, true);

        $requestData = $request->json()->all();    

        foreach ($requestData as $institute) {
            foreach ($institute['departments'] as $department) {
                foreach ($department['projects'] as $project) {
                    $projectId = $project['project_id'];
                    $newPlaces = $project['places'];    
                  
                    foreach ($projectsCollection as &$currentInstitute) {
                        if (isset($currentInstitute['departments'])) {
                            foreach ($currentInstitute['departments'] as &$currentDepartment) {
                                if (isset($currentDepartment['projects'])) {
                                    foreach ($currentDepartment['projects'] as &$currentProject) {
                                        if ($currentProject['project_id'] === $projectId) {
                                            $currentProject['places'] = $newPlaces;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }    
        
        $newFilePath = '3_updated.json';
        Storage::put($newFilePath, json_encode($projectsCollection, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
        
        return response()->json($newFilePath);
    }

    public function getAndSaveDuplicates()
    {
        $filePath = '3_updated.json';
        $filteredFilePath = 'duplicates2.json';
        $filteredProjects = $this->candidateDistributionService->getAndSaveDuplicates($filePath, $filteredFilePath); 
        
        return response()->json($filteredProjects);
    }
    
    //удаление дубликатов
    public function removeDuplicates()
    {
        $filePath = '3_updated.json';
        $duplicatesFilePath = 'duplicates2.json';
        $filteredProjects = $this->candidateDistributionService->removeDuplicates($filePath, $duplicatesFilePath); 
        
        return response()->json($filteredProjects);
    }

    

    // шаг 2 удаление заявок с приоритетом 2 и 3 для проектных команд
    public function filterParticipations()
    {
        $filePath = '3_updated.json';
        $filteredFilePath = '3_updated.json';
        $filteredProjects = $this->candidateDistributionService->filterParticipations($filePath, $filteredFilePath);        
                
        return response()->json($filteredProjects);
    }

    // шаг 3 удаление заявок с приоритетом 3. Формирование команды из 1 и 2 приоритета
    public function secondFilterParticipations()
    {       
        $filePath = '3_updated.json';
        $filteredFilePath = '3_updated.json';                
        $filteredProjects = $this->candidateDistributionService->secondFilterParticipations($filePath, $filteredFilePath);
                              
        return response()->json($filteredProjects);
    }

    // шаг 4 получение лишних кандидатов. Удаление дубликатов. оставляем уникальные заявки лишних кандидатов
    public function collectExcessParticipations()
    {
        $filePath = '3_updated.json';
        $filteredFilePath = '3_updated.json';
        $result = $this->candidateDistributionService->collectExcessParticipations($filePath, $filteredFilePath);
    
        return $result;
    }

    // шаг 5 получение кандидатов без заявок.
    public function getCandidatesWithoutParticipation()
    {
        $filePath = '3_updated.json';
        $filteredFilePath = '3_updated.json';
        $result = $this->candidateDistributionService->getCandidatesWithoutParticipation($filePath, $filteredFilePath);
    
        return $result;
    }

    // Шаг 6 распределение лишних кандидатов  
    public function distributeExcessParticipations()
    {
        $filePath = '3_updated.json';
        $filteredFilePath = '3_updated.json';        
        $result = $this->candidateDistributionService->distributeExcessParticipations($filePath, $filteredFilePath);   

        return $result;
    }
     
    // Шаг 7 распределение молчунов  
    public function distributeWithoutParticipation()
    {
        $filePath = '3_updated.json';
        $filteredFilePath = '3_updated.json';
        $result = $this->candidateDistributionService->distributeWithoutParticipation($filePath, $filteredFilePath);
    
        return $result;
    } 

    // Шаг 7 распределение молчунов  
    public function clearCandidates()
    {
        $filePath = '3_updated.json';
        $filteredFilePath = '3_1_updated.json';      
        $result = $this->candidateDistributionService->clearCandidates($filePath, $filteredFilePath);
    
        return $result;
    }  
}