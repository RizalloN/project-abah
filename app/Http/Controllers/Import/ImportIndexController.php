<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Models\NamaReport;

class ImportIndexController extends Controller
{
    public function index()
    {
        $reports = NamaReport::where('active', 1)->get();

        return view('import.index', compact('reports'));
    }
}