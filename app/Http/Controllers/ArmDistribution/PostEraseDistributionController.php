<?php

namespace App\Http\Controllers\ArmDistribution;


use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class PostEraseDistributionController extends Controller
{
    public function __invoke()
    {
              
        $this->deleteFiles();   
        return response()->json([
            'message' => 'Файлы успешно удалены',
        ]);
    }

    //--------------------------------------------------------------------------------------------------------------

    public function deleteFiles()
    {
        // список файлов для удаления
        $filesToDelete = ['duplicates.json', '3_updated.json', '3_1_updated.json',
                          '4_grouped_participations.json', '5_manual.json', '6_manual.json',
                          '7_log.json', '8_final.json'
                        ];

        // результат удаления
        foreach ($filesToDelete as $file) {
            if (Storage::exists($file)) {
                Storage::delete($file); // удаляем файл
            }
        }
    }

}
