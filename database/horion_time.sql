-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 16-09-2026 a las 00:28:08
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `horion_time`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `accesos_supervisor`
--

DROP TABLE IF EXISTS `accesos_supervisor`;
CREATE TABLE IF NOT EXISTS `accesos_supervisor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `widget_asistencia` tinyint(1) DEFAULT 1,
  `widget_novedades` tinyint(1) DEFAULT 1,
  `widget_horas_extras` tinyint(1) DEFAULT 1,
  `widget_turnos` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertas`
--

DROP TABLE IF EXISTS `alertas`;
CREATE TABLE IF NOT EXISTS `alertas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `tipo` enum('warning','error','info','success') DEFAULT 'info',
  `canal` enum('in_app','email','push','todos') DEFAULT 'in_app',
  `titulo` varchar(150) NOT NULL,
  `mensaje` text NOT NULL,
  `leido` tinyint(1) DEFAULT 0,
  `leida_email` tinyint(1) DEFAULT 0,
  `prioridad` int(11) DEFAULT 0 COMMENT '0=baja, 1=media, 2=alta, 3=critica',
  `accion_url` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Datos extra: URL de acción, ID de registro relacionado' CHECK (json_valid(`metadata`)),
  `programada_para` datetime DEFAULT NULL COMMENT 'Para notificaciones programadas',
  PRIMARY KEY (`id`),
  KEY `idx_usuario_leido` (`usuario_id`,`leido`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_canal_leido` (`canal`,`leido`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `api_log`
--

DROP TABLE IF EXISTS `api_log`;
CREATE TABLE IF NOT EXISTS `api_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `token_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `metodo` varchar(10) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `request_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_body`)),
  `response_status` int(11) DEFAULT NULL,
  `tiempo_respuesta_ms` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_endpoint` (`endpoint`),
  KEY `idx_creado` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `api_rate_limit`
--

DROP TABLE IF EXISTS `api_rate_limit`;
CREATE TABLE IF NOT EXISTS `api_rate_limit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identificador` varchar(100) NOT NULL COMMENT 'IP o token_hash',
  `endpoint` varchar(100) NOT NULL,
  `peticiones` int(11) DEFAULT 1,
  `ventana_inicio` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_identificador` (`identificador`,`endpoint`,`ventana_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `api_tokens`
--

DROP TABLE IF EXISTS `api_tokens`;
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL COMMENT 'NULL = Token de aplicación (sin usuario específico)',
  `nombre` varchar(150) NOT NULL COMMENT 'Ej: App Móvil, Integración Nómina',
  `token_hash` varchar(255) NOT NULL,
  `token_visible` varchar(32) NOT NULL COMMENT 'Primeros 32 chars para mostrar al usuario',
  `permisos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '["read:asistencia", "write:marcacion", ...]' CHECK (json_valid(`permisos`)),
  `expira_en` datetime DEFAULT NULL COMMENT 'NULL = nunca expira',
  `ultimo_uso` datetime DEFAULT NULL,
  `ip_restringida` varchar(45) DEFAULT NULL COMMENT 'Si se quiere limitar a una IP',
  `estado` enum('activo','revocado','expirado') DEFAULT 'activo',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  UNIQUE KEY `token_visible` (`token_visible`),
  KEY `empresa_id` (`empresa_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(50) NOT NULL,
  `tabla_afectada` varchar(100) NOT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `valores_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`valores_anteriores`)),
  `valores_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`valores_nuevos`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cola_notificaciones`
--

DROP TABLE IF EXISTS `cola_notificaciones`;
CREATE TABLE IF NOT EXISTS `cola_notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_envio` enum('email','push') NOT NULL,
  `destinatario_email` varchar(150) DEFAULT NULL,
  `destinatario_push_token` text DEFAULT NULL,
  `asunto` varchar(255) NOT NULL,
  `cuerpo` text NOT NULL,
  `intentos` int(11) DEFAULT 0,
  `max_intentos` int(11) DEFAULT 3,
  `estado` enum('pendiente','enviado','fallido') DEFAULT 'pendiente',
  `ultimo_error` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `procesado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_empresa`
--

