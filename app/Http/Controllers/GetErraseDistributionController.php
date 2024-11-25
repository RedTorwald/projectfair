<?php

namespace App\Http\Controllers;



use Illuminate\Support\Facades\Storage;

class GetErraseDistributionController extends Controller
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
        $filesToDelete = ['3_updated.json', '4_grouped_participations.json', '5_manual.json', '6_manual.json'];

        // результат удаления
        foreach ($filesToDelete as $file) {
            if (Storage::exists($file)) {
                Storage::delete($file); // удаляем файл
            }
        }
    }


}
