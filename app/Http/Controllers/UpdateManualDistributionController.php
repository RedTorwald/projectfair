<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class UpdateManualDistributionController extends Controller
{
    public function __invoke(Request $request)
    {
        /*
        $filePath = Storage::exists('6_manual.json') 
        ? '6_manual.json' 
        : '3_updated.json';*/

        $filePath = Storage::exists('6_manual.json') 
        ? '6_manual.json' 
        : (Storage::exists('3_updated.json') 
            ? '3_updated.json' 
            : '2_distribution.json');
    
        $jsonData = json_decode(Storage::get($filePath), true);

        $projects = $jsonData['projects'];
        $excessParticipations = $jsonData['excess_participations'];
        $withoutParticipation = $jsonData['without_participation'];

        // фильтр на реквест изменненых
        $candidatesData = array_filter($request->all(), function ($candidate) {
            return isset($candidate['selected_project']);
        });

        
        foreach ($candidatesData as $candidate) {
            $candidateId = $candidate['candidate_id'];
            $priority = $candidate['priority'];           

            // очистка из списка лишних и молчунов для распределнных вручную
            if ($priority === 4) {                
                $excessParticipations = array_filter($excessParticipations, function ($item) use ($candidateId) {
                    return $item['candidate_id'] !== $candidateId;
                });
            } else {                
                $withoutParticipation = array_filter($withoutParticipation, function ($item) use ($candidateId) {
                    return $item['candidate_id'] !== $candidateId;
                });
            }
        }
        
        $excessParticipations = array_values($excessParticipations);
        $withoutParticipation = array_values($withoutParticipation);

        // добавление на проект
        foreach ($candidatesData as $candidate) {
            $candidateId = $candidate['candidate_id'];
            $selectedProjectId = $candidate['selected_project'];
            $candidateInstitute = $candidate['institute_id'];
            $candidateDepartment = $candidate['department_id'];

            foreach ($projects as &$institute) {
                if ($institute['institute_id'] === $candidateInstitute) {
                    foreach ($institute['departments'] as &$department) {
                        if ($department['department_id'] === $candidateDepartment) {
                            foreach ($department['projects'] as &$project) {
                                if ($project['project_id'] === $selectedProjectId) {
                                    $project['candidates'][] = [
                                        'candidate_id' => $candidate['candidate_id'],
                                        'fio' => $candidate['fio'],
                                        'priority' => $candidate['priority'],
                                        'state_id' => 1,
                                        'created_at' => now()->toDateTimeString(),
                                    ];
                                    $project['candidates_count']++;
                                    break 3; 
                                }
                            }
                        }
                    }
                }
            }
        }

        
        $outputData = [
            'projects' => $projects,
            'excess_participations' => $excessParticipations,
            'without_participation' => $withoutParticipation,
        ];

        $outputFilePath = '6_manual.json';
        Storage::put($outputFilePath, json_encode($outputData, JSON_PRETTY_PRINT));

        return response()->json($outputData);
    }
}