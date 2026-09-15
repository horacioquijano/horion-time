<?php
namespace App\Models;
use PDO;

class EmpresaModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getAll() {
        $stmt = $this->db->prepare("SELECT * FROM empresas ORDER BY fecha_creacion DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $sql = "INSERT INTO empresas (nombre, nit, direccion, ubicacion_adicional, telefono, email, configuracion_personalizada) 
                VALUES (:nombre, :nit, :direccion, :ubicacion, :telefono, :email, :config)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nombre' => $data['nombre'],
            ':nit' => $data['nit'],
            ':direccion' => $data['direccion'],
            ':ubicacion' => $data['ubicacion_adicional'] ?? null,
            ':telefono' => $data['telefono'],
            ':email' => $data['email'],
            ':config' => json_encode(['jornada_horas' => 48, 'tolerancia_min' => 10])
        ]);
    }

    public function delete($id, $isAdmin) {
        // VALIDACIÓN CRÍTICA: Solo administradores pueden eliminar (Memoria 12)
        if (!$isAdmin) {
            throw new \Exception("Acceso denegado: Solo los administradores pueden eliminar empresas.");
        }
        $stmt = $this->db->prepare("DELETE FROM empresas WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}