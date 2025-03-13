<?php

namespace App\Http\Controllers\ArmDistribution;

use App\Http\Controllers\Controller;
use App\Http\Services\CandidateDistributionService;
use Illuminate\Support\Facades\Storage;

class GetFinalDistributionController extends Controller
{
    protected $candidateDistributionService;

    public function __construct(CandidateDistributionService $candidateDistributionService)
    {
        $this->candidateDistributionService = $candidateDistributionService;
    }


    public function __invoke()
    {
        // отключение лимита по запросу (можно настроить на сервере)
        set_time_limit(0);

     
        $filteredFilePath = Storage::exists('3_updated.json') 
        ? '3_updated.json' 
        : '2_distribution.json';

        $jsonData = Storage::get($filteredFilePath); 
        $filteredParticipations = json_decode($jsonData, true); 

        // респонс
        return response()->json($filteredParticipations);
    }

}
