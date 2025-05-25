<?php

namespace App\Http\Controllers\ArmDistribution;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

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
