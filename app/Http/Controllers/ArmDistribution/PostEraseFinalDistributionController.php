<?php

namespace App\Http\Controllers\ArmDistribution;


use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class PostEraseFinalDistributionController extends Controller
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
        $filesToDelete = ['6_final_distribution'];

        // результат удаления
        foreach ($filesToDelete as $file) {
            if (Storage::exists($file)) {
                Storage::delete($file); // удаляем файл
            }
        }
    }

}
