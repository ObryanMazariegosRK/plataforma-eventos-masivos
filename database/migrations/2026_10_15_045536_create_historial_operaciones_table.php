
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_operaciones', function (Blueprint $table) {

            $table->id();

            // Tipo de operación realizada.
            $table->string('tipo', 50);

            // Usuario afectado por la operación.
            $table->unsignedBigInteger('usuario_id')->nullable();

            // Registros relacionados.
            $table->unsignedBigInteger('evento_id')->nullable();
            $table->unsignedBigInteger('reserva_id')->nullable();
            $table->unsignedBigInteger('inventario_id')->nullable();
            $table->unsignedBigInteger('pago_id')->nullable();

            // Usuario que ejecutó o registró la acción.
            // Puede ser NULL si la acción fue automática.
            $table->unsignedBigInteger('registrado_por')->nullable();

            // Información adicional.
            $table->text('descripcion')->nullable();
            $table->json('datos')->nullable();

            // Evita duplicar la misma operación al reintentar.
            $table->string('clave_idempotencia', 191)->unique();

            // Fecha de creación.
            $table->timestamp('created_at')->useCurrent();

            // Índices para las consultas de auditoría.
            $table->index(['tipo', 'created_at']);
            $table->index(['usuario_id', 'created_at']);
            $table->index(['evento_id', 'created_at']);
            $table->index(['reserva_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_operaciones');
    }
};
