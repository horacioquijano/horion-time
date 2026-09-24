<?php
namespace App\Models;
use PDO;

class CargaMasivaModel {
    private $db;
    public function __construct($db) { $this->db = $db; }

    // =====================================================
    //  LEYENDA PARAMETRIZABLE (letras → turnos)
    // =====================================================
    public function getParametros($empresa_id = null) {
        $st = $this->db->prepare("SELECT * FROM parametros_turnos WHERE estado='activo' AND (empresa_id IS NULL OR empresa_id = ?) ORDER BY (empresa_id IS NOT NULL), id");
        $st->execute([(int)($empresa_id ?? 0)]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarParametros($rows, $empresa_id = null) {
        foreach ($rows as $i => $r) {
            $id = (int)($r['id'] ?? 0);
            $codigo = strtoupper(trim((string)($r['codigo'] ?? '')));
            if ($codigo === '') continue;
            if ($id > 0) {
                $st = $this->db->prepare("UPDATE parametros_turnos SET codigo=?, nombre=?, hora_entrada=?, hora_salida=?, horas_trabajadas=?, es_descanso=?, es_vacacion=?, recargo_nocturno=?, color=? WHERE id=?");
                $st->execute([
                    $codigo, $r['nombre'] ?? $codigo,
                    $r['hora_entrada'] ?: null, $r['hora_salida'] ?: null,
                    (float)($r['horas_trabajadas'] ?? 0),
                    (int)($r['es_descanso'] ?? 0), (int)($r['es_vacacion'] ?? 0), (int)($r['recargo_nocturno'] ?? 0),
                    $r['color'] ?? '#e2e8f0', $id
                ]);
            } else {
                $st = $this->db->prepare("INSERT INTO parametros_turnos (empresa_id, codigo, nombre, hora_entrada, hora_salida, horas_trabajadas, es_descanso, es_vacacion, recargo_nocturno, color) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $st->execute([
                    $empresa_id, $codigo, $r['nombre'] ?? $codigo,
                    $r['hora_entrada'] ?: null, $r['hora_salida'] ?: null,
                    (float)($r['horas_trabajadas'] ?? 0),
                    (int)($r['es_descanso'] ?? 0), (int)($r['es_vacacion'] ?? 0), (int)($r['recargo_nocturno'] ?? 0),
                    $r['color'] ?? '#e2e8f0'
                ]);
            }
        }
        return true;
    }

    // =====================================================
    //  ANÁLISIS DEL EXCEL (preview antes de confirmar)
    // =====================================================
    public function analizarFilas(array $rows, $mes, $anio, $empresa_id) {
        $params = $this->getParametros($empresa_id);
        $codigos = [];
        foreach ($params as $p) $codigos[strtoupper($p['codigo'])] = $p;

        // 1) Localizar fila de encabezado y mapa columna => día
        $headerRow = null;
        $colIdent = $colNombre = $colCargo = $colServ = null;
        $dayMap = [];

        foreach ($rows as $ri => $r) {
            $vals = array_map(fn($v) => strtoupper(trim((string)$v)), $r);
            if (in_array('IDENTIFICACION', $vals, true)) {
                $headerRow = $ri;
                foreach ($r as $ci => $v) {
                    $u = strtoupper(trim((string)$v));
                    if ($u === 'IDENTIFICACION')                          $colIdent = $ci;
                    elseif (strpos($u, 'NOMBRES') === 0 || $u === 'NOMBRE') $colNombre = $ci;
                    elseif ($u === 'CARGO')                               $colCargo = $ci;
                    elseif (strpos($u, 'SERVICIO') === 0)                 $colServ  = $ci;
                    elseif ($u === 'HORA' || $u === 'TOTAL HORA' || $u === 'DIFERENCIA' || $u === 'DIRECCION') {
                        // columnas de totales/auxiliares: no son días
                        continue;
                    }
                    elseif (preg_match('/^\d{1,2}$/', $u) && (int)$u >= 1 && (int)$u <= 31) {
                        $dayMap[$ci] = (int)$u;
                    }
                }
                break;
            }
        }
        if ($headerRow === null || !$dayMap) {
            throw new \Exception('No se encontró el encabezado (fila con IDENTIFICACION y columnas de días 1-31). Verifica el formato.');
        }

        $diasMes = (int)date('t', mktime(0, 0, 0, $mes, 1, $anio));
        $preview = [];

        // 2) Recorrer filas posteriores al encabezado
        foreach ($rows as $ri => $r) {
            if ($ri <= $headerRow) continue;

            $ident  = trim((string)($r[$colIdent]  ?? ''));
            $nombre = trim((string)($r[$colNombre] ?? ''));
            $cargo  = trim((string)($r[$colCargo]  ?? ''));
            $serv   = trim((string)($r[$colServ]   ?? ''));

            // Filtrar filas que NO son empleados
            if ($ident === '' && $nombre === '') continue; // fila vacía
            if ($ident !== '' && preg_match('/^\D/', $ident)) continue; // "cédula" que empieza con letra = nota al pie
            if (stripos($ident, 'VACACIONES') !== false) continue;      // fila tipo "VACACIONES HASTA..."
            if (stripos($nombre, 'NOTA:') === 0) continue;
            if (stripos($nombre, 'HORARIO') === 0 && $ident === '') continue;
            if (stripos($nombre, 'TURNOS DE') === 0) continue;
            if (preg_match('/^(URGENCIAS|QUIROFANO|PISO|SALA|PRIMER|SEGUNDO|LAVANDERIA|LOS DOMINGOS|POR LA NOCHE|SALE A)/i', $nombre) && $ident === '') continue;

            // Leer días
            $dias = []; $avisos = [];
            foreach ($dayMap as $ci => $dia) {
                if ($dia > $diasMes) continue;
                $val = trim((string)($r[$ci] ?? ''));
                if ($val === '') continue;

                // Número (totales semanales tipo 44/33/22/176): ignorar
                if (is_numeric($val)) continue;

                $up = strtoupper($val);

                // Letra válida de la leyenda
                if (isset($codigos[$up])) { $dias[$dia] = $codigos[$up]['codigo']; continue; }

                // Variantes de VACACIONES
                if (strpos($up, 'VACACION') === 0) {
                    $dias[$dia] = isset($codigos['V']) ? 'V' : 'L';
                    continue;
                }

                // Texto libre largo (ej: "PASA A URGENCIAS CUBRIR VACACIONES DE MENDOZA")
                if (strlen($up) > 5) {
                    $avisos[] = "Día $dia: «" . mb_substr($val, 0, 35) . "…» (texto libre)";
                    continue;
                }

                // Letra suelta no reconocida
                if (strlen($up) <= 3 && !preg_match('/^\d+$/', $up)) {
                    $avisos[] = "Día $dia: letra «$up» no está en la leyenda";
                }
            }

            // Si la fila no tiene días ni avisos ni cédula: ignorar
            if (!$dias && !$avisos && $ident === '') continue;

            // 3) Cruzar con usuarios existentes + fusionar cédulas repetidas
            $clave = ($ident !== '') ? $ident : ('SIN_CEDULA_' . $ri);

            if ($ident !== '' && isset($preview[$ident])) {
                // FUSIÓN: sumar días del segundo bloque (ej: URGENCIAS + MOVIL)
                foreach ($dias as $d => $cod) {
                    if (!isset($preview[$ident]['dias'][$d])) {
                        $preview[$ident]['dias'][$d] = $cod;
                    }
                }
                $preview[$ident]['avisos'] = array_merge($preview[$ident]['avisos'], $avisos);
                if ($preview[$ident]['servicio'] === '') $preview[$ident]['servicio'] = $serv;
                else $preview[$ident]['servicio'] .= ' + ' . $serv;
                ksort($preview[$ident]['dias']);
                continue;
            }

            $preview[$clave] = [
                'identificacion' => $ident,
                'nombre'         => $nombre,
                'cargo'          => $cargo,
                'servicio'       => $serv,
                'dias'           => $dias,
                'avisos'         => $avisos,
                'usuario_id'     => null,
                'accion'         => 'omitir',
            ];
        }

        // 4) Calcular horas y marcar acción (actualizar / crear / omitir)
        $horasPorCodigo = [];
        foreach ($params as $p) $horasPorCodigo[$p['codigo']] = (float)$p['horas_trabajadas'];

        foreach ($preview as $k => &$row) {
            $row['usuario_id'] = null;
            $row['accion'] = 'omitir';

            if ($row['identificacion'] !== '') {
                $st = $this->db->prepare("SELECT id FROM usuarios WHERE identificacion = ? LIMIT 1");
                $st->execute([$row['identificacion']]);
                $uid = $st->fetchColumn();
                if ($uid) {
                    $row['usuario_id'] = (int)$uid;
                    $row['accion'] = 'actualizar';
                } else {
                    $row['accion'] = 'crear';
                }
            } elseif (!$row['dias']) {
                // sin cédula y sin días → omitir definitivamente
                continue;
            } else {
                $row['avisos'][] = 'Sin cédula: no se puede asignar. Agrégala al Excel o crea el empleado manualmente.';
            }

            $tot = 0.0;
            foreach ($row['dias'] as $cod) $tot += $horasPorCodigo[$cod] ?? 0;
            $row['horas'] = $tot;
        }
        unset($row);

        return $preview;
    }

    // =====================================================
    //  CONFIRMAR CARGA (escribir en turnos_calendario)
    // =====================================================
    public function confirmarCarga(array $preview, $empresa_id, $mes, $anio, $usuario_sess, $crearUsuarios, $limpiarMes, $archivoNombre) {
        $params = $this->getParametros($empresa_id);
        $horasPorCodigo = [];
        foreach ($params as $p) $horasPorCodigo[$p['codigo']] = (float)$p['horas_trabajadas'];

        $ok = 0; $err = 0; $detalles = [];
        $lote = $this->crearLote($empresa_id, $mes, $anio, $archivoNombre, 0, 0, '', $usuario_sess);

        foreach ($preview as $row) {
            $uid = $row['usuario_id'];
            try {
                // Crear usuario si corresponde
                if ($uid === null && $crearUsuarios && $row['identificacion'] !== '' && $row['nombre'] !== '') {
                    $uid = $this->crearUsuarioBasico($empresa_id, $row['identificacion'], $row['nombre'], $row['cargo']);
                    if ($uid) {
                        $detalles[] = "Creado: {$row['nombre']} ({$row['identificacion']})";
                    } else {
                        $err++;
                        $detalles[] = "No se pudo crear: {$row['nombre']}";
                        continue;
                    }
                }

                if (!$uid) {
                    $err++;
                    $detalles[] = "Omitido: " . ($row['nombre'] ?: $row['identificacion'] ?: '(sin datos)');
                    continue;
                }

                // Limpiar mes previo si se pidió
                if ($limpiarMes) {
                    $st = $this->db->prepare("DELETE FROM turnos_calendario WHERE empresa_id=? AND usuario_id=? AND YEAR(fecha)=? AND MONTH(fecha)=?");
                    $st->execute([$empresa_id, $uid, $anio, $mes]);
                }

                // Insertar cada día
                foreach ($row['dias'] as $dia => $cod) {
                    $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, (int)$dia);
                    $this->upsertDia($empresa_id, $uid, $fecha, $cod, $row['servicio'], $horasPorCodigo[$cod] ?? 0, 'carga_masiva', $lote, $usuario_sess);
                }
                $ok++;
            } catch (\Throwable $e) {
                $err++;
                $detalles[] = "Error {$row['identificacion']}: " . $e->getMessage();
            }
        }

        $this->db->prepare("UPDATE cargas_masivas SET filas_ok=?, filas_error=?, detalle_error=? WHERE id=?")
                 ->execute([$ok, $err, implode(' | ', array_slice($detalles, 0, 40)), $lote]);

        return ['lote' => $lote, 'ok' => $ok, 'err' => $err];
    }

