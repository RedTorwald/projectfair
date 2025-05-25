<?php

namespace App\Http\Controllers\ArmDistribution;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

/**
 * @OA\Patch(
 *     path="/arm/approveDistribution",
 *     summary="Обновление итогового распределения кандидатов по проектам",
 *     description="Метод используется для перемещения кандидатов между проектами. Он удаляет кандидата из одного проекта и добавляет его в другой, обновляя соответствующие данные в файле.",
 *     operationId="updateCandidateProject",
 *     tags={"ARM Distribution"},
 *     @OA\RequestBody(
 *         required=true,
 *         description="Данные для обновления распределения кандидатов",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="institute_id", type="integer", example=1),
 *                 @OA\Property(property="department_id", type="integer", example=2),
 *                 @OA\Property(property="project_id", type="integer", example=1001),
 *                 @OA\Property(property="candidate_id", type="integer", example=123),
 *                 @OA\Property(property="selected_institute_id", type="integer", example=3),
 *                 @OA\Property(property="selected_department_id", type="integer", example=4),
 *                 @OA\Property(property="selected_project_id", type="integer", example=2001)
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Успешное обновление распределения кандидатов",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="message", type="string", example="Candidates transferred successfully")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Ошибка при обработке запроса"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Файл не найден"
 *     )
 * )
 */

class UpdateCandidateProjectController extends Controller
{
    public function __invoke(Request $request)  
    {
        $filePath = '6_final_distribution.json';
      
        
        if (!Storage::exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $jsonData = json_decode(Storage::get($filePath), true);
        $projects = &$jsonData['projects'];
        $requests = $request->all();

        foreach ($requests as $transfer) {
            $instituteId = $transfer['institute_id'];
            $departmentId = $transfer['department_id'];
            $projectId = $transfer['project_id'];
            $candidateId = $transfer['candidate_id'];
            $selectedInstituteId = $transfer['selected_institute_id'];
            $selectedDepartmentId = $transfer['selected_department_id'];
            $selectedProjectId = $transfer['selected_project_id'];

            // Удаляем кандидата из исходного проекта
            foreach ($projects as &$institute) {
                if ($institute['institute_id'] === $instituteId) {
                    foreach ($institute['departments'] as &$department) {
                        if ($department['department_id'] === $departmentId) {
                            foreach ($department['projects'] as &$project) {
                                if ($project['project_id'] === $projectId) {
                                    foreach ($project['candidates'] as $index => $candidate) {
                                        if ($candidate['candidate_id'] === $candidateId) {
                                            $movedCandidate = $candidate;
                                            unset($project['candidates'][$index]);
                                            $project['candidates'] = array_values($project['candidates']);
                                            break 3;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Добавляем кандидата в выбранный проект
            foreach ($projects as &$institute) {
                if ($institute['institute_id'] === $selectedInstituteId) {
                    foreach ($institute['departments'] as &$department) {
                        if ($department['department_id'] === $selectedDepartmentId) {
                            foreach ($department['projects'] as &$project) {
                                if ($project['project_id'] === $selectedProjectId) {
                                    $project['candidates'][] = $movedCandidate;
                                    break 3;
                                }
                            }
                        }
                    }
                }
            }
        }

        Storage::put($filePath, json_encode($jsonData, JSON_PRETTY_PRINT));
        return response()->json(['message' => 'Candidates transferred successfully']);
    }

}
