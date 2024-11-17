<?php

namespace App\Http\Controllers;



use Illuminate\Support\Facades\Storage;

class GetManualDistributionController extends Controller
{


    public function __invoke()
    {

        $filteredFilePath = Storage::exists('6_manual.json') 
        ? '6_manual.json' 
        : (Storage::exists('3_updated.json') 
            ? '3_updated.json' 
            : '2_distribution.json');

        $outputFilePath = '5_manual.json';  
        
        $this->findEligibleProjectsForCandidates($filteredFilePath, $outputFilePath); 
        
        $jsonData = Storage::get($outputFilePath); 
        $filteredParticipations = json_decode($jsonData, true); 
        
        return response()->json($filteredParticipations);
    }

    //--------------------------------------------------------------------------------------------------------------

    public function findEligibleProjectsForCandidates(string $filePath, string $outputFilePath)
    {
        
        $jsonData = json_decode(Storage::get($filePath), true);       
        $candidates = array_merge($jsonData['excess_participations'], $jsonData['without_participation']);
       
        $candidatesWithProjects = [];
       
        $projects = $jsonData['projects'];

        foreach ($candidates as $candidate) {
            if (is_null($candidate['institute_id'])) {
                continue;
            }
            $candidateInstitute = $candidate['institute_id'];
            $candidateDepartment = $candidate['department_id'];
            $candidateSpecialityId = $candidate['speciality_id'];


            $eligibleProjects = [];

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
                                }
                            }
                        }
                    }
                }
            }

            usort($eligibleProjects, function ($a, $b) {
                return $a['project_id'] <=> $b['project_id'];
            });
            
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
                'eligible_projects' => $eligibleProjects,
            ];
        }

        usort($candidatesWithProjects, function ($a, $b) {
            return $a['candidate_id'] <=> $b['candidate_id'];
        });

        
        Storage::put($outputFilePath, json_encode($candidatesWithProjects, JSON_PRETTY_PRINT));
        return response()->json($candidatesWithProjects);
    }



}
