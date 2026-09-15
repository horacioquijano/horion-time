<?php
namespace App\Models;
use PDO;

class UsuarioModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    private function columnas() {
        return $this->db->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Crear usuario: guarda SOLO los campos que existen en la tabla
     * (incluye sede_id, cargo, telefono, area, es_jefe, jefe_inmediato_id, etc.)
     */
    public function create($data) {
        $cols = $this->columnas();
        $usa = array_intersect_key($data, array_flip($cols));
        unset($usa['id'], $usa['password'], $usa['fecha_creacion']);
        if (isset($usa['password_hash']) && $usa['password_hash'] === null) unset($usa['password_hash']);
        if (empty($usa['nombre_completo']) || empty($usa['email'])) {
            throw new \Exception('Nombre completo y email son obligatorios');
        }
        $campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($usa)));
        $marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($usa)));
        $stmt = $this->db->prepare("INSERT INTO usuarios ($campos) VALUES ($marks)");
        foreach ($usa as $k => $v) $stmt->bindValue(":$k", $v);
        if (!$stmt->execute()) throw new \Exception('No se pudo crear el usuario');
        return (int)$this->db->lastInsertId();
    }

    /** Actualizar usuario: solo campos existentes; la empresa no se cambia aquí */
    public function update($id, $data) {
        $cols = $this->columnas();
        $usa = array_intersect_key($data, array_flip($cols));
        unset($usa['id'], $usa['password'], $usa['empresa_id'], $usa['fecha_creacion']);
        if (isset($usa['password_hash']) && $usa['password_hash'] === null) unset($usa['password_hash']);
        if (!$usa) throw new \Exception('Nada que actualizar');
        $sets = implode(', ', array_map(fn($k) => "`$k` = :$k", array_keys($usa)));
        $stmt = $this->db->prepare("UPDATE usuarios SET $sets WHERE id = :id");
        foreach ($usa as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->bindValue(':id', (int)$id);
        return $stmt->execute();
    }

    public function getAll() {
        $sql = "SELECT u.*, r.nombre AS rol_nombre, s.nombre AS sede_nombre, e.nombre AS empresa_nombre
                FROM usuarios u
                LEFT JOIN roles r ON u.rol_id = r.id
                LEFT JOIN sedes s ON u.sede_id = s.id
                LEFT JOIN empresas e ON u.empresa_id = e.id
                ORDER BY u.nombre_completo ASC";
        try {
            return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { return []; }
    }

    public function getByEmpresa($empresa_id) {
        $sql = "SELECT u.*, r.nombre AS rol_nombre, s.nombre AS sede_nombre, e.nombre AS empresa_nombre
                FROM usuarios u
                LEFT JOIN roles r ON u.rol_id = r.id
                LEFT JOIN sedes s ON u.sede_id = s.id
                LEFT JOIN empresas e ON u.empresa_id = e.id
                WHERE u.empresa_id = :empresa_id
                ORDER BY u.nombre_completo ASC";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':empresa_id' => (int)$empresa_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) { return []; }
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM usuarios WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }
}