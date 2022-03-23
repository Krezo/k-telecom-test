<?php

namespace App\Http\Controllers;

use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Rules\EquipmentUnqiueSerialNumberRule;
use App\Rules\EquipmentValidSerialNumberRule;
use App\Rules\StringOrArrayRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EquipmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return EquipmentResource::collection(
            Equipment::paginate($request->get('per_page') ?: 30)->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Если флаг установлен, то ошбикбки валидации не прерывают дальнейющую обработку (валидные данные добавляются!)
        // Ошибки сохраняются в переменной $errorBug
        $withErrorBug = true;
        $errorBug = [];

        // Правила валидации для серийного номера
        $serialNumberRules = ["bail", "string", "max:20", new EquipmentUnqiueSerialNumberRule, new EquipmentValidSerialNumberRule];

        // Первичная валидация
        $equpmentValidatedData = $request->validate([
            "equipment_type_id" => ["bail", "required", "integer", "exists:equipment_types,id"],
            "serial_number" => ["bail", "required", new StringOrArrayRule],
            "remark" => ["nullable", "string"],
        ]);

        // Определяем массовое заполнение
        // serial_number массив
        if (is_array($equpmentValidatedData["serial_number"])) {
            // Проверяем перед началом заполнения весь список серийных номером на уникальность
            if (!$withErrorBug) {
                // Проверяем сразу весь массив серийных номеров на уникальность
                $request->validate([
                    "serial_number.*" => $serialNumberRules
                ]);
                // Добавляем в БД
                foreach ($equpmentValidatedData["serial_number"] as $serialNumber) {
                    Equipment::create(array_merge($equpmentValidatedData, [
                        "serial_number" => $serialNumber
                    ]));
                }
            }
            // Проверям каждый серийный номер отдельно, при ошибке сохраняем сообщение в массив $errorBug
            else {
                foreach ($equpmentValidatedData["serial_number"] as $i => $serialNumber) {
                    try {
                        $request->validate([
                            "serial_number.$i" => $serialNumberRules
                        ]);
                        // Добавляем в БД
                        Equipment::create(array_merge($equpmentValidatedData, [
                            "serial_number" => $serialNumber
                        ]));
                    } catch (ValidationException $th) {
                        if ($withErrorBug) {
                            $errorBug = array_merge($errorBug, $th->errors());
                        } else throw $th;
                    }
                }
                if (count($errorBug) > 0) throw ValidationException::withMessages($errorBug);
            }
            return;
        }
        // Если serial_number строка
        $request->validate([
            "serial_number" => $serialNumberRules
        ]);
        Equipment::create($equpmentValidatedData);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return new EquipmentResource(Equipment::findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $findedEquipment = Equipment::findOrFail($id);
        $updatedValidationData = $request->validate([
            "equipment_type_id" => ["bail", "integer", "exists:equipment_types,id"],
            "serial_number" => ["bail", "string", "max:20", (new EquipmentUnqiueSerialNumberRule)->setIngnoreId($findedEquipment->id), new EquipmentValidSerialNumberRule],
            "remark" => ["nullable", "string"]
        ]);
        $findedEquipment->update($updatedValidationData);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Equipment::findOrFail($id)->delete();
    }

    public function search(Request $request)
    {
        $equipmentSearch = Equipment::query();
        $request->validate([
            // "equipment_type_id" => ["bail","integer", "exists:equipment_types,id"],
            'serial_number' => ["bail", "string", "max:20"],
            // "remark" => ["nullable", "string"]
        ]);
        if ($searchSerialNumber = $request->get('serial_number')) {
            $equipmentSearch->where('serial_number', 'like', "%$searchSerialNumber%");
        }
        return EquipmentResource::collection(
            $equipmentSearch->paginate($request->get('per_page') ?: 30)->withQueryString()
        );
    }
}
