<?php

use App\Http\Controllers\Api\V1\AreaController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\StudentAuthController;
use App\Http\Controllers\Api\V1\ChatAnalyticsController;
use App\Http\Controllers\Api\V1\ChatbotController;
use App\Http\Controllers\Api\V1\CursoController;
use App\Http\Controllers\Api\V1\DevController;
use App\Http\Controllers\Api\V1\DocenteController;
use App\Http\Controllers\Api\V1\EstudianteController;
use App\Http\Controllers\Api\V1\GrupoHorarioController;
use App\Http\Controllers\Api\V1\HistorialController;
use App\Http\Controllers\Api\V1\KbDocumentController;
use App\Http\Controllers\Api\V1\KnowledgeBaseController;
use App\Http\Controllers\Api\V1\PabellonController;
use App\Http\Controllers\Api\V1\PeriodoController;
use App\Http\Controllers\Api\V1\PlanEstudiosController;
use App\Http\Controllers\Api\V1\ProgresoController;
use App\Http\Controllers\Api\V1\InscripcionController;
use App\Http\Controllers\Api\V1\ProgramacionController;
use App\Http\Controllers\Api\V1\RolController;
use App\Http\Controllers\Api\V1\SolicitudController;
use App\Http\Controllers\Api\V1\TipoSolicitudController;
use App\Http\Controllers\Api\V1\UsuarioController;
use App\Http\Controllers\Api\V1\WhatsappController;
use Illuminate\Support\Facades\Route;

Route::get('/health-check', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Backend conectado correctamente',
        'timestamp' => now()
    ]);
});

