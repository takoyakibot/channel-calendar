<?php

namespace App\Http\Controllers;

use App\Models\Group;

class CalendarController extends Controller
{
    public function index()
    {
        return view('calendar.index', ['group' => null]);
    }

    public function show(Group $group)
    {
        return view('calendar.index', ['group' => $group]);
    }
}
