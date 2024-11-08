<?php

namespace App\Http\Controllers;

use App\Models\Participation;
use App\Http\Services\CandidateDistributionService;
use Illuminate\Support\Facades\Storage;

class GetCandidatesController extends Controller
{
    public function __invoke()
    {        
        $inputFilePath = Storage::exists('3_updated.json') 
            ? '3_updated.json' 
            : '2_distribution.json';
    
        $outputFilePath = '4_grouped_participations.json';    
       
        $resultData = $this->groupParticipations($inputFilePath, $outputFilePath);
            
        return response()->json($resultData);
    }

    public function groupParticipations(string $inputFilePath, string $outputFilePath)
    {
        
        $jsonData = json_decode(Storage::get($inputFilePath), true);    
       
        $allParticipations = array_merge(
            $jsonData['excess_participations'] ?? [],
            $jsonData['without_participation'] ?? []
        );
    
        $groupCandidates = function ($candidates) {
            $groupedData = [];
    
            foreach ($candidates as $candidate) {
                $instituteId = $candidate['institute_id'];
                $instituteName = $candidate['institute_name'];
                $departmentId = $candidate['department_id'];
                $departmentName = $candidate['department_name'];
                $course = $candidate['course'];
                $specialityId = $candidate['speciality_id'];
                $specialityName = $candidate['speciality_name'];
    

                $instituteIndex = array_search($instituteId, array_column($groupedData, 'institute_id'));
                if ($instituteIndex === false) {
                    $groupedData[] = [
                        'institute_id' => $instituteId,
                        'institute_name' => $instituteName,
                        'departments' => []
                    ];
                    $instituteIndex = array_key_last($groupedData);
                }
    

                $departmentIndex = array_search($departmentId, array_column($groupedData[$instituteIndex]['departments'], 'department_id'));
                if ($departmentIndex === false) {
                    $groupedData[$instituteIndex]['departments'][] = [
                        'department_id' => $departmentId,
                        'department_name' => $departmentName,
                        'courses' => []
                    ];
                    $departmentIndex = array_key_last($groupedData[$instituteIndex]['departments']);
                }

                $courseIndex = array_search($course, array_column($groupedData[$instituteIndex]['departments'][$departmentIndex]['courses'], 'course'));
                if ($courseIndex === false) {
                    $groupedData[$instituteIndex]['departments'][$departmentIndex]['courses'][] = [
                        'course' => $course,
                        'specialities' => []
                    ];
                    $courseIndex = array_key_last($groupedData[$instituteIndex]['departments'][$departmentIndex]['courses']);
                }
    
                
                $specialityIndex = array_search($specialityId, array_column($groupedData[$instituteIndex]['departments'][$departmentIndex]['courses'][$courseIndex]['specialities'], 'speciality_id'));
                if ($specialityIndex === false) {
                    $groupedData[$instituteIndex]['departments'][$departmentIndex]['courses'][$courseIndex]['specialities'][] = [
                        'speciality_id' => $specialityId,
                        'speciality_name' => $specialityName,
                        'candidates' => []
                    ];
                    $specialityIndex = array_key_last($groupedData[$instituteIndex]['departments'][$departmentIndex]['courses'][$courseIndex]['specialities']);
                }
    
              
                $groupedData[$instituteIndex]['departments'][$departmentIndex]['courses'][$courseIndex]['specialities'][$specialityIndex]['candidates'][] = [
                    'candidate_id' => $candidate['candidate_id'],
                    'fio' => $candidate['fio'],
                    'training_group' => $candidate['training_group'],
                    'priority' => $candidate['priority'],
                    'created_at' => $candidate['created_at'],
                ];
            }
    
            return $groupedData;
        };
    
       
        $groupedData = $groupCandidates($allParticipations);
    
        Storage::put($outputFilePath, json_encode($groupedData, JSON_PRETTY_PRINT));
    
        return $groupedData;
    }

   
}
