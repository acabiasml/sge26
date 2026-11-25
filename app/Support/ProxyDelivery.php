<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class ProxyDelivery
{
    public const DATE_LABELS = [
        'requested_on' => 'Data da solicitação',
        'authority_issued_on' => 'Data de emissão da procuração',
    ];

    public static function rules(string $deliveryDateField): array
    {
        return [
            'requested_on' => ['exclude_unless:recipient_kind,proxy', 'required', 'date_format:Y-m-d', 'before_or_equal:'.$deliveryDateField],
            'authority_issued_on' => ['exclude_unless:recipient_kind,proxy', 'required', 'date_format:Y-m-d', 'before_or_equal:requested_on'],
        ];
    }

    public static function validateTerm(array $data): void
    {
        if ($data['recipient_kind'] === 'proxy'
            && $data['authority_issued_on'] < CarbonImmutable::parse($data['requested_on'])->subMonthsNoOverflow(6)->toDateString()) {
            throw ValidationException::withMessages(['authority_issued_on' => 'A procuração deve ter sido emitida nos seis meses anteriores à solicitação.']);
        }
    }
}
