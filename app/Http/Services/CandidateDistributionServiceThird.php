<?php

namespace App\Http\Services;
use App\Models\Project;
use App\Models\Candidate;
use App\Models\Participation;
use Illuminate\Support\Facades\Storage;

class CandidateDistributionServiceThird
{

    //метод 0. удаление дубликатов
    public function removeDuplicatesFromStructure(string $filePath, string $duplicatesFilePath)
    {
        // Считываем основной файл
        $jsonData = Storage::get($filePath);
        $projectsData = collect(json_decode($jsonData, true));

        $duplicatesData = collect(json_decode(Storage::get($duplicatesFilePath), true));

        // Преобразуем дубликаты для удобства поиска (по candidate_id и project_id)
        $duplicatesSet = $duplicatesData->map(function ($duplicate) {
            return [
                'candidate_id' => $duplicate['candidate_id'],
                'project_id' => $duplicate['project_id'],
            ];
        });
    
        // Проходим по структуре проектов и удаляем дубликаты
        $filteredProjectsData = $projectsData->map(function ($project) use ($duplicatesSet) {
            $project['candidates'] = collect($project['candidates'])->reject(function ($candidate) use ($duplicatesSet, $project) {
                return $duplicatesSet->contains(function ($duplicate) use ($candidate, $project) {
                    return $duplicate['candidate_id'] === $candidate['candidate_id']
                        && $duplicate['project_id'] === $project['project_id'];
                });
            })->values()->toArray();
    
            // Обновляем количество кандидатов в проекте
            $project['candidates_count'] = count($project['candidates']);
            return $project;
        });
    
        // Сохраняем обновлённую структуру обратно в файл
        Storage::put($filePath, json_encode($filteredProjectsData->values()->toArray(), JSON_PRETTY_PRINT));
    
        return $filteredProjectsData->values()->toArray();
    }
    
    //метод 1. отбор команд из первых приоритетов и удаление заявок с приоритетом >1 для прошедших кандидатов
    public function filterParticipations(string $filePath, string $filteredFilePath)
    {
        // Загружаем данные JSON и преобразуем в коллекцию
        $jsonData = Storage::get($filePath);
        $projects = collect(json_decode($jsonData, true)); // коллекция проектов

        $selectedCandidates = []; // массив для хранения ID кандидатов, попавших в команды

        // Первый проход: формируем команды и собираем кандидатов с приоритетом 1
        $projects = $projects->map(function ($project) use (&$selectedCandidates) {
            $placesCount = $project['places']; // количество мест в проекте
            $candidates = collect($project['candidates']); // кандидаты в проекте

            // Отбираем кандидатов с приоритетом 1, ограничивая количество мест
            $teamCandidates = $candidates
                ->filter(function ($candidate) {
                    return $candidate['priority'] == 1;
                })
                ->take($placesCount)
                ->pluck('candidate_id')
                ->toArray();

            // Добавляем выбранных кандидатов в общий массив
            $selectedCandidates = array_merge($selectedCandidates, $teamCandidates);

            // Удаляем дубликаты из списка выбранных кандидатов
            $selectedCandidates = array_unique($selectedCandidates);

            // Возвращаем проект без изменений на этом этапе
            return $project;
        });

        // Второй проход: удаляем заявки с приоритетом >1 для кандидатов из списка
        $projects = $projects->map(function ($project) use ($selectedCandidates) {
            $candidates = collect($project['candidates']); // кандидаты в проекте

            // Удаляем заявки с приоритетом >1 для кандидатов из команд
            $filteredCandidates = $candidates->filter(function ($candidate) use ($selectedCandidates) {
                // Удаляем заявку, если кандидат есть в списке выбранных и приоритет >1
                return !(in_array($candidate['candidate_id'], $selectedCandidates) && $candidate['priority'] > 1);
            });

            // Обновляем список кандидатов и их количество в проекте
            $project['candidates'] = $filteredCandidates->values()->toArray();
            $project['candidates_count'] = $filteredCandidates->count();

            return $project; // возвращаем обновлённый проект
        });

        // Преобразуем результат в массив и сохраняем в файл JSON
        $filteredProjectsData = $projects->values()->toArray();
        Storage::put($filteredFilePath, json_encode($filteredProjectsData, JSON_PRETTY_PRINT));

        return $filteredProjectsData;
    }

