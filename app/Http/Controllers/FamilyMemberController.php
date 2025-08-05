<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\FamilyMemberRequest;
use App\Models\FamilyMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class FamilyMemberController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(FamilyMember::class, 'family_member');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $familyMembers = FamilyMember::where('user_id', Auth::id())
            ->orderBy('first_name')
            ->paginate(15);

        return Inertia::render('FamilyMembers/Index', [
            'familyMembers' => $familyMembers,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('FamilyMembers/Create', [
            'genders' => ['male', 'female'],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FamilyMemberRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();

        FamilyMember::create($validated);

        return redirect()->route('family-members.index')
            ->with('success', 'Family member created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FamilyMember $familyMember): Response
    {
        return Inertia::render('FamilyMembers/Edit', [
            'familyMember' => $familyMember,
            'genders' => ['male', 'female'],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FamilyMemberRequest $request, FamilyMember $familyMember): RedirectResponse
    {
        $familyMember->update($request->validated());

        return redirect()->route('family-members.index')
            ->with('success', 'Family member updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FamilyMember $familyMember): RedirectResponse
    {
        $familyMember->delete();

        return redirect()->route('family-members.index')
            ->with('success', 'Family member deleted successfully.');
    }
}