    public function crearLote($empresa_id, $mes, $anio, $archivo, $ok, $err, $detalle, $usuario_id) {
        $st = $this->db->prepare("INSERT INTO cargas_masivas (empresa_id, mes, anio, archivo_nombre, filas_ok, filas_error, detalle_error, usuario_id) VALUES (?,?,?,?,?,?,?,?)");
        $st->execute([$empresa_id, $mes, $anio, $archivo, $ok, $err, $detalle, $usuario_id]);
        return (int)$this->db->lastInsertId();
    }

    public function upsertDia($empresa_id, $usuario_id, $fecha, $codigo, $servicio, $horas, $origen, $lote_id, $creado_por) {
        $st = $this->db->prepare("INSERT INTO turnos_calendario (empresa_id, usuario_id, fecha, codigo, servicio, horas, origen, lote_id, creado_por)
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE codigo=VALUES(codigo), servicio=VALUES(servicio), horas=VALUES(horas), origen=VALUES(origen), lote_id=VALUES(lote_id)");
        return $st->execute([$empresa_id, $usuario_id, $fecha, $codigo, $servicio, $horas, $origen, $lote_id, $creado_por]);
    }

    public function borrarDia($empresa_id, $usuario_id, $fecha) {
        return $this->db->prepare("DELETE FROM turnos_calendario WHERE empresa_id=? AND usuario_id=? AND fecha=?")
                        ->execute([$empresa_id, $usuario_id, $fecha]);
    }

    /** Crea un empleado básico solo con columnas que realmente existen en la tabla */
    private function crearUsuarioBasico($empresa_id, $ident, $nombre, $cargo) {
        $rol = $this->db->query("SELECT id FROM roles WHERE nombre='Empleado' LIMIT 1")->fetchColumn();
        if (!$rol) return null;

        $cols = $this->db->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);

        $fila = [
            'empresa_id'      => $empresa_id,
            'nombre_completo' => $nombre,
            'identificacion'  => $ident,
            'email'           => $ident . '@horion.local',
            'password_hash'   => password_hash('Horion2026.', PASSWORD_DEFAULT),
            'rol_id'          => (int)$rol,
            'cargo'           => $cargo,
            'estado'          => 'activo',
        ];

        // Solo insertar columnas que existen en la tabla
        foreach ($fila as $k => $v) {
            if (!in_array($k, $cols, true)) unset($fila[$k]);
        }

        $campos = implode(', ', array_map(fn($k) => "`$k`", array_keys($fila)));
        $marks  = implode(', ', array_map(fn($k) => ":$k", array_keys($fila)));
        $st = $this->db->prepare("INSERT INTO usuarios ($campos) VALUES ($marks)");
        foreach ($fila as $k => $v) $st->bindValue(":$k", $v);
        $st->execute();
        return (int)$this->db->lastInsertId();
    }

