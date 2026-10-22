<?php
// config/database.php

// Sube 3 niveles desde /config/ para guardar la base fuera del directorio /www/
$dbPath = dirname(__DIR__, 2) . '/db/db_presentismo.sqlite';

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 1. Tabla de empleados
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS empleados (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            legajo TEXT UNIQUE,
            dni TEXT UNIQUE,
            apellido TEXT NOT NULL,
            nombre TEXT NOT NULL,
            cargo TEXT,
            activo INTEGER DEFAULT 1
        );
    ");

    // 2. Tabla de motivos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS motivos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL UNIQUE,
            limite_anual INTEGER DEFAULT 0
        );
    ");

    // 3. Tabla de inasistencias
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inasistencias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empleado_id INTEGER NOT NULL,
            fecha DATE NOT NULL,
            fecha_fin DATE,
            motivo TEXT,
            FOREIGN KEY (empleado_id) REFERENCES empleados(id)
        );
    ");

    // 4. Tabla de créditos de feria
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS creditos_feria (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empleado_id INTEGER NOT NULL,
            dias_generados INTEGER NOT NULL,
            concepto TEXT,
            fecha_carga DATE DEFAULT (date('now')),
            FOREIGN KEY (empleado_id) REFERENCES empleados(id)
        );
    ");

    // =========================================================================
    // ACTUALIZACIÓN AUTO-MIGRACIÓN (Añade columnas a tablas existentes viejas)
    // =========================================================================
    try { $pdo->exec("ALTER TABLE inasistencias ADD COLUMN fecha_fin DATE"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE inasistencias ADD COLUMN motivo TEXT"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE empleados ADD COLUMN dni TEXT"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE empleados ADD COLUMN activo INTEGER DEFAULT 1"); } catch (PDOException $e) {}

} catch (PDOException $e) {
    die("Error de conexión con la base de datos: " . $e->getMessage());
}