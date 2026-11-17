<?php

namespace App\Http\Controllers;

use App\Http\Requests\Master\UpdateStoreProfileRequest;
use App\Models\StoreProfile;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StoreProfileController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function edit(): View
    {
        $profile = StoreProfile::current();

        return view('store-profile.edit', compact('profile'));
    }

    public function update(UpdateStoreProfileRequest $request): RedirectResponse|JsonResponse
    {
        $profile = StoreProfile::current();
        $oldValues = $profile->only([
            'name', 'address', 'phone', 'email', 'tax_id',
            'receipt_header', 'receipt_footer', 'receipt_paper_width', 'logo_path',
        ]);

        $validated = $request->validated();

        if ($request->hasFile('logo')) {
            // Delete existing logo if any
            if ($profile->logo_path && Storage::disk('public')->exists($profile->logo_path)) {
                Storage::disk('public')->delete($profile->logo_path);
            }

            $path = $request->file('logo')->store('logos', 'public');
            $validated['logo_path'] = $path;
        }

        unset($validated['logo']);

        $profile->update($validated);

        AuditLogger::log(
            action: 'store_profile.updated',
            module: 'store_settings',
            reference: $profile,
            oldValues: $oldValues,
            newValues: $profile->only([
                'name', 'address', 'phone', 'email', 'tax_id',
                'receipt_header', 'receipt_footer', 'receipt_paper_width', 'logo_path',
            ])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Profil toko & pengaturan struk berhasil diperbarui.',
                'data' => $profile,
            ]);
        }

        return redirect()->route('store-profile.edit')
            ->with('success', 'Profil toko & pengaturan struk berhasil diperbarui.');
    }
}
