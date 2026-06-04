-- =====================================
-- 1. Crear base de datos
-- =====================================
CREATE DATABASE IF NOT EXISTS control_permisos_pruebas
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE control_permisos_pruebas;

-- =====================================
-- 2. Tabla: cargos
-- =====================================
CREATE TABLE cargo (
  id_cargo INT AUTO_INCREMENT PRIMARY KEY,
  nombre_cargo VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO cargo (nombre_cargo) VALUES 
('Administrador'),
('Administrativo'),
('Auxiliar'),
('Coordinador'),
('Gerencia');

-- =====================================
-- 3. Tabla: usuarios
-- =====================================
CREATE TABLE usuarios (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  usuario VARCHAR(50) NOT NULL UNIQUE,
  telefono VARCHAR(20),
  password VARCHAR(255) NOT NULL,

  id_cargo INT NOT NULL,
  area ENUM('Contabilidad', 'Riesgos', 'Operaciones', 'Sistemas', 'Parqueadero') DEFAULT NULL,

  codigo_recuperacion VARCHAR(255) DEFAULT NULL,
  fecha_codigo DATETIME DEFAULT NULL,

  tiempo_pendiente_recuperar TIME DEFAULT '00:00:00',

  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  id_coordinador INT DEFAULT NULL,

  FOREIGN KEY (id_cargo) REFERENCES cargo(id_cargo),
  FOREIGN KEY (id_coordinador) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================
-- 4. Tabla: permisos laborales
-- =====================================
CREATE TABLE permisos (
  id_permiso INT AUTO_INCREMENT PRIMARY KEY,

  id_usuario INT NOT NULL,

  tipo_permiso ENUM('Médicos', 'Laborales', 'Personales') NOT NULL,

  -- SOLO PARA MÉDICOS
  tipo_pago ENUM('remunerado', 'no_remunerado') DEFAULT NULL,

  motivo TEXT NOT NULL,
  documento_pdf VARCHAR(255) DEFAULT NULL,

  fecha_salida DATE NOT NULL,
  hora_salida TIME NOT NULL,

  fecha_regreso_aprox DATE NOT NULL,
  hora_regreso_aprox TIME NOT NULL,

  fecha_regreso_real DATE DEFAULT NULL,
  hora_regreso_real TIME DEFAULT NULL,

  encargado_ausencia VARCHAR(150) DEFAULT NULL,

  estado ENUM(
    'pendiente',
    'aprobado',
    'rechazado',
    'reenviado',
    'cancelado',
    'finalizado'
  ) DEFAULT 'pendiente',

  asignado_a ENUM(
    'auxiliar',
    'coordinador',
    'gerente',
    'administrador',
    'administrativo'
  ) DEFAULT 'coordinador',

  id_asignado INT DEFAULT NULL,

  motivo_rechazo TEXT DEFAULT NULL,

  tiempo_total_ausencia TIME DEFAULT NULL,
  genera_recuperacion BOOLEAN DEFAULT FALSE,

  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
  FOREIGN KEY (id_asignado) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================
-- 5. Tabla: recuperación de tiempo
-- =====================================
CREATE TABLE recuperacion_tiempo (
  id_recuperacion INT AUTO_INCREMENT PRIMARY KEY,

  id_usuario INT NOT NULL,
  id_permiso INT NOT NULL,

  -- Fecha de la solicitud
  fecha_solicitud DATE NOT NULL,
  hora_solicitud TIME NOT NULL,

  -- Inicio de la recuperación
  fecha_inicio_recuperacion DATE NOT NULL,
  hora_inicio_recuperacion TIME NOT NULL,

  -- Fin de la recuperación
  fecha_fin_recuperacion DATE NOT NULL,
  hora_fin_recuperacion TIME NOT NULL,

  -- Control de tiempo
  tiempo_a_recuperar TIME NOT NULL,
  tiempo_recuperado TIME DEFAULT '00:00:00',

  estado ENUM(
    'pendiente',
    'aprobado',
    'rechazado',
    'finalizado'
  ) DEFAULT 'pendiente',

  aprobado_por INT DEFAULT NULL,
  fecha_aprobacion DATE DEFAULT NULL,
  hora_aprobacion TIME DEFAULT NULL,

  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
  FOREIGN KEY (id_permiso) REFERENCES permisos(id_permiso),
  FOREIGN KEY (aprobado_por) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================
-- 6. Tabla: notificaciones
-- =====================================
CREATE TABLE notificaciones (
  id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  tipo ENUM(
    'permiso',
    'aprobacion',
    'rechazo',
    'recuperacion'
  ) DEFAULT 'permiso',
  mensaje TEXT NOT NULL,
  fecha_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
  leido BOOLEAN DEFAULT FALSE,

  FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


ALTER TABLE permisos 
ADD COLUMN fecha_recuperacion DATETIME DEFAULT NULL 
AFTER encargado_ausencia;
DESCRIBE permisos;
