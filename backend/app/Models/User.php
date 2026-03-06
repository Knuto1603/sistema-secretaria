<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'tipo_usuario',
        'username',
        'codigo_universitario',
        'escuela_id',
        'anio_ingreso',
        'email',
        'password',
        'password_set_at',
        'activo',
        'ultima_actualizacion_historial',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_set_at' => 'datetime',
            'ultima_actualizacion_historial' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    /**
     * Relación con códigos OTP
     */
    public function otpCodes(): HasMany
    {
        return $this->hasMany(OtpCode::class);
    }

    /**
     * Relación con escuela profesional
     */
    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }

    /**
     * Cursos que el alumno ha aprobado (historial académico)
     */
    public function historialAcademico(): HasMany
    {
        return $this->hasMany(HistorialAcademico::class);
    }

    /**
     * Cursos aprobados (acceso directo a través del historial)
     */
    public function cursosAprobados()
    {
        return $this->belongsToMany(Curso::class, 'historial_academico', 'user_id', 'curso_id')
            ->withPivot('fuente')
            ->withTimestamps();
    }

    // ==========================================
    // MÉTODOS DE TIPO DE USUARIO
    // ==========================================

    /**
     * Verifica si es developer (god user)
     */
    public function isDeveloper(): bool
    {
        return $this->tipo_usuario === 'developer';
    }

    /**
     * Verifica si es administrativo
     */
    public function isAdministrativo(): bool
    {
        return $this->tipo_usuario === 'administrativo';
    }

    /**
     * Verifica si es estudiante
     */
    public function isEstudiante(): bool
    {
        return $this->tipo_usuario === 'estudiante';
    }

    /**
     * Verifica si puede acceder con username + password
     */
    public function usesUsernameAuth(): bool
    {
        return $this->isDeveloper() || $this->isAdministrativo();
    }

    /**
     * Verifica si puede acceder con código universitario
     */
    public function usesCodigoAuth(): bool
    {
        return $this->isEstudiante();
    }

    // ==========================================
    // MÉTODOS DE CONTRASEÑA
    // ==========================================

    /**
     * Verifica si el estudiante ya estableció su contraseña
     */
    public function hasPasswordSet(): bool
    {
        return $this->password_set_at !== null;
    }

    /**
     * Establece la contraseña y marca la fecha
     */
    public function setPasswordWithTimestamp(string $password): void
    {
        $this->update([
            'password' => $password,
            'password_set_at' => now(),
        ]);
    }

    // ==========================================
    // MÉTODOS DE EMAIL
    // ==========================================

    /**
     * Obtiene el email institucional del estudiante
     */
    public function getEmailInstitucional(): string
    {
        if ($this->isEstudiante() && $this->codigo_universitario) {
            return $this->codigo_universitario . '@alumnos.unp.edu.pe';
        }

        return $this->email;
    }

    /**
     * Genera el email institucional basado en el código
     */
    public static function generarEmailEstudiante(string $codigo): string
    {
        return $codigo . '@alumnos.unp.edu.pe';
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope para filtrar solo estudiantes
     */
    public function scopeEstudiantes($query)
    {
        return $query->where('tipo_usuario', 'estudiante');
    }

    /**
     * Scope para filtrar solo administrativos
     */
    public function scopeAdministrativos($query)
    {
        return $query->where('tipo_usuario', 'administrativo');
    }

    /**
     * Scope para filtrar solo developers
     */
    public function scopeDevelopers($query)
    {
        return $query->where('tipo_usuario', 'developer');
    }

    /**
     * Busca un usuario por código universitario
     */
    public static function findByCodigoUniversitario(string $codigo): ?self
    {
        return self::where('codigo_universitario', $codigo)->first();
    }

    /**
     * Busca un usuario por username
     */
    public static function findByUsername(string $username): ?self
    {
        return self::where('username', $username)->first();
    }

    // ==========================================
    // MÉTODOS DE CÓDIGO UNIVERSITARIO
    // ==========================================

    /**
     * Parsea el código universitario y extrae sus componentes
     * Formato: FFEGGGGNNN (10 dígitos)
     * FF = Facultad (05 = FII)
     * E = Escuela (0=Industrial, 1=Informática, 2=Agroindustrial, 3=Mecatrónica)
     * GGGG = Año de ingreso
     * NNN = Correlativo
     */
    public static function parsearCodigoUniversitario(string $codigo): ?array
    {
        if (strlen($codigo) !== 10 || !ctype_digit($codigo)) {
            return null;
        }

        return [
            'facultad' => substr($codigo, 0, 2),      // 05
            'escuela' => substr($codigo, 2, 1),       // 0, 1, 2, 3
            'anio_ingreso' => (int) substr($codigo, 3, 4),  // 2021
            'correlativo' => substr($codigo, 7, 3),   // 002
        ];
    }

    /**
     * Asigna automáticamente la escuela y año de ingreso basándose en el código
     */
    public function asignarDatosDesdeCodigoUniversitario(): bool
    {
        if (!$this->codigo_universitario) {
            return false;
        }

        $datos = self::parsearCodigoUniversitario($this->codigo_universitario);
        if (!$datos) {
            return false;
        }

        // Buscar la escuela por código
        $escuela = Escuela::findByCodigo($datos['escuela']);
        if ($escuela) {
            $this->escuela_id = $escuela->id;
        }

        $this->anio_ingreso = $datos['anio_ingreso'];
        $this->save();

        return true;
    }

    /**
     * Obtiene el nombre de la escuela (para mostrar)
     */
    public function getNombreEscuela(): ?string
    {
        return $this->escuela?->nombre_corto;
    }

    /**
     * Obtiene la información completa del estudiante
     */
    public function getInfoEstudiante(): array
    {
        return [
            'codigo_universitario' => $this->codigo_universitario,
            'escuela' => $this->escuela?->nombre_corto,
            'escuela_completo' => $this->escuela?->nombre,
            'anio_ingreso' => $this->anio_ingreso,
            'promocion' => $this->anio_ingreso ? "Promoción {$this->anio_ingreso}" : null,
        ];
    }
}