    //метод 2. удаление заявок с приоритетом 3 для выбранных кандидатов
    public function secondFilterParticipations(string $filePath, string $filteredFilePath)
    {
        // Загружаем данные JSON и преобразуем в коллекцию
        $jsonData = Storage::get($filePath);
        $projects = collect(json_decode($jsonData, true)); // коллекция проектов

        $selectedCandidates = []; // массив для хранения ID кандидатов, попавших в команды

        // Первый проход: формируем команды и собираем кандидатов с приоритетами 1 и 2
        $projects = $projects->map(function ($project) use (&$selectedCandidates) {
            $placesCount = $project['places']; // количество мест в проекте
            $candidates = collect($project['candidates']); // кандидаты в проекте

            // Отбираем кандидатов с приоритетами 1 и 2, ограничивая количество мест
            $teamCandidates = $candidates
                ->filter(function ($candidate) {
                    return $candidate['priority'] == 1 || $candidate['priority'] == 2;
                })
                ->take($placesCount)
                ->pluck('candidate_id')
                ->toArray();

            // Добавляем выбранных кандидатов в общий массив
            $selectedCandidates = array_merge($selectedCandidates, $teamCandidates);

            // Удаляем дубликаты из списка выбранных кандидатов
            $selectedCandidates = array_unique($selectedCandidates);

            // Возвращаем проект без изменений на этом этапе
            return $project;
        });

        // Второй проход: удаляем заявки с приоритетом 3 для кандидатов из списка
        $projects = $projects->map(function ($project) use ($selectedCandidates) {
            $candidates = collect($project['candidates']); // кандидаты в проекте

            // Удаляем заявки с приоритетом 3 для кандидатов из команд
            $filteredCandidates = $candidates->filter(function ($candidate) use ($selectedCandidates) {
                // Удаляем заявку, если кандидат есть в списке выбранных и приоритет == 3
                return !(in_array($candidate['candidate_id'], $selectedCandidates) && $candidate['priority'] == 3);
            });

            // Обновляем список кандидатов и их количество в проекте
            $project['candidates'] = $filteredCandidates->values()->toArray();
            $project['candidates_count'] = $filteredCandidates->count();

            return $project; // возвращаем обновлённый проект
        });

        // Преобразуем результат в массив и сохраняем в файл JSON
        $filteredProjectsData = $projects->values()->toArray();
        Storage::put($filteredFilePath, json_encode($filteredProjectsData, JSON_PRETTY_PRINT));

        return $filteredProjectsData;
    }

