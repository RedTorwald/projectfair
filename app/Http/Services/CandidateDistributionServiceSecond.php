<?php

namespace App\Http\Services;
use App\Models\Project;
use App\Models\Candidate;
use App\Models\Participation;
use Illuminate\Support\Facades\Storage;

class CandidateDistributionServiceSecond
{

    //метод 1. удаление заявок с приоритетом 2 и 3 для выбранных кандидатов
    public function filterParticipations(array $structure)
    {
        $selectedCandidates = []; // для хранения кандидатов, попавших на проекты        
        $filteredStructure = []; // отфильтрованные данные
            
        foreach ($structure as $instituteId => $institute) { // перебор институтов
            $filteredDepartments = [];    
    
            foreach ($institute['departments'] as $departmentId => $department) { // перебор кафедр
                $filteredProjects = [];
    
                foreach ($department['projects'] as $projectId => $project) { // перебор проектов кафедры
                    $placesCount = $project['places'];
    
                    $teamCandidates = collect($project['candidates']) // команда из первых приоритетов
                        ->where('priority', 1) // кандидаты с приоритетом 1
                        ->take($placesCount) // аналог limit
                        ->pluck('candidate_id')
                        ->toArray();
    
                    $selectedCandidates = array_merge($selectedCandidates, $teamCandidates); // массив всех проектных команд
    
                    // добавление данных проекта в отфильтрованный список
                    $filteredProjects[$projectId] = [
                        'project_id' => $project['project_id'],
                        'title' => $project['title'],
                        'places' => $project['places'],
                        'candidates_count' => count($project['candidates']),
                        'specialities' => $project['specialities'],  
                        'candidates' => $project['candidates'],
                    ];
                }
    
                // добавление кафедры с отфильтрованными проектами
                $filteredDepartments[$departmentId] = [
                    'department_id' => $department['department_id'],
                    'department_name' => $department['department_name'],
                    'projects' => $filteredProjects,
                ];
            }
    
            // добавление института с отфильтрованными департаментами
            $filteredStructure[$instituteId] = [
                'institute_id' => $institute['institute_id'],
                'institute_name' => $institute['institute_name'],
                'departments' => $filteredDepartments,
            ];
        }
    
        // удаление заявок с приоритетом 2 и 3 для кандидатов, попавших в проектную команду
        $filteredStructure = collect($filteredStructure)->map(function ($institute) use ($selectedCandidates) 
        {
            $institute['departments'] = collect($institute['departments'])->map(function ($department) use ($selectedCandidates) 
            {
                $department['projects'] = collect($department['projects'])->map(function ($project) use ($selectedCandidates) 
                {
                    $filteredCandidates = collect($project['candidates'])->filter(function ($candidate) use ($selectedCandidates) 
                    {
                        // удаление заявок приоритета выше 1
                        return !(in_array($candidate['candidate_id'], $selectedCandidates) && $candidate['priority'] > 1);
                    });
    
                    // обновление проекта с кандидатами и специальностями
                    return [
                        'project_id' => $project['project_id'],                        
                        'places' => $project['places'],
                        'candidates_count' => $filteredCandidates->count(),  
                        'title' => $project['title'],                      
                        'candidates' => $filteredCandidates->values()->toArray(),
                        'specialities' => $project['specialities'],
                    ];
                });
    
                return $department;
            });
    
            return $institute;
        });
    
        // возвращаем отфильтрованные данные в виде массива
        return $filteredStructure->toArray();
    }

    //метод 2. удаление заявок с приоритетом 3 для выбранных кандидатов
    public function secondFilterParticipations(array $projectsData)
    {      
        $selectedCandidates = [];
        
        foreach ($projectsData as &$institute) {
            foreach ($institute['departments'] as &$department) {
                foreach ($department['projects'] as &$project) {
                    $placesCount = $project['places'];

                    // команда из 1 и 2 приоритетов
                    $teamCandidates = collect($project['candidates'])
                        ->filter(function ($candidate) {
                            return $candidate['priority'] == 1 || $candidate['priority'] == 2;})
                        ->take($placesCount)
                        ->pluck('candidate_id')
                        ->toArray();                     
                    $selectedCandidates = array_merge($selectedCandidates, $teamCandidates);
                }
            }
        }

        // удаление заявок с приоритетом 3 для кандидатов в проектных командах
        foreach ($projectsData as &$institute) {
            foreach ($institute['departments'] as &$department) {
                foreach ($department['projects'] as &$project) {                    
                    $filteredCandidates = collect($project['candidates'])->filter(function ($candidate) use ($selectedCandidates) {
                        //удаляем заявку == 3
                        if (in_array($candidate['candidate_id'], $selectedCandidates) && $candidate['priority'] == 3) {
                            return false; // удаляем заявку
                        }
                        return true; // пропускаем заявки с приоритетом 1 или 2, либо вне команды
                    });                    
                    $project['candidates'] = $filteredCandidates->values()->toArray();
                    $project['candidates_count'] = count($project['candidates']); // Обновляем количество кандидатов в проекте
                }
            }
        }       
        
        return $projectsData; 
    }

