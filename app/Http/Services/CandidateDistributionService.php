<?php

namespace App\Http\Services;
use App\Models\Project;
use App\Models\Candidate;
use App\Models\Participation;
use Illuminate\Support\Facades\Storage;

class CandidateDistributionService
{

    //метод 1. удаление заявок с приоритетом 2 и 3 для выбранных кандидатов
    public function filterParticipations($filePath, $filteredFilePath)
    {
        
        $jsonData = Storage::get($filePath);
        $structure = collect(json_decode($jsonData, true)); // преобразование в коллекцию 
        
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
    
        // удаление заявок с приоритетом 2 и 3 для кандидатов, попоавших в проектную команду
        $filteredStructure = collect($filteredStructure)->map(function ($institute) use ($selectedCandidates) // преобразование в коллекцию и передача selectedCandidates
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

        $projectsData =$filteredStructure->toArray();

        Storage::put($filteredFilePath, json_encode($projectsData, JSON_PRETTY_PRINT));
        return $projectsData;
    }

    //метод 2. удаление заявок с приоритетом 3 для выбранных кандидатов
    public function secondFilterParticipations(string $filePath, string $filteredFilePath)
    {        
        $jsonData = Storage::get($filePath);
        $projectsData = json_decode($jsonData, true);
       
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
       
        Storage::put($filteredFilePath, json_encode($projectsData, JSON_PRETTY_PRINT));
        return $projectsData; 
    }
    
    //метод 3. получение лишних кандидатов
    public function collectExcessParticipations(string $filePath, string $filteredFilePath)
    {        
        $jsonData = Storage::get($filePath);
        $structure = collect(json_decode($jsonData, true));
        
        // Массив для хранения лишних заявок. Не попавших на проект
        $excessParticipations = [];        
        
        $filteredStructure = $structure->map(function ($institute) use (&$excessParticipations) {
            $filteredDepartments = collect($institute['departments'])->map(function ($department) use (&$excessParticipations) {
                $filteredProjects = collect($department['projects'])->map(function ($project) use (&$excessParticipations) {

                    $placesCount = $project['places'];    
                    // получаем связанный проект через модель
                    $projectModel = Project::find($project['project_id']); // Найдем проект по его ID
                    if (!$projectModel) {
                        return; // Пропустим, если проект не найден
                    }

                    // разделяем кандидатов на тех, кто помещается в проект, и кто не помещается
                    $teamCandidates = collect($project['candidates'])->take($placesCount);
                    $excessCandidates = collect($project['candidates'])->slice($placesCount);

                    // проходим по лишним кандидатам и добавляем их в excess_participations
                    $excessCandidates->each(function ($candidate) use (&$excessParticipations) {
                        $candidateModel = Candidate::find($candidate['candidate_id']); 
                        if (!$candidateModel) {
                            return; 
                        }

                        // получение специальности кандидата с помощью метода getSpeciality() по id
                        $speciality = $candidateModel->getSpeciality()->id; 
                        $speciality_name = $candidateModel->getSpeciality()->name; 

                        // получение департамента кандидата через специальность
                        $departmentModel = $candidateModel->getSpeciality()->department;
                        $instituteModel = $departmentModel->institute;

                        // добавляем лишнюю заявку в excess_participations
                        $excessParticipations[] = [
                            'candidate_id' => $candidateModel->id,
                            'fio' => $candidateModel->fio,
                            'training_group' => $candidateModel->training_group,
                            'priority' => $candidate['priority'], 
                            'course' => $candidate['course'],                                                                 
                            'department_id' => $departmentModel->id,
                            'department_name' => $departmentModel->name,
                            'institute_id' => $instituteModel->id,
                            'institute_name' => $instituteModel->name,
                            'speciality_id' => $speciality,  // специальность кандидата
                            'speciality_name' => $speciality_name,
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

        
        //удаление лишних заявок, которые прошли на проект
        $excessParticipations = $this->removeDuplicateExcessParticipations($filteredStructure, collect($excessParticipations));
        // удаляем дубликаты кандидатов
        $uniqueExcessParticipations = $this->removeDuplicatesFromExcessParticipations($excessParticipations);

        // формируем результат с обновленными проектами и лишними заявками
        $result = [
            'projects' => $filteredStructure->toArray(),
            'excess_participations' => $uniqueExcessParticipations->values()->toArray(), // Используем values() для сброса ключей
        ];        
    
        Storage::put($filteredFilePath, json_encode($result, JSON_PRETTY_PRINT));
        
        
        return response()->json([
            'data' => $result,
        ]);
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
    public function getCandidatesWithoutParticipation(string $filePath, string $outputFilePath)
    {             
        $jsonData = Storage::get($filePath);
        $data = json_decode($jsonData, true);

        $currentYear = now()->year; // получение текущего года
        $currentMonth = now()->month; 
        $currentTime = now();         
        $semesterMonth = ($currentMonth > 1 && $currentMonth < 9) ? 2 : 9;

        $projects = $data['projects'];
        $excessParticipations = $data['excess_participations'];
    
        // получаем уникальные заявки по candidate_id за сентябрь с state_id 1
        $participations = Participation::with('project')
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $semesterMonth)
            ->where('state_id', 1)
            ->pluck('candidate_id')
            ->unique();
    
        // получаем всех кандидатов, которые могут отправлять заявки, can_send_participations 1
        $candidatesWhoCanSend = Candidate::where('can_send_participations', 1)
            ->pluck('id'); // Получаем список всех candidate_id
    
        // получаем разность для определения кандидатов без заявок
        $candidatesWithoutParticipation = $candidatesWhoCanSend->diff($participations);    
        
        $withoutParticipation = $candidatesWithoutParticipation->map(function ($candidateId) use ($currentTime) {            
            $candidate = Candidate::find($candidateId);
            if (!$candidate) {
                return null; 
            }    
            // используем методы модели Candidate для получения специальности, кафедры и института
            $speciality = $candidate->getSpeciality();
            
            $department = $candidate->getDepartment();
            $institute = $candidate->getInstitute();
    
            return [
                'candidate_id' => $candidate->id,
                'fio' => $candidate->fio,
                'training_group' => $candidate->training_group,
                'course' => $candidate->course,                
                'priority' => 5, // Устанавливаем приоритет как 5
                'state_id' => 1, // Устанавливаем state_id как 1
                'created_at' => $currentTime->toDateTimeString(), // текущее время
                'speciality_id' => $speciality ? $speciality->id : null, 
                'speciality_name' => $speciality ? $speciality->name : null, 
                'department_id' => $department ? $department->id : null, 
                'department_name' => $department ? $department->name : null,               
                'institute_id' => $institute ? $institute->id : null, 
                'institute_name' => $institute ? $institute->name : null, 
                
            ];
        })->filter()->values(); 
    
        $withoutParticipationArray = $withoutParticipation->toArray();

        shuffle($withoutParticipationArray); // перемешивание молчунов
       
        $result = [
            'projects' => $projects,
            'excess_participations' => $excessParticipations,
            'without_participation' => $withoutParticipationArray,
        ];
        
        Storage::put($outputFilePath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return response()->json($result);
    }

    //метод 7. распределение лишних
    public function distributeExcessParticipations(string $filePath, string $outputFilePath)
    {
        
        $jsonData = json_decode(Storage::get($filePath), true);
        
        foreach ($jsonData['excess_participations'] as $key => $candidate) {
            $candidateInstitute = $candidate['institute_id'];
            $candidateDepartment = $candidate['department_id'];
            $candidateSpecialityId = $candidate['speciality_id'];
            
            $candidateCourse = $candidate['course'];

            
           

            $eligibleProjects = [];  //подходящие проекты по институту, департаменту и специальности

            foreach ($jsonData['projects'] as &$institute) {
                if ($institute['institute_id'] == $candidateInstitute) {
                    foreach ($institute['departments'] as &$department) {
                        if ($department['department_id'] == $candidateDepartment) {
                            foreach ($department['projects'] as &$project) {
                                // совпадение специальности
                                $projectSpecialities = array_column($project['specialities'], 'id'); // получаем массив специальностей проекта
                                $projectCourses = array_column($project['specialities'], 'course'); // получаем массив курсов проекта
                                if (in_array($candidateSpecialityId, $projectSpecialities) && in_array($candidateCourse, $projectCourses)) {
                                    // добавляем проект при наличии свободных места
                                    if ($project['candidates_count'] < $project['places']) {
                                        $eligibleProjects[] = &$project;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // сортировка проектов (возрастание)
            usort($eligibleProjects, function ($a, $b) {
                return $a['candidates_count'] <=> $b['candidates_count'];
            });

           
            if (!empty($eligibleProjects)) {
                $selectedProject = &$eligibleProjects[0];

                
                if ($selectedProject['candidates_count'] < $selectedProject['places']) {
                    $selectedProject['candidates'][] = [
                        'candidate_id' => $candidate['candidate_id'],
                        'fio' => $candidate['fio'],
                        'training_group' => $candidate['training_group'],
                        'priority' => $candidate['priority'],
                        'state_id' => 1, // state_id 1 
                        'created_at' => now()->toDateTimeString(),
                        'institute_id' => $candidateInstitute,
                        'department_id' =>  $candidateDepartment,
                        'speciality_id' => $candidateSpecialityId, 
                    ];
                    
                    $selectedProject['candidates_count']++; // увеличиваем счетчик

                    // удаляем из excess_participations
                    unset($jsonData['excess_participations'][$key]);
                }
            }
        }
        
        $jsonData['excess_participations'] = array_values($jsonData['excess_participations']);        
        Storage::put($outputFilePath, json_encode($jsonData, JSON_PRETTY_PRINT));
        return response()->json($jsonData);
    }


    //метод 8. распределение молчунов
    public function distributeWithoutParticipation(string $filePath, string $outputFilePath)
    {
        
        $jsonData = json_decode(Storage::get($filePath), true);

        
        foreach ($jsonData['without_participation'] as $key => $candidate) {
            $candidateInstitute = $candidate['institute_id'] ?? null;  
            $candidateDepartment = $candidate['department_id'] ?? null;  
            $candidateSpecialityId = $candidate['speciality_id'] ?? null;

            $candidateCourse = $candidate['course'] ?? null;
            
            if (is_null($candidateInstitute) || is_null($candidateDepartment) || is_null($candidateSpecialityId)) {
                continue; // скипаем пустого
            }            

            $eligibleProjects = [];

            foreach ($jsonData['projects'] as &$institute) {
                if ($institute['institute_id'] == $candidateInstitute) {
                    foreach ($institute['departments'] as &$department) {
                        if ($department['department_id'] == $candidateDepartment) {
                            foreach ($department['projects'] as &$project) {                              
                                $projectSpecialities = array_column($project['specialities'], 'id'); 
                                $projectCourses = array_column($project['specialities'], 'course');
                                if (in_array($candidateSpecialityId, $projectSpecialities) && in_array($candidateCourse, $projectCourses)) {                                    
                                    if ($project['candidates_count'] < $project['places']) {
                                        $eligibleProjects[] = &$project;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            usort($eligibleProjects, function ($a, $b) {
                return $a['candidates_count'] <=> $b['candidates_count'];
            });
            
            if (!empty($eligibleProjects)) {
                $selectedProject = &$eligibleProjects[0];
               
                if ($selectedProject['candidates_count'] < $selectedProject['places']) {
                    $selectedProject['candidates'][] = [
                        'candidate_id' => $candidate['candidate_id'],
                        'fio' => $candidate['fio'],
                        'training_group' => $candidate['training_group'],
                        'priority' => $candidate['priority'],
                        'state_id' => 1, 
                        'created_at' => now()->toDateTimeString(),
                        'institute_id' =>  $candidateInstitute,
                        'department_id' =>  $candidateDepartment,
                        'speciality_id' => $candidateSpecialityId, 
                    ];

                    
                    $selectedProject['candidates_count']++;
                    
                    unset($jsonData['without_participation'][$key]);
                }
            }
        }

        $jsonData['without_participation'] = array_values($jsonData['without_participation']);        
        Storage::put($outputFilePath, json_encode($jsonData, JSON_PRETTY_PRINT));        
        return response()->json($jsonData);
    }  



    public function getAndSaveDuplicates(string $filePath, string $duplicatesFilePath)
    {
        // Считываем данные из файла
        $jsonData = Storage::get($filePath);
        $projectsData = collect(json_decode($jsonData, true));

        // Собираем всех кандидатов из всех проектов в один массив
        $allCandidates = $projectsData->flatMap(function ($institute) {
            return collect($institute['departments'])->flatMap(function ($department) {
                return collect($department['projects'])->flatMap(function ($project) {
                    return collect($project['candidates'])->map(function ($candidate) use ($project) {
                        return [
                            'candidate_id' => $candidate['candidate_id'],
                            'priority' => $candidate['priority'],
                            'project_id' => $project['project_id'],
                            'created_at' => $candidate['created_at'],
                        ];
                    });
                });
            });
        });

        // Группируем кандидатов по `candidate_id` и `priority`, чтобы найти дубликаты
        $duplicates = $allCandidates
            ->groupBy(fn($candidate) => $candidate['candidate_id'] . '_' . $candidate['priority'])
            ->filter(fn($group) => $group->count() > 1)
            ->flatMap(function ($group) {
                return $group->sortBy('created_at')->splice(1); // Оставляем все, кроме самой ранней заявки
            });

        // Сохраняем дубликаты в файл
        Storage::put($duplicatesFilePath, json_encode($duplicates->values()->toArray(), JSON_PRETTY_PRINT));

        return $duplicates->values()->toArray();
    }

    public function removeDuplicates(string $filePath, string $duplicatesFilePath)
    {
        // Считываем основную структуру
        $jsonData = Storage::get($filePath);
        $projectsData = collect(json_decode($jsonData, true));

        // Считываем дубликаты
        $duplicatesData = collect(json_decode(Storage::get($duplicatesFilePath), true));

        // Преобразуем дубликаты в удобный для поиска формат
        $duplicatesSet = $duplicatesData->map(function ($duplicate) {
            return [
                'candidate_id' => $duplicate['candidate_id'],
                'project_id' => $duplicate['project_id'],
            ];
        });

        // Удаляем дубликаты из структуры
        $filteredProjectsData = $projectsData->map(function ($institute) use ($duplicatesSet) {
            $institute['departments'] = collect($institute['departments'])->map(function ($department) use ($duplicatesSet) {
                $department['projects'] = collect($department['projects'])->map(function ($project) use ($duplicatesSet) {
                    $project['candidates'] = collect($project['candidates'])->reject(function ($candidate) use ($duplicatesSet, $project) {
                        return $duplicatesSet->contains(function ($duplicate) use ($candidate, $project) {
                            return $duplicate['candidate_id'] === $candidate['candidate_id']
                                && $duplicate['project_id'] === $project['project_id'];
                        });
                    })->values()->toArray();

                    // Обновляем количество кандидатов
                    $project['candidates_count'] = count($project['candidates']);
                    return $project;
                })->toArray();

                return $department;
            })->toArray();

            return $institute;
        });

        // Сохраняем обновлённую структуру обратно в файл
        Storage::put($filePath, json_encode($filteredProjectsData->values()->toArray(), JSON_PRETTY_PRINT));

        return $filteredProjectsData->values()->toArray();
    }


    public function clearCandidates(string $filePath, string $outputFilePath)
    {
   
        $jsonData = Storage::get($filePath);
        $projectsData = json_decode($jsonData, true);

        // Удаляем разделы excess_participation и without_participation
       // unset($projectsData['excess_participations']);
       // unset($projectsData['without_participation']);

        // Проходимся по структуре и очищаем списки кандидатов
        if (isset($projectsData['projects'])) {
            foreach ($projectsData['projects'] as &$institute) {
                if (isset($institute['departments'])) {
                    foreach ($institute['departments'] as &$department) {
                        if (isset($department['projects'])) {
                            foreach ($department['projects'] as &$project) {
                                // Очищаем список кандидатов
                                $project['candidates'] = [];
                                // Сбрасываем счётчик кандидатов                               
                            }
                        }
                    }
                }
            }
        }
        
        Storage::put($outputFilePath, json_encode($projectsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $projectsData;
    }


    
}