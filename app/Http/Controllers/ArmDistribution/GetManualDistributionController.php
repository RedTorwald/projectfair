<?php

namespace App\Http\Controllers\ArmDistribution;


use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Project;
use Illuminate\Support\Facades\DB;


/**
 * @OA\Get(
 *     path="/arm/manualDistribution",
 *     summary="Ручное распределение кандидатов по проектам",
 *     description="Получение кандидатов и подходящих для них проектов",
 *     operationId="getManualDistribution",
 *     tags={"ARM Distribution"},
 *     @OA\Response(
 *         response=200,
 *         description="Успешное распределение кандидатов по проектам",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="candidates",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="candidate_id", type="integer", example=1),
 *                     @OA\Property(property="fio", type="string", example="Иванов Иван Иванович"),
 *                     @OA\Property(property="course", type="string", example="1 курс"),
 *                     @OA\Property(property="training_group", type="string", example="Группа A"),
 *                     @OA\Property(property="priority", type="integer", example=1),
 *                     @OA\Property(property="institute_id", type="integer", example=1),
 *                     @OA\Property(property="institute_name", type="string", example="Институт Name"),
 *                     @OA\Property(property="department_id", type="integer", example=1),
 *                     @OA\Property(property="department_name", type="string", example="Кафедра Name"),
 *                     @OA\Property(property="speciality_id", type="integer", example=101),
 *                     @OA\Property(property="speciality_name", type="string", example="Программирование"),
 *                     @OA\Property(property="eligible_projects_ids", type="array", @OA\Items(type="integer"), example={1001, 1002})
 *                 )
 *             ),
 *             @OA\Property(
 *                 property="eligible_projects",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="project_id", type="integer", example=1001),
 *                     @OA\Property(property="project_title", type="string", example="Проект по программированию"),
 *                     @OA\Property(property="places", type="integer", example=3),
 *                     @OA\Property(property="candidates_count", type="integer", example=1)
 *                 )
 *             ),
 *             @OA\Property(
 *                 property="empty_projects",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="project_id", type="integer", example=1003),
 *                     @OA\Property(property="project_title", type="string", example="Проект по веб-разработке"),
 *                     @OA\Property(property="places", type="integer", example=5),
 *                     @OA\Property(property="candidates_count", type="integer", example=0),
 *                     @OA\Property(property="department_id", type="integer", example=2),
 *                     @OA\Property(property="department_name", type="string", example="Кафедра Веб-разработки"),
 *                     @OA\Property(property="institute_id", type="integer", example=2),
 *                     @OA\Property(property="institute_name", type="string", example="Институт информационных технологий")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Ошибка при обработке распределения"
 *     )
 * )
 */

class GetManualDistributionController extends Controller
{
    public function __invoke()
    {
        $filteredFilePath = Storage::exists('3_updated.json') 
            ? '3_updated.json' 
            : '2_distribution.json';

        $outputFilePath = '5_manual.json';  
        
        $this->findEligibleProjectsForCandidates($filteredFilePath, $outputFilePath); 
        
        $jsonData = Storage::get($outputFilePath); 
        $filteredParticipations = json_decode($jsonData, true); 
        
        return response()->json($filteredParticipations);
    }

    public function findEligibleProjectsForCandidates(string $filePath, string $outputFilePath)
    {
        // читаем данные из исходного JSON
        $jsonData = json_decode(Storage::get($filePath), true);
        $candidates = array_merge($jsonData['excess_participations'], $jsonData['without_participation']);
        
        $candidatesWithProjects = [];
        $projects = $jsonData['projects'];

        $allEligibleProjects = [];

        // перебор кандидатов и распределение подходящих проектов
        foreach ($candidates as $candidate) {
            if (is_null($candidate['institute_id'])) {
                continue;
            }

            $candidateInstitute = $candidate['institute_id'];
            $candidateDepartment = $candidate['department_id'];
            $candidateSpecialityId = $candidate['speciality_id'];
           

            $eligibleProjects = [];
            $eligibleProjectsID = [];

            foreach ($projects as $institute) {
                if ($institute['institute_id'] === $candidateInstitute) {
                    foreach ($institute['departments'] as $department) {
                        if ($department['department_id'] === $candidateDepartment) {
                            foreach ($department['projects'] as $project) {
                                $projectSpecialities = array_column($project['specialities'], 'id');
                                
                                if (in_array($candidateSpecialityId, $projectSpecialities)) {
                                    $eligibleProjects[] = [
                                        'project_id' => $project['project_id'],
                                        'project_title' => $project['title'],
                                        'places' => $project['places'],
                                        'candidates_count' => $project['candidates_count'],
                                    ];
                                    $eligibleProjectsID[] = $project['project_id'];

                                    if (!isset($allEligibleProjects[$project['project_id']])) {
                                        $allEligibleProjects[$project['project_id']] = [
                                            'project_id' => $project['project_id'],
                                            'project_title' => $project['title'],
                                            'places' => $project['places'],
                                            'candidates_count' => $project['candidates_count'],
                                            'specialities' => $project['specialities'],
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }

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

        // получение пустых проектов
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
                    'title' => $project->project_title,
                    'places' => $project->places,
                    'candidates_count' => $project->candidates_count,
                    'department_id' => $project->department_id,
                    'department_name' => $project->department_name,
                    'institute_id' => $project->institute_id,
                    'institute_name' => $project->institute_name,
                    'specialities' => $specialities,
                ];
            })->toArray();

        // добавление пустых проектов подходящим кандидатам и в список eligible_projects
        foreach ($candidatesWithProjects as &$candidate) {
            foreach ($emptyProjectsData as $emptyProject) {
                foreach ($emptyProject['specialities'] as $speciality) {
                    if (
                        $candidate['institute_id'] === $emptyProject['institute_id'] &&
                        $candidate['department_id'] === $emptyProject['department_id'] &&
                        $candidate['speciality_id'] === $speciality->id &&
                        $candidate['course'] === $speciality->course
                    ) {
                        $candidate['eligible_projects_ids'][] = $emptyProject['project_id'];
                        $allEligibleProjects[$emptyProject['project_id']] = [
                            'project_id' => $emptyProject['project_id'],
                            'project_title' => $emptyProject['title'],
                            'places' => $emptyProject['places'],
                            'candidates_count' => $emptyProject['candidates_count'],
                            
                        ];
                    }
                }
            }
        }

        // формирование итоговой структуры
        $result = [
            'candidates' => $candidatesWithProjects,
            'eligible_projects' => array_values($allEligibleProjects),
            'empty_projects' => $emptyProjectsData,
        ];

        Storage::put($outputFilePath, json_encode($result, JSON_PRETTY_PRINT));
    }
}

