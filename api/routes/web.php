<?php

declare(strict_types=1);

use App\Http\Controllers\Administracion\AuditoriaController;
use App\Http\Controllers\Administracion\ProfesionalController;
use App\Http\Controllers\Administracion\SalaController;
use App\Http\Controllers\Administracion\TarifaController;
use App\Http\Controllers\Administracion\TipoAtencionController;
use App\Http\Controllers\Administracion\UsuarioController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Agenda\CitaController;
use App\Http\Controllers\Agenda\DisponibilidadController;
use App\Http\Controllers\Agenda\ConsultaAgendaController;
use App\Http\Controllers\Pacientes\ConsentimientoController;
use App\Http\Controllers\Pacientes\PacienteController;
use App\Http\Controllers\FichaClinica\DocumentoController;
use App\Http\Controllers\FichaClinica\EvolucionController;
use App\Http\Controllers\FichaClinica\HistorialController;
use App\Http\Controllers\FichaClinica\RecetaController;
use Illuminate\Support\Facades\Route;

/*
|-------------------------------------------------------------------------------
| Rutas de la API de ClinicaSalud
|-------------------------------------------------------------------------------
|
| Todas cuelgan del grupo `web` de Laravel (ver bootstrap/app.php) y del
| prefijo `/api/v1`, que se aplica aqui y no en el esqueleto: queda a la vista
| que TODA la superficie de la API esta versionada en una sola linea.
|
| Estas rutas las consume el BFF de Nuxt (web/server/api), que reenvia las
| cookies del navegador. Ver docs/01 y docs/09.
|
| EL CONTROL FINO NO ESTA ACA. Las rutas declaran que recurso se toca; quien
| puede tocarlo, y sobre todo quien puede leer una ficha clinica, se decide en
| App\Policies. Ver docs/03.
|
*/
Route::prefix('api/v1')->name('api.v1.')->group(function (): void {

    /*
    |---------------------------------------------------------------------------
    | Autenticacion (OIDC contra Authentik)
    |---------------------------------------------------------------------------
    */
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::get('login', [AuthController::class, 'login'])->name('login');
        Route::get('callback', [AuthController::class, 'callback'])->name('callback');

        Route::middleware('auth')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('usuario', [AuthController::class, 'usuario'])->name('usuario');
        });
    });

    Route::middleware('auth')->group(function (): void {

        /*
        |-----------------------------------------------------------------------
        | Agenda medica
        |-----------------------------------------------------------------------
        | Recepcion entra aqui: puede agendar y ver la agenda de su clinica.
        */
        Route::get('agenda', ConsultaAgendaController::class)->name('agenda');
        Route::get('agenda/disponibilidad', DisponibilidadController::class)->name('agenda.disponibilidad');

        Route::apiResource('citas', CitaController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy'])
            ->parameters(['citas' => 'cita'])
            ->names('citas');

        /*
        |-----------------------------------------------------------------------
        | Pacientes y consentimientos
        |-----------------------------------------------------------------------
        | Datos de identificacion y cobertura: no es informacion clinica, asi que
        | recepcion si la puede ver. Los consentimientos tambien: se firman en
        | recepcion.
        */
        Route::apiResource('pacientes', PacienteController::class)
            ->only(['index', 'store', 'show', 'update'])
            ->parameters(['pacientes' => 'paciente'])
            ->names('pacientes');

        Route::apiResource('pacientes.consentimientos', ConsentimientoController::class)
            ->only(['index', 'store'])
            ->parameters(['pacientes' => 'paciente', 'consentimientos' => 'consentimiento'])
            ->names('pacientes.consentimientos');

        /*
        |-----------------------------------------------------------------------
        | FICHA CLINICA
        |-----------------------------------------------------------------------
        | Todo lo que cuelga de aqui esta bajo el middleware `ficha.auditada`:
        | cada intento queda anotado en `accesos_fichas`, permitido o denegado.
        |
        | La separacion es deliberada: estas rutas son las unicas que abren
        | informacion clinica, y por eso son las unicas que se auditan. Recepcion
        | recibe 404 en todas ellas, y el intento queda registrado igual.
        */
        Route::middleware('ficha.auditada')->group(function (): void {

            // Historial del paciente: evoluciones y recetas, en una sola vista.
            Route::get('fichas/{paciente}', [HistorialController::class, 'show'])
                ->name('fichas.show');

            Route::apiResource('fichas.evoluciones', EvolucionController::class)
                ->only(['index', 'store', 'show'])
                ->parameters(['fichas' => 'paciente', 'evoluciones' => 'evolucion'])
                ->names('fichas.evoluciones');

            Route::apiResource('fichas.recetas', RecetaController::class)
                ->only(['index', 'store', 'show'])
                ->parameters(['fichas' => 'paciente', 'recetas' => 'receta'])
                ->names('fichas.recetas');

            // Anular una receta. Ruta aparte porque anular no es editar: es un
            // cambio de estado con consecuencia clinica.
            Route::post('fichas/{paciente}/recetas/{receta}/anular', [RecetaController::class, 'anular'])
                ->name('fichas.recetas.anular');

            // Formato imprimible (HTML de una pagina, listo para imprimir).
            // Lleva `{paciente}` para que el middleware de auditoria sepa a que
            // ficha corresponde sin consultar la base.
            Route::get('fichas/{paciente}/recetas/{receta}/imprimir', [DocumentoController::class, 'receta'])
                ->name('fichas.recetas.imprimir');
        });

        /*
        |-----------------------------------------------------------------------
        | Administracion de la clinica
        |-----------------------------------------------------------------------
        | Hay dos grupos, y no es cosmetico.
        |
        | 1. CATALOGO DE LA AGENDA (profesionales, salas, tipos de atencion).
        |    Sin el, agendar es imposible: hay que saber a quien se agenda, en
        |    que sala y cuanto dura. El LISTADO abre para los tres roles
        |    (`verParaAgenda` en la Policy); crear y modificar, no.
        |
        |    Por eso NO llevan el middleware `admin`: si lo llevaran, la
        |    recepcion recibiria 403 y no podria agendar nada.
        |
        | 2. ADMINISTRACION PROPIAMENTE DICHA (tarifas, usuarios, bitacora).
        |    Solo el administrador de la clinica, con el middleware que lo corta
        |    antes de llegar al controlador.
        |
        | Ver docs/03.
        */
        Route::prefix('admin')->name('admin.')->group(function (): void {

            // 1. Catalogo de la agenda.
            Route::apiResource('profesionales', ProfesionalController::class)
                ->only(['index', 'store', 'show', 'update'])
                ->parameters(['profesionales' => 'profesional'])
                ->names('profesionales');

            Route::apiResource('salas', SalaController::class)
                ->only(['index', 'store', 'show', 'update'])
                ->parameters(['salas' => 'sala'])
                ->names('salas');

            Route::apiResource('tipos-atencion', TipoAtencionController::class)
                ->only(['index', 'store', 'show', 'update'])
                ->parameters(['tipos-atencion' => 'tipoAtencion'])
                ->names('tipos-atencion');

            // 2. Administracion propiamente dicha.
            Route::middleware('admin')->group(function (): void {

                Route::apiResource('tarifas', TarifaController::class)
                    ->only(['index', 'store', 'show', 'update'])
                    ->parameters(['tarifas' => 'tarifa'])
                    ->names('tarifas');

                Route::apiResource('usuarios', UsuarioController::class)
                    ->only(['index', 'store', 'show', 'update'])
                    ->parameters(['usuarios' => 'usuario'])
                    ->names('usuarios');

                // Bitacora de accesos a ficha clinica. Solo el administrador.
                Route::get('auditoria', AuditoriaController::class)->name('auditoria');
            });
        });
    });
});