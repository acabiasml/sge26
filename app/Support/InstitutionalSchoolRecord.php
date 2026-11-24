<?php

namespace App\Support;

use App\Models\CredentialBook;
use App\Models\IssuedDocument;
use App\Models\School;
use Barryvdh\DomPDF\Facade\Pdf;

final class InstitutionalSchoolRecord
{
    public const DOCUMENT_TYPES = [
        'credential-delivery' => 'delivery',
        'school-visit' => 'visit',
        'administrative-meeting' => 'administrative',
        'pedagogical-meeting' => 'pedagogical',
        'document-disposal' => 'disposal',
        'school-inventory' => 'inventory',
        'school-correspondence' => 'correspondence',
    ];

    public const CERTIFICATE_BOOKS = ['certificate', 'diploma', 'high-school'];

    public const RECIPIENTS = ['student' => 'Titular', 'guardian' => 'Responsável legal', 'proxy' => 'Procurador'];

    public const DELIVERY_METHODS = ['physical' => 'Física', 'digital' => 'Digital'];

    private const MEETING_FIELDS = [
        'subject' => ['Pauta ou identificação da reunião', 'text'],
        'place' => ['Local ou meio de realização', 'text'],
        'participants' => ['Participantes e funções', 'textarea'],
        'decisions' => ['Deliberações e encaminhamentos', 'textarea'],
    ];

    public static function definitions(): array
    {
        return [
            'delivery' => [
                'notice' => 'Registre a entrega e preserve o protocolo assinado pelo recebedor. Use o registro de origem quando o documento já foi expedido pelo Beabá.',
                'attachment' => 'Protocolo assinado de recebimento',
                'required' => true,
                'fields' => [
                    'subject' => ['Documento, titular e referência de registro', 'text'],
                    'recipient' => ['Nome completo de quem recebeu', 'text'],
                    'recipient_kind' => ['Qualificação do recebedor', self::RECIPIENTS],
                    'method' => ['Forma de entrega', self::DELIVERY_METHODS],
                ],
            ],
            'visit' => [
                'notice' => 'Lance o termo de visita de inspeção, supervisão ou acompanhamento e arquive o original assinado.',
                'attachment' => 'Termo de visita assinado',
                'required' => true,
                'fields' => [
                    'subject' => ['Finalidade da visita', 'text'],
                    'visitor' => ['Visitante e função', 'text'],
                    'institution' => ['Órgão ou instituição de origem', 'text'],
                    'school_representative' => ['Responsável pelo acompanhamento na escola', 'text'],
                    'findings' => ['Observações, orientações e prazos', 'textarea'],
                ],
            ],
            'administrative' => [
                'notice' => 'Registre a reunião administrativa e arquive a ata assinada.',
                'attachment' => 'Ata administrativa assinada',
                'required' => true,
                'fields' => self::MEETING_FIELDS,
            ],
            'pedagogical' => [
                'notice' => 'Registre a reunião pedagógica e arquive a ata assinada.',
                'attachment' => 'Ata pedagógica assinada',
                'required' => true,
                'fields' => self::MEETING_FIELDS + [
                    'scope' => ['Etapa, turma, equipe ou projeto pedagógico', 'text'],
                ],
            ],
            'disposal' => [
                'notice' => 'Use este livro somente para registrar eliminações já autorizadas. Informe a destinação prevista na tabela de temporalidade. O lançamento serve como comprovante e preserva o acervo do Beabá.',
                'attachment' => 'Termo de eliminação assinado e documentos de autorização',
                'required' => true,
                'fields' => [
                    'subject' => ['Série ou tipo documental e período abrangido', 'text'],
                    'volume' => ['Quantidade e unidade de medida', 'text'],
                    'retention' => ['Tabela de temporalidade, código de classificação e prazo cumprido', 'textarea'],
                    'authorization' => ['Ato de autorização e responsável pela decisão', 'textarea'],
                    'method' => ['Método de eliminação e responsáveis pela execução', 'textarea'],
                ],
            ],
            'inventory' => [
                'notice' => 'Registre a incorporação, movimentação ou baixa patrimonial. Use o número do tombo para consultar a trajetória do bem.',
                'attachment' => 'Comprovante patrimonial, quando disponível',
                'required' => false,
                'fields' => [
                    'subject' => ['Número do tombo ou identificação patrimonial', 'text'],
                    'movement' => ['Movimentação', ['addition' => 'Incorporação', 'transfer' => 'Transferência', 'withdrawal' => 'Baixa']],
                    'asset' => ['Descrição do bem', 'text'],
                    'location' => ['Origem e local de guarda ou destino', 'text'],
                    'custodian' => ['Responsável pela guarda ou movimentação', 'text'],
                    'reference' => ['Referência da nota, termo ou ato patrimonial', 'text'],
                ],
            ],
            'correspondence' => [
                'notice' => 'Registre a remessa ou o recebimento de documentos institucionais. Preserve o recibo, comprovante ou referência do protocolo externo.',
                'attachment' => 'Documento ou comprovante de remessa / recebimento',
                'required' => false,
                'fields' => [
                    'subject' => ['Assunto e identificação do documento', 'text'],
                    'direction' => ['Movimento', ['outgoing' => 'Remessa', 'incoming' => 'Recebimento']],
                    'sender' => ['Remetente', 'text'],
                    'recipient' => ['Destinatário', 'text'],
                    'reference' => ['Meio, rastreio ou número do protocolo de origem', 'text'],
                ],
            ],
        ];
    }

    public static function title(string $kind): string
    {
        return CredentialBook::KINDS[$kind];
    }

    public static function displayFields(array $fields, array $values): array
    {
        $details = [];
        foreach ($fields as $key => [$label, $input]) {
            $details[$label] = is_array($input) ? $input[$values[$key]] : $values[$key];
        }

        return $details;
    }

    public static function pdf(School $school, IssuedDocument $document): string
    {
        return Pdf::loadView('reports.institutional-school-record', compact('school', 'document'))
            ->setPaper('a4', 'portrait')->output();
    }
}
