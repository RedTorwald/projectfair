<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 *  Подготавливает данные для апи
 */
class SupervisorResource extends JsonResource
{
    /**
     * Подготавливает данные для апи
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'fio' => $this->fio,
            'email' => $this->email,
            'about' => $this->about,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

/**
 * @OA\Schema()
 */
class Supervisor extends SupervisorResource
{
    /**
     * ID Преподавателя
     * @var int
     * @OA\Property()
     */
    public $id;
    /**
     * ФИО
     * @var string
     * @OA\Property()
     */
    public $fio;
    /**
     * Почта
     * @var string
     * @OA\Property()
     */
    public $email;
    /**
     * Информация о преподавателе
     * @var string
     * @OA\Property()
     */
    public $about;
    /**
     * Должность
     * @var string
     * @OA\Property()
     */
    public $position;

    /**
     * Дата создания записи в БД
     * @var string
     * @OA\Property()
     */
    public $created_at;
    /**
     * Дата обновления записи в БД
     * @var string
     * @OA\Property()
     */
    public $updated_at;
}