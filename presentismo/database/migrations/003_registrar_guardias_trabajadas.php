-- Tabla para registrar las guardias trabajadas y el saldo generado por feria
CREATE TABLE IF NOT EXISTS guardias_feria (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    empleado_id INTEGER NOT NULL,
    periodo_feria TEXT NOT NULL, -- Ej: "Enero 2026" o "Julio 2026"
    fecha_desde DATE NOT NULL,
    fecha_hasta DATE NOT NULL,
    dias_credito INTEGER NOT NULL,
    observaciones TEXT,
    fecha_carga DATE DEFAULT (date('now')),
    FOREIGN KEY (empleado_id) REFERENCES empleados(id)
);

-- Modificación/Aseguramiento de la tabla inasistencias para asociar la feria compensada
-- (SQLite permite agregar columnas con ALTER TABLE)
ALTER TABLE inasistencias ADD COLUMN feria_origen TEXT;

ALTER TABLE inasistencias ADD COLUMN feria_origen TEXT;

