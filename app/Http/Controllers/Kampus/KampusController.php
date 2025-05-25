<?php

namespace App\Http\Controllers\Kampus;

use App\Http\Controllers\Controller;
use App\Http\Services\HarvestSettingService;
use App\Http\Services\ProjectService;
use App\Models\Project;
use App\Models\ProjectStateEnum;

/**
 * Данные для кампуса
 */
class KampusController extends Controller
{
    public function __construct(private ProjectService $projectService)
    {
    }
  
    public function __invoke()
    {
        $arhiveProjects = $this->projectService->filter(stateIds: [ProjectStateEnum::arhive->value]);
        $arhiveProjects = $arhiveProjects->map(function (Project $project) {
            $project->participants = $project->getParticipants();
            return $project;
        });

        return $arhiveProjects;
    }
}
