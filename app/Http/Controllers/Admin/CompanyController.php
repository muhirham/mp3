<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('admin.masterdata.companies', compact('companies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'legal_name'     => 'nullable|string|max:255',
            'short_name'     => 'nullable|string|max:50',
            'code'           => 'nullable|string|max:20',
            'address'        => 'nullable|string',
            'city'           => 'nullable|string|max:100',
            'province'       => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            'website'        => 'nullable|string|max:255',
            'tax_number'     => 'nullable|string|max:100',
            'logo'           => 'nullable|image|max:2048',
            'logo_small'     => 'nullable|image|max:1024',
            'is_default'     => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($request, $data) {
            // handle logo
            if ($request->hasFile('logo')) {
                $data['logo_path'] = $request->file('logo')->store('companies', 'public');
            }

            if ($request->hasFile('logo_small')) {
                $data['logo_small_path'] = $request->file('logo_small')->store('companies', 'public');
            }

            $data['is_active']  = $request->boolean('is_active', true);
            $isDefaultRequested = $request->boolean('is_default', false);

            if ($isDefaultRequested) {
                Company::query()->update(['is_default' => false]);
                $data['is_default'] = true;
            } else {
                $data['is_default'] = false;
            }

            Company::create($data);
        });

        return redirect()
            ->route('companies.index')
            ->with('success', 'Company berhasil ditambahkan.');
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'legal_name'     => 'nullable|string|max:255',
            'short_name'     => 'nullable|string|max:50',
            'code'           => 'nullable|string|max:20',
            'address'        => 'nullable|string',
            'city'           => 'nullable|string|max:100',
            'province'       => 'nullable|string|max:100',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            'website'        => 'nullable|string|max:255',
            'tax_number'     => 'nullable|string|max:100',
            'logo'           => 'nullable|image|max:2048',
            'logo_small'     => 'nullable|image|max:1024',
            'is_default'     => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($request, $data, $company) {
            // logo utama
            if ($request->hasFile('logo')) {
                if ($company->logo_path) {
                    Storage::disk('public')->delete($company->logo_path);
                }
                $data['logo_path'] = $request->file('logo')->store('companies', 'public');
            }

            // logo kecil
            if ($request->hasFile('logo_small')) {
                if ($company->logo_small_path) {
                    Storage::disk('public')->delete($company->logo_small_path);
                }
                $data['logo_small_path'] = $request->file('logo_small')->store('companies', 'public');
            }

            $data['is_active']  = $request->boolean('is_active', true);
            $isDefaultRequested = $request->boolean('is_default', false);

            if ($isDefaultRequested) {
                Company::where('id', '!=', $company->id)->update(['is_default' => false]);
                $data['is_default'] = true;
            } else {
                // kalau user uncheck dan sebelumnya default, ya jadi bukan default
                if ($company->is_default && !$isDefaultRequested) {
                    $data['is_default'] = false;
                }
            }

            $company->update($data);
        });

        return redirect()
            ->route('companies.index')
            ->with('success', 'Company berhasil diupdate.');
    }

    public function destroy(Company $company)
    {
        // soft delete aja
        $company->delete();

        return redirect()
            ->route('companies.index')
            ->with('success', 'Company berhasil dihapus.');
    }
}
