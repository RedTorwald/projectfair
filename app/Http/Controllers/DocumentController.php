<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Project;

class DocumentController extends Controller
{
    
    public function __invoke()
    {   
        
        $filePath = 'documents.json'; // имя документа с project_review
        
        if (!Storage::exists($filePath)) {
            return response()->json(['error' => 'Файл не найден'], 404);
        }
        
        $jsonData = json_decode(Storage::get($filePath), true);
              
        $projectsData = array_map(function ($item) {
            return [
                'id' => $item['id'], 
                'project_review' => $item['review'], 
            ];}, $jsonData);

       
        foreach ($projectsData as $project) {
            Project::updateOrCreate(
                ['id' => $project['id']], 
                ['project_review' => $project['project_review']] 
            );
        }

        return response()->json(['message' => 'Данные успешно импортированы'], 200);
    }
}