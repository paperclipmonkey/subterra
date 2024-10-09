<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaveRequest;
use App\Http\Requests\UpdateCaveRequest;
use App\Models\Cave;

class CaveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCaveRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Cave $cave)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cave $cave)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCaveRequest $request, Cave $cave)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cave $cave)
    {
        //
    }
}
