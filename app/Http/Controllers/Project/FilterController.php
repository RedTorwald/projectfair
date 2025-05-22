<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\StartPageResource;
use App\Http\Services\ProjectService;
use App\Models\Project;
use App\Models\ProjectStateEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Получение проектов с фильтрацией. Скрывать данные других институтов если пользователь авторизован
 */
class FilterController extends Controller
{
    public function __construct(private ProjectService $projectService)
    {
    }

    private function stringToIntArray($string): array
    {
        if (isset($string) && is_string($string)) {
            $stringArray = json_decode($string, true);
            return array_map(function ($value) {
                return intval($value);
            }, $stringArray ?? []);
        } else {
            return [];
        }
    }

    /**
     * @OA\Get(
     *     path="/api/projects/filter",
     *     summary="Получить проекты с фильтрацией",
     *      tags={"Project"},
     *      @OA\Parameter(
     *         name="page",
     *         description="Номер страницы (отчет с нуля)",
     *          in = "query",
     *         required=false,
     *         @OA\Schema(
     *               type="integer"
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="skills",
     *         description="Массив ID скилов",
     *          in = "query",
     *         required=false,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                  type="integer"
     *                 )
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="specialties",
     *         description="Массив ID специальностей",
     *          in = "query",
     *         required=false,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                  type="integer"
     *                 )
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="type",
     *         description="Массив ID типов проекта",
     *          in = "query",
     *         required=false,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                  type="integer"
     *                 )
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="state",
     *         description="Массив ID состояний проекта",
     *          in = "query",
     *         required=false,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                  type="integer"
     *                 )
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="supervisor",
     *         description="Массив ID руководителей",
     *          in = "query",
     *         required=false,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                  type="integer"
     *                 )
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="difficulty",
     *         description="Массив с сложностями",
     *          in = "query",
     *         required=false,
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(
     *                  type="integer"
     *                 )
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="order",
     *         description="Порядок сортировки (возрастающий - asc, убывающий - desc)",
     *          in = "query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="sortBy",
     *         description="По какому полю сортировать",
     *          in = "query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="pageSize",
     *         description="Размер страницы. Позитивное число ИЛИ 'max' для получения без пагинации",
     *          in = "query",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="7 проектов на странице + навигация",
     *          content={
     *         @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                @OA\Property(
     *                property="projectCount",
     *                  type="integer",
     *                  description="Общее количество проектов",
     *                ),
     *                @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Массив проектов",
     *                 @OA\Items(ref="#/components/schemas/Project")
     *                ),
     *              )
     *          )
     *          }
     *     ),
     * )
     */
    public function __invoke(Request $request)
    {
        // $candinfo = var_dump(implode($request-> all()));
        // print($candinfo);
        $isTeacher = $request->cookie('is_teacher') === '1' || $request->cookie('is_teacher') === true;
        $isStudent = $request->cookie('is_student') === '1' || $request->cookie('is_student') === true;
        $isAuth = $isTeacher || $isStudent;

        $difficulties = $this->stringToIntArray($request->input('difficulty'));
        $title = $request->input('title') ?? '';
        $states = $this->stringToIntArray($request->input('state'));
        $publicStates = ProjectStateEnum::getPublicStatesIds();

        $allowedStudentStates = [1, 2, 4, 5];

        if ($isTeacher) {
            $states = empty($states) ? null : $states;
        } elseif ($isStudent) {
            $states = empty($states) ? $allowedStudentStates : array_intersect($allowedStudentStates, $states);
        } else {
            $states = !empty($states) ? array_intersect($publicStates, $states) : $publicStates;
        }
        
        
        $types = $this->stringToIntArray($request->input('type'));
        $dateStart = $request->input('date_start') ?? '';
        $dateEnd = $request->input('date_end') ?? '';
        $skills = $this->stringToIntArray($request->input('skills'));
        $specialities = $this->stringToIntArray($request->input('specialties'));
        $candidate = $request->get('candidate');
        $supervisors = $this->stringToIntArray($request->input('supervisors'));

        if (isset($candidate)) {
            $candidateInsitute = $candidate->getInstitute();
            if (isset($candidateInsitute)) {
                $candidateSpecialityIds = $candidateInsitute
                    ->specialities
                    ->pluck('id')->toArray();
                $specialities = array_merge($specialities, $candidateSpecialityIds);
            }
        }

        $projectCollection = $this->projectService->filter(
            difficulties: $difficulties,
            title: $title,
            typeIds: $types,
            stateIds: $states,
            dateStart: $dateStart,
            dateEnd: $dateEnd,
            skillIds: $skills,
            specialityIds: $specialities,
            supervisorIds: $supervisors,
        );
    
        $projectCollection = $isAuth
        ? $this->sortProjects($request, $projectCollection)
        : $this->randomizeProjects($projectCollection);
    
        $projectCount = count($projectCollection);
        $projectCollection = $this->paginateProjects($request, $projectCollection);
    
        return response()->json([
            'data' => StartPageResource::collection($projectCollection),
            'projectCount' => $projectCount,
            'auth_status' => [
                'is_teacher' => $isTeacher,
                'is_student' => $isStudent
            ]
        ])->setStatusCode(200);
    }

    private function randomizeProjects(Collection $projectCollection): Collection
    {
        $archivedStateId = ProjectStateEnum::arhive->value;

        $archivedProjects = $projectCollection->filter(function (Project $project) use ($archivedStateId) {
            return $project->state_id === $archivedStateId;
        });
    
        return $archivedProjects->shuffle();
    }

    private function sortProjects(Request $request, Collection $projectCollection): Collection
    {
        $sortBy = $request->get('sortBy') ?? 'state.show_priority';
        $order = $request->get('order') ?? 'desc';
        return $projectCollection->sortBy([fn (Project $a, Project $b) => $a->getState()->showPriority <= $b->getState()->showPriority, [$sortBy, $order]]);
    }

    private function paginateProjects(Request $request, Collection $projectCollection): Collection
    {
        $page = intval($request->input('page'));
        $pageSize = $request->input('pageSize') ?? 7;
        if ($pageSize == 'max') {
            return $projectCollection;
        }
        $pageSize = intval($pageSize);
        if ($pageSize <= 0) {
            $pageSize = 7;
        }
        $startIndex = $pageSize * ($page - 1);

        return $projectCollection->slice($startIndex, $pageSize);
    }
}
