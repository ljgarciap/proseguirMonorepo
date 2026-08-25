<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SCRUM-245 — Firma Electrónica interna (Ley 527 de 1999, arts. 7-8: firma
 * electrónica simple, no firma digital certificada).
 *
 * Tabla polimórfica (firmable_type/firmable_id) para poder firmar cualquier
 * documento del ERP (Actas de Comité, Informe Técnico, Análisis Financiero,
 * ...) sin volver a tocar el esquema — ver spec en SCRUM-245.
 *
 * Append-only por diseño: sin `updated_at` (ver FirmaElectronica::UPDATED_AT
 * = null), sin update/delete expuestos en el modelo, y acá además un
 * trigger MySQL que rechaza UPDATE/DELETE a nivel de motor — defensa en
 * profundidad, porque el valor legal de esta tabla depende de que sea
 * verdaderamente inmutable, no solo de que la app de hoy no la edite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firmas_electronicas', function (Blueprint $table) {
            $table->id();

            $table->string('firmable_type');
            $table->unsignedBigInteger('firmable_id');

            $table->foreignId('usuario_id')->constrained('users');

            // Snapshot de identidad en el momento de firmar — nunca se lee
            // desde users en el momento de mostrar la firma, para que un
            // cambio posterior de nombre/rol/cédula no reescriba la
            // historia (mismo criterio que 'nombre_firmante' vs FK sola
            // en la propuesta original).
            $table->string('nombre_firmante', 150);
            $table->string('numero_documento_firmante', 30);
            $table->string('rol_firmante', 100);

            $table->string('metodo_validacion', 30);
            $table->string('direccion_ip', 45);
            $table->string('user_agent', 255)->nullable();

            $table->string('documento_path', 500);
            $table->char('documento_hash_sha256', 64);
            $table->string('hash_algoritmo', 20)->default('sha256');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['firmable_type', 'firmable_id']);
        });

        // Defensa en profundidad: ninguna conexión (incluida una consola
        // artisan tinker de emergencia) puede UPDATE/DELETE sobre esta
        // tabla. Una revocación real es un problema legal/operativo que
        // requiere intervención manual de un DBA fuera de la app — no un
        // caso que la aplicación deba resolver con un simple update.
        //
        // Solo MySQL (test/prod) — mismo criterio que la migración
        // 2026_06_14_210000_add_visto_bueno_to_internal_documents_status.php,
        // que ya guarda SQL específico de motor detrás de
        // DB::getDriverName() === 'mysql'. Los tests locales corren sobre
        // SQLite (ver phpunit.xml), así que el trigger no existe ahí — los
        // tests que verifican este comportamiento (ver
        // FirmaElectronicaServiceTest::test_firmas_electronicas_es_append_only_*)
        // se saltan explícitamente fuera de MySQL en vez de dar un falso
        // verde.
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER firmas_electronicas_no_update
                BEFORE UPDATE ON firmas_electronicas
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'firmas_electronicas es append-only: no se permite UPDATE';
                END
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER firmas_electronicas_no_delete
                BEFORE DELETE ON firmas_electronicas
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'firmas_electronicas es append-only: no se permite DELETE';
                END
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS firmas_electronicas_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS firmas_electronicas_no_delete');
        }
        Schema::dropIfExists('firmas_electronicas');
    }
};
