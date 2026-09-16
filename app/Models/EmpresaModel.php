<?php
namespace App\Models;
use PDO;

class EmpresaModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /** Listar empresas (con filtro opcional) */
    public function getAll($empresa_id = null) {
        if ($empresa_id !== null) {
            $st = $this->db->prepare("SELECT * FROM empresas WHERE id = ? ORDER BY nombre");
            $st->execute([$empresa_id]);
        } else {
            $st = $this->db->query("SELECT * FROM empresas ORDER BY nombre");
        }
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $st = $this->db->prepare("SELECT * FROM empresas WHERE id = ? LIMIT 1");
        $st->execute([(int)$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Columnas reales de la tabla */
    private function columnas(): array {
        return $this->db->query("SHOW COLUMNS FROM empresas")->fetchAll(PDO::FETCH_COLUMN);
    }

    /** CREAR empresa — tolerante a campos faltantes */
    public function create($data) {
        $cols = $this->columnas();

        // Mapa: columna => valor (con fallback seguro si el form no lo envía)
        $mapa = [
            'nombre'        => trim((string)($data['nombre'] ?? '')),
            'nit'           => trim((string)($data['nit'] ?? $data['identificacion'] ?? '')),
            'identificacion'=> trim((string)($data['identificacion'] ?? $data['nit'] ?? '')),
            'email'         => trim((string)($data['email'] ?? $data['correo'] ?? '')),
            'correo'        => trim((string)($data['correo'] ?? $data['email'] ?? '')),
            'telefono'      => trim((string)($data['telefono'] ?? $data['tel'] ?? '')),
            'direccion'     => trim((string)($data['direccion'] ?? '')),
            'ciudad'        => trim((string)($data['ciudad'] ?? '')),
            'departamento'  => trim((string)($data['departamento'] ?? '')),
            'estado'        => ($data['estado'] ?? 'activo'),
            'logo'          => ($data['logo'] ?? null),
            'fecha_creacion'=> date('Y-m-d H:i:s'),
            'creado_en'     => date('Y-m-d H:i:s'),
        ];

        // Solo insertar columnas que EXISTEN en la tabla y que tengan valor o sean obligatorias
        $fila = [];
        foreach ($mapa as $col => $val) {
            if (!in_array($col, $cols, true)) continue;   // la columna no existe → saltar
            if ($val === '' && !in_array($col, ['nombre','estado'], true)) continue; // vacío y no obligatorio → saltar
            $fila[$col] = $val;
        }

        if (empty($fila['nombre'])) {
            throw new \Exception('El nombre de la empresa es obligatorio');
        }

        $campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($fila)));
        $marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($fila)));
        $st = $this->db->prepare("INSERT INTO empresas ($campos) VALUES ($marks)");
        foreach ($fila as $k => $v) $st->bindValue(":$k", $v);
        $st->execute();

        return (int)$this->db->lastInsertId();
    }

    /** ACTUALIZAR empresa — tolerante */
    public function update($id, $data) {
        $cols = $this->columnas();
        $mapa = [
            'nombre'        => trim((string)($data['nombre'] ?? '')),
            'nit'           => trim((string)($data['nit'] ?? $data['identificacion'] ?? '')),
            'identificacion'=> trim((string)($data['identificacion'] ?? $data['nit'] ?? '')),
            'email'         => trim((string)($data['email'] ?? $data['correo'] ?? '')),
            'correo'        => trim((string)($data['correo'] ?? $data['email'] ?? '')),
            'telefono'      => trim((string)($data['telefono'] ?? '')),
            'direccion'     => trim((string)($data['direccion'] ?? '')),
            'ciudad'        => trim((string)($data['ciudad'] ?? '')),
            'departamento'  => trim((string)($data['departamento'] ?? '')),
            'estado'        => ($data['estado'] ?? null),
        ];

        $sets = [];
        $fila = ['id' => (int)$id];
        foreach ($mapa as $col => $val) {
            if (!in_array($col, $cols, true)) continue;
            if ($val === null || $val === '') continue;  // no tocar campos vacíos
            $sets[] = "`$col` = :$col";
            $fila[$col] = $val;
        }
        if (!$sets) throw new \Exception('Nada que actualizar');

        $st = $this->db->prepare("UPDATE empresas SET " . implode(', ', $sets) . " WHERE id = :id");
        foreach ($fila as $k => $v) $st->bindValue(":$k", $v);
        return $st->execute();
    }

    /** ELIMINAR empresa */
    public function delete($id, $soft = false) {
        if ($soft) {
            $st = $this->db->prepare("UPDATE empresas SET estado = 'inactivo' WHERE id = ?");
            return $st->execute([(int)$id]);
        }
        $st = $this->db->prepare("DELETE FROM empresas WHERE id = ?");
        return $st->execute([(int)$id]);
    }
}