DROP TABLE IF EXISTS `configuracion_empresa`;
CREATE TABLE IF NOT EXISTS `configuracion_empresa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `jornada_semanal_horas` decimal(4,2) DEFAULT 48.00,
  `tolerancia_entrada_min` int(11) DEFAULT 10,
  `tolerancia_salida_min` int(11) DEFAULT 5,
  `permitir_marcacion_temprana` tinyint(1) DEFAULT 1,
  `requerir_foto_marcacion` tinyint(1) DEFAULT 0,
  `requerir_geolocalizacion` tinyint(1) DEFAULT 0,
  `radio_gps_metros` int(11) DEFAULT 100,
  `festivos_personalizados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array de fechas YYYY-MM-DD' CHECK (json_valid(`festivos_personalizados`)),
  `dias_descanso` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array de números 0(Dom) a 6(Sab)' CHECK (json_valid(`dias_descanso`)),
  `notificaciones_email` tinyint(1) DEFAULT 1,
  `auditoria_activa` tinyint(1) DEFAULT 1,
  `motor_reglas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON con porcentajes y horarios de recargos' CHECK (json_valid(`motor_reglas`)),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa_id` (`empresa_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion_empresa`
--

INSERT INTO `configuracion_empresa` (`id`, `empresa_id`, `jornada_semanal_horas`, `tolerancia_entrada_min`, `tolerancia_salida_min`, `permitir_marcacion_temprana`, `requerir_foto_marcacion`, `requerir_geolocalizacion`, `radio_gps_metros`, `festivos_personalizados`, `dias_descanso`, `notificaciones_email`, `auditoria_activa`, `motor_reglas`, `fecha_actualizacion`) VALUES
(1, 1, 48.00, 10, 5, 1, 0, 0, 100, '[\"2026-01-01\", \"2026-01-12\", \"2026-03-30\", \"2026-05-01\", \"2026-07-20\", \"2026-12-25\"]', '[0, 6]', 1, 1, '{\"jornada_diurna\": {\"inicio\": \"06:00\", \"fin\": \"22:00\", \"recargo\": 0}, \"jornada_nocturna\": {\"inicio\": \"22:00\", \"fin\": \"06:00\", \"recargo\": 35}, \"hora_extra_diurna\": {\"recargo\": 25}, \"hora_extra_nocturna\": {\"recargo\": 75}, \"dominical\": {\"recargo\": 75}, \"festivo\": {\"recargo\": 100}}', '2026-09-09 18:07:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_recargos`
--

DROP TABLE IF EXISTS `configuracion_recargos`;
CREATE TABLE IF NOT EXISTS `configuracion_recargos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `tipo_recargo` enum('jornada_diurna','jornada_nocturna','hora_extra_diurna','hora_extra_nocturna','dominical','festivo','dominical_festivo') NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `porcentaje_recargo` decimal(5,2) NOT NULL COMMENT 'Ej: 25.00 = 25%',
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tipo_empresa` (`empresa_id`,`tipo_recargo`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion_recargos`
--

INSERT INTO `configuracion_recargos` (`id`, `empresa_id`, `tipo_recargo`, `hora_inicio`, `hora_fin`, `porcentaje_recargo`, `descripcion`, `activo`) VALUES
(1, 1, 'jornada_diurna', '06:00:00', '22:00:00', 0.00, 'Jornada ordinaria diurna sin recargo', 1),
(2, 1, 'jornada_nocturna', '22:00:00', '06:00:00', 35.00, 'Recargo nocturno del 35% sobre el valor de la hora ordinaria', 1),
(3, 1, 'hora_extra_diurna', '06:00:00', '22:00:00', 25.00, 'Hora extra diurna con recargo del 25%', 1),
(4, 1, 'hora_extra_nocturna', '22:00:00', '06:00:00', 75.00, 'Hora extra nocturna con recargo del 75%', 1),
(5, 1, 'dominical', '00:00:00', '23:59:59', 75.00, 'Trabajo dominical con recargo del 75%', 1),
(6, 1, 'festivo', '00:00:00', '23:59:59', 100.00, 'Trabajo en día festivo con recargo del 100%', 1),
(7, 1, 'dominical_festivo', '00:00:00', '23:59:59', 150.00, 'Trabajo en domingo festivo con recargo del 150%', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dashboard_cache`
--

DROP TABLE IF EXISTS `dashboard_cache`;
CREATE TABLE IF NOT EXISTS `dashboard_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tipo_cache` enum('resumen_dia','asistencia_sede','tardanzas_top','incidencias_tipo') NOT NULL,
  `datos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`datos`)),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cache` (`empresa_id`,`fecha`,`tipo_cache`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `delegaciones_supervision`
--

DROP TABLE IF EXISTS `delegaciones_supervision`;
CREATE TABLE IF NOT EXISTS `delegaciones_supervision` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supervisor_original_id` int(11) NOT NULL,
  `supervisor_delegado_id` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `estado` enum('activa','vencida','cancelada') DEFAULT 'activa',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `supervisor_original_id` (`supervisor_original_id`),
  KEY `idx_delegado` (`supervisor_delegado_id`,`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dispositivos`
--

DROP TABLE IF EXISTS `dispositivos`;
CREATE TABLE IF NOT EXISTS `dispositivos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `sede_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `tipo` enum('tablet','pc','movil','kiosco') DEFAULT 'kiosco',
  `mac_address` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `estado` enum('activo','inactivo','mantenimiento') DEFAULT 'activo',
  `modo_kiosco` tinyint(1) DEFAULT 0,
  `pin_acceso` varchar(10) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL COMMENT 'Código QR único del dispositivo',
  `ultima_actividad` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mac_address` (`mac_address`),
  KEY `empresa_id` (`empresa_id`),
  KEY `idx_sede` (`sede_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empleado_horario`
--

DROP TABLE IF EXISTS `empleado_horario`;
CREATE TABLE IF NOT EXISTS `empleado_horario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `horario_id` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL COMMENT 'NULL = indefinido',
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_asignacion` (`usuario_id`,`fecha_inicio`),
  KEY `horario_id` (`horario_id`),
  KEY `idx_empleado_horario_activo` (`usuario_id`,`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `empleado_horario`
--

INSERT INTO `empleado_horario` (`id`, `usuario_id`, `horario_id`, `fecha_inicio`, `fecha_fin`, `estado`, `creado_en`) VALUES
(1, 5, 14, '2026-09-15', '2026-10-15', 'activo', '2026-09-15 13:35:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas`
--

DROP TABLE IF EXISTS `empresas`;
CREATE TABLE IF NOT EXISTS `empresas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `nit` varchar(50) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `direccion` varchar(255) NOT NULL,
  `ubicacion_adicional` text DEFAULT NULL COMMENT 'Detalles de ubicación extendida (piso, torre, referencias)',
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `configuracion_personalizada` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Reglas de negocio específicas de la empresa' CHECK (json_valid(`configuracion_personalizada`)),
  `resumen_diario_email` tinyint(1) NOT NULL DEFAULT 1,
  `alertas_tardanza` tinyint(1) NOT NULL DEFAULT 1,
  `alertas_ausencia` tinyint(1) NOT NULL DEFAULT 1,
  `alerta_minutos_tardanza` int(11) NOT NULL DEFAULT 15,
  `logo_url` varchar(255) DEFAULT NULL,
  `timezone` varchar(50) NOT NULL DEFAULT 'America/Bogota',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nit` (`nit`),
  KEY `idx_nit` (`nit`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `empresas`
--

INSERT INTO `empresas` (`id`, `nombre`, `nit`, `logo`, `direccion`, `ubicacion_adicional`, `telefono`, `email`, `fecha_creacion`, `estado`, `configuracion_personalizada`, `resumen_diario_email`, `alertas_tardanza`, `alertas_ausencia`, `alerta_minutos_tardanza`, `logo_url`, `timezone`) VALUES
(1, 'Horion Time S.A.S.', '900123456-7', NULL, 'Calle 100 #15-20', 'Torre A, Piso 8', '+57 300 123 4567', 'admin@horiontime.co', '2026-09-09 16:27:05', 'activo', NULL, 1, 1, 1, 15, NULL, 'America/Bogota'),
(5, 'Fundacion Campbell', '900002780', NULL, 'Calle 31#14-118', NULL, '3003001234', NULL, '2026-09-14 21:43:06', 'activo', NULL, 1, 1, 1, 15, NULL, 'America/Bogota');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios`
--

DROP TABLE IF EXISTS `horarios`;
CREATE TABLE IF NOT EXISTS `horarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` enum('fijo','flexible','rotativo') DEFAULT 'fijo',
  `tolerancia_entrada` int(11) DEFAULT 10 COMMENT 'Minutos de tolerancia',
  `tolerancia_salida` int(11) DEFAULT 5,
  `descanso_almuerzo_inicio` time DEFAULT '12:00:00',
  `descanso_almuerzo_fin` time DEFAULT '13:00:00',
  `jornada_diurna_inicio` time DEFAULT '06:00:00',
  `jornada_diurna_fin` time DEFAULT '22:00:00',
  `jornada_nocturna_inicio` time DEFAULT '22:00:00',
  `jornada_nocturna_fin` time DEFAULT '06:00:00',
  `festivos_trabajados` tinyint(1) DEFAULT 0,
  `horas_semanales` decimal(5,2) DEFAULT 48.00,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `horarios`
--

INSERT INTO `horarios` (`id`, `empresa_id`, `nombre`, `descripcion`, `tipo`, `tolerancia_entrada`, `tolerancia_salida`, `descanso_almuerzo_inicio`, `descanso_almuerzo_fin`, `jornada_diurna_inicio`, `jornada_diurna_fin`, `jornada_nocturna_inicio`, `jornada_nocturna_fin`, `festivos_trabajados`, `horas_semanales`, `estado`, `creado_en`, `fecha_creacion`) VALUES
(4, 1, 'Administrativo', NULL, 'fijo', 10, 5, '12:00:00', '13:00:00', '06:00:00', '22:00:00', '22:00:00', '06:00:00', 0, 48.00, 'activo', '2026-09-15 13:22:59', '2026-09-09 16:27:05'),
(5, 1, 'Hospitalario', NULL, 'rotativo', 10, 5, '12:00:00', '13:00:00', '06:00:00', '22:00:00', '22:00:00', '06:00:00', 0, 48.00, 'activo', '2026-09-15 13:22:59', '2026-09-09 16:27:05'),
(6, 1, 'Flexible', NULL, 'flexible', 10, 5, '12:00:00', '13:00:00', '06:00:00', '22:00:00', '22:00:00', '06:00:00', 0, 40.00, 'activo', '2026-09-15 13:22:59', '2026-09-09 16:27:05'),
(7, 1, 'Administrativo', NULL, 'fijo', 10, 5, '12:00:00', '13:00:00', '06:00:00', '22:00:00', '22:00:00', '06:00:00', 0, 48.00, 'activo', '2026-09-15 13:22:59', '2026-09-09 16:27:51'),
(8, 1, 'Hospitalario', NULL, 'rotativo', 10, 5, '12:00:00', '13:00:00', '06:00:00', '22:00:00', '22:00:00', '06:00:00', 0, 48.00, 'activo', '2026-09-15 13:22:59', '2026-09-09 16:27:51'),
(9, 1, 'Flexible', NULL, 'flexible', 10, 5, '12:00:00', '13:00:00', '06:00:00', '22:00:00', '22:00:00', '06:00:00', 0, 40.00, 'activo', '2026-09-15 13:22:59', '2026-09-09 16:27:51'),
(13, 5, 'Administrativo', NULL, 'fijo', 10, 5, '12:00:00', '13:00:00', '06:00:00', '22:00:00', '22:00:00', '06:00:00', 0, 48.00, 'activo', '2026-09-15 13:22:59', '2026-09-14 23:37:10'),
(14, 5, 'Turno Dia a Dia', 'Descanso sabado y Domingo ', 'fijo', 10, 5, '12:00:00', '13:00:00', '06:00:00', '22:00:00', '22:00:00', '06:00:00', 0, 48.00, 'activo', '2026-09-15 13:35:05', '2026-09-15 13:35:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horas_extras`
--

DROP TABLE IF EXISTS `horas_extras`;
CREATE TABLE IF NOT EXISTS `horas_extras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tipo` enum('diurna','nocturna','dominical','festivo','dominical_festivo') NOT NULL,
  `hora_inicio_programada` time NOT NULL,
  `hora_fin_programada` time NOT NULL,
  `hora_inicio_real` time DEFAULT NULL,
  `hora_fin_real` time DEFAULT NULL,
  `horas_solicitadas` decimal(5,2) NOT NULL,
  `horas_aprobadas` decimal(5,2) DEFAULT NULL,
  `horas_rechazadas` decimal(5,2) DEFAULT 0.00,
  `valor_hora_ordinaria` decimal(10,2) DEFAULT NULL COMMENT 'Se calcula automáticamente',
  `porcentaje_aplicado` decimal(5,2) DEFAULT NULL,
  `valor_total` decimal(10,2) DEFAULT NULL COMMENT 'Horas aprobadas * valor_hora * (1 + recargo)',
  `estado` enum('pendiente','autorizada','aprobada','rechazada','pagada') DEFAULT 'pendiente',
  `solicitante_id` int(11) NOT NULL,
  `aprobador_id` int(11) DEFAULT NULL,
  `justificacion` text NOT NULL,
  `observaciones` text DEFAULT NULL,
  `soporte_documento` varchar(255) DEFAULT NULL,
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_aprobacion` datetime DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL,
  `numero_nomina` varchar(50) DEFAULT NULL COMMENT 'Referencia de pago en nómina',
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_estado` (`estado`),
  KEY `idx_tipo` (`tipo`),
  KEY `solicitante_id` (`solicitante_id`),
  KEY `aprobador_id` (`aprobador_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `horas_extras`
--

INSERT INTO `horas_extras` (`id`, `usuario_id`, `empresa_id`, `fecha`, `tipo`, `hora_inicio_programada`, `hora_fin_programada`, `hora_inicio_real`, `hora_fin_real`, `horas_solicitadas`, `horas_aprobadas`, `horas_rechazadas`, `valor_hora_ordinaria`, `porcentaje_aplicado`, `valor_total`, `estado`, `solicitante_id`, `aprobador_id`, `justificacion`, `observaciones`, `soporte_documento`, `fecha_solicitud`, `fecha_aprobacion`, `fecha_pago`, `numero_nomina`) VALUES
(2, 2, 5, '2026-09-14', 'diurna', '00:00:00', '00:00:00', NULL, NULL, 1.00, 0.00, 0.00, NULL, NULL, NULL, 'rechazada', 2, 2, 'Prueba para verificar si guarda o no .. toca hacer pruebas', 'se niega por prueba', NULL, '2026-09-15 01:16:59', '2026-09-14 21:03:29', NULL, NULL),
(3, 2, 5, '2026-09-14', 'diurna', '00:00:00', '00:00:00', NULL, NULL, 1.00, 1.00, 0.00, NULL, NULL, NULL, 'aprobada', 2, 2, 'Documento o solicitud numero 2 para realizar pruebas de flujo .', 'Se permite por cuestion corporativa ', NULL, '2026-09-15 01:32:10', '2026-09-14 21:03:18', NULL, NULL),
(4, 2, 5, '2026-09-14', 'nocturna', '22:05:00', '23:05:00', NULL, NULL, 1.00, 1.00, 0.00, NULL, 75.00, NULL, 'aprobada', 2, 2, 'Prueba numero 3 para ver si sale en el plano del supervisor .', 'Prueba de aceptar ', NULL, '2026-09-15 02:06:05', '2026-09-14 21:14:57', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_accesos_kiosco`
--

DROP TABLE IF EXISTS `log_accesos_kiosco`;
CREATE TABLE IF NOT EXISTS `log_accesos_kiosco` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dispositivo_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL COMMENT 'Si se identificó con PIN o facial',
  `identificacion_manual` varchar(50) DEFAULT NULL COMMENT 'Si se usó teclado para ID',
  `metodo` enum('pin','facial','qr','teclado') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `fecha_acceso` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `dispositivo_id` (`dispositivo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones_usuario`
--

DROP TABLE IF EXISTS `notificaciones_usuario`;
CREATE TABLE IF NOT EXISTS `notificaciones_usuario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `recibir_email_alertas` tinyint(1) DEFAULT 1,
  `recibir_email_info` tinyint(1) DEFAULT 0,
  `recibir_push_alertas` tinyint(1) DEFAULT 1,
  `recibir_push_info` tinyint(1) DEFAULT 0,
  `resumen_diario_email` tinyint(1) DEFAULT 0,
  `hora_resumen` time DEFAULT '08:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `notificaciones_usuario`
--

INSERT INTO `notificaciones_usuario` (`id`, `usuario_id`, `recibir_email_alertas`, `recibir_email_info`, `recibir_push_alertas`, `recibir_push_info`, `resumen_diario_email`, `hora_resumen`) VALUES
(2, 2, 1, 0, 1, 0, 1, '08:00:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `novedades`
--

DROP TABLE IF EXISTS `novedades`;
CREATE TABLE IF NOT EXISTS `novedades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `tipo` enum('incapacidad','permiso','vacaciones','calamidad','licencia','compensatorio','remoto','comision','llegada_tarde','salida_anticipada','correccion_marcacion') NOT NULL,
  `subtipo` varchar(50) DEFAULT NULL COMMENT 'Ej: maternidad, paternidad, luto, etc.',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `horas_solicitadas` decimal(5,2) DEFAULT NULL COMMENT 'Para permisos por horas',
  `motivo` text NOT NULL,
  `soporte_documento` varchar(255) DEFAULT NULL COMMENT 'Ruta del archivo adjunto (PDF, imagen)',
  `aprobador_id` int(11) DEFAULT NULL,
  `estado` enum('pendiente','aprobada','rechazada','cancelada') DEFAULT 'pendiente',
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_aprobacion` datetime DEFAULT NULL,
  `observaciones_aprobador` text DEFAULT NULL,
  `dias_calculados` int(11) DEFAULT NULL COMMENT 'Calculado automáticamente',
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_fechas` (`fecha_inicio`,`fecha_fin`),
  KEY `aprobador_id` (`aprobador_id`),
  KEY `idx_novedades_fecha` (`fecha_inicio`,`empresa_id`),
  KEY `idx_novedades_usuario_fecha` (`usuario_id`,`fecha_inicio`,`fecha_fin`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `novedades`
--

INSERT INTO `novedades` (`id`, `usuario_id`, `empresa_id`, `tipo`, `subtipo`, `fecha_inicio`, `fecha_fin`, `horas_solicitadas`, `motivo`, `soporte_documento`, `aprobador_id`, `estado`, `fecha_solicitud`, `fecha_aprobacion`, `observaciones_aprobador`, `dias_calculados`) VALUES
(1, 2, 5, '', NULL, '2026-09-15', '2026-09-15', NULL, 'INCAPACIDAD POR 2 DIAS', NULL, NULL, 'aprobada', '2026-09-15 00:31:25', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registros_asistencia`
--

DROP TABLE IF EXISTS `registros_asistencia`;
CREATE TABLE IF NOT EXISTS `registros_asistencia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `sede_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `tipo_marcacion` enum('entrada','salida_almuerzo','regreso_almuerzo','salida') NOT NULL,
  `hora_registro` time NOT NULL,
  `hora_sistema` timestamp NOT NULL DEFAULT current_timestamp(),
  `metodo_marcacion` enum('foto','facial','qr','pin','manual','gps') NOT NULL,
  `foto_evidencia` varchar(255) DEFAULT NULL COMMENT 'Ruta de la imagen capturada',
  `coordenadas_gps` point DEFAULT NULL COMMENT 'Coordenadas geográficas',
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `dispositivo_info` text DEFAULT NULL COMMENT 'User Agent, IP, modelo de dispositivo',
  `estado` enum('validado','pendiente','rechazado','corregido') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL COMMENT 'ID del usuario que registró (para correcciones manuales)',
  PRIMARY KEY (`id`),
  KEY `idx_usuario_fecha` (`usuario_id`,`fecha`),
  KEY `idx_empresa_fecha` (`empresa_id`,`fecha`),
  KEY `idx_estado` (`estado`),
  KEY `sede_id` (`sede_id`),
  KEY `idx_registros_fecha` (`fecha`,`empresa_id`),
  KEY `idx_asistencia_usuario_fecha` (`usuario_id`,`fecha`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `registros_asistencia`
--

INSERT INTO `registros_asistencia` (`id`, `usuario_id`, `empresa_id`, `sede_id`, `fecha`, `tipo_marcacion`, `hora_registro`, `hora_sistema`, `metodo_marcacion`, `foto_evidencia`, `coordenadas_gps`, `lat`, `lng`, `dispositivo_info`, `estado`, `observaciones`, `creado_por`) VALUES
(1, 2, 5, 3, '2026-09-14', 'entrada', '21:26:19', '2026-09-15 02:26:19', 'facial', '/horion-time/public/uploads/marcaciones/marcacion_2_20260914_212619.jpg', NULL, 10.94720733, -74.78689069, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/152.0.0.0 Safari\\/537.36\",\"ip\":\"::1\"}', 'pendiente', '', 2),
(2, 2, 5, 3, '2026-09-14', 'salida_almuerzo', '21:27:45', '2026-09-15 02:27:45', 'foto', '/horion-time/public/uploads/marcaciones/marcacion_2_20260914_212745.jpg', NULL, 10.94713336, -74.78688076, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/152.0.0.0 Safari\\/537.36\",\"ip\":\"::1\"}', 'pendiente', 'Prueba de almuerzo ', 2),
(3, 2, 5, NULL, '2026-09-14', 'regreso_almuerzo', '21:28:20', '2026-09-15 02:28:20', 'facial', '/horion-time/public/uploads/marcaciones/marcacion_2_20260914_212820.jpg', NULL, 10.94711446, -74.78689202, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/152.0.0.0 Safari\\/537.36\",\"ip\":\"::1\"}', 'pendiente', '', 2),
(4, 2, 5, 5, '2026-09-15', 'salida', '07:57:59', '2026-09-15 12:57:59', 'foto', '/horion-time/public/uploads/marcaciones/marcacion_2_20260915_075759.jpg', NULL, 10.95308059, -74.78768865, '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/152.0.0.0 Safari\\/537.36\",\"ip\":\"::1\"}', 'pendiente', '', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes_log`
--

DROP TABLE IF EXISTS `reportes_log`;
CREATE TABLE IF NOT EXISTS `reportes_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL,
  `tipo_reporte` varchar(50) NOT NULL,
  `filtros_aplicados` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filtros_aplicados`)),
  `formato_exportacion` enum('excel','pdf','csv','impresion') NOT NULL,
  `registros_generados` int(11) DEFAULT 0,
  `fecha_generacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `empresa_id` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) DEFAULT NULL COMMENT 'NULL = Rol Super Admin Global',
  `nombre` varchar(100) NOT NULL,
  `permisos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{"crear_empresa": true, "eliminar_empresa": false, ...}' CHECK (json_valid(`permisos`)),
  PRIMARY KEY (`id`),
  KEY `empresa_id` (`empresa_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `empresa_id`, `nombre`, `permisos`) VALUES
(1, NULL, 'SuperAdmin', '{\"crear\": true, \"editar\": true, \"transferir\": true, \"eliminar\": true, \"ver_todo\": true}'),
(2, NULL, 'Admin_Empresa', '{\"crear\": true, \"editar\": true, \"transferir\": true, \"eliminar\": true, \"ver_todo\": true}'),
(3, NULL, 'Editor_Activos', '{\"crear\": true, \"editar\": true, \"transferir\": true, \"eliminar\": false, \"ver_todo\": false}'),
(4, NULL, 'Lector_Activos', '{\"crear\": false, \"editar\": false, \"transferir\": false, \"eliminar\": false, \"ver_todo\": false}'),
(5, NULL, 'RRHH', '{\"crear\": true, \"editar\": true, \"transferir\": false, \"eliminar\": false, \"ver_todo\": true}'),
(6, NULL, 'Supervisor', '{\"crear\": false, \"editar\": false, \"transferir\": false, \"eliminar\": false, \"ver_todo\": false}'),
(7, NULL, 'Empleado', '{\"crear\": false, \"editar\": false, \"transferir\": false, \"eliminar\": false, \"ver_todo\": false}');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sedes`
--

DROP TABLE IF EXISTS `sedes`;
CREATE TABLE IF NOT EXISTS `sedes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `direccion` varchar(255) NOT NULL,
  `ciudad` varchar(100) NOT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `lat` decimal(10,8) DEFAULT NULL COMMENT 'Latitud GPS',
  `lng` decimal(11,8) DEFAULT NULL COMMENT 'Longitud GPS',
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sedes`
--

INSERT INTO `sedes` (`id`, `empresa_id`, `nombre`, `direccion`, `ciudad`, `telefono`, `lat`, `lng`, `estado`) VALUES
(1, 1, 'Sede Principal Bogotá', 'Calle 100 #15-20', 'Bogotá', '+57 1 234 5678', 4.67620000, -74.05210000, 'activo'),
(2, 5, 'Campbell Soledad', 'calle 20#1-23', 'Soledad', '', NULL, NULL, 'activo'),
(3, 5, 'Campbell Malambo', 'calle 41#1-123', 'Malambo', '', NULL, NULL, 'activo'),
(4, 1, 'Horion Time S.A.S. – Sede Central', '', '', NULL, NULL, NULL, 'activo'),
(5, 5, 'Fundacion Campbell – Sede Central', '', '', NULL, NULL, NULL, 'activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_novedad`
--

DROP TABLE IF EXISTS `tipos_novedad`;
CREATE TABLE IF NOT EXISTS `tipos_novedad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `requiere_soporte` tinyint(1) DEFAULT 0,
  `requiere_aprobacion` tinyint(1) DEFAULT 1,
  `afecta_nomina` tinyint(1) DEFAULT 1,
  `color` varchar(7) DEFAULT '#03a950',
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_codigo` (`empresa_id`,`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tipos_novedad`
--

INSERT INTO `tipos_novedad` (`id`, `empresa_id`, `nombre`, `codigo`, `requiere_soporte`, `requiere_aprobacion`, `afecta_nomina`, `color`, `activo`) VALUES
(1, 1, 'Incapacidad General', 'INC_GEN', 1, 1, 1, '#e53935', 1),
(2, 1, 'Incapacidad Laboral', 'INC_LAB', 1, 1, 1, '#d32f2f', 1),
(3, 1, 'Licencia Maternidad', 'LIC_MAT', 1, 1, 1, '#e91e63', 1),
(4, 1, 'Licencia Paternidad', 'LIC_PAT', 1, 1, 1, '#9c27b0', 1),
(5, 1, 'Licencia de Luto', 'LIC_LUT', 1, 1, 1, '#607d8b', 1),
(6, 1, 'Vacaciones', 'VAC', 0, 1, 1, '#03a950', 1),
(7, 1, 'Permiso Remunerado', 'PER_REM', 0, 1, 1, '#2196f3', 1),
(8, 1, 'Permiso No Remunerado', 'PER_NREM', 0, 1, 0, '#ff9800', 1),
(9, 1, 'Calamidad Doméstica', 'CAL_DOM', 1, 1, 1, '#f44336', 1),
(10, 1, 'Día Compensatorio', 'COMP', 0, 1, 1, '#4caf50', 1),
(11, 1, 'Trabajo Remoto', 'REMOTO', 0, 1, 0, '#00bcd4', 1),
(12, 1, 'Comisión de Servicios', 'COMISION', 0, 1, 0, '#795548', 1),
(13, 1, 'Llegada Tarde', 'TARDE', 0, 0, 0, '#ffeb3b', 1),
(14, 1, 'Salida Anticipada', 'SAL_ANT', 0, 0, 0, '#ffc107', 1),
(15, 1, 'Corrección Marcación', 'COR_MAR', 0, 1, 0, '#607d8b', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turnos`
--

DROP TABLE IF EXISTS `turnos`;
CREATE TABLE IF NOT EXISTS `turnos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `horario_id` int(11) NOT NULL,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  `nombre` varchar(100) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `tipo` enum('dia','tarde','noche','rotativo','personalizado') DEFAULT 'dia',
  `color_calendario` varchar(7) DEFAULT '#03a950' COMMENT 'Color HEX para calendario',
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `dia_semana` tinyint(4) NOT NULL DEFAULT 0,
  `hora_entrada` time NOT NULL DEFAULT '00:00:00',
  `hora_salida` time NOT NULL DEFAULT '00:00:00',
  `es_descanso` tinyint(1) NOT NULL DEFAULT 0,
  `tolerancia_entrada_min` int(11) NOT NULL DEFAULT 10,
  `tolerancia_salida_min` int(11) NOT NULL DEFAULT 10,
  PRIMARY KEY (`id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `idx_horario` (`horario_id`),
  KEY `idx_turnos_horario` (`horario_id`,`dia_semana`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `turnos`
--

INSERT INTO `turnos` (`id`, `horario_id`, `empresa_id`, `nombre`, `hora_inicio`, `hora_fin`, `tipo`, `color_calendario`, `descripcion`, `activo`, `dia_semana`, `hora_entrada`, `hora_salida`, `es_descanso`, `tolerancia_entrada_min`, `tolerancia_salida_min`) VALUES
(11, 14, 1, '', '00:00:00', '00:00:00', 'dia', '#03a950', NULL, 1, 0, '07:00:00', '19:00:00', 1, 10, 10),
(12, 14, 1, '', '00:00:00', '00:00:00', 'dia', '#03a950', NULL, 1, 1, '07:00:00', '19:00:00', 0, 10, 10),
(13, 14, 1, '', '00:00:00', '00:00:00', 'dia', '#03a950', NULL, 1, 2, '07:00:00', '19:00:00', 0, 10, 10),
(14, 14, 1, '', '00:00:00', '00:00:00', 'dia', '#03a950', NULL, 1, 3, '07:00:00', '19:00:00', 0, 10, 10),
(15, 14, 1, '', '00:00:00', '00:00:00', 'dia', '#03a950', NULL, 1, 4, '07:00:00', '19:00:00', 0, 10, 10),
(16, 14, 1, '', '00:00:00', '00:00:00', 'dia', '#03a950', NULL, 1, 5, '07:00:00', '19:00:00', 0, 10, 10),
(17, 14, 1, '', '00:00:00', '00:00:00', 'dia', '#03a950', NULL, 1, 6, '07:00:00', '19:00:00', 1, 10, 10);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turnos_asignados`
--

DROP TABLE IF EXISTS `turnos_asignados`;
CREATE TABLE IF NOT EXISTS `turnos_asignados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `turno_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `sede_id` int(11) DEFAULT NULL,
  `estado` enum('programado','completado','cancelado','ausente') DEFAULT 'programado',
  `observaciones` text DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_fecha` (`usuario_id`,`fecha`),
  KEY `turno_id` (`turno_id`),
  KEY `sede_id` (`sede_id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  `rol_id` int(11) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `identificacion` varchar(50) DEFAULT NULL,
  `jefe_inmediato_id` int(11) DEFAULT NULL,
  `cargo` varchar(100) DEFAULT NULL,
  `fecha_contratacion` date DEFAULT NULL,
  `sede_id` int(11) DEFAULT NULL,
  `horario_asignado_id` int(11) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `tipo_documento` varchar(30) DEFAULT 'cc',
  `fecha_nacimiento` date DEFAULT NULL,
  `tipo_contrato` varchar(50) DEFAULT 'indefinido',
  `salario_base` decimal(12,2) DEFAULT NULL,
  `equipo_computo` varchar(100) DEFAULT NULL,
  `eps` varchar(100) DEFAULT NULL,
  `arl` varchar(100) DEFAULT NULL,
  `fondo_pension` varchar(100) DEFAULT NULL,
  `tipo_sangre` varchar(10) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `es_jefe` tinyint(1) NOT NULL DEFAULT 0,
  `area` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `empresa_id` (`empresa_id`),
  KEY `rol_id` (`rol_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `empresa_id`, `rol_id`, `nombre_completo`, `email`, `password_hash`, `estado`, `fecha_creacion`, `identificacion`, `jefe_inmediato_id`, `cargo`, `fecha_contratacion`, `sede_id`, `horario_asignado_id`, `telefono`, `direccion`, `tipo_documento`, `fecha_nacimiento`, `tipo_contrato`, `salario_base`, `equipo_computo`, `eps`, `arl`, `fondo_pension`, `tipo_sangre`, `fecha_ingreso`, `es_jefe`, `area`) VALUES
(1, 1, 1, 'Horacio Quijano Bravo', 'inghoracioquijano@gmail.com', '$2y$10$/2Vbg7mlLT.TqtEs.M87/OQgwCRshIJuD8l6hKOeIRQYKWGJCO7cG', 'activo', '2026-09-14 18:42:43', '13570273', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'cc', NULL, 'indefinido', NULL, NULL, NULL, NULL, NULL, NULL, '2024-04-14', 0, NULL),
(2, 1, 1, 'Super Administrador', 'superadmin@horiontime.co', '$2y$10$W0B6Z2QJoqEkC6ZpGAX90.rDZHNCL9GS0ZffnB6HSUuBI5Cz/KNh.', 'activo', '2026-09-14 19:09:53', '000000000', NULL, 'Super Administrador de Plataforma', '2026-09-14', NULL, NULL, NULL, NULL, 'cc', NULL, 'indefinido', NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-14', 0, NULL),
(4, 5, 2, 'Horacio Admin', 'horacioquijano@gmail.com', '$2y$10$sLPLOK2wXgP0trN4UunoLO1eg0kxfTXNrZT5zaYQqoJrhuEgTiEHe', 'activo', '2026-09-14 23:33:58', '13570273', NULL, 'Cordinador de Sistemas', NULL, 5, NULL, '3008592767', NULL, 'cc', NULL, 'indefinido', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'sistemas'),
(5, 5, 7, 'Jasmin Gerra', 'usuario@gmail.com', '$2y$10$stIa8jw9aRxHjxc905lBkuGT04Ojz0nD1MFSyGh2IvJMIor7ohPUO', 'activo', '2026-09-15 13:04:48', '123123123', 4, 'Aseadora', NULL, 5, 14, '3003004569', NULL, 'cc', NULL, 'indefinido', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Zonas de la clinica');

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `accesos_supervisor`
--
ALTER TABLE `accesos_supervisor`
  ADD CONSTRAINT `accesos_supervisor_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `alertas`
--
ALTER TABLE `alertas`
  ADD CONSTRAINT `alertas_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alertas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD CONSTRAINT `api_tokens_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `api_tokens_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `configuracion_empresa`
--
ALTER TABLE `configuracion_empresa`
  ADD CONSTRAINT `configuracion_empresa_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `configuracion_recargos`
--
ALTER TABLE `configuracion_recargos`
  ADD CONSTRAINT `configuracion_recargos_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `dashboard_cache`
--
ALTER TABLE `dashboard_cache`
  ADD CONSTRAINT `dashboard_cache_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `delegaciones_supervision`
--
ALTER TABLE `delegaciones_supervision`
  ADD CONSTRAINT `delegaciones_supervision_ibfk_1` FOREIGN KEY (`supervisor_original_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `delegaciones_supervision_ibfk_2` FOREIGN KEY (`supervisor_delegado_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `dispositivos`
--
ALTER TABLE `dispositivos`
  ADD CONSTRAINT `dispositivos_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dispositivos_ibfk_2` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `empleado_horario`
--
ALTER TABLE `empleado_horario`
  ADD CONSTRAINT `empleado_horario_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `empleado_horario_ibfk_2` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD CONSTRAINT `horarios_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `horas_extras`
--
ALTER TABLE `horas_extras`
  ADD CONSTRAINT `horas_extras_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `horas_extras_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `horas_extras_ibfk_3` FOREIGN KEY (`solicitante_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `horas_extras_ibfk_4` FOREIGN KEY (`aprobador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `log_accesos_kiosco`
--
ALTER TABLE `log_accesos_kiosco`
  ADD CONSTRAINT `log_accesos_kiosco_ibfk_1` FOREIGN KEY (`dispositivo_id`) REFERENCES `dispositivos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notificaciones_usuario`
--
ALTER TABLE `notificaciones_usuario`
  ADD CONSTRAINT `notificaciones_usuario_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `novedades`
--
ALTER TABLE `novedades`
  ADD CONSTRAINT `novedades_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `novedades_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `novedades_ibfk_3` FOREIGN KEY (`aprobador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `registros_asistencia`
--
ALTER TABLE `registros_asistencia`
  ADD CONSTRAINT `registros_asistencia_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registros_asistencia_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registros_asistencia_ibfk_3` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `reportes_log`
--
ALTER TABLE `reportes_log`
  ADD CONSTRAINT `reportes_log_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reportes_log_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `sedes`
--
ALTER TABLE `sedes`
  ADD CONSTRAINT `sedes_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tipos_novedad`
--
ALTER TABLE `tipos_novedad`
  ADD CONSTRAINT `tipos_novedad_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `turnos`
--
ALTER TABLE `turnos`
  ADD CONSTRAINT `turnos_ibfk_1` FOREIGN KEY (`horario_id`) REFERENCES `horarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `turnos_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `turnos_asignados`
--
ALTER TABLE `turnos_asignados`
  ADD CONSTRAINT `turnos_asignados_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `turnos_asignados_ibfk_2` FOREIGN KEY (`turno_id`) REFERENCES `turnos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `turnos_asignados_ibfk_3` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`);

DELIMITER $$
--
-- Eventos
--
DROP EVENT IF EXISTS `limpiar_logs_antiguos`$$
CREATE DEFINER=`root`@`localhost` EVENT `limpiar_logs_antiguos` ON SCHEDULE EVERY 1 MONTH STARTS '2026-09-09 11:45:38' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    DELETE FROM audit_log WHERE fecha_hora < DATE_SUB(NOW(), INTERVAL 24 MONTH);
END$$

DROP EVENT IF EXISTS `limpiar_delegaciones`$$
CREATE DEFINER=`root`@`localhost` EVENT `limpiar_delegaciones` ON SCHEDULE EVERY 1 DAY STARTS '2026-09-09 13:03:36' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE delegaciones_supervision SET estado = 'vencida' 
  WHERE estado = 'activa' AND fecha_fin < CURDATE()$$

DROP EVENT IF EXISTS `limpiar_api_log`$$
CREATE DEFINER=`root`@`localhost` EVENT `limpiar_api_log` ON SCHEDULE EVERY 1 DAY STARTS '2026-09-09 13:21:58' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM api_log WHERE creado_en < DATE_SUB(NOW(), INTERVAL 90 DAY)$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
