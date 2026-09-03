<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SCRUM-317 rebote (2026-09-02): config('app.timezone') es 'UTC' — todo
 * correo que muestra fecha/hora al usuario debe convertir a hora Colombia
 * explícitamente vía Carbon::macro('bogota') (AppServiceProvider::boot()),
 * el único lugar donde vive 'America/Bogota'. Este test cubre el macro en
 * sí: si se rompe, se rompe en los ~18 correos que lo usan.
 */
class CarbonBogotaMacroTest extends TestCase
{
    public function test_bogota_converts_utc_to_colombia_time(): void
    {
        $utc = Carbon::create(2026, 9, 2, 21, 0, 0, 'UTC');

        $bogota = $utc->bogota();

        $this->assertSame('16:00', $bogota->format('H:i'));
        $this->assertSame('America/Bogota', $bogota->getTimezone()->getName());
    }

    public function test_bogota_shifts_the_calendar_day_near_midnight(): void
    {
        // 02:30 UTC == 21:30 del día anterior en Bogotá (UTC-5) — el caso
        // que rompe un correo que solo muestra fecha (sin hora) si no
        // convierte antes de formatear.
        $utc = Carbon::create(2026, 9, 3, 2, 30, 0, 'UTC');

        $this->assertSame('02/09/2026', $utc->bogota()->format('d/m/Y'));
    }

    public function test_bogota_does_not_mutate_the_original_instance(): void
    {
        $utc = Carbon::create(2026, 9, 2, 21, 0, 0, 'UTC');

        $utc->bogota();

        $this->assertSame('UTC', $utc->getTimezone()->getName());
        $this->assertSame('21:00', $utc->format('H:i'));
    }
}
