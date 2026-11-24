<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Support\BrazilianStates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canManageSchools() || $request->user()->canManagePeople(), 403);

        return view('schools.index', [
            'schools' => School::query()
                ->when(! $request->user()->isAdministrator(), fn ($query) => $query->whereIn('id', $request->user()->manageableSchoolIds()))
                ->orderBy('name')
                ->paginate(10),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->canManageSchools(), 403);

        return view('schools.create');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageSchools(), 403);

        $data = $this->validatedData($request);
        $data['logo_path'] = $this->storeLogo($request);

        $school = School::query()->create($data);

        return redirect()->route('schools.edit', $school)
            ->with('status', __('Escola cadastrada com sucesso.'));
    }

    public function edit(Request $request, School $school): View
    {
        abort_unless($request->user()->canManageSchool($school->id), 403);

        return view('schools.edit', [
            'school' => $school,
        ]);
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($school->id), 403);

        $data = $this->validatedData($request, $school);
        $logoPath = $this->storeLogo($request, $school);

        if ($logoPath) {
            $data['logo_path'] = $logoPath;
        }

        $school->update($data);

        return redirect()->route('schools.edit', $school)
            ->with('status', __('Escola atualizada com sucesso.'));
    }

    public function destroy(Request $request, School $school): RedirectResponse
    {
        abort_unless($request->user()->canManageSchools(), 403);

        if ($school->roles()->exists()) {
            return redirect()->route('schools.index')
                ->with('status', __('Não é possível excluir uma escola que possui vínculos cadastrados. Desative a escola se ela não estiver mais em uso.'));
        }

        $school->delete();

        return redirect()->route('schools.index')
            ->with('status', __('Escola excluída com sucesso.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?School $school = null): array
    {
        $authorizationRules = ['document_authorizations' => ['sometimes', 'array:'.implode(',', array_keys(School::DOCUMENT_AUTHORIZATIONS))]];
        foreach (array_keys(School::DOCUMENT_AUTHORIZATIONS) as $kind) {
            $field = 'document_authorizations.'.$kind;
            $authorizationRules[$field] = ['nullable', 'array:act,published_on,starts_on,ends_on'];
            $authorizationRules[$field.'.act'] = ['nullable', 'required_with:'.$field.'.published_on,'.$field.'.starts_on,'.$field.'.ends_on', 'string', 'max:350'];
            $authorizationRules[$field.'.published_on'] = ['nullable', 'required_with:'.$field.'.act', 'date_format:Y-m-d', 'before_or_equal:today'];
            $authorizationRules[$field.'.starts_on'] = ['nullable', 'required_with:'.$field.'.act', 'date_format:Y-m-d'];
            $authorizationRules[$field.'.ends_on'] = ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$field.'.starts_on'];
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'cnpj' => ['required', 'string', 'max:255', Rule::unique('schools', 'cnpj')->ignore($school)],
            'inep' => ['required', 'string', 'max:255', Rule::unique('schools', 'inep')->ignore($school)],
            'founded_at' => ['required', 'date'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'maintainer' => ['nullable', 'string', 'max:2000'],
            'creation_act' => ['nullable', 'string', 'max:2000'],
            'accreditation_act' => ['nullable', 'string', 'max:2000'],
            'education_system' => ['nullable', 'string', 'max:255'],
            'registry_medium' => ['sometimes', 'required', Rule::in(array_keys(School::REGISTRY_MEDIA))],
            'letterhead_text' => ['required', 'string', 'max:5000'],
            'letterhead_text_elementary' => ['nullable', 'string', 'max:5000'],
            'letterhead_text_high_school' => ['nullable', 'string', 'max:5000'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'address' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2', Rule::in(BrazilianStates::codes())],
            'postal_code' => ['required', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ] + $authorizationRules);

        if ($request->user()->canManageSchools()) {
            $data['active'] = $request->boolean('active');
        } else {
            unset($data['active']);
        }
        unset($data['logo']);

        return $data;
    }

    private function storeLogo(Request $request, ?School $school = null): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }

        $directory = public_path('brand/schools');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if ($school?->logo_path && File::exists(public_path($school->logo_path))) {
            File::delete(public_path($school->logo_path));
        }

        $file = $request->file('logo');
        $filename = Str::uuid().'.'.$file->extension();
        $file->move($directory, $filename);

        return 'brand/schools/'.$filename;
    }
}