    //метод 3. получение лишних кандидатов    
    public function collectExcessParticipations(array $structure)
    {
        $structure = collect($structure); // преобразуем в коллекцию, если нужно работать с методами коллекции
        
        $excessParticipations = []; // массив для лишних заявок, не попавших на проект
    
        $filteredStructure = $structure->map(function ($institute) use (&$excessParticipations) {
            $filteredDepartments = collect($institute['departments'])->map(function ($department) use (&$excessParticipations) {
                $filteredProjects = collect($department['projects'])->map(function ($project) use (&$excessParticipations) {
    
                    $placesCount = $project['places'];
                    
                    // поиск модели проекта по ID
                    $projectModel = Project::find($project['project_id']);
                    if (!$projectModel) {
                        return; // пропуск, если проект не найден
                    }
    
                    // получение департамента и института через модель проекта
                    $departmentModel = $projectModel->department;
                    $instituteModel = $departmentModel->institute;
    
                    // разделение кандидатов на тех, кто помещается в проект и лишних
                    $teamCandidates = collect($project['candidates'])->take($placesCount);
                    $excessCandidates = collect($project['candidates'])->slice($placesCount);
    
                    // обработка лишних кандидатов
                    $excessCandidates->each(function ($candidate) use (&$excessParticipations, $departmentModel, $instituteModel) {
                        $candidateModel = Candidate::find($candidate['candidate_id']);
                        if (!$candidateModel) {
                            return;
                        }
    
                        $speciality = $candidateModel->getSpeciality()->id;
    
                        $excessParticipations[] = [
                            'candidate_id' => $candidateModel->id,
                            'priority' => $candidate['priority'],
                            'department_id' => $departmentModel->id,
                            'institute_id' => $instituteModel->id,
                            'speciality_id' => $speciality,
                            'created_at' => $candidate['created_at'],
                        ];
                    });
    
                    return [
                        'project_id' => $projectModel->id,
                        'title' => $project['title'],
                        'places' => $project['places'],
                        'candidates_count' => $teamCandidates->count(),
                        'candidates' => $teamCandidates->values()->toArray(),
                        'specialities' => $project['specialities'],
                    ];
                });
    
                return [
                    'department_id' => $department['department_id'],
                    'department_name' => $department['department_name'],
                    'projects' => $filteredProjects->toArray(),
                ];
            });
    
            return [
                'institute_id' => $institute['institute_id'],
                'institute_name' => $institute['institute_name'],
                'departments' => $filteredDepartments->toArray(),
            ];
        });
    
        // удаление дубликатов и лишних заявок
        $excessParticipations = $this->removeDuplicateExcessParticipations($filteredStructure, collect($excessParticipations));
        $uniqueExcessParticipations = $this->removeDuplicatesFromExcessParticipations($excessParticipations);
    
        // формирование результата
        return [
            'projects' => $filteredStructure->toArray(),
            'excess_participations' => $uniqueExcessParticipations->values()->toArray(),
        ];
    }
       
    //метод 4. удаление лишних заявок, которые прошли на проект
    public function removeDuplicateExcessParticipations($projects, $excessParticipations)
    {
        // Проходим по каждому кандидату из excess_participations
        $filteredExcessParticipations = $excessParticipations->reject(function ($excessCandidate) use ($projects) {            
            foreach ($projects as $institute) {                
                foreach ($institute['departments'] as $department) {                   
                    foreach ($department['projects'] as $project) {
                        // проверяем на наличие в проекте кандидата из лишних кандидатов
                        $candidateInProject = collect($project['candidates'])->contains('candidate_id', $excessCandidate['candidate_id']);
                        if ($candidateInProject) {
                            return true; // удаляем из excessParticipations, если кандидат найден в проекте
                        }
                    }
                }
            }
            return false; 
        });
    
        return $filteredExcessParticipations->values();
    }

    //метод 5. получение уникальных лишних кандидатов
    public function removeDuplicatesFromExcessParticipations($excessParticipations)
    {        
        $uniqueExcessParticipations = $excessParticipations->unique('candidate_id')->map(function ($candidate) { // используем коллекцию для фильтрации уникальных кандидатов по candidate_id
            // Обновляем значение priority 4
            $candidate['priority'] = 4;
            return $candidate;
        });
        
        return $uniqueExcessParticipations->values();
    }

