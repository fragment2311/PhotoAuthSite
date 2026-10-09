<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\ChangeHistoryService;
use Illuminate\Http\Request;

class LocationsController extends Controller
{
    public function index()
    {
        $locations = Location::query()
            ->orderBy('LocationName')
            ->get();

        return view('admin.locations.index', [
            'locations' => $locations,
        ]);
    }

    public function create()
    {
        return view('admin.locations.form', [
            'location' => null,
            'lastChange' => null,
            'pageTitle' => 'Создать локацию',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'LocationName' => ['required', 'string', 'max:150', 'unique:location,LocationName'],
            'LocationDate' => ['required', 'date'],
        ], [
            'LocationName.required' => 'Название локации обязательно для заполнения.',
            'LocationName.unique' => 'Локация с таким названием уже существует.',
            'LocationName.max' => 'Название локации не должно превышать 150 символов.',
            'LocationDate.required' => 'Дата обязательна для заполнения.',
            'LocationDate.date' => 'Дата должна быть корректной.',
        ]);

        Location::query()->create([
            'LocationName' => trim($data['LocationName']),
            'LocationDate' => $data['LocationDate'] ?? now()->toDateString(),
        ]);

        return redirect()
            ->route('admin.locations.index')
            ->with('status', 'Локация успешно создана.');
    }

    public function edit(Location $location)
    {
        $lastChange = app(ChangeHistoryService::class)
            ->latestFor(class_basename($location), $location->getKey());

        return view('admin.locations.form', [
            'location' => $location,
            'lastChange' => $lastChange,
            'pageTitle' => 'Редактировать локацию',
        ]);
    }

    public function update(Request $request, Location $location)
    {
        $data = $request->validate([
            'LocationName' => [
                'required',
                'string',
                'max:150',
                'unique:location,LocationName,' . $location->IdLocation . ',IdLocation',
            ],
            'LocationDate' => ['required', 'date'],
        ], [
            'LocationName.required' => 'Название локации обязательно для заполнения.',
            'LocationName.unique' => 'Локация с таким названием уже существует.',
            'LocationName.max' => 'Название локации не должно превышать 150 символов.',
            'LocationDate.required' => 'Дата обязательна для заполнения.',
            'LocationDate.date' => 'Дата должна быть корректной.',
        ]);

        $location->LocationName = trim($data['LocationName']);
        $location->LocationDate = $data['LocationDate'] ?? now()->toDateString();
        $location->save();

        return redirect()
            ->route('admin.locations.index')
            ->with('status', 'Локация успешно обновлена.');
    }
}
