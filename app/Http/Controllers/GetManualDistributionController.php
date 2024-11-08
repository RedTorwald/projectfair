<?php

namespace App\Http\Controllers;

use App\Models\Participation;
use App\Http\Services\CandidateDistributionService;
use Illuminate\Support\Facades\Storage;

class GetManualDistributionController extends Controller
{
    protected $candidateDistributionService;

    public function __construct(CandidateDistributionService $candidateDistributionService)
    {
        $this->candidateDistributionService = $candidateDistributionService;
    }


    public function __invoke()
    {

        $filteredFilePath = Storage::exists('8_updated.json') 
        ? '8_updated.json' 
        : '7_without_distribution.json';

        $outputFilePath = '11_without_participations_with_projects.json';  
        
        $this->findEligibleProjectsForCandidates($filteredFilePath, $outputFilePath);
 
        
        $jsonData = Storage::get($outputFilePath); 
        $filteredParticipations = json_decode($jsonData, true); 

        // респонс
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

        
        Storage::put($outputFilePath, json_encode($candidatesWithProjects, JSON_PRETTY_PRINT));
        return response()->json($candidatesWithProjects);
    }



}
