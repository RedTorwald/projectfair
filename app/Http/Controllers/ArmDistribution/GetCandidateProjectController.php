<?php

namespace App\Http\Controllers\ArmDistribution;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;


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

       /*
        $filePath = Storage::exists('3_updated.json') 
            ? '3_updated.json' 
            : '2_distribution.json';


        $outputFilePath = '10_test.json';*/

        
       

        
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
