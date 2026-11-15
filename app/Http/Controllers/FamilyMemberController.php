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
    public function index(): Response|RedirectResponse
    {
        $familyMembers = FamilyMember::where('user_id', Auth::id())
            ->orderBy('first_name')
            ->paginate(15);

        if ($familyMembers->currentPage() > $familyMembers->lastPage()) {
            return redirect()->route('family-members.index', ['page' => $familyMembers->lastPage()]);
        }

        return Inertia::render('FamilyMembers/Index', [
            'familyMembers' => $familyMembers,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(FamilyMemberRequest $request): RedirectResponse
    {
        $request->user()->familyMembers()->create($request->validated());

        return redirect()->route('family-members.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(FamilyMemberRequest $request, FamilyMember $familyMember): RedirectResponse
    {
        $familyMember->update($request->validated());

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FamilyMember $familyMember): RedirectResponse
    {
        $familyMember->delete();

        return redirect()->back();
    }
}
