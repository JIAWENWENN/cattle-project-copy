<?php

namespace App\Http\Controllers;

use App\Models\VeterinaryContact;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VeterinaryContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Health/VeterinaryContact', [
            'contacts' => VeterinaryContact::latest()->get()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Health/ContactCreate');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->normalizeContactFields($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:veterinarian,clinic,supplier',
            'position' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+?60|0)(?:1\d{8,9}|[3-9]\d{7,8})$/'],
            'alt_phone' => ['nullable', 'string', 'max:20', 'regex:/^(?:\+?60|0)(?:1\d{8,9}|[3-9]\d{7,8})$/'],
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'availability' => 'nullable|string|max:255',
            'emergency' => 'boolean',
            'notes' => 'nullable|string',
            'profile_photo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('contact-photos', 'public');
            $validated['photo_path'] = $path;
        }

        VeterinaryContact::create($validated);

        return redirect()->route('health.contact.index')
            ->with('success', 'Contact created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $contact = VeterinaryContact::findOrFail($id);
        return Inertia::render('Health/ContactShow', [
            'contact' => $contact
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $contact = VeterinaryContact::findOrFail($id);
        return Inertia::render('Health/ContactEdit', [
            'contact' => $contact
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $contact = VeterinaryContact::findOrFail($id);

        $this->normalizeContactFields($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:veterinarian,clinic,supplier',
            'position' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+?60|0)(?:1\d{8,9}|[3-9]\d{7,8})$/'],
            'alt_phone' => ['nullable', 'string', 'max:20', 'regex:/^(?:\+?60|0)(?:1\d{8,9}|[3-9]\d{7,8})$/'],
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'availability' => 'nullable|string|max:255',
            'emergency' => 'boolean',
            'notes' => 'nullable|string',
            'profile_photo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($contact->photo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($contact->photo_path);
            }
            $path = $request->file('profile_photo')->store('contact-photos', 'public');
            $validated['photo_path'] = $path;
        }

        $contact->update($validated);

        return redirect()->route('health.contact.index')
            ->with('success', 'Contact updated successfully.');
    }

    private function normalizeContactFields(Request $request): void
    {
        $request->merge([
            'phone' => preg_replace('/[\s-]+/', '', (string) $request->input('phone', '')),
            'alt_phone' => preg_replace('/[\s-]+/', '', (string) $request->input('alt_phone', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $contact = VeterinaryContact::findOrFail($id);
        $contact->delete();

        return redirect()->route('health.contact.index')
            ->with('success', 'Contact deleted successfully.');
    }
}
