<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectSpeciality;
use App\Models\Participation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class UpdateProjectStateController extends Controller
{
    public function __invoke()
    {
        $currentMonth = now()->month; // получение текущего месяца для определния типа перевода проекта (из "одобрен" в "активный" или из "активный" в "одобрен")
        $stateId = ($currentMonth == 9) ? 9 : 2; // 2 - активный, 9 - одобрен

        $projects = $this->getProjectsWithState($stateId); // получение проектов в нужном состоянии 


        return response()->json(['message' => 'Перевод состояния']);
    }


    //получение проектов и необходимых моделей с нужным состоянием (2 - активный)
    private function getProjectsWithState(int $stateId)
    {
        return Project::with(['department', 'supervisors', 'type', 'themeSource', 'projectSpecialities'])
            ->where('state_id', $stateId)
            ->get();
    }    
}