    //метод 6. получение молчунов
    public function getCandidatesWithoutParticipation(array $data)
    {
        $currentYear = now()->year; // текущий год
        $currentTime = now(); // текущее время
    
        $projects = $data['projects'];
        $excessParticipations = $data['excess_participations'];
    
        // получаем уникальные заявки по candidate_id за сентябрь с state_id 1
        $participations = Participation::with('project')
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', 9)
            ->where('state_id', 1)
            ->pluck('candidate_id')
            ->unique();
    
        // получаем всех кандидатов, которые могут отправлять заявки
        $candidatesWhoCanSend = Candidate::where('can_send_participations', 1)
            ->pluck('id');
    
        // вычисляем кандидатов без заявок
        $candidatesWithoutParticipation = $candidatesWhoCanSend->diff($participations);
    
        // создаем массив кандидатов без заявок
        $withoutParticipation = $candidatesWithoutParticipation->map(function ($candidateId) use ($currentTime) {
            $candidate = Candidate::find($candidateId);
            if (!$candidate) {
                return null;
            }
    
            // получение специальности, кафедры и института кандидата
            $speciality = $candidate->getSpeciality();
            $department = $candidate->getDepartment();
            $institute = $candidate->getInstitute();
    
            return [
                'candidate_id' => $candidate->id,
                'fio' => $candidate->fio,
                'priority' => 5,
                'state_id' => 1,
                'created_at' => $currentTime->toDateTimeString(),
                'speciality_id' => $speciality ? $speciality->id : null,
                'department_id' => $department ? $department->id : null,
                'institute_id' => $institute ? $institute->id : null,
            ];
        })->filter()->values();
    
        $withoutParticipationArray = $withoutParticipation->toArray();
        shuffle($withoutParticipationArray); // перемешиваем кандидатов без заявок
    
        // формируем результат
        return [
            'projects' => $projects,
            'excess_participations' => $excessParticipations,
            'without_participation' => $withoutParticipationArray,
        ];
    }
    

    //метод 7. распределение лишних
    public function distributeExcessParticipations(array &$data)
    {
        foreach ($data['excess_participations'] as $key => $candidate) {
            $candidateInstitute = $candidate['institute_id'];
            $candidateDepartment = $candidate['department_id'];
            $candidateSpecialityId = $candidate['speciality_id'];

            $eligibleProjects = []; // подходящие проекты по институту, департаменту и специальности

            foreach ($data['projects'] as &$institute) {
                if ($institute['institute_id'] == $candidateInstitute) {
                    foreach ($institute['departments'] as &$department) {
                        if ($department['department_id'] == $candidateDepartment) {
                            foreach ($department['projects'] as &$project) {
                                // проверка на совпадение специальности
                                $projectSpecialities = array_column($project['specialities'], 'id');
                                if (in_array($candidateSpecialityId, $projectSpecialities) && 
                                    $project['candidates_count'] < $project['places']) {
                                    $eligibleProjects[] = &$project;
                                }
                            }
                        }
                    }
                }
            }

            // сортировка проектов по возрастанию количества кандидатов
            usort($eligibleProjects, function ($a, $b) {
                return $a['candidates_count'] <=> $b['candidates_count'];
            });

            // распределение кандидата, если есть подходящие проекты
            if (!empty($eligibleProjects)) {
                $selectedProject = &$eligibleProjects[0];

                // добавляем кандидата в проект
                $selectedProject['candidates'][] = [
                    'candidate_id' => $candidate['candidate_id'],
                    'priority' => $candidate['priority'],
                    'state_id' => 1,
                    'created_at' => now()->toDateTimeString(),
                ];
                $selectedProject['candidates_count']++; // увеличиваем счётчик кандидатов в проекте

                // удаляем кандидата из лишних заявок
                unset($data['excess_participations'][$key]);
            }
        }

        // обновляем индекс в массиве лишних заявок
        $data['excess_participations'] = array_values($data['excess_participations']);

        return $data;
    }


    //метод 8. распределение молчунов
    public function distributeWithoutParticipation(array &$data)
    {
        foreach ($data['without_participation'] as $key => $candidate) {
            $candidateInstitute = $candidate['institute_id'] ?? null;  
            $candidateDepartment = $candidate['department_id'] ?? null;  
            $candidateSpecialityId = $candidate['speciality_id'] ?? null;
            
            if (is_null($candidateInstitute) || is_null($candidateDepartment) || is_null($candidateSpecialityId)) {
                continue; // пропускаем пустого кандидата
            }            

            $eligibleProjects = [];

            foreach ($data['projects'] as &$institute) {
                if ($institute['institute_id'] == $candidateInstitute) {
                    foreach ($institute['departments'] as &$department) {
                        if ($department['department_id'] == $candidateDepartment) {
                            foreach ($department['projects'] as &$project) {                              
                                $projectSpecialities = array_column($project['specialities'], 'id'); 
                                if (in_array($candidateSpecialityId, $projectSpecialities) && 
                                    $project['candidates_count'] < $project['places']) {
                                    $eligibleProjects[] = &$project;
                                }
                            }
                        }
                    }
                }
            }
            
            // сортируем подходящие проекты по количеству кандидатов
            usort($eligibleProjects, function ($a, $b) {
                return $a['candidates_count'] <=> $b['candidates_count'];
            });

            if (!empty($eligibleProjects)) {
                $selectedProject = &$eligibleProjects[0];

                $selectedProject['candidates'][] = [
                    'candidate_id' => $candidate['candidate_id'],
                    'priority' => $candidate['priority'],
                    'state_id' => 1,
                    'created_at' => now()->toDateTimeString(),
                ];

                $selectedProject['candidates_count']++; // увеличиваем счётчик кандидатов
                unset($data['without_participation'][$key]); // удаляем кандидата из молчунов
            }
        }

        $data['without_participation'] = array_values($data['without_participation']); // обновляем индексы
        
        return $data;
    }
    
}