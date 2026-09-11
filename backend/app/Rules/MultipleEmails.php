<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * SCRUM-347: los campos de "correo de notificación" del formulario aceptan
 * un solo destinatario (regla 'email' de Laravel) — Juan Andrés probó
 * separar varios correos con coma y con punto y coma, con y sin espacios,
 * y en todos los casos la validación rechazaba el valor completo (una
 * regla 'email' sobre el string entero nunca iba a aceptar 2 direcciones
 * juntas). Esta regla acepta 1 o más direcciones separadas por coma y/o
 * punto y coma (espacios alrededor de cada una son opcionales) y valida
 * cada una por separado.
 *
 * Ver MultipleEmails::parse() para obtener el array de destinatarios ya
 * separados y validados, listo para Mail::to().
 */
class MultipleEmails implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('El campo :attribute debe ser una lista de correos válida.');
            return;
        }

        $correos = self::parse($value);

        if (empty($correos)) {
            $fail('El campo :attribute debe incluir al menos un correo.');
            return;
        }

        foreach ($correos as $correo) {
            if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
                $fail("El campo :attribute contiene un correo inválido: '{$correo}'.");
                return;
            }
        }
    }

    /**
     * Separa un string de uno o más correos (coma y/o punto y coma como
     * delimitador, espacios alrededor opcionales) en un array de
     * direcciones ya recortadas, descartando entradas vacías (delimitador
     * repetido o al final). No valida formato — usar junto con la regla de
     * arriba, que sí lo hace, antes de confiar en el resultado.
     *
     * @return array<int, string>
     */
    public static function parse(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/[,;]+/', $value) ?: []),
            fn (string $correo) => $correo !== ''
        ));
    }
}
