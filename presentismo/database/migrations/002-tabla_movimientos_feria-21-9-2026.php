CREATE TABLE IF NOT EXISTS movimientos_feria (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    empleado_id INTEGER NOT NULL,
    tipo TEXT NOT NULL, -- 'SUMAR' o 'DESCONTAR'
    dias INTEGER NOT NULL,
    periodo TEXT,       -- Ej: "Feria Enero 2026" o "Feria Julio 2026"
    concepto TEXT,      -- Ej: "Guardia efectuada" o "Ajuste de días"
    fecha_carga DATE DEFAULT (date('now')),
    FOREIGN KEY (empleado_id) REFERENCES empleados(id)
);