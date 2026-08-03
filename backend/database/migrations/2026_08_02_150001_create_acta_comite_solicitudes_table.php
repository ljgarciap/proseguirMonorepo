<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acta_comite_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acta_comite_id')->constrained('actas_comite')->onDelete('cascade');
            $table->foreignId('credito_ordinario_id')->nullable()->constrained('credito_ordinarios')->onDelete('cascade');
            $table->string('origen'); // sistema, manual

            // Snapshot desnormalizado al momento de inclusión — el acta no
            // debe cambiar si el crédito se modifica después (SCRUM-169).
            $table->string('cliente_nombre')->nullable();
            $table->string('cliente_identificacion')->nullable();
            $table->string('tipo_solicitud')->nullable();
            $table->decimal('monto', 15, 2)->nullable();
            $table->string('amortizacion')->nullable();
            $table->integer('plazo_meses')->nullable();
            $table->decimal('tasa_interes', 8, 4)->nullable();
            $table->decimal('porcentaje_financiacion', 5, 2)->nullable();
            $table->text('garantias')->nullable();
            $table->text('fuente_pago')->nullable();

            // Decisión (pestaña Decisión y detalle de solicitudes)
            $table->string('estado_decision')->nullable(); // aprobado, rechazado, pendiente
            $table->decimal('monto_decision', 15, 2)->nullable();
            $table->string('vigencia_aprobacion')->nullable();
            $table->text('observaciones')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acta_comite_solicitudes');
    }
};
