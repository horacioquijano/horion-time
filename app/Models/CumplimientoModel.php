<?php
namespace App\Models;
use PDO;

class CumplimientoModel {
    private $db;
    const TOL_ENTRADA_MIN = 10;
    const TOL_SALIDA_MIN  = 15;

    public function __construct($db) { $this->db = $db; }

    /** Resumen mensual: por empleado, por servicio y KPIs globales */
    public function getResumen($empresa_id, $mes, $anio) {
        // 1) Calendario programado (Panel de Turnos)
        $sql = "SELECT tc.usuario_id, tc.fecha, tc.codigo, tc.servicio,
                       pt.hora_entrada, pt.hora_salida, pt.horas_trabajadas,
                       pt.es_descanso, pt.es_vacacion, pt.recargo_nocturno,
                       u.nombre_completo, u.identificacion
                FROM turnos_calendario tc
                LEFT JOIN parametros_turnos pt ON pt.codigo = tc.codigo
                INNER JOIN usuarios u ON u.id = tc.usuario_id
                WHERE YEAR(tc.fecha) = ? AND MONTH(tc.fecha) = ?";
        $p = [$anio, $mes];
        if ($empresa_id) { $sql .= " AND tc.empresa_id = ?"; $p[] = (int)$empresa_id; }
        $st = $this->db->prepare($sql); $st->execute($p);
        $cal = $st->fetchAll(PDO::FETCH_ASSOC);

        // 2) Marcaciones reales del mes
        $sql2 = "SELECT usuario_id, fecha, tipo_marcacion, hora_registro
                 FROM registros_asistencia
                 WHERE YEAR(fecha) = ? AND MONTH(fecha) = ?";
        $p2 = [$anio, $mes];
        if ($empresa_id) { $sql2 .= " AND empresa_id = ?"; $p2[] = (int)$empresa_id; }
        $st2 = $this->db->prepare($sql2); $st2->execute($p2);
        $marcas = $st2->fetchAll(PDO::FETCH_ASSOC);

        $mx = [];
        foreach ($marcas as $m) { $mx[(int)$m['usuario_id'] . '|' . $m['fecha']][] = $m; }

        $emp = []; $progKeys = [];
        foreach ($cal as $c) {
            $uid = (int)$c['usuario_id'];
            $fecha = $c['fecha'];
            $progKeys[$uid . '|' . $fecha] = true;
            if (!isset($emp[$uid])) {
                $emp[$uid] = $this->filaBase($uid, $c['nombre_completo'], $c['identificacion']);
            }
            if (empty($emp[$uid]['servicio']) && !empty($c['servicio'])) $emp[$uid]['servicio'] = $c['servicio'];

            $dia = $mx[$uid . '|' . $fecha] ?? [];

            // Día V: vacaciones → solo alerta si marcó
            if ((int)($c['es_vacacion'] ?? 0) === 1) {
                if ($dia) $emp[$uid]['alertas_vacacion']++;
                continue;
            }
            // Día L: libre → si marcó, es extra
            if ((int)($c['es_descanso'] ?? 0) === 1) {
                if ($dia) { $emp[$uid]['dias_extra']++; $emp[$uid]['horas_extra'] += $this->horasDelDia($dia); }
                continue;
            }

            // Día laboral programado
            $emp[$uid]['dias_prog']++;
            $emp[$uid]['horas_prog'] += (float)($c['horas_trabajadas'] ?? 0);

            if (!$dia) { $emp[$uid]['ausencias']++; continue; }

            $entrada = $this->marcaDeTipo($dia, 'entrada');
            if ($entrada !== null) {
                $emp[$uid]['dias_asistidos']++;
                if (!empty($c['hora_entrada'])) {
                    $tEnt = $this->min($c['hora_entrada']);
                    $tMar = $this->min($entrada);
                    if ($tEnt >= 720 && $tMar < 720) $tMar += 1440; // noche: marca pasada medianoche
                    if ($tMar > $tEnt + self::TOL_ENTRADA_MIN) $emp[$uid]['tardanzas']++;
                }
            } else {
                $emp[$uid]['ausencias']++;
            }

            if (!empty($c['hora_salida'])) {
                $salida = $this->salidaDelTurno($uid, $fecha, $c, $mx);
                if ($salida !== null) {
                    $tSal = $this->min($c['hora_salida']);
                    $tMar = $this->min($salida);
                    if ((int)($c['recargo_nocturno'] ?? 0) === 1 && $tMar >= 720) $tMar -= 1440;
                    if ($tMar < $tSal - self::TOL_SALIDA_MIN) $emp[$uid]['salidas_tempranas']++;
                }
            }

            $emp[$uid]['horas_trab'] += $this->horasDelDia($dia);
        }

        // 3) Marcas en días SIN programación
        $faltantes = [];
        foreach ($mx as $key => $lista) {
            if (isset($progKeys[$key])) continue;
            [$uidS, ] = explode('|', $key);
            $uid = (int)$uidS;
            if (!isset($emp[$uid])) $faltantes[] = $uid;
        }
        if ($faltantes) {
            $marks = implode(',', array_fill(0, count($faltantes), '?'));
            $st3 = $this->db->prepare("SELECT id, nombre_completo, identificacion FROM usuarios WHERE id IN ($marks)");
            $st3->execute($faltantes);
            foreach ($st3->fetchAll(PDO::FETCH_ASSOC) as $u) {
                $emp[(int)$u['id']] = $this->filaBase((int)$u['id'], $u['nombre_completo'], $u['identificacion']);
            }
        }
        foreach ($mx as $key => $lista) {
            if (isset($progKeys[$key])) continue;
            [$uidS, ] = explode('|', $key);
            $uid = (int)$uidS;
            if (!isset($emp[$uid])) continue;
            $emp[$uid]['marcas_sin_turno']++;
            $emp[$uid]['horas_trab'] += $this->horasDelDia($lista);
        }

        // 4) Porcentajes y orden
        foreach ($emp as &$r) {
            $r['horas_extra'] = round($r['horas_extra'], 2);
            $r['horas_trab']  = round($r['horas_trab'], 2);
            $r['horas_prog']  = round($r['horas_prog'], 2);
            $r['pct'] = $r['dias_prog'] > 0 ? round($r['dias_asistidos'] / $r['dias_prog'] * 100) : null;
        }
        unset($r);
        usort($emp, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

        // 5) Agregado por servicio
        $serv = [];
        foreach ($emp as $r) {
            $k = $r['servicio'] !== '' ? $r['servicio'] : 'SIN SERVICIO';
            if (!isset($serv[$k])) $serv[$k] = ['servicio'=>$k,'empleados'=>0,'prog'=>0,'asist'=>0,'tard'=>0,'aus'=>0,'extra_h'=>0.0,'pct'=>null];
            $serv[$k]['empleados']++;
            $serv[$k]['prog']  += $r['dias_prog'];
            $serv[$k]['asist'] += $r['dias_asistidos'];
            $serv[$k]['tard']  += $r['tardanzas'];
            $serv[$k]['aus']   += $r['ausencias'];
            $serv[$k]['extra_h'] += $r['horas_extra'];
        }
        foreach ($serv as &$s) { $s['pct'] = $s['prog'] > 0 ? round($s['asist'] / $s['prog'] * 100) : null; $s['extra_h'] = round($s['extra_h'],2); }
        unset($s);
        ksort($serv);

        // 6) KPIs globales
        $totProg = $totAsist = $totTard = $totAus = $totVac = 0; $totExtra = 0.0;
        foreach ($emp as $r) {
            $totProg += $r['dias_prog']; $totAsist += $r['dias_asistidos'];
            $totTard += $r['tardanzas']; $totAus += $r['ausencias'];
            $totExtra += $r['horas_extra']; $totVac += $r['alertas_vacacion'];
        }
        $kpis = [
            'empleados' => count($emp),
            'pct_global' => $totProg > 0 ? round($totAsist / $totProg * 100) : null,
            'tardanzas' => $totTard,
            'ausencias' => $totAus,
            'horas_extra' => round($totExtra, 2),
            'alertas_vacacion' => $totVac,
        ];

        return ['empleados' => $emp, 'servicios' => $serv, 'kpis' => $kpis];
    }

    private function filaBase($uid, $nombre, $ident) {
        return ['usuario_id'=>$uid,'nombre'=>$nombre,'identificacion'=>$ident,'servicio'=>'',
            'dias_prog'=>0,'dias_asistidos'=>0,'tardanzas'=>0,'salidas_tempranas'=>0,'ausencias'=>0,
            'dias_extra'=>0,'horas_extra'=>0.0,'alertas_vacacion'=>0,'horas_prog'=>0.0,'horas_trab'=>0.0,
            'marcas_sin_turno'=>0,'pct'=>null];
    }

    private function min($h) { return (int)substr($h, 0, 2) * 60 + (int)substr($h, 3, 2); }

    private function marcaDeTipo(array $dia, $tipo) {
        $h = null;
        foreach ($dia as $m) if ($m['tipo_marcacion'] === $tipo) { if ($h === null || $m['hora_registro'] < $h) $h = $m['hora_registro']; }
        return $h;
    }

    private function ultimaDeTipo(array $dia, $tipo) {
        $h = null;
        foreach ($dia as $m) if ($m['tipo_marcacion'] === $tipo) { if ($h === null || $m['hora_registro'] > $h) $h = $m['hora_registro']; }
        return $h;
    }

    /** Salida del turno: en noche busca la madrugada del día siguiente */
    private function salidaDelTurno($uid, $fecha, $c, $mx) {
        if ((int)($c['recargo_nocturno'] ?? 0) === 1) {
            $next = date('Y-m-d', strtotime($fecha . ' +1 day'));
            $s = $this->ultimaDeTipo($mx[$uid . '|' . $next] ?? [], 'salida');
            if ($s !== null && $this->min($s) < 720) return $s;
        }
        return $this->ultimaDeTipo($mx[$uid . '|' . $fecha] ?? [], 'salida');
    }

    /** Horas del día: entrada→salida descontando almuerzo */
    private function horasDelDia(array $dia) {
        $ent  = $this->marcaDeTipo($dia, 'entrada');
        $sal  = $this->ultimaDeTipo($dia, 'salida');
        $sAlm = $this->marcaDeTipo($dia, 'salida_almuerzo');
        $rAlm = $this->marcaDeTipo($dia, 'regreso_almuerzo');
        $h = 0.0;
        if ($ent && $sal) {
            $d = $this->min($sal) - $this->min($ent); if ($d < 0) $d += 1440;
            if ($sAlm && $rAlm) { $a = $this->min($rAlm) - $this->min($sAlm); if ($a < 0) $a += 1440; $d -= $a; }
            $h = $d / 60;
        } elseif ($ent && $sAlm) {
            $h = ($this->min($sAlm) - $this->min($ent)) / 60;
        } elseif ($rAlm && $sal) {
            $h = ($this->min($sal) - $this->min($rAlm)) / 60;
        }
        return max(0, $h);
    }
}