    //метод 3. работа с лишними заявками
    public function collectExcessParticipations(string $filePath, string $filteredFilePath)
    {
        // Считываем данные из файла с проектами
        $jsonData = Storage::get($filePath);
        $projects = collect(json_decode($jsonData, true));
    
        // Массив для хранения лишних заявок (тех, кто не попал в проекты)
        $excessParticipations = [];
    
        // Обрабатываем проекты
        $filteredProjects = $projects->map(function ($project) use (&$excessParticipations) {
            // Получаем количество мест в проекте
            $placesCount = $project['places'];
    
            // Разделяем кандидатов на тех, кто помещается в проект, и тех, кто не помещается
            $teamCandidates = collect($project['candidates'])->take($placesCount);
            $excessCandidates = collect($project['candidates'])->slice($placesCount);
    
            // Обрабатываем лишних кандидатов
            $excessCandidates->each(function ($candidate) use (&$excessParticipations, $project) {
                // Получаем специальность кандидата
                $candidateModel = Candidate::find($candidate['candidate_id']);
                $speciality = $candidateModel->getSpeciality();
                
                // Добавляем кандидата в список лишних
                $excessParticipations[] = [
                    'candidate_id' => $candidate['candidate_id'],
                    'fio' => $candidate['fio'],
                    'training_group' => $candidate['training_group'],
                    'priority' => $candidate['priority'],
                    'course' => $candidate['course'],
                    'institute_id' => $project['institute_id'], // институт проекта
                    'department_id' => $project['department_id'], // департамент проекта
                    'speciality_id' => $speciality->id ?? null, // id специальности кандидата
                    'speciality_name' => $speciality->name ?? null, // название специальности кандидата
                    'created_at' => $candidate['created_at'],
                    'updated_at' => $candidate['updated_at'],
                ];
            });
    
            // Формируем новый список кандидатов для проекта
            return [
                'project_id' => $project['project_id'],
                'title' => $project['title'],
                'places' => $project['places'],
                'candidates_count' => $teamCandidates->count(),
                'institute_id' => $project['institute_id'], // добавляем институт в структуру проекта
                'department_id' => $project['department_id'], // добавляем департамент в структуру проекта
                'candidates' => $teamCandidates->values()->toArray(),
                'specialities' => $project['specialities'],
            ];
        });
    
        // Удаляем лишние заявки, которые уже попали на проекты
        $excessParticipations = $this->removeDuplicateExcessParticipations($filteredProjects, collect($excessParticipations));
    
        // Оставляем только уникальных лишних кандидатов
        $uniqueExcessParticipations = $this->removeDuplicatesFromExcessParticipations($excessParticipations);
    
        // Формируем итоговую структуру
        $result = [
            'projects' => $filteredProjects->toArray(),
            'excess_participations' => $uniqueExcessParticipations->values()->toArray(),
        ];
    
        // Сохраняем итоговую структуру в файл
        Storage::put($filteredFilePath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
        return response()->json([
            'data' => $result,
        ]);
    }
    
    //метод 4. удаление лишних заявок, которые прошли на проект
    public function removeDuplicateExcessParticipations($projects, $excessParticipations)
    {
        // Проходим по каждому кандидату из excessParticipations
        $filteredExcessParticipations = $excessParticipations->reject(function ($excessCandidate) use ($projects) {
            foreach ($projects as $project) {
                // Проверяем, содержится ли кандидат в проекте
                $candidateInProject = collect($project['candidates'])->contains('candidate_id', $excessCandidate['candidate_id']);
                if ($candidateInProject) {
                    return true; // Удаляем из excessParticipations, если кандидат найден в проекте
                }
            }
            return false; // Оставляем кандидата, если его нет ни в одном проекте
        });
    
        return $filteredExcessParticipations->values(); // Возвращаем оставшиеся заявки
    }

    //метод 5. получение уникальных лишних кандидатов
    public function removeDuplicatesFromExcessParticipations($excessParticipations)
    {
        // Удаляем дубликаты кандидатов по их candidate_id
        $uniqueExcessParticipations = $excessParticipations->unique('candidate_id')->map(function ($candidate) {
            // Устанавливаем значение priority равным 4
            $candidate['priority'] = 4;
            return $candidate;
        });

        return $uniqueExcessParticipations->values(); // Возвращаем обновлённые уникальные заявки
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
    
            $eligibleProjects = []; // подходящие проекты
    
            foreach ($jsonData['projects'] as &$project) {
                // проверяем институт, департамент и наличие подходящей специальности
                if (
                    $project['institute_id'] == $candidateInstitute &&
                    $project['department_id'] == $candidateDepartment &&
                    in_array($candidateSpecialityId, array_column($project['specialities'], 'id')) &&
                    in_array($candidateCourse, array_column($project['specialities'], 'course'))
                ) {
                    // добавляем проект, если есть свободные места
                    if (count($project['candidates']) < $project['places']) {
                        $eligibleProjects[] = &$project;
                    }
                }
            }
    
            // сортируем проекты по количеству занятых мест (возрастание)
            usort($eligibleProjects, function ($a, $b) {
                return count($a['candidates']) <=> count($b['candidates']);
            });
    
            // распределяем кандидата в подходящий проект
            if (!empty($eligibleProjects)) {
                $selectedProject = &$eligibleProjects[0]; // выбираем проект с наименьшей загрузкой
    
                $selectedProject['candidates'][] = [
                    'candidate_id' => $candidate['candidate_id'],
                    'fio' => $candidate['fio'],
                    'training_group' => $candidate['training_group'],
                    'priority' => $candidate['priority'],
                    'state_id' => 1, // state_id 1
                    'created_at' => now()->toDateTimeString(),
                ];
    
                // удаляем кандидата из списка "лишних"
                unset($jsonData['excess_participations'][$key]);
            }
        }
    
        // обновляем список "лишних" кандидатов
        $jsonData['excess_participations'] = array_values($jsonData['excess_participations']);
    
        // сохраняем обновленные данные
        Storage::put($outputFilePath, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return response()->json($jsonData);
    }
    
    //метод 8. распределение молчунов
    public function distributeWithoutParticipation(string $filePath, string $outputFilePath)
    {
        // Загружаем данные из файла
        $jsonData = json_decode(Storage::get($filePath), true);

        foreach ($jsonData['without_participation'] as $key => $candidate) {
            $candidateInstitute = $candidate['institute_id'] ?? null;
            $candidateDepartment = $candidate['department_id'] ?? null;
            $candidateSpecialityId = $candidate['speciality_id'] ?? null;
            $candidateCourse = $candidate['course'] ?? null;

            // Пропускаем кандидатов без указанных параметров
            if (is_null($candidateInstitute) || is_null($candidateDepartment) || is_null($candidateSpecialityId)) {
                continue;
            }

            // Список подходящих проектов
            $eligibleProjects = [];

            foreach ($jsonData['projects'] as &$project) {
                // Проверяем соответствие института и департамента
                if (
                    $project['institute_id'] == $candidateInstitute &&
                    $project['department_id'] == $candidateDepartment
                ) {
                    // Проверяем, есть ли подходящая специальность и курс
                    $projectSpecialities = array_column($project['specialities'], 'id');
                    $projectCourses = array_column($project['specialities'], 'course');
                    if (
                        in_array($candidateSpecialityId, $projectSpecialities) &&
                        in_array($candidateCourse, $projectCourses)
                    ) {
                        // Проверяем наличие свободных мест
                        if ($project['candidates_count'] < $project['places']) {
                            $eligibleProjects[] = &$project;
                        }
                    }
                }
            }

            // Сортируем проекты по количеству занятых мест (возрастание)
            usort($eligibleProjects, function ($a, $b) {
                return $a['candidates_count'] <=> $b['candidates_count'];
            });

            // Если есть подходящие проекты, распределяем кандидата
            if (!empty($eligibleProjects)) {
                $selectedProject = &$eligibleProjects[0];

                if ($selectedProject['candidates_count'] < $selectedProject['places']) {
                    // Добавляем кандидата в проект
                    $selectedProject['candidates'][] = [
                        'candidate_id' => $candidate['candidate_id'],
                        'fio' => $candidate['fio'],
                        'training_group' => $candidate['training_group'],
                        'priority' => $candidate['priority'],
                        'speciality_id' => $candidate['speciality_id'], // Добавляем специальность кандидата
                        'speciality_name' => $candidate['speciality_name'], // Добавляем название специальности кандидата
                        'state_id' => 1, // Состояние "распределен"
                        'created_at' => now()->toDateTimeString(),
                    ];

                    // Увеличиваем счетчик занятых мест
                    $selectedProject['candidates_count']++;

                    // Удаляем кандидата из списка "без участия"
                    unset($jsonData['without_participation'][$key]);
                }
            }
        }

        // Перезаписываем массив без распределенных кандидатов
        $jsonData['without_participation'] = array_values($jsonData['without_participation']);

        // Сохраняем обновленный JSON в файл
        Storage::put($outputFilePath, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Возвращаем JSON-ответ
        return response()->json($jsonData);
    }

    //метод 9. старая структура
    public function groupProjects(string $inputFilePath, string $outputFilePath)
    {
        // Читаем содержимое JSON-файла
        $jsonData = json_decode(Storage::get($inputFilePath), true);

        if (!$jsonData || !isset($jsonData['projects'])) {
            return response()->json(['error' => 'Invalid file structure or no projects data found'], 400);
        }

        // Инициализируем массив для хранения сгруппированных данных
        $groupedData = [
            'projects' => [],
            'excess_participations' => $jsonData['excess_participations'] ?? [],
            'without_participation' => $jsonData['without_participation'] ?? [],
        ];

        // Группируем проекты по институтам и департаментам
        foreach ($jsonData['projects'] as $project) {
            $departmentId = $project['department_id'];
            $instituteId = $project['institute_id'];

            // Проверяем, есть ли уже институт в массиве
            if (!isset($groupedData['projects'][$instituteId])) {
                $groupedData['projects'][$instituteId] = [
                    'institute_id' => $instituteId,
               //     'institute_name' => $project['institute_name'], // если нужно добавить название института
                    'departments' => []
                ];
            }

            // Проверяем, есть ли уже департамент в институте
            if (!isset($groupedData['projects'][$instituteId]['departments'][$departmentId])) {
                $groupedData['projects'][$instituteId]['departments'][$departmentId] = [
                    'department_id' => $departmentId,
                   // 'department_name' => $project['department_name'], // если нужно добавить название департамента
                    'projects' => []
                ];
            }

            // Добавляем проект в соответствующий департамент
            $groupedData['projects'][$instituteId]['departments'][$departmentId]['projects'][] = $project;
        }

        // Преобразуем результат в удобный формат, если нужно
        $groupedData['projects'] = array_values($groupedData['projects']); // это сбросит ключи для институтов

        // Сохраняем результат в новый JSON-файл
        Storage::put($outputFilePath, json_encode($groupedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json($jsonData);
    }


}