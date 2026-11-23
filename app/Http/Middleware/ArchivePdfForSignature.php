<?php

namespace App\Http\Middleware;

use App\Models\IssuedDocument;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ArchivePdfForSignature
{
    public const CAPTURE = 'beaba_signature_pdf';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->input('signature_mode') !== 'digital' || ! $request->user()) {
            return $next($request);
        }

        $request->attributes->set(self::CAPTURE, []);
        $path = null;
        try {
            return DB::transaction(function () use ($request, $next, &$path) {
                $response = $next($request);
                if (! $response->isSuccessful() || ! str_starts_with($response->headers->get('Content-Type', ''), 'application/pdf')) {
                    return $response;
                }
                $ids = $request->attributes->get(self::CAPTURE);
                if (count($ids) !== 1 || ! is_string($response->getContent())) {
                    return $response;
                }
                $document = IssuedDocument::whereKey($ids[0])->where('issued_by_user_id', $request->user()->id)
                    ->whereNotIn('type', IssuedDocument::CREDENTIAL_TYPES)->first();
                if (! $document || ($document->payload['is_draft'] ?? false)) {
                    return $response;
                }
                $bytes = $response->getContent();
                $digest = hash('sha256', $bytes);
                if ($document->file_path) {
                    $document->update(['file_sha256' => $digest]);
                } else {
                    $path = 'document-signatures/'.$document->uuid.'/original.pdf';
                    throw_unless(Storage::disk('local')->put($path, $bytes), \RuntimeException::class, 'Não foi possível arquivar o original.');
                    chmod(Storage::disk('local')->path($path), 0600);
                    $document->update(['file_path' => $path, 'file_sha256' => $digest]);
                }

                return redirect()->route('document-signatures.show', $document);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }
}
