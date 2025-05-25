<?php

namespace App\Http\Controllers\Admin\Project;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;

/**
 * Получить информацию о проектах
 */
class IndexController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/admin/projects/",
     *     summary="Получить информацию о всех проектах",
     *      tags={"ADMIN"},
     *     @OA\Response(
     *         response="200",
     *         description="Все проекты",
     *         @OA\JsonContent(
     *              type="array",
     *                  @OA\Items(
     *                 ref="#/components/schemas/Project"
     *         )
     * )
     *     ),
     * )
     */
    public function __invoke(Request $request)
    {
        $mentor_id = $request->get('mentor_id');
        
        $projects = Project::all();
        
        if ($mentor_id) {
            $new_projects = [];
            foreach ($projects as $project) {
                foreach ($project->supervisors as $supervisor) {
                    if ($supervisor->id == $mentor_id) {
                        array_push($new_projects, $project);
                    }
               }
           }
            return ProjectResource::collection($new_projects);
        } else if ($mentor_id == -1) {
            return ProjectResource::collection([]);
        }

        return ProjectResource::collection($projects);
    }
}
