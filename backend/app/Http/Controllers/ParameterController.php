<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ParameterController extends Controller
{
    protected $allowedTables = [
        'sectores' => \App\Models\Sector::class,
        'document_types' => \App\Models\DocumentType::class,
        'accounting_categories' => \App\Models\AccountingCategory::class,
        'accounting_priorities' => \App\Models\AccountingPriority::class,
    ];

    public function index($table)
    {
        if (!isset($this->allowedTables[$table])) {
            return response()->json(['message' => 'Tabla no permitida'], 400);
        }

        $model = $this->allowedTables[$table];
        return response()->json($model::all());
    }

    public function store(Request $request, $table)
    {
        if (!isset($this->allowedTables[$table])) {
            return response()->json(['message' => 'Tabla no permitida'], 400);
        }

        $model = $this->allowedTables[$table];
        $columns = Schema::getColumnListing($table);
        $data = $request->only($columns);

        $record = $model::create($data);
        return response()->json($record, 201);
    }

    public function update(Request $request, $table, $id)
    {
        if (!isset($this->allowedTables[$table])) {
            return response()->json(['message' => 'Tabla no permitida'], 400);
        }

        $model = $this->allowedTables[$table];
        $record = $model::findOrFail($id);
        
        $columns = Schema::getColumnListing($table);
        $data = $request->only($columns);

        $record->update($data);
        return response()->json($record);
    }

    public function destroy($table, $id)
    {
        if (!isset($this->allowedTables[$table])) {
            return response()->json(['message' => 'Tabla no permitida'], 400);
        }

        $model = $this->allowedTables[$table];
        $record = $model::findOrFail($id);
        $record->delete();

        return response()->json(['message' => 'Registro eliminado']);
    }
}