// Servir archivos de storage (evita dependencia del symlink nginx)
Route::get('/storage/{path}', function (string $path) {
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    if (!$disk->exists($path)) {
        abort(404);
    }
    return response($disk->get($path), 200, [
        'Content-Type'  => $disk->mimeType($path),
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.+');

// =============================================
// RUTAS DE AUTENTICACIÓN (Públicas)
// =============================================

Route::prefix('auth')->group(function () {
    // Login para administrativos y developer (por username)
    Route::post('/admin/login', [AuthController::class, 'loginAdmin']);

    // Login legacy por email (mantener compatibilidad temporal)
    // TODO: Eliminar cuando se migre completamente el frontend
    Route::post('/login', [AuthController::class, 'login']);

    // =============================================
    // RUTAS DE AUTENTICACIÓN ESTUDIANTES (Públicas)
    // Diseño RESTful: recursos como sustantivos
    // =============================================
    Route::prefix('estudiante')->group(function () {
        // Recurso: verificacion (estado del código universitario)
        Route::post('/verificacion', [StudentAuthController::class, 'verificarCodigo']);

        // Recurso: otp (código de verificación)
        Route::post('/otp', [StudentAuthController::class, 'solicitarOtp']);       // Crear OTP
        Route::patch('/otp', [StudentAuthController::class, 'verificarOtp']);      // Validar OTP

        // Recurso: password
        Route::post('/password', [StudentAuthController::class, 'establecerPassword']);    // Crear (primera vez)
        Route::put('/password', [StudentAuthController::class, 'restablecerPassword']);    // Reemplazar

        // Recurso: sesion (token de acceso)
        Route::post('/sesion', [StudentAuthController::class, 'login']);           // Crear sesión

        // Recurso: recuperacion (proceso de recuperación de contraseña)
        Route::post('/recuperacion', [StudentAuthController::class, 'solicitarRecuperacion']);   // Iniciar
        Route::patch('/recuperacion', [StudentAuthController::class, 'verificarRecuperacion']);  // Validar OTP
    });
});

// Mantener ruta legacy /login para compatibilidad
Route::post('/login', [AuthController::class, 'login']);

// =============================================
// WHATSAPP BOT (Webhook público desde n8n)
// Protegido por X-Webhook-Secret header
// =============================================
Route::prefix('whatsapp')->group(function () {
    // Recibir mensaje entrante (llamado por n8n al recibir mensaje de WhatsApp)
    Route::post('/message', [WhatsappController::class, 'receiveMessage']);

    // Notificar que un agente tomó/liberó control (desde n8n o herramienta externa)
    Route::post('/agent/take/{phone}', [WhatsappController::class, 'agentTakeControl']);
    Route::post('/agent/release/{phone}', [WhatsappController::class, 'agentRelease']);
});

// =============================================
// RUTAS PROTEGIDAS
// =============================================

Route::middleware(['auth:sanctum', 'user.active'])->group(function () {

    // Rutas de autenticación
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/me/password', [AuthController::class, 'cambiarPassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Rutas de Periodos
    Route::prefix('periodos')->group(function () {
        Route::get('/', [PeriodoController::class, 'index']);
        Route::get('/active', [PeriodoController::class, 'active']);
        Route::get('/{id}', [PeriodoController::class, 'show']);
        Route::post('/', [PeriodoController::class, 'store']);
        Route::put('/{id}', [PeriodoController::class, 'update']);
        Route::delete('/{id}', [PeriodoController::class, 'destroy']);
        Route::patch('/{id}/activate', [PeriodoController::class, 'setActive']);
        Route::patch('/{id}/deactivate', [PeriodoController::class, 'deactivate']);
        Route::patch('/{id}/toggle-solicitudes', [PeriodoController::class, 'toggleSolicitudes'])
            ->middleware('role:secretaria|admin|developer');
    });

    // Rutas de Programacion Académica
    Route::prefix('programacion')->group(function () {
        Route::get('/', [ProgramacionController::class, 'index']);
        Route::get('/grupos', [ProgramacionController::class, 'grupos']);
        Route::get('/para-mi', [ProgramacionController::class, 'paraMi']);
        Route::get('/todos-periodo', [ProgramacionController::class, 'todosParaSolicitud']);
        Route::get('/template', [ProgramacionController::class, 'downloadTemplate']);
        Route::get('/export', [ProgramacionController::class, 'export'])
            ->middleware('role:secretaria|admin|developer');
        Route::get('/secciones-del-curso', [ProgramacionController::class, 'seccionesDelCurso'])
            ->middleware('role:secretaria|admin|developer');
        Route::get('/{id}', [ProgramacionController::class, 'show']);
        Route::post('/', [ProgramacionController::class, 'store'])
            ->middleware('role:secretaria|admin|developer');
        Route::post('/import', [ProgramacionController::class, 'import'])
            ->middleware('role:secretaria|admin|developer');
        Route::post('/import-campus', [ProgramacionController::class, 'importCampus'])
            ->middleware('role:secretaria|admin|developer');
        Route::post('/import-html', [ProgramacionController::class, 'importHtml'])
            ->middleware('role:secretaria|admin|developer');

        // Inscripciones por sección
        Route::post('/inscripciones/import-html', [InscripcionController::class, 'importHtml'])
            ->middleware('role:secretaria|admin|developer');
        Route::get('/{id}/inscripciones', [InscripcionController::class, 'index']);
        Route::get('/{id}/inscripciones/stats', [InscripcionController::class, 'stats']);
        Route::delete('/{id}/inscripciones/{userId}', [InscripcionController::class, 'destroy'])
            ->middleware('role:secretaria|admin|developer');

        Route::put('/{id}', [ProgramacionController::class, 'update'])
            ->middleware('role:secretaria|admin|developer');
        Route::delete('/{id}', [ProgramacionController::class, 'destroy'])
            ->middleware('role:secretaria|admin|developer');
        Route::delete('/periodo/{periodoId}', [ProgramacionController::class, 'destroyPeriodo'])
            ->middleware('role:developer');
        Route::patch('/{id}/toggle-lleno', [ProgramacionController::class, 'toggleLleno'])
            ->middleware('role:secretaria|admin|developer');
    });

    // Rutas de cursos
    Route::get('/cursos', [CursoController::class, 'index']);
    Route::get('/cursos/{id}', [CursoController::class, 'show']);
    Route::patch('/cursos/{id}/nombre', [CursoController::class, 'updateNombre'])
        ->middleware('role:secretaria|admin|developer');

    // Equivalencias de cursos
    Route::get('/cursos/{id}/equivalencias', [CursoController::class, 'equivalencias']);
    Route::post('/cursos/{id}/equivalencias', [CursoController::class, 'agregarEquivalencia'])
        ->middleware('role:secretaria|admin|developer');
    Route::delete('/cursos/{cursoId}/equivalencias/{equivalenteId}', [CursoController::class, 'eliminarEquivalencia'])
        ->middleware('role:secretaria|admin|developer');

    // Docentes (lectura, para formularios)
    Route::get('/docentes', [DocenteController::class, 'index']);

    // =============================================
    // DEPARTAMENTOS (AREAS CON PREFIJOS)
    // =============================================
    Route::prefix('areas')->group(function () {
        Route::get('/', [AreaController::class, 'index']);
        Route::middleware('role:secretaria|admin|developer')->group(function () {
            Route::post('/', [AreaController::class, 'store']);
            Route::put('/{id}', [AreaController::class, 'update']);
            Route::delete('/{id}', [AreaController::class, 'destroy']);
            Route::post('/auto-asignar', [AreaController::class, 'autoAsignar']);
        });
    });

    // Escuelas (lectura, para formularios)
    Route::get('/escuelas', function () {
        $escuelas = \App\Models\Escuela::orderBy('nombre')->get(['id', 'codigo', 'nombre', 'nombre_corto']);
        return response()->json(['success' => true, 'data' => $escuelas]);
    });

    // =============================================
    // GRUPOS HORARIO (plantillas de horario G1-G14)
    // =============================================
    Route::prefix('grupos-horario')->group(function () {
        Route::get('/', [GrupoHorarioController::class, 'index']);

        Route::middleware('role:secretaria|admin|developer')->group(function () {
            Route::post('/', [GrupoHorarioController::class, 'store']);
            Route::put('/{id}', [GrupoHorarioController::class, 'update']);
            Route::delete('/{id}', [GrupoHorarioController::class, 'destroy']);
            Route::patch('/{id}/toggle', [GrupoHorarioController::class, 'toggle']);
            Route::post('/{id}/detalle', [GrupoHorarioController::class, 'addDetalle']);
            Route::put('/{id}/detalle/{detalleId}', [GrupoHorarioController::class, 'updateDetalle']);
            Route::delete('/{id}/detalle/{detalleId}', [GrupoHorarioController::class, 'removeDetalle']);
        });
    });

    // =============================================
    // PABELLONES & AULAS
    // =============================================
    Route::prefix('pabellones')->group(function () {
        Route::get('/', [PabellonController::class, 'index']);

        Route::middleware('role:secretaria|admin|developer')->group(function () {
            Route::post('/', [PabellonController::class, 'store']);
            Route::put('/{id}', [PabellonController::class, 'update']);
            Route::delete('/{id}', [PabellonController::class, 'destroy']);
            Route::post('/{id}/aulas', [PabellonController::class, 'storeAula']);
        });
    });

    Route::prefix('aulas')->middleware('role:secretaria|admin|developer')->group(function () {
        Route::get('/huerfanas', [PabellonController::class, 'indexHuerfanas']);
        Route::delete('/huerfanas-sin-curso', [PabellonController::class, 'eliminarHuerfanasSinCurso']);
        Route::put('/{id}', [PabellonController::class, 'updateAula']);
        Route::delete('/{id}', [PabellonController::class, 'destroyAula']);
        Route::patch('/{id}/toggle', [PabellonController::class, 'toggleAula']);
    });

    // =============================================
    // PROGRAMACIÓN INTERACTIVA (Borradores)
    // =============================================
    Route::prefix('programacion-interactiva')
        ->middleware('role:secretaria|admin|secretario academico|developer')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\BorradorProgramacionController::class, 'index']);
            Route::post('/generar', [\App\Http\Controllers\Api\V1\BorradorProgramacionController::class, 'generar']);
            Route::get('/{id}', [\App\Http\Controllers\Api\V1\BorradorProgramacionController::class, 'show']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\BorradorProgramacionController::class, 'destroy']);
            Route::post('/{id}/publicar', [\App\Http\Controllers\Api\V1\BorradorProgramacionController::class, 'publicar']);
            Route::post('/{id}/secciones', [\App\Http\Controllers\Api\V1\BorradorProgramacionController::class, 'agregarSeccion']);
            Route::put('/{id}/secciones/{seccionId}', [\App\Http\Controllers\Api\V1\BorradorProgramacionController::class, 'updateSeccion']);
            Route::patch('/{id}/secciones/bulk', [\App\Http\Controllers\Api\V1\BorradorProgramacionController::class, 'bulkUpdate']);
            Route::delete('/{id}/secciones/{seccionId}', [\App\Http\Controllers\Api\V1\BorradorProgramacionController::class, 'deleteSeccion']);
        });

    // Rutas de Tipos de Solicitud (solo admin/secretaria)
    Route::prefix('tipos-solicitud')->middleware('role:admin|secretaria|decano|secretario academico|developer')->group(function () {
        Route::get('/', [TipoSolicitudController::class, 'index']);
        Route::get('/{id}', [TipoSolicitudController::class, 'show']);
        Route::post('/', [TipoSolicitudController::class, 'store']);
        Route::put('/{id}', [TipoSolicitudController::class, 'update']);
        Route::delete('/{id}', [TipoSolicitudController::class, 'destroy']);
        Route::patch('/{id}/toggle', [TipoSolicitudController::class, 'toggle']);
    });

    // Rutas de Solicitudes
    Route::prefix('solicitudes')->group(function () {
        // Para estudiantes - ver sus propias solicitudes
        Route::get('/mis-solicitudes', [SolicitudController::class, 'misSolicitudes']);

        // Programaciones donde el estudiante ya tiene solicitud activa
        Route::get('/programaciones-activas', [SolicitudController::class, 'programacionesActivas']);

        // Estadísticas (admin/secretaria)
        Route::get('/estadisticas', [SolicitudController::class, 'estadisticas'])
            ->middleware('role:admin|secretaria|decano|secretario academico|developer');

        // Métricas de cupo extra por curso (secciones + solicitantes)
        Route::get('/metricas-cupo', [SolicitudController::class, 'metricasCupo'])
            ->middleware('role:admin|secretaria|decano|secretario academico|developer');

        // Exportar métricas a Excel (4 hojas)
        Route::get('/exportar-metricas', [SolicitudController::class, 'exportarMetricas'])
            ->middleware('role:admin|secretaria|decano|secretario academico|developer');

        // Cursos con solicitudes (para filtro admin)
        Route::get('/cursos-solicitados', [SolicitudController::class, 'cursosConSolicitud'])
            ->middleware('role:admin|secretaria|decano|secretario academico|developer');

        // Exportar CSV con filtros
        Route::get('/exportar', [SolicitudController::class, 'exportar'])
            ->middleware('role:admin|secretaria|decano|secretario academico|developer');

        // Crear solicitud (estudiantes)
        Route::post('/', [SolicitudController::class, 'store']);

        // Para admin/secretaria/decano - ver todas las solicitudes
        Route::get('/', [SolicitudController::class, 'index'])
            ->middleware('role:admin|secretaria|decano|secretario academico|developer');

        // Anular solicitud (el propio estudiante, solo si está pendiente/en_revision)
        Route::delete('/{id}', [SolicitudController::class, 'anular']);

        // Apelar solicitud rechazada (el propio estudiante)
        Route::post('/{id}/respuesta', [SolicitudController::class, 'responder']);

        // Ver detalle (todos pueden, pero estudiantes solo las suyas)
        Route::get('/{id}', [SolicitudController::class, 'show']);

        // Actualizar estado (admin/secretaria/decano)
        Route::patch('/{id}/estado', [SolicitudController::class, 'updateEstado'])
            ->middleware('role:admin|secretaria|decano|secretario academico|developer');
    });

    // =============================================
    // RUTAS DE GESTIÓN DE USUARIOS
    // =============================================

    // Usuarios Administrativos
    Route::prefix('usuarios/administrativos')
        ->middleware('role:admin|secretario academico|developer')
        ->group(function () {
            Route::get('/', [UsuarioController::class, 'index']);
            Route::get('/{id}', [UsuarioController::class, 'show']);
            Route::post('/', [UsuarioController::class, 'store']);
            Route::put('/{id}', [UsuarioController::class, 'update']);
            Route::delete('/{id}', [UsuarioController::class, 'destroy']);
            Route::patch('/{id}/toggle', [UsuarioController::class, 'toggle']);
            Route::patch('/{id}/roles', [UsuarioController::class, 'asignarRoles']);
        });

    // Estudiantes
    Route::prefix('usuarios/estudiantes')
        ->middleware('role:admin|secretaria|secretario academico|developer')
        ->group(function () {
            Route::get('/', [EstudianteController::class, 'index']);
            Route::post('/', [EstudianteController::class, 'store']);
            Route::get('/import/template', [EstudianteController::class, 'downloadTemplate']);
            Route::post('/import', [EstudianteController::class, 'import']);
            Route::post('/import-html', [EstudianteController::class, 'importHtml']);
            Route::post('/import-historiales-zip', [EstudianteController::class, 'importHistorialesZip']);
            Route::get('/import-status/{id}',      [EstudianteController::class, 'importStatus']);
            Route::get('/{id}', [EstudianteController::class, 'show']);
            Route::put('/{id}', [EstudianteController::class, 'update']);
            Route::patch('/{id}/toggle', [EstudianteController::class, 'toggle']);
            Route::post('/{id}/reenviar-otp', [EstudianteController::class, 'reenviarOtp']);
            Route::post('/{id}/reset-activacion', [EstudianteController::class, 'resetActivacion']);
            Route::post('/{id}/inhabilitar-resetear', [EstudianteController::class, 'inhabilitarYResetear']);
            Route::get('/{id}/historial',     [EstudianteController::class, 'historial']);
            Route::get('/{id}/inscripciones', [EstudianteController::class, 'inscripciones']);
        });

    // Historial académico del estudiante autenticado
    Route::prefix('historial')->group(function () {
        Route::get('/', [HistorialController::class, 'index']);
        Route::post('/sync', [HistorialController::class, 'sync']);
        Route::post('/importar-pdf', [HistorialController::class, 'importarPdf']);
        Route::delete('/limpiar', [HistorialController::class, 'limpiar']);
    });

    // Plan de Estudios
    Route::prefix('plan-estudios')
        ->group(function () {
            Route::get('/', [PlanEstudiosController::class, 'index']);
            Route::get('/mi-plan', [PlanEstudiosController::class, 'miPlan']);
            Route::get('/template', [PlanEstudiosController::class, 'downloadTemplate']);

            // Gestión de versiones (planes)
            Route::get('/planes', [PlanEstudiosController::class, 'planes']);
            Route::post('/planes', [PlanEstudiosController::class, 'crearPlan'])
                ->middleware('role:admin|secretario academico|developer');
            Route::patch('/planes/{id}/activar', [PlanEstudiosController::class, 'activarPlan'])
                ->middleware('role:admin|secretario academico|developer');
            Route::patch('/planes/{id}', [PlanEstudiosController::class, 'actualizarPlan'])
                ->middleware('role:admin|secretario academico|developer');
            Route::delete('/planes/{id}', [PlanEstudiosController::class, 'eliminarPlan'])
                ->middleware('role:admin|secretario academico|developer');

            // Actualizar curso del plan
            Route::patch('/{id}', [PlanEstudiosController::class, 'actualizarCursoPlan'])
                ->middleware('role:admin|secretario academico|developer');

            // Importación
            Route::post('/import', [PlanEstudiosController::class, 'import'])
                ->middleware('role:admin|secretario academico|developer');
            Route::post('/import-pdf', [PlanEstudiosController::class, 'importPdf'])
                ->middleware('role:admin|secretario academico|developer');
            Route::post('/debug-pdf', [PlanEstudiosController::class, 'debugPdf'])
                ->middleware('role:developer');
            Route::delete('/', [PlanEstudiosController::class, 'destroy'])
                ->middleware('role:admin|secretario academico|developer');
        });

    // Progreso académico
    Route::prefix('progreso')->group(function () {
        Route::get('/mi-progreso', [ProgresoController::class, 'miProgreso']);
        Route::get('/{userId}', [ProgresoController::class, 'progresoAlumno'])
            ->middleware('role:admin|secretaria|secretario academico|developer');
        Route::patch('/{userId}/egresante', [ProgresoController::class, 'toggleEgresante'])
            ->middleware('role:admin|secretaria|secretario academico|developer');
    });

    // Roles (solo lectura)
    Route::prefix('roles')
        ->middleware('role:admin|secretario academico|developer')
        ->group(function () {
            Route::get('/', [RolController::class, 'index']);
            Route::get('/{id}', [RolController::class, 'show']);
        });

    // =============================================
    // CHATBOT (todos los autenticados)
    // =============================================
    Route::prefix('chatbot')->group(function () {
        Route::get('conversations', [ChatbotController::class, 'conversations']);
        Route::post('conversations', [ChatbotController::class, 'newConversation']);
        Route::get('conversations/{id}', [ChatbotController::class, 'conversation']);
        Route::post('conversations/{id}/messages', [ChatbotController::class, 'sendMessage']);
        Route::delete('conversations/{id}', [ChatbotController::class, 'deleteConversation']);

        // Analytics (admin, secretaria, developer)
        Route::middleware('role:admin|secretaria|secretario academico|decano|developer')
            ->prefix('analytics')
            ->group(function () {
                Route::get('top-topics', [ChatAnalyticsController::class, 'topTopics']);
                Route::get('knowledge-gaps', [ChatAnalyticsController::class, 'knowledgeGaps']);
                Route::get('summary', [ChatAnalyticsController::class, 'summary']);
            });
    });

    // =============================================
    // KNOWLEDGE BASE
    // =============================================

    // Lectura abierta para todos los autenticados
    Route::get('knowledge-base', [KnowledgeBaseController::class, 'index']);
    Route::get('knowledge-base/documents', [KbDocumentController::class, 'index']);
    Route::get('knowledge-base/documents/{id}/download', [KbDocumentController::class, 'download']);
    Route::get('knowledge-base/documents/{id}', [KbDocumentController::class, 'show']);
    Route::get('knowledge-base/{id}', [KnowledgeBaseController::class, 'show']);

    // Gestión (admin/secretaria/developer)
    Route::middleware('role:admin|secretaria|secretario academico|developer')
        ->prefix('knowledge-base')
        ->group(function () {
            Route::post('/', [KnowledgeBaseController::class, 'store']);
            Route::put('/{id}', [KnowledgeBaseController::class, 'update']);
            Route::delete('/{id}', [KnowledgeBaseController::class, 'destroy']);
            Route::patch('/{id}/toggle', [KnowledgeBaseController::class, 'toggle']);
            Route::post('/{id}/relations', [KnowledgeBaseController::class, 'addRelation']);
            Route::delete('/{id}/relations/{targetId}', [KnowledgeBaseController::class, 'removeRelation']);

            // Documentos
            Route::post('/documents', [KbDocumentController::class, 'store']);
            Route::put('/documents/{id}', [KbDocumentController::class, 'update']);
            Route::delete('/documents/{id}', [KbDocumentController::class, 'destroy']);
            Route::patch('/documents/{id}/toggle', [KbDocumentController::class, 'toggle']);
            Route::post('/documents/{id}/reprocess', [KbDocumentController::class, 'reprocess']);

            // Adjuntar / desadjuntar documentos de un artículo
            Route::post('/{id}/documents/{docId}', [KnowledgeBaseController::class, 'attachDocument']);
            Route::delete('/{id}/documents/{docId}', [KnowledgeBaseController::class, 'detachDocument']);
        });

    // =============================================
    // WHATSAPP - PANEL DE SECRETARÍA (Sanctum)
    // =============================================
    Route::prefix('whatsapp')
        ->middleware('role:admin|secretaria|secretario academico|developer')
        ->group(function () {
            Route::get('/queue', [WhatsappController::class, 'queue']);         // Cola de espera
            Route::get('/sessions', [WhatsappController::class, 'sessions']);   // Todas las sesiones
            Route::get('/sessions/{phone}', [WhatsappController::class, 'session']); // Detalle
            Route::patch('/sessions/{phone}/close', [WhatsappController::class, 'closeSession']); // Cerrar
        });

    // =============================================
    // PANEL DEVELOPER (solo developer)
    // =============================================
    Route::middleware('role:developer')
        ->prefix('dev')
        ->group(function () {
            Route::get('health', [DevController::class, 'health']);
            Route::get('activity-logs', [DevController::class, 'activityLogs']);
            Route::get('email-logs', [DevController::class, 'emailLogs']);
            Route::get('settings', [DevController::class, 'getSettings']);
            Route::patch('settings/{key}', [DevController::class, 'updateSetting']);
            Route::post('maintenance/cache-clear', [DevController::class, 'clearCache']);
            Route::post('maintenance/logs-clear', [DevController::class, 'clearLogs']);
            Route::get('mail/config', [DevController::class, 'mailConfig']);
            Route::post('mail/test', [DevController::class, 'testMail']);
            Route::get('routes', [DevController::class, 'routes']);
            Route::post('impersonate/{userId}', [DevController::class, 'impersonate']);
            Route::delete('impersonate', [DevController::class, 'stopImpersonation']);
        });
});
