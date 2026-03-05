<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\StudentAuthController;
use App\Http\Controllers\Api\V1\ChatAnalyticsController;
use App\Http\Controllers\Api\V1\ChatbotController;
use App\Http\Controllers\Api\V1\CursoController;
use App\Http\Controllers\Api\V1\DevController;
use App\Http\Controllers\Api\V1\EstudianteController;
use App\Http\Controllers\Api\V1\KbDocumentController;
use App\Http\Controllers\Api\V1\KnowledgeBaseController;
use App\Http\Controllers\Api\V1\PeriodoController;
use App\Http\Controllers\Api\V1\PlanEstudiosController;
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

Route::middleware('auth:sanctum')->group(function () {

    // Rutas de autenticación
    Route::get('/me', [AuthController::class, 'me']);
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
    });

    // Rutas de Programacion Académica
    Route::prefix('programacion')->group(function () {
        Route::get('/', [ProgramacionController::class, 'index']);
        Route::get('/template', [ProgramacionController::class, 'downloadTemplate']);
        Route::get('/{id}', [ProgramacionController::class, 'show']);
        Route::post('/import', [ProgramacionController::class, 'import'])
            ->middleware('role:secretaria|admin|developer');
        Route::patch('/{id}/toggle-lleno', [ProgramacionController::class, 'toggleLleno'])
            ->middleware('role:secretaria|admin|developer');
    });

    // Ruta de cursos
    Route::get('/cursos', [CursoController::class, 'index']);
    Route::get('/cursos/{id}', [CursoController::class, 'show']);

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

        // Crear solicitud (estudiantes)
        Route::post('/', [SolicitudController::class, 'store']);

        // Para admin/secretaria/decano - ver todas las solicitudes
        Route::get('/', [SolicitudController::class, 'index'])
            ->middleware('role:admin|secretaria|decano|secretario academico|developer');

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
        ->middleware('role:admin|secretario academico|developer')
        ->group(function () {
            Route::get('/', [EstudianteController::class, 'index']);
            Route::get('/import/template', [EstudianteController::class, 'downloadTemplate']);
            Route::post('/import', [EstudianteController::class, 'import']);
            Route::get('/{id}', [EstudianteController::class, 'show']);
            Route::put('/{id}', [EstudianteController::class, 'update']);
            Route::patch('/{id}/toggle', [EstudianteController::class, 'toggle']);
            Route::post('/{id}/reenviar-otp', [EstudianteController::class, 'reenviarOtp']);
        });

    // Plan de Estudios
    Route::prefix('plan-estudios')
        ->group(function () {
            Route::get('/', [PlanEstudiosController::class, 'index']);          // Todos los autenticados
            Route::get('/template', [PlanEstudiosController::class, 'downloadTemplate']); // Plantilla

            // Solo admin/secretaria pueden gestionar el plan
            Route::post('/import', [PlanEstudiosController::class, 'import'])
                ->middleware('role:admin|secretario academico|developer');
            Route::delete('/', [PlanEstudiosController::class, 'destroy'])
                ->middleware('role:admin|secretario academico|developer');
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
            Route::get('routes', [DevController::class, 'routes']);
            Route::post('impersonate/{userId}', [DevController::class, 'impersonate']);
            Route::delete('impersonate', [DevController::class, 'stopImpersonation']);
        });
});
