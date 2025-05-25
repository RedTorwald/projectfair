<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupervisorResource;
use App\Models\Supervisor;

/**
 * Контроллер для работы с преподавателями
 *
 * @OA\Tag(
 *     name="Supervisor",
 *     description="Операции с преподавателями"
 * )
 */
class IndexController extends Controller
{
    /**
     * Получение всех преподавателей
     *
     * @OA\Get(
     *     path="/api/supervisors",
     *     summary="Получить всех преподавателей",
     *     tags={"Supervisor"},
     *     @OA\Response(
     *         response=200,
     *         description="Список всех преподавателей",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Supervisor")
     *         )
     *     )
     * )
     */
    public function __invoke()
    {
        $supervisors = Supervisor::all();
        return SupervisorResource::collection($supervisors);
    }
}