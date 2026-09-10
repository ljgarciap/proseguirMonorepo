<?php

namespace Tests\Unit;

use App\Rules\MultipleEmails;
use PHPUnit\Framework\TestCase;

/**
 * SCRUM-347: "en las notificaciones se pueda agregar más de un correo
 * destinatario" — probado por el reportante con coma y punto y coma, con y
 * sin espacios, y ninguna combinación pasaba la regla 'email' anterior
 * (una sola dirección a la vez). Cubre el parseo y la validación por
 * separado.
 */
class MultipleEmailsTest extends TestCase
{
    public function test_parse_separa_por_coma_y_punto_y_coma_con_o_sin_espacios(): void
    {
        $this->assertEquals(
            ['a@test.com', 'b@test.com', 'c@test.com', 'd@test.com'],
            MultipleEmails::parse('a@test.com, b@test.com;c@test.com ; d@test.com')
        );
    }

    public function test_parse_descarta_entradas_vacias_por_delimitador_repetido_o_al_final(): void
    {
        $this->assertEquals(
            ['a@test.com', 'b@test.com'],
            MultipleEmails::parse('a@test.com,,b@test.com,;')
        );
    }

    public function test_parse_un_solo_correo_sigue_funcionando(): void
    {
        $this->assertEquals(['solo@test.com'], MultipleEmails::parse('solo@test.com'));
    }

    public function test_valida_lista_de_correos_validos(): void
    {
        $falló = false;
        (new MultipleEmails())->validate('correo_notificacion', 'a@test.com; b@test.com', function () use (&$falló) {
            $falló = true;
        });

        $this->assertFalse($falló);
    }

    public function test_rechaza_si_alguno_de_la_lista_es_invalido(): void
    {
        $falló = false;
        (new MultipleEmails())->validate('correo_notificacion', 'a@test.com, no-es-un-correo', function () use (&$falló) {
            $falló = true;
        });

        $this->assertTrue($falló);
    }

    public function test_rechaza_string_vacio(): void
    {
        $falló = false;
        (new MultipleEmails())->validate('correo_notificacion', '   ', function () use (&$falló) {
            $falló = true;
        });

        $this->assertTrue($falló);
    }
}
