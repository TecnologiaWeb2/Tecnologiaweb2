<?php
// src/Modelo/Socio.php — Socia o socio de la asociación (quien confecciona las prendas). Inmutable.
declare(strict_types=1);

final readonly class Socio
{
    public const ESPECIALIDADES = ['Costura', 'Bordado', 'Tejido', 'Estampado'];
    public const PATRON_CI = '/^\d{5,10}(-[0-9][A-Z])?$/';          // 1234567 o 1234567-1A
    public const PATRON_TELEFONO = '/^[67]\d{7}$/';                  // celular boliviano

    public function __construct(
        public string $ci,
        public string $nombre,
        public string $telefono,
        public string $comunidad,
        public string $especialidad,
        public string $fechaIngreso,
        public bool $activo = true,
        public ?int $id = null,
    ) {
        if (preg_match(self::PATRON_CI, $ci) !== 1) {
            throw new InvalidArgumentException("CI inválido: «{$ci}».");
        }
        if (mb_strlen(trim($nombre)) < 5) {
            throw new InvalidArgumentException('El nombre es demasiado corto.');
        }
        if (preg_match(self::PATRON_TELEFONO, $telefono) !== 1) {
            throw new InvalidArgumentException("Teléfono inválido: «{$telefono}».");
        }
        if (!in_array($especialidad, self::ESPECIALIDADES, true)) {
            throw new InvalidArgumentException("Especialidad desconocida: «{$especialidad}».");
        }
        if (DateTimeImmutable::createFromFormat('!Y-m-d', $fechaIngreso)?->format('Y-m-d') !== $fechaIngreso) {
            throw new InvalidArgumentException("Fecha inválida: «{$fechaIngreso}».");
        }
    }

    /** Copia con el id asignado por la base de datos. */
    public function conId(int $id): self
    {
        return new self($this->ci, $this->nombre, $this->telefono, $this->comunidad, $this->especialidad,
            $this->fechaIngreso, $this->activo, $id);
    }

    /** "15/03/2019" */
    public function fechaIngresoTexto(): string
    {
        return DateTimeImmutable::createFromFormat('!Y-m-d', $this->fechaIngreso)->format('d/m/Y');
    }
}
