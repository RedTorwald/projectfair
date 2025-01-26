<?php

namespace App\Http\Controllers\ArmDistribution;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class GetExistenceController extends Controller
{
    public function __invoke()
    {        
        $filesToCheck = [
            '8_final.json',
            '6_manual.json',
            '5_manual.json',
            '3_updated.json', 
        ];
       
       

        foreach ($filesToCheck as $file) {
            if (Storage::exists($file)) {
                return response()->json(true);
            }
        }
        
        return response()->json(false);
    }
}