    // =====================================================
    //  PANEL / HISTORIAL
    // =====================================================
    public function getPanel($empresa_id, $mes, $anio) {
        $st = $this->db->prepare("SELECT tc.usuario_id, tc.fecha, tc.codigo, tc.servicio, tc.horas, u.nombre_completo, u.identificacion
            FROM turnos_calendario tc
            INNER JOIN usuarios u ON u.id = tc.usuario_id
            WHERE tc.empresa_id=? AND YEAR(tc.fecha)=? AND MONTH(tc.fecha)=?
            ORDER BY u.nombre_completo, tc.fecha");
        $st->execute([$empresa_id, $anio, $mes]);
        $panel = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $uid = (int)$r['usuario_id'];
            if (!isset($panel[$uid])) $panel[$uid] = ['usuario' => $r, 'dias' => [], 'horas' => 0.0];
            $dia = (int)substr($r['fecha'], 8, 2);
            $panel[$uid]['dias'][$dia] = $r;
            $panel[$uid]['horas'] += (float)$r['horas'];
        }
        return $panel;
    }

    public function getHistorial($empresa_id) {
        $st = $this->db->prepare("SELECT c.*, u.nombre_completo AS cargado_por
            FROM cargas_masivas c
            LEFT JOIN usuarios u ON u.id = c.usuario_id
            WHERE c.empresa_id=?
            ORDER BY c.id DESC LIMIT 30");
        $st->execute([$empresa_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revertirLote($lote_id) {
        $this->db->prepare("DELETE FROM turnos_calendario WHERE lote_id=?")->execute([(int)$lote_id]);
        return $this->db->prepare("UPDATE cargas_masivas SET estado='revertido' WHERE id=?")->execute([(int)$lote_id]);
    }
}
