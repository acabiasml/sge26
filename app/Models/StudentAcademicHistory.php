<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use App\Support\HistoryFormationWorkloads;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAcademicHistory extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    public function scopeForIssuance(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_unified'), true)
            ->where($this->qualifyColumn('active'), true)
            ->whereIn($this->qualifyColumn('education_stage'), [
                AcademicCourse::STAGE_ELEMENTARY,
                AcademicCourse::STAGE_HIGH_SCHOOL,
                AcademicCourse::STAGE_TECHNICAL,
            ])->where(function (Builder $q): void {
                $q->where('education_stage', '!=', AcademicCourse::STAGE_TECHNICAL)
                    ->orWhere(function (Builder $technical): void {
                        $technical->whereNotNull('technical_course_id')
                            ->whereExists(function ($enrolled): void {
                                $enrolled->selectRaw('1')->from('student_enrollments as technical_students')
                                    ->join('academic_course_student_enrollment as enrolled_courses', 'enrolled_courses.student_enrollment_id', '=', 'technical_students.id')
                                    ->where('technical_students.status', '!=', StudentEnrollment::STATUS_CANCELLED)
                                    ->whereColumn('technical_students.person_id', 'student_academic_histories.person_id')
                                    ->whereColumn('enrolled_courses.academic_course_id', 'student_academic_histories.technical_course_id');
                            })
                            ->whereHas('technicalCourse', fn ($course) => $course->where('stage', AcademicCourse::STAGE_TECHNICAL))
                            ->whereNotExists(function ($integrated): void {
                                $integrated->selectRaw('1')->from('student_enrollments as integrated_students')
                                    ->join('academic_course_student_enrollment as technical_courses', 'technical_courses.student_enrollment_id', '=', 'integrated_students.id')
                                    ->join('academic_course_student_enrollment as regular_courses', 'regular_courses.student_enrollment_id', '=', 'integrated_students.id')
                                    ->join('academic_courses as regular_matrix', 'regular_matrix.id', '=', 'regular_courses.academic_course_id')
                                    ->where('regular_matrix.stage', AcademicCourse::STAGE_HIGH_SCHOOL)
                                    ->whereColumn('integrated_students.person_id', 'student_academic_histories.person_id')
                                    ->whereColumn('technical_courses.academic_course_id', 'student_academic_histories.technical_course_id');
                            })
                            ->whereNotExists(function ($links): void {
                                $links->selectRaw('1')->from('student_enrollments as itinerary_links')
                                    ->join('academic_course_student_enrollment as itinerary_enrollment_courses', 'itinerary_enrollment_courses.student_enrollment_id', '=', 'itinerary_links.id')
                                    ->join('academic_courses as itinerary_regular_matrix', 'itinerary_regular_matrix.id', '=', 'itinerary_enrollment_courses.academic_course_id')
                                    ->join('academic_course_technical_itinerary as matrix_itineraries', 'matrix_itineraries.academic_course_id', '=', 'itinerary_regular_matrix.id')
                                    ->where('itinerary_regular_matrix.stage', AcademicCourse::STAGE_HIGH_SCHOOL)
                                    ->whereColumn('itinerary_links.person_id', 'student_academic_histories.person_id')
                                    ->whereColumn('matrix_itineraries.technical_course_id', 'student_academic_histories.technical_course_id');
                            });
                    });
            });
    }

    protected $fillable = [
        'person_id',
        'school_id',
        'created_by_person_id',
        'updated_by_person_id',
        'title',
        'stage',
        'legal_basis',
        'notes',
        'issued_place',
        'issued_date',
        'active',
        'is_unified',
        'education_stage',
        'technical_course_id',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'active' => 'boolean',
            'is_unified' => 'boolean',
            'technical_course_id' => 'integer',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'title',
            'stage',
            'issued_place',
        ];
    }

    /** @return array<string, array{planned: ?float, completed: ?float}> */
    public function formationWorkloadTotals(?StudentAcademicHistoryYear $selectedYear = null): array
    {
        return HistoryFormationWorkloads::totals($this, $selectedYear);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function technicalCourse(): BelongsTo
    {
        return $this->belongsTo(AcademicCourse::class, 'technical_course_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'updated_by_person_id');
    }

    public function years(): HasMany
    {
        return $this->hasMany(StudentAcademicHistoryYear::class)->orderBy('position');
    }

    public function components(): HasMany
    {
        return $this->hasMany(StudentAcademicHistoryComponent::class)->orderBy('position');
    }
}
