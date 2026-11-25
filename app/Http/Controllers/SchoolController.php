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
    private const AUTHORIZATION_MESSAGES = [
        'document_authorizations.accreditation.ends_on.prohibited' => 'O credenciamento em Mato Grosso tem prazo indeterminado.',
    ];

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

    public function editAuthorizations(Request $request, School $school): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($school->id), 403);

        return redirect()->to(route('schools.edit', $school).'#section-atos-autorizativos');
    }

    public function updateAuthorizations(Request $request, School $school): RedirectResponse
    {
        abort_unless($request->user()->canManageSchool($school->id), 403);

        $school->update($request->validate($this->authorizationRules($school->state === 'MT'), self::AUTHORIZATION_MESSAGES));

        return redirect()->to(route('schools.edit', $school).'#section-atos-autorizativos')
            ->with('status', __('Atos autorizativos atualizados.'));
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
            'letterhead_mode' => ['sometimes', 'required', Rule::in(['automatic', 'shared', 'by_stage'])],
            'letterhead_text' => ['exclude_if:letterhead_mode,automatic', 'required', 'string', 'max:5000'],
            'letterhead_text_elementary' => [Rule::excludeIf($request->has('letterhead_mode')), 'nullable', 'string', 'max:5000'],
            'letterhead_text_high_school' => ['exclude_if:letterhead_mode,automatic', 'exclude_if:letterhead_mode,shared', 'required_if:letterhead_mode,by_stage', 'nullable', 'string', 'max:5000'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'address' => ['required', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2', Rule::in(BrazilianStates::codes())],
            'postal_code' => ['required', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
        ] + $this->authorizationRules($request->input('state') === 'MT'), self::AUTHORIZATION_MESSAGES);

        if (isset($data['letterhead_mode'])) {
            if ($data['letterhead_mode'] === 'automatic') {
                $data['letterhead_text'] = null;
                $data['letterhead_text_elementary'] = null;
                $data['letterhead_text_high_school'] = null;
            } elseif ($data['letterhead_mode'] === 'by_stage' && $data['letterhead_text'] !== $data['letterhead_text_high_school']) {
                $data['letterhead_text_elementary'] = $data['letterhead_text'];
                $data['letterhead_text'] = filled($school?->letterhead_text) ? $school->letterhead_text : $data['letterhead_text_elementary'];
            } else {
                $data['letterhead_text_elementary'] = null;
                $data['letterhead_text_high_school'] = null;
            }
        }

        if ($request->user()->canManageSchools()) {
            $data['active'] = $request->boolean('active');
        } else {
            unset($data['active']);
        }
        unset($data['logo'], $data['letterhead_mode']);

        return $data;
    }

    private function authorizationRules(bool $matoGrosso): array
    {
        $rules = ['document_authorizations' => ['sometimes', 'array:'.implode(',', array_keys(School::documentAuthorizationLabels()))]];
        foreach (array_keys(School::documentAuthorizationLabels()) as $kind) {
            $field = 'document_authorizations.'.$kind;
            $fields = 'act,published_on,starts_on,ends_on,source_url';
            if (School::supportsDocumentAuthorizationRenewal($kind)) {
                $fields .= ',renewal_protocol,renewal_filed_on';
                $rules[$field.'.renewal_protocol'] = ['nullable', 'required_with:'.$field.'.renewal_filed_on', 'regex:~^[0-9]{1,12}/[0-9]{4}$~'];
                $rules[$field.'.renewal_filed_on'] = ['nullable', 'required_with:'.$field.'.renewal_protocol', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.$field.'.starts_on'];
            }
            $rules[$field] = ['nullable', 'array:'.$fields];
            $rules[$field.'.act'] = ['nullable', 'required_with:'.$field.'.published_on,'.$field.'.starts_on,'.$field.'.ends_on,'.$field.'.source_url,'.$field.'.renewal_protocol', 'string', 'max:350'];
            $rules[$field.'.published_on'] = ['nullable', 'required_with:'.$field.'.act', 'date_format:Y-m-d', 'before_or_equal:today'];
            $rules[$field.'.starts_on'] = ['nullable', 'required_with:'.$field.'.act', 'date_format:Y-m-d'];
            $rules[$field.'.source_url'] = ['nullable', 'url:https', 'max:350'];
            $rules[$field.'.ends_on'] = $matoGrosso && $kind === 'accreditation'
                ? ['prohibited', 'nullable', 'date_format:Y-m-d']
                : ['nullable', 'required_with:'.$field.'.renewal_protocol', 'date_format:Y-m-d', 'after_or_equal:'.$field.'.starts_on'];
        }

        return $rules;
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
