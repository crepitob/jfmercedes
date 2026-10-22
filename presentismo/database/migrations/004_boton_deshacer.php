CREATE TABLE IF NOT EXISTS adjuntos_inasistencias (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    inasistencia_id INTEGER NOT NULL,
    nombre_original TEXT NOT NULL,
    nombre_archivo TEXT NOT NULL,
    tipo_documento TEXT DEFAULT 'certificado', -- 'certificado' o 'nota'
    fecha_subida DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inasistencia_id) REFERENCES inasistencias(id) ON DELETE CASCADE
);