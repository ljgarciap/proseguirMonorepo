<?php

namespace App\Services;

use App\Models\DocumentRequestItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * SCRUM-256 (comentario Juan Andrés Ramírez, 2026-08-26, sobre el fix
 * original del ticket): los guards existentes (puedeSubirDocumento() en el
 * frontend, el 422 de re-carga en CreditoOrdinarioController::transition())
 * solo evitan volver a cargar un archivo en EL MISMO documento ya
 * satisfecho — ninguno evita que el cliente use el mismo archivo físico
 * para satisfacer 2+ documentos DISTINTOS del expediente (ej.
 * "Compraventa.pdf" cargado como RUT Y como Documento de identidad, cada
 * uno queda "Cargado (1)" individualmente válido). Se compara por hash de
 * contenido, no por nombre, porque el cliente puede renombrar el archivo
 * antes de subirlo.
 */
class DuplicateDocumentGuard
{
    /**
     * Devuelve un mensaje de error si $archivo ya fue cargado como otro
     * documento del mismo DocumentRequest, o null si no hay duplicado.
     */
    public function archivoDuplicadoEnOtroDocumento(DocumentRequestItem $item, UploadedFile $archivo): ?string
    {
        $request = $item->request;
        if (!$request) {
            return null;
        }

        $hashNuevo = @hash_file('sha256', $archivo->getRealPath());
        if ($hashNuevo === false) {
            return null;
        }

        $otros = $request->items()->with(['upload', 'requirement'])
            ->where('id', '!=', $item->id)
            ->get();

        foreach ($otros as $otro) {
            $upload = $otro->upload;
            if (!$upload || !$upload->filename) {
                continue;
            }

            $hashExistente = $this->hashDeUpload($upload->filename);
            if ($hashExistente !== null && $hashExistente === $hashNuevo) {
                $nombreOtro = $otro->requirement->nombre ?? 'otro documento';
                return "Este archivo ya fue cargado como \"{$nombreOtro}\". Cada documento requiere un archivo distinto.";
            }
        }

        return null;
    }

    // Mismo fallback de disco que ClientUploadController::download() (nota
    // SCRUM-146 ahí): los archivos sincronizados desde Crédito Ordinario
    // quedan en el disco 'public', el resto de cargas de cliente en el
    // disco por defecto.
    private function hashDeUpload(string $filename): ?string
    {
        if (Storage::exists($filename)) {
            $hash = hash_file('sha256', Storage::path($filename));
            return $hash ?: null;
        }
        if (Storage::disk('public')->exists($filename)) {
            $hash = hash_file('sha256', Storage::disk('public')->path($filename));
            return $hash ?: null;
        }
        return null;
    }
}
