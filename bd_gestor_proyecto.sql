CREATE DATABASE IF NOT EXISTS gestion_proyectos CHARACTER SET utf8mb4 COLLATE UTF8MB4_UNICODE_CI;
USE gestion_proyectos;

-- 1. Tabla Rol
CREATE TABLE rol (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE NOT NULL,
    descripcion VARCHAR(150) NULL
) ENGINE=InnoDB;

-- 2. Tabla Usuario
CREATE TABLE usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    correo VARCHAR(120) UNIQUE NOT NULL,
    contrasena VARCHAR(120) NOT NULL,
    id_rol INT NOT NULL,
    estado ENUM('ACTIVO', 'INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    FOREIGN KEY (id_rol) REFERENCES rol(id_rol)
) ENGINE=InnoDB;

-- 3. Tabla Categoria
CREATE TABLE categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) UNIQUE NOT NULL,
    descripcion VARCHAR(150) NULL,
    estado ENUM('ACTIVA', 'INACTIVA') NOT NULL DEFAULT 'ACTIVA'
) ENGINE=InnoDB;

-- 4. Tabla Proyectos
CREATE TABLE proyectos (
    id_proyecto INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    descripcion TEXT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    estado ENUM('PENDIENTE', 'EN_PROCESO', 'FINALIZADO') NOT NULL DEFAULT 'PENDIENTE',
    id_usuario INT NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
) ENGINE=InnoDB;

-- 5. Tabla Tarea
CREATE TABLE tarea (
    id_tarea INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(120) NOT NULL,
    descripcion TEXT NULL,
    id_proyecto INT NOT NULL,
    id_categoria INT NOT NULL,
    prioridad ENUM('BAJA', 'MEDIA', 'ALTA') NOT NULL DEFAULT 'MEDIA',
    estado ENUM('PENDIENTE', 'EN_PROCESO', 'COMPLETA') NOT NULL DEFAULT 'PENDIENTE',
    fecha_limite DATE NULL,
    FOREIGN KEY (id_proyecto) REFERENCES proyectos(id_proyecto),
    FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria)
) ENGINE=InnoDB;

-- 6. Tabla Token de Acceso
CREATE TABLE token_acceso (
    id_token INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    fecha_creacion DATETIME NOT NULL,
    fecha_expiracion DATETIME NOT NULL,
    activo TINYINT NOT NULL DEFAULT 1,
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
) ENGINE=InnoDB;


-- ==========================================
-- INSERCIÓN DE DATOS INICIALES Y DE PRUEBA
-- ==========================================

-- Roles
INSERT INTO rol (id_rol, nombre, descripcion) VALUES
(1, 'administrador', 'Control total del sistema y gestión general'),
(2, 'usuario', 'Usuario estándar con acceso a proyectos y tareas asignadas');

-- Usuarios (con credenciales solicitadas y contraseñas intactas)
INSERT INTO usuario (id_usuario, nombre, usuario, correo, contrasena, id_rol, estado) VALUES
(1, 'Administrador General', 'admin', 'admin@sistema.com', 'ADmin*', 1, 'ACTIVO'),
(2, 'Ana Gómez', 'ana', 'ana@sistema.com', 'Ana2026*', 2, 'ACTIVO'),
(3, 'Carlos Pérez', 'carlos', 'carlos@sistema.com', 'Carlos2026*', 2, 'ACTIVO'),
(4, 'Usuario Inactivo', 'inactivo', 'inactivo@sistema.com', 'Inactivo2026*', 2, 'INACTIVO');

-- Categorías
INSERT INTO categoria (id_categoria, nombre, descripcion, estado) VALUES
(1, 'Backend', 'Desarrollo de lógica de servidor, APIs y bases de datos', 'ACTIVA'),
(2, 'Frontend', 'Interfaces de usuario y componentes visuales', 'ACTIVA'),
(3, 'Diseño UI/UX', 'Wireframes y prototipos de interfaz', 'ACTIVA'),
(4, 'Control de Calidad', 'Pruebas de software y depuración de errores', 'ACTIVA');

-- Proyectos
INSERT INTO proyectos (id_proyecto, nombre, descripcion, fecha_inicio, fecha_fin, estado, id_usuario) VALUES
(1, 'Sistema de Inventario', 'Plataforma web para control de stock en almacén.', '2026-01-10', '2026-05-30', 'FINALIZADO', 2),
(2, 'E-commerce TechStore', 'Tienda en línea con pasarela de pagos integrada.', '2026-04-01', '2026-10-30', 'EN_PROCESO', 2),
(3, 'App de Productividad', 'Aplicación móvil para gestión de tareas personales.', '2026-09-01', NULL, 'PENDIENTE', 3);

-- Tareas
INSERT INTO tarea (id_tarea, titulo, descripcion, id_proyecto, id_categoria, prioridad, estado, fecha_limite) VALUES
(1, 'Diseñar esquema de base de datos', 'Crear tablas relacionales en MySQL.', 1, 1, 'ALTA', 'COMPLETA', '2026-01-20'),
(2, 'Configurar autenticación JWT', 'Implementar seguridad basada en tokens.', 2, 1, 'ALTA', 'EN_PROCESO', '2026-06-15'),
(3, 'Maquetar carrito de compras', 'Diseñar vista responsiva con Tailwind CSS.', 2, 2, 'MEDIA', 'PENDIENTE', '2026-07-01'),
(4, 'Pruebas unitarias de login', 'Verificar respuestas de la API de usuarios.', 2, 4, 'BAJA', 'PENDIENTE', '2026-07-10');

-- Tokens de acceso
INSERT INTO token_acceso (id_token, id_usuario, token, fecha_creacion, fecha_expiracion, activo) VALUES
(1, 1, 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.admin_token_sample', '2026-10-01 08:00:00', '2026-11-01 08:00:00', 1),
(2, 2, 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.ana_token_sample', '2026-10-20 10:30:00', '2026-10-27 10:30:00', 1);