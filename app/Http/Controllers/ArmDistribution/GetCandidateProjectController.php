<?php

namespace App\Http\Controllers\ArmDistribution;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Get(
 *     path="/arm/approveDistribution",
 *     summary="Получение итогового распределения",
 *     description="Контроллер получает данные о кандидатах и подходящих для них проектах на основе существующих распределений. Структура ответа включает институты, кафедры, проекты и кандидатов с массивом подходящих проектных ID.",
 *     operationId="getCandidateProject",
 *     tags={"ARM Distribution"},
 *     @OA\Response(
 *         response=200,
 *         description="Успешное получение данных о кандидатах и подходящих проектах",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="projects",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="institute_id", type="integer", example=3),
 *                     @OA\Property(property="institute_name", type="string", example="Институт высоких технологий"),
 *                     @OA\Property(
 *                         property="departments",
 *                         type="array",
 *                         @OA\Items(
 *                             type="object",
 *                             @OA\Property(property="department_id", type="integer", example=2),
 *                             @OA\Property(property="department_name", type="string", example="Автоматизации и управления"),
 *                             @OA\Property(
 *                                 property="projects",
 *                                 type="array",
 *                                 @OA\Items(
 *                                     type="object",
 *                                     @OA\Property(property="project_id", type="integer", example=1511),
 *                                     @OA\Property(property="title", type="string", example="Разработка мобильного приложения для бронирования мест"),
 *                                     @OA\Property(property="places", type="integer", example=15),
 *                                     @OA\Property(property="candidates_count", type="integer", example=10),
 *                                     @OA\Property(
 *                                         property="candidates",
 *                                         type="array",
 *                                         @OA\Items(
 *                                             type="object",
 *                                             @OA\Property(property="candidate_id", type="integer", example=4071),
 *                                             @OA\Property(property="training_group", type="string", example="АСУб-21-2"),
 *                                             @OA\Property(property="course", type="integer", example=4),
 *                                             @OA\Property(property="fio", type="string", example="Андреев Андрей Андреевич"),
 *                                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-02-04T14:49:35.000000Z"),
 *                                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-02-04T23:37:13.000000Z"),
 *                                             @OA\Property(property="institute_id", type="integer", example=4),
 *                                             @OA\Property(property="priority", type="integer", example=1),
 *                                             @OA\Property(property="department_id", type="integer", example=59),
 *                                             @OA\Property(property="speciality_id", type="integer", example=4),
 *                                             @OA\Property(property="stranger", type="integer", example=1),
 *                                             @OA\Property(
 *                                                 property="eligible_projects_ids",
 *                                                 type="array",
 *                                                 @OA\Items(type="integer", example=1524)
 *                                             )
 *                                         )
 *                                     )
 *                                 )
 *                             )
 *                         )
 *                     )
 *                 )
 *             ),
 *             @OA\Property(
 *                 property="eligible_projects",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="project_id", type="integer", example=1511),
 *                     @OA\Property(property="project_title", type="string", example="Разработка мобильного приложения"),
 *                     @OA\Property(property="places", type="integer", example=15),
 *                     @OA\Property(property="candidates_count", type="integer", example=10),
 *                     @OA\Property(
 *                         property="specialities",
 *                         type="array",
 *                         @OA\Items(
 *                             type="object",
 *                             @OA\Property(property="id", type="integer", example=101),
 *                             @OA\Property(property="name", type="string", example="Программирование")
 *                         )
 *                     ),
 *                     @OA\Property(property="institute_id", type="integer", example=3),
 *                     @OA\Property(property="department_id", type="integer", example=2)
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Ошибка при обработке запроса"
 *     )
 * )
 */

class GetCandidateProjectController extends Controller
{
    public function __invoke()
    {
        
        
        $filePath = Storage::exists('6_final_distribution.json') 
        ? '6_final_distribution.json' 
        : (Storage::exists('3_updated.json') 
            ? '3_updated.json' 
            : '2_distribution.json');

        $outputFilePath = '6_final_distribution.json';
  
        $jsonData = json_decode(Storage::get($filePath), true);

        
        $jsonData = $this->matchCandidatesWithEligibleProjects($filePath, $outputFilePath);
        
       
        $jsonData = Storage::get($outputFilePath); 
        $jsonData = json_decode($jsonData, true);
        
        return response()->json($jsonData);

      
    }

    
    public function matchCandidatesWithEligibleProjects(string $filePath, string $outputFilePath)
    {
        // читаем данные из исходного JSON
        $jsonData = json_decode(Storage::get($filePath), true);
        $projects = $jsonData['projects'];
    
        $eligibleProjects = [];
    
        // проходим по всей структуре институт->кафедра->проект->кандидаты
        foreach ($projects as &$institute) {
            foreach ($institute['departments'] as &$department) {
                foreach ($department['projects'] as &$project) {
                    foreach ($project['candidates'] as &$candidate) {
                        $eligibleProjectsID = [];
    
                        // если кандидат подал заявку не от своего института, ищем проекты по всей структуре
                        if (isset($candidate['stranger']) && $candidate['stranger'] === 1) {
                            foreach ($projects as $inst) {
                                foreach ($inst['departments'] as $dept) {
                                    foreach ($dept['projects'] as $proj) {
                                        $projectSpecialities = array_column($proj['specialities'], 'id');
    
                                        if (in_array($candidate['speciality_id'], $projectSpecialities)) {
                                            $eligibleProjectsID[] = $proj['project_id'];
                                        }
                                    }
                                }
                            }
                        } else {
                            // если кандидат подал заявку от своего института, ищем проекты в рамках института и кафедры
                            foreach ($institute['departments'] as $dept) {                               
                                if ($dept['department_id'] === $candidate['department_id']) {
                                    foreach ($dept['projects'] as $proj) {
                                        $projectSpecialities = array_column($proj['specialities'], 'id');
    
                                        if (in_array($candidate['speciality_id'], $projectSpecialities)) {
                                            $eligibleProjectsID[] = $proj['project_id'];
                                        }
                                    }
                                }
                            }
                        }
    
                        $candidate['eligible_projects_ids'] = $eligibleProjectsID;
                    }
    
                    // формируем список eligible_projects без кандидатов
                    if (!isset($eligibleProjects[$project['project_id']])) {
                        $eligibleProjects[$project['project_id']] = [
                            'project_id' => $project['project_id'],
                            'project_title' => $project['title'],
                            'places' => $project['places'],
                            'candidates_count' => $project['candidates_count'],
                            'specialities' => $project['specialities'],
                            'institute_id' => $institute['institute_id'],
                            'department_id' => $department['department_id']
                        ];
                    }
                }
            }
        }
    
        // сохраняем обновлённую структуру
        Storage::put($outputFilePath, json_encode(['projects' => $projects, 'eligible_projects' => array_values($eligibleProjects)], JSON_PRETTY_PRINT));        
    }

}
