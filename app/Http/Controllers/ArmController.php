<?php

namespace App\Http\Controllers;

use App\Models\Participation;
use App\Models\Candidate;
use App\Models\Project;
use App\Models\Department;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ArmController extends Controller
{
    public function __invoke()
    {
        // Шаг 1: Получение и сохранение данных за текущий год
        $this->saveParticipationsData();

        // Шаг 2: Чтение данных из файла и применение логики фильтрации
        $this->sortParticipationsByPriority();       
        // Шаг 3: 
        $this->filterParticipations();
        // Шаг 4: 
        $this->secondFilterParticipations();
        // Шаг 5: 
        $this->collectExcessParticipations();
        // Шаг 6: 
        $this->cleanExcessParticipations();       
        // Шаг 7: 
        $this->removeDuplicateExcessParticipations();
        // Шаг 8: 
        $this->getCandidatesWithoutParticipation();
        // Шаг 9: 
        $this->addDepartmentInfoToJson();
        // Шаг 10: 
        $this->addProjectDepartmentInfoToJson();
        // Шаг 11: 
        $this->addInstituteInfoToProjects();
        // Шаг 12: 
        $this->groupProjectsByInstitute();
        // Шаг 13: 
        $this->addInstituteToCandidates();

        // Шаг 14: 
        $this->addSpecialitiesToCandidates();
        // Шаг 15: 
        $this->addSpecialitiesToProjects();
        // Шаг 16: 
        $this->distributeExcessParticipations();
        // Шаг 17: 
      //  $this->distributeWithoutParticipation();

        /*
        // Шаг 14: 
        $this->distributeExcessParticipations();
        // Шаг 15: 
        $this->distributeWithoutParticipationAndExcess();
*/

        $filePath = '15_projects_with_specialities.json';
        $jsonData = Storage::get($filePath); // Читаем данные из файла
        $filteredParticipations = json_decode($jsonData, true); // Декодируем JSON в массив

        // Возвращаем отфильтрованные данные в формате JSON
        return response()->json($filteredParticipations);
    }


    //--------------------------------------------------------------------------------------------------------------
    // шаг 1 получение проектов и студентов на проектах
    public function saveParticipationsData()
    {
        
        $currentYear = Carbon::now()->year;

        // записи за сентябрь текущего года с полем places из связанной таблицы projects
        $participations = Participation::with('project') // модель projects
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', 9) // Сентябрь
            ->where('state_id', 1) // заявки со state_id = 1
            ->get();

        // Группируем данные по project_id и собираем информацию о кандидатах и поле places из связанной таблицы projects
        $groupedParticipations = $participations->groupBy('project_id')->map(function ($item) {
            $project = $item->first()->project; // Достаём данные о проекте
            return [
                'project_id' => $project->id,
                'places' => $project->places,
                'candidates_count' => $item->count(),
                'candidates' => $item->map(function ($participation) {
                    return [
                        'candidate_id' => $participation->candidate_id,
                        'priority' => $participation->priority,
                        'state_id' => $participation->state_id,                     
                        'created_at' => $participation->created_at, 
                    ];
                })                
            ];
        });
        $jsonData = [           
            'projects' => $groupedParticipations 
        ];

        $filePath = '1_participations.json'; 
        Storage::put($filePath, json_encode($jsonData , JSON_PRETTY_PRINT)); 
        return response()->json($jsonData);
    }

    // шаг 2 сортируем по приоритету на командах
    public function sortParticipationsByPriority()
    {
        $filePath = '1_participations.json';
        $jsonData = Storage::get($filePath);
        $participations = json_decode($jsonData, true);
    
        $projectsCollection = collect($participations['projects']);
    
        $sortedParticipations = $projectsCollection->map(function ($project) {
            $sortedCandidates = collect($project['candidates'])->sortBy(function ($candidate) {
                return [$candidate['priority'], $candidate['created_at']];
            })->values(); // Преобразуем в индексированный массив
    
            return [
                'project_id' => $project['project_id'],
                'places' => $project['places'],
                'candidates_count' => $sortedCandidates->count(),
                'candidates' => $sortedCandidates->toArray(),                
            ];
        });
    
        $sortedFilePath = '2_sorted_participation.json';
        Storage::put($sortedFilePath, json_encode($sortedParticipations, JSON_PRETTY_PRINT));
    
        return response()->json($sortedParticipations);
    }

    // шаг 3 удаление заявок с приоритетом 2 и 3 для проектных команд
    public function filterParticipations()
    {
        $filePath = '2_sorted_participation.json';
        $jsonData = Storage::get($filePath);
        $projectsCollection = collect(json_decode($jsonData, true));

        // Массив для хранения ID кандидатов, которых мы уже выбрали в команды по проектам
        $selectedCandidates = [];
        // Массив для хранения отфильтрованных данных проектов
        $filteredProjects = [];

        // Шаг 1: Проходим по каждому проекту
        foreach ($projectsCollection as $project) {
            $placesCount = $project['places'];

            // Находим кандидатов с приоритетом 1 и формируем команду (по количеству `places`)
            $teamCandidates = collect($project['candidates'])
                ->where('priority', 1)
                ->take($placesCount)
                ->pluck('candidate_id')
                ->toArray(); // Получаем список ID кандидатов в команде

            // Сохраняем кандидатов, попавших в команду этого проекта
            $selectedCandidates = array_merge($selectedCandidates, $teamCandidates);

            // Сохраняем этот проект с командой кандидатов
            $filteredProjects[] = [
                'project_id' => $project['project_id'],
                'places' => $project['places'],
                'candidates_count' => count($project['candidates']),
                'candidates' => $project['candidates'],                
            ];
        }

        // Шаг 2: Удаляем заявки с приоритетом 2 и 3 для выбранных кандидатов в других проектах
        $filteredProjects = collect($filteredProjects)->map(function ($project) use ($selectedCandidates) {
            // Проходим по кандидатам каждого проекта
            $filteredCandidates = collect($project['candidates'])->filter(function ($candidate) use ($selectedCandidates) {
                // Если кандидат выбран в команду и его приоритет 2 или 3, удаляем его заявку
                if (in_array($candidate['candidate_id'], $selectedCandidates) && $candidate['priority'] > 1) {
                    return false; // Удаляем заявку
                }
                return true; // Оставляем заявки с приоритетом 1 или невыбранных кандидатов
            });

            // Обновляем проект с отфильтрованными кандидатами
            return [
                'project_id' => $project['project_id'],
                'places' => $project['places'],
                'candidates_count' => $filteredCandidates->count(),
                'candidates' => $filteredCandidates->values()->toArray(),                
            ];
        });

        // Сохраняем отфильтрованные данные
        $filteredFilePath = '3_filtered_participation.json';
        Storage::put($filteredFilePath, json_encode($filteredProjects, JSON_PRETTY_PRINT));

        return response()->json($filteredProjects);
    }

    // шаг 4 формирование команды из 1 и 2 приоритета, удаление приоритета 3 для проектных команд
    public function secondFilterParticipations()
    {       
        $filePath = '3_filtered_participation.json';
        $jsonData = Storage::get($filePath);
        $projectsData = json_decode($jsonData, true);
    
        // Массив для хранения ID кандидатов, которых мы уже выбрали в команды по проектам
        $selectedCandidates = [];
    
        // Шаг 1: Проходим по каждому проекту
        foreach ($projectsData as $project) {
            $placesCount = $project['places'];
    
            // Находим кандидатов с приоритетом 1 и 2 и формируем команду (по количеству `places`)
            $teamCandidates = collect($project['candidates'])
                ->filter(function ($candidate) {
                    return $candidate['priority'] == 1 || $candidate['priority'] == 2;
                })
                ->take($placesCount)
                ->pluck('candidate_id')
                ->toArray(); // Получаем список ID кандидатов в команде
    
            // Сохраняем кандидатов, попавших в команду этого проекта
            $selectedCandidates = array_merge($selectedCandidates, $teamCandidates);
        }
    
        // Шаг 2: Удаляем заявки с приоритетом 3 для выбранных кандидатов в других проектах
        $processedProjects = collect($projectsData)->map(function ($project) use ($selectedCandidates) {
            // Проходим по кандидатам каждого проекта
            $filteredCandidates = collect($project['candidates'])->filter(function ($candidate) use ($selectedCandidates) {
                // Если кандидат выбран в команду и его приоритет 3, удаляем его заявку
                if (in_array($candidate['candidate_id'], $selectedCandidates) && $candidate['priority'] == 3) {
                    return false; // Удаляем заявку
                }
                return true; // Оставляем заявки с приоритетом 1 или 2, либо невыбранных кандидатов
            });
    
            // Обновляем проект с отфильтрованными кандидатами
            return [
                'project_id' => $project['project_id'],
                'places' => $project['places'],
                'candidates_count' => $filteredCandidates->count(),
                'candidates' => $filteredCandidates->values()->toArray(),                
            ];
        });

    
        // Шаг 3: Сохраняем отфильтрованные данные
        $filteredFilePath = '4_second_filtered_participation.json';
        Storage::put($filteredFilePath, json_encode($processedProjects, JSON_PRETTY_PRINT));
    
        return response()->json($processedProjects);
    }

    // шаг 5 получение лишних кандидатов
    public function collectExcessParticipations()
    {        
        $filePath = '4_second_filtered_participation.json';
        $jsonData = Storage::get($filePath);
        $data = json_decode($jsonData, true);
    
        // Получаем проекты
        $projects = collect($data);
        $excessParticipations = [];
    
        // Проходим по каждому проекту
        $filteredProjects = $projects->map(function ($project) use (&$excessParticipations) {
            $placesCount = $project['places']; // Количество мест на проекте
    
            // Разделяем кандидатов на тех, кто помещается в проект, и тех, кто не помещается
            $teamCandidates = collect($project['candidates'])->take($placesCount);
            $excessCandidates = collect($project['candidates'])->slice($placesCount);
    
            // Проходим по лишним кандидатам
            $excessCandidates->each(function ($candidate) use (&$excessParticipations, $project) {
                // Добавляем лишнюю заявку в excess_participations
                $excessParticipations[] = [
                    'candidate_id' => $candidate['candidate_id'],
                    'priority' => $candidate['priority'],
                    'project_id' => $project['project_id'],
                    'state_id' => $candidate['state_id'],
                    'created_at' => $candidate['created_at'],
                ];
            });
    
            // Возвращаем обновленный проект, где только кандидаты, попавшие в команду
            return [
                'project_id' => $project['project_id'],
                'places' => $project['places'],
                'candidates_count' => $teamCandidates->count(),
                'candidates' => $teamCandidates->toArray(),                
            ];
        });
    
        // Формируем результат с обновленными проектами и excess_participations
        $result = [
            'projects' => $filteredProjects->toArray(),
            'excess_participations' => $excessParticipations,
        ];
           
        $outputFilePath = '5_projects_with_excess_participations.json';
        Storage::put($outputFilePath, json_encode($result, JSON_PRETTY_PRINT));
    
        return response()->json([           
            'data' => $result,
        ]);
    }

    // шаг 6 удаление дубликатов из лишних кандидатов. Проверка повторяющихся кандидатов из проекта и списка лишних кандидатов
    public function cleanExcessParticipations()
    {        
        $filePath = '5_projects_with_excess_participations.json';
        $jsonData = Storage::get($filePath);
        $data = json_decode($jsonData, true);
    
        // Получаем проекты и excess_participations
        $projects = collect($data['projects']);
        $excessParticipations = collect($data['excess_participations']);
    
        // Проходим по каждому кандидату из excess_participations
        $filteredExcessParticipations = $excessParticipations->reject(function ($excessCandidate) use ($projects) {
            // Проверяем, есть ли кандидат в одной из команд проектов
            $candidateInProject = $projects->contains(function ($project) use ($excessCandidate) {
                return collect($project['candidates'])->contains('candidate_id', $excessCandidate['candidate_id']);
            });
    
            // Если кандидат найден в команде, его заявку нужно удалить из excess_participations
            return $candidateInProject;
        });
    
        
        $result = [
            'projects' => $projects->toArray(),
            'excess_participations' => $filteredExcessParticipations->toArray(),
        ];
    
        // Сохраняем результат
        $outputFilePath = '6_updated_projects_with_excess_participations.json';
        Storage::put($outputFilePath, json_encode($result, JSON_PRETTY_PRINT));
    
        return response()->json([            
            'data' => $result,
        ]);
    }

    // шаг 7 оставляем уникальные заявки лишнинх кандидатов
    public function removeDuplicateExcessParticipations()
    {        
        $filePath = '6_updated_projects_with_excess_participations.json';
        $jsonData = Storage::get($filePath);
        $data = json_decode($jsonData, true);
       
        $projects = collect($data['projects']);
        $excessParticipations = collect($data['excess_participations']);

        // Удаляем дублирующиеся заявки по candidate_id
        $uniqueExcessParticipations = $excessParticipations->unique('candidate_id')->values();

        $cleanedExcessParticipations = $uniqueExcessParticipations->map(function ($participation) {
            unset($participation['project_id']);
            $participation['priority'] = 4;
            return $participation;
        });

        // Обновляем структуру данных
        $result = [
            'projects' => $projects->toArray(),
            'excess_participations' => $cleanedExcessParticipations->toArray(),
        ];
        
        $outputFilePath = '7_cleaned_projects_with_unique_excess_participations.json';
        Storage::put($outputFilePath, json_encode($result, JSON_PRETTY_PRINT));

        return response()->json([            
            'data' => $result,
        ]);
    }

   //-------------------------------------------------------------------------------------------------------
   // шаг 8 получение студентов без заявки (молчунов)
   public function getCandidatesWithoutParticipation()
   {       
       $currentYear = now()->year;
       $currentTime = now();
          
       $filePath = '7_cleaned_projects_with_unique_excess_participations.json';
       $jsonData = Storage::get($filePath);
       $data = json_decode($jsonData, true);
   
       // Получаем проекты и excess_participations из файла
       $projects = $data['projects'];
       $excessParticipations = $data['excess_participations'];
   
       // Шаг 2: Получаем список уникальных заявок по candidate_id за сентябрь с определенным state_id
       $participations = Participation::with('project')
           ->whereYear('created_at', $currentYear)
           ->whereMonth('created_at', 9)
           ->where('state_id', 1)
           ->pluck('candidate_id')
           ->unique();
   
       // Шаг 3: Получаем всех кандидатов, которые могут отправлять заявки (can_send_participations == 1)
       $candidatesWhoCanSend = Candidate::where('can_send_participations', 1)
           ->where('course', '!=', 6)
           ->pluck('id'); // Получаем список всех candidate_id
   
       // Шаг 4: Определяем кандидатов, которые не отправляли заявки (исключаем тех, кто есть в списке заявок)
       $candidatesWithoutParticipation = $candidatesWhoCanSend->diff($participations);
         
       // Шаг 5: Формируем структуру по формату
       $withoutParticipation = $candidatesWithoutParticipation->map(function ($candidateId) use ($currentTime) {
           return [
               'candidate_id' => $candidateId,
               'priority' => 5, // Устанавливаем приоритет как 5
               'state_id' => 1, // Устанавливаем state_id как 1
               'created_at' => $currentTime->toDateTimeString(), // Текущее время
           ];
       })->values();
   
       // Шаг 6: Формируем итоговую структуру данных
       $result = [
           'projects' => $projects,
           'excess_participations' => $excessParticipations,
           'without_participation' => $withoutParticipation->toArray(),
       ];   
       
       $outputFilePath = '8_without_participation.json';
       Storage::put($outputFilePath, json_encode($result, JSON_PRETTY_PRINT));
   
       // Возвращаем результат в виде JSON
       return response()->json($result);
   }

    // шаг 9 получение кафедры для лишних студентов и без заявки 
   public function addDepartmentInfoToJson()
   {
       // Шаг 1: Чтение существующего JSON файла
       $filePath = '8_without_participation.json';
       $jsonData = json_decode(Storage::get($filePath), true);
   
       // Шаг 2: Обработка excess_participations
       if (isset($jsonData['excess_participations'])) {
           $jsonData['excess_participations'] = collect($jsonData['excess_participations'])->map(function ($participation) {
               // Получаем информацию о кандидате
               $candidate = Candidate::find($participation['candidate_id']);
               
               // Если кандидат найден, добавляем его кафедру
               if ($candidate && $candidate->getDepartment()) {
                   $participation['department'] = $candidate->getDepartment()->id;
               } else {
                   $participation['department'] = null; // Если кафедра не найдена
               }
   
               return $participation;
           })->toArray();
       }
   
       // Шаг 3: Обработка without_participation
       if (isset($jsonData['without_participation'])) {
           $jsonData['without_participation'] = collect($jsonData['without_participation'])->map(function ($participation) {
               // Получаем информацию о кандидате
               $candidate = Candidate::find($participation['candidate_id']);
               
               // Если кандидат найден, добавляем его кафедру
               if ($candidate && $candidate->getDepartment()) {
                   $participation['department'] = $candidate->getDepartment()->id;
               } else {
                   $participation['department'] = null; // Если кафедра не найдена
               }
   
               return $participation;
           })->toArray();
       }
   
       // Шаг 4: Сохранение обновленного JSON
       $outputFilePath = '9_updated_with_departments.json';
       Storage::put($outputFilePath, json_encode($jsonData, JSON_PRETTY_PRINT));
   
       // Шаг 5: Возвращаем обновленный JSON в ответе
       return response()->json($jsonData);
   }

    // шаг 10 получение кафедры для проекта
   public function addProjectDepartmentInfoToJson()
   {
       // Шаг 1: Чтение существующего JSON файла
       $filePath = '9_updated_with_departments.json';
       $jsonData = json_decode(Storage::get($filePath), true);
   
       // Шаг 2: Обработка проектов
       if (isset($jsonData['projects'])) {
           $jsonData['projects'] = collect($jsonData['projects'])->map(function ($project) {
               // Получаем проект по его project_id
               $projectModel = Project::find($project['project_id']);
               
               // Если проект найден, добавляем его кафедру
               if ($projectModel && $projectModel->department) {
                   $project['department'] = $projectModel->department->id; // Добавляем название кафедры
               } else {
                   $project['department'] = null; // Если кафедра не найдена
               }
   
               return $project;
           })->toArray();
       }
   
       // Шаг 3: Сохранение обновленного JSON
       $outputFilePath = '10_updated_projects_with_departments.json';
       Storage::put($outputFilePath, json_encode($jsonData, JSON_PRETTY_PRINT));
   
       // Шаг 4: Возвращаем обновленный JSON в ответе
       return response()->json($jsonData);
   }

   // шаг 11 получение института для проекта
   public function addInstituteInfoToProjects()
   {
       // Шаг 1: Чтение существующего JSON файла
       $filePath = '10_updated_projects_with_departments.json';
       $jsonData = json_decode(Storage::get($filePath), true);
   
       // Шаг 2: Обработка проектов
       if (isset($jsonData['projects'])) {
           $jsonData['projects'] = collect($jsonData['projects'])->map(function ($project) {
               // Проверяем, есть ли поле department с id
               if (isset($project['department']) && $project['department']) {
                   // Находим кафедру по id
                   $department = Department::find($project['department']);
   
                   // Если кафедра найдена, добавляем информацию об институте
                   if ($department && $department->institute) {
                       $project['institute'] = $department->institute->id; 
                   } else {
                       $project['institute'] = null; 
                   }
               } else {
                   $project['institute'] = null; 
               }
   
               return $project;
           })->toArray();
       }
   
       // Шаг 3: Сохранение обновленного JSON
       $outputFilePath = '11_updated_projects_with_institutes.json';
       Storage::put($outputFilePath, json_encode($jsonData, JSON_PRETTY_PRINT));
   
       // Шаг 4: Возвращаем обновленный JSON в ответе
       return response()->json($jsonData);
   }

    // шаг 12 сортировка проектов по институту и кафедре + проекты по возрастанию 
    public function groupProjectsByInstitute()
    {
        // Шаг 1: Чтение существующего JSON файла
        $filePath = '11_updated_projects_with_institutes.json';
        $jsonData = json_decode(Storage::get($filePath), true);
    
        // Шаг 2: Создаем структуру для группировки по институтам
        $groupedData = [];
    
        if (isset($jsonData['projects'])) {
            // Шаг 3: Проходим по каждому проекту
            foreach ($jsonData['projects'] as $project) {
                $institute = $project['institute'] ?? 'Unknown Institute'; // Если институт не найден, используем 'Unknown Institute'
                $department = $project['department'] ?? 'Unknown Department';
    
                // Шаг 4: Добавляем проекты в соответствующую группу по институту
                if (!isset($groupedData[$institute])) {
                    $groupedData[$institute] = []; // Инициализируем группу по институту
                }
    
                // Добавляем проект в соответствующую группу
                $groupedData[$institute][$department][] = [
                    'project_id' => $project['project_id'],
                    'places' => $project['places'],
                    'candidates_count' => $project['candidates_count'],
                    'candidates' => $project['candidates'],
                ];
            }
        }
    
        // Шаг 5: Преобразуем в нужную структуру
        $finalData = [];
        foreach ($groupedData as $institute => $departments) {
            $finalDepartments = array_map(function ($projects, $department) {
                // Сортируем проекты по количеству кандидатов (candidates_count)
                usort($projects, function ($projectA, $projectB) {
                    return $projectA['candidates_count'] <=> $projectB['candidates_count'];
                });
    
                return [
                    'department' => $department,
                    'projects' => $projects,
                ];
            }, $departments, array_keys($departments));
    
            // Добавляем отсортированные департаменты к институту
            $finalData[] = [
                'institute' => $institute,
                'departments' => $finalDepartments,
            ];
        }
    
        // Шаг 6: Добавляем разделы excess_participations и without_participation, если они существуют
        $excessParticipations = $jsonData['excess_participations'] ?? [];
        $withoutParticipation = $jsonData['without_participation'] ?? [];
    
        $finalDataWithExtras = [
            'projects' => $finalData,
            'excess_participations' => $excessParticipations,
            'without_participation' => $withoutParticipation,
        ];
    
        
        $outputFilePath = '12_grouped_projects_with_institutes_and_extras.json';
        Storage::put($outputFilePath, json_encode($finalDataWithExtras, JSON_PRETTY_PRINT));  
        return response()->json($finalDataWithExtras);
    }

    // шаг 13 добавление института для студентов
    public function addInstituteToCandidates()
    {
        // Шаг 1: Чтение существующего JSON файла
        $filePath = '12_grouped_projects_with_institutes_and_extras.json';
        $jsonData = json_decode(Storage::get($filePath), true);

        // Шаг 2: Обрабатываем excess_participations
        if (isset($jsonData['excess_participations'])) {
            foreach ($jsonData['excess_participations'] as &$excessParticipation) {
                $candidateId = $excessParticipation['candidate_id'] ?? null;

                if ($candidateId) {
                    
                    $candidate = Candidate::find($candidateId);
                    if ($candidate) {
                        $institute = $candidate->getInstitute();
                        $excessParticipation['institute'] = $institute ? $institute->id : 'Unknown Institute';
                    } else {
                        $excessParticipation['institute'] = 'Unknown Institute';
                    }
                }
            }
        }

        // Шаг 3: Обрабатываем without_participation
        if (isset($jsonData['without_participation'])) {
            foreach ($jsonData['without_participation'] as &$withoutParticipation) {
                $candidateId = $withoutParticipation['candidate_id'] ?? null;

                if ($candidateId) {
                    
                    $candidate = Candidate::find($candidateId);
                    if ($candidate) {
                        $institute = $candidate->getInstitute();
                        $withoutParticipation['institute'] = $institute ? $institute->id : 'Unknown Institute';
                    } else {
                        $withoutParticipation['institute'] = 'Unknown Institute';
                    }
                }
            }
        }

        // Шаг 4: Сохранение обновленного JSON
        $outputFilePath = '13_candidates_with_institutes.json';
        Storage::put($outputFilePath, json_encode($jsonData, JSON_PRETTY_PRINT));

        // Шаг 5: Возвращаем обновленный JSON в ответе
        return response()->json($jsonData);
    }

    // шаг 14 добавление специальностей кандидатам
    public function addSpecialitiesToCandidates()
    {        
        $filePath = '13_candidates_with_institutes.json';
        $jsonData = json_decode(Storage::get($filePath), true);
       
        // Шаг 2: Проходим по каждому кандидату из списка excess_participations
        foreach ($jsonData['excess_participations'] as &$candidate) {
            // Находим кандидата в базе данных
            $candidateModel = Candidate::find($candidate['candidate_id']);
            if ($candidateModel) {
                // Получаем специальность кандидата
                $speciality = $candidateModel->getSpeciality();
                if ($speciality) {
                    $candidate['speciality'] = [
                        'id' => $speciality->id,
                        'name' => $speciality->name,
                    ];
                } else {
                    $candidate['speciality'] = null; // Если специальность не найдена
                }
            }
        }

        // Шаг 3: Проходим по каждому кандидату из списка without_participation
        foreach ($jsonData['without_participation'] as &$candidate) {
            // Находим кандидата в базе данных
            $candidateModel = Candidate::find($candidate['candidate_id']);
            if ($candidateModel) {
                // Получаем специальность кандидата
                $speciality = $candidateModel->getSpeciality();
                if ($speciality) {
                    $candidate['speciality'] = [
                        'id' => $speciality->id,                        
                    ];
                } else {
                    $candidate['speciality'] = null; // Если специальность не найдена
                }
            }
        }

        // Шаг 4: Сохранение обновленных данных обратно в файл
        $outputFilePath = '14_candidates_with_specialities.json';
        Storage::put($outputFilePath, json_encode($jsonData, JSON_PRETTY_PRINT));

        // Шаг 5: Возвращаем обновленный JSON в ответе
        return response()->json($jsonData);
    }

    // шаг 15 добавление специальностей проекта
    public function addSpecialitiesToProjects()
    {
        // Шаг 1: Чтение существующего JSON файла с проектами
        $filePath = '14_candidates_with_specialities.json';
        $jsonData = json_decode(Storage::get($filePath), true);

        // Проверяем наличие раздела с проектами
       

            // Шаг 2: Проходим по каждому институту
        foreach ($jsonData['projects'] as &$institute) {
            // Проходим по каждому департаменту в институте
            foreach ($institute['departments'] as &$department) {
                // Проходим по каждому проекту в департаменте
                foreach ($department['projects'] as &$project) {
                    // Находим проект в базе данных по его ID
                    $projectModel = Project::find($project['project_id']);
                    
                    if ($projectModel) {
                        // Получаем специальности проекта через метод projectSpecialities()
                        $specialities = $projectModel->projectSpecialities;

                        // Добавляем специальности к проекту после project_id
                        $project['specialities'] = $specialities->map(function ($speciality) {
                            return [
                                'id' => $speciality->speciality_id,                                
                            ];
                        })->toArray();
                    } else {
                        //пустой массив специальностей
                        $project['specialities'] = [];
                    }
                }
            }
        }

        // Шаг 3: Сохранение обновленного JSON файла
        $outputFilePath = '15_projects_with_specialities.json';
        Storage::put($outputFilePath, json_encode($jsonData, JSON_PRETTY_PRINT));

        // Шаг 4: Возвращаем обновленный JSON в ответе
        return response()->json($jsonData);
    
    }

    // шаг 16 распределение лишних 
    public function distributeExcessParticipations()
    {        
        $filePath = '15_projects_with_specialities.json';
        $jsonData = json_decode(Storage::get($filePath), true);
    
        // Шаг 2: Обрабатываем список excess_participations
        foreach ($jsonData['excess_participations'] as $key => $candidate) {
            $candidateInstitute = $candidate['institute'];
            $candidateDepartment = $candidate['department'];
            $candidateSpecialityId = $candidate['speciality']['id'];
    
            // Шаг 3: Находим подходящие проекты по институту, департаменту и специальности
            $eligibleProjects = [];
    
            foreach ($jsonData['projects'] as &$institute) {
                if ($institute['institute'] == $candidateInstitute) {
                    foreach ($institute['departments'] as &$department) {
                        if ($department['department'] == $candidateDepartment) {
                            foreach ($department['projects'] as &$project) {
                                // Проверяем совпадение специальности
                                $projectSpecialities = array_column($project['specialities'], 'id');
                                if (in_array($candidateSpecialityId, $projectSpecialities)) {
                                    $eligibleProjects[] = &$project;
                                }
                            }
                        }
                    }
                }
            }
    
            // Шаг 4: Сортировка проектов по количеству кандидатов (возрастание)
            usort($eligibleProjects, function ($a, $b) {
                return $a['candidates_count'] <=> $b['candidates_count'];
            });
    
            // Шаг 5: Добавляем кандидата на первый проект с минимальным количеством кандидатов
            if (!empty($eligibleProjects)) {
                $selectedProject = &$eligibleProjects[0];
                
                // Проверяем, есть ли свободные места на проекте
                if ($selectedProject['candidates_count'] < $selectedProject['places']) {
                    $selectedProject['candidates'][] = [
                        'candidate_id' => $candidate['candidate_id'],
                        'priority' => $candidate['priority'],
                        'state_id' => 1, 
                        'created_at' => now()->toDateTimeString()
                    ];
    
                    // Увеличиваем количество кандидатов на проекте
                    $selectedProject['candidates_count']++;
    
                    // Удаляем кандидата из excess_participations
                    unset($jsonData['excess_participations'][$key]);
                }
            }
        }
    
        // Шаг 6: Сохранение обновленного JSON файла
        $outputFilePath = '16_distributed_excess_participations.json';
        Storage::put($outputFilePath, json_encode($jsonData, JSON_PRETTY_PRINT));
    
        // Возвращаем обновленный JSON в ответе
        return response()->json($jsonData);
    }
   
    // шаг 17 распределение молчунов 
    public function distributeWithoutParticipation()
    {        
        // Шаг 1: Чтение существующего JSON файла
        $filePath = '16_distributed_excess_participations.json';
        $jsonData = json_decode(Storage::get($filePath), true);

        // Шаг 2: Обрабатываем список without_participation
        foreach ($jsonData['without_participation'] as $key => $candidate) {
            // Игнорируем кандидатов без специальности
            if (is_null($candidate['speciality'])) {
                continue; // Пропускаем этого кандидата
            }

            $candidateInstitute = $candidate['institute'];
            $candidateDepartment = $candidate['department'];
            $candidateSpecialityId = $candidate['speciality']['id'];

            // Шаг 3: Находим подходящие проекты по институту, департаменту и специальности
            $eligibleProjects = [];

            foreach ($jsonData['projects'] as &$institute) {
                if ($institute['institute'] == $candidateInstitute) {
                    foreach ($institute['departments'] as &$department) {
                        if ($department['department'] == $candidateDepartment) {
                            foreach ($department['projects'] as &$project) {
                                // Проверяем совпадение специальности
                                $projectSpecialities = array_column($project['specialities'], 'id');
                                if (in_array($candidateSpecialityId, $projectSpecialities)) {
                                    $eligibleProjects[] = &$project;
                                }
                            }
                        }
                    }
                }
            }

            // Шаг 4: Сортировка проектов по количеству кандидатов (возрастание)
            usort($eligibleProjects, function ($a, $b) {
                return $a['candidates_count'] <=> $b['candidates_count'];
            });

            // Шаг 5: Добавляем кандидата на первый проект с минимальным количеством кандидатов
            if (!empty($eligibleProjects)) {
                $selectedProject = &$eligibleProjects[0];
                
                // Проверяем, есть ли свободные места на проекте
                if ($selectedProject['candidates_count'] < $selectedProject['places']) {
                    $selectedProject['candidates'][] = [
                        'candidate_id' => $candidate['candidate_id'],
                        'priority' => $candidate['priority'],
                        'state_id' => 1, 
                        'created_at' => now()->toDateTimeString()
                    ];

                    // Увеличиваем количество кандидатов на проекте
                    $selectedProject['candidates_count']++;

                    // Удаляем кандидата из without_participation
                    unset($jsonData['without_participation'][$key]);
                }
            }
        }

        // Шаг 6: Сохранение обновленного JSON файла
        $outputFilePath = '17_distributed_without_participation.json';
        Storage::put($outputFilePath, json_encode($jsonData, JSON_PRETTY_PRINT));

        // Возвращаем обновленный JSON в ответе
        return response()->json($jsonData);
    }


}
