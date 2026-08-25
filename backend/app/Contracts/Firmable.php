<?php

namespace App\Contracts;

/**
 * SCRUM-245 — Cualquier modelo que quiera soportar firma electrónica
 * implementa esto. El contrato es el punto de conexión con
 * FirmaElectronicaService: por ahora nada lo implementa (no se toca
 * ActaComite en este ticket), pero conectar un módulo nuevo se reduce a
 * implementar estos 4 métodos y registrar el slug en el allowlist del
 * FirmaElectronicaController — no hace falta tocar el servicio.
 */
interface Firmable
{
    /**
     * Identificador único del documento dentro de firmable_type — en un
     * modelo Eloquent ya lo trae Model::getKey(), se declara acá explícito
     * para que el contrato no dependa implícitamente de Eloquent.
     *
     * Sin tipo de retorno a propósito: Illuminate\Database\Eloquent\Model::getKey()
     * tampoco lo declara, y PHP exige que la firma heredada sea compatible
     * con la de la interfaz — declarar `int|string` acá rompería a
     * cualquier modelo Eloquent que implemente Firmable sin sobreescribir
     * getKey().
     */
    public function getKey();

    /**
     * Slug estable usado en la URL de la API y en el allowlist del
     * controller (ej. 'acta-comite'). Nunca se acepta el nombre de clase
     * PHP directo desde el frontend — este slug es la única forma en que
     * el cliente puede indicar "qué tipo de documento" quiere firmar.
     */
    public static function firmableSlug(): string;

    /**
     * Genera los bytes del PDF que se va a congelar y hashear. Debe
     * devolver el documento EXACTO que se le muestra al firmante antes de
     * firmar — el hash se calcula sobre este mismo string.
     */
    public function generarPdfParaFirma(): string;

    /**
     * Nombre base (sin extensión, sin timestamp) del archivo del PDF
     * congelado, ej. 'acta-045'.
     */
    public function nombreArchivoFirma(): string;

    /**
     * Slugs de users.roles autorizados a firmar este documento. Array
     * vacío = cualquier usuario autenticado puede firmar (el caso de uso
     * concreto define esta lista, FirmaElectronicaService solo la aplica).
     */
    public function rolesAutorizadosParaFirmar(): array;
}
