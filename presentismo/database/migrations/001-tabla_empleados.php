        CREATE TABLE IF NOT EXISTS empleados (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            legajo TEXT UNIQUE,
            dni TEXT UNIQUE,
            apellido TEXT NOT NULL,
            nombre TEXT NOT NULL,
            cargo TEXT,
            activo INTEGER DEFAULT 1
        );


    // 2. Tabla de motivos
        CREATE TABLE IF NOT EXISTS motivos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL UNIQUE,
            limite_anual INTEGER DEFAULT 0
        );

    // 3. Tabla de inasistencias
        CREATE TABLE IF NOT EXISTS inasistencias (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empleado_id INTEGER NOT NULL,
            fecha DATE NOT NULL,
            fecha_fin DATE,
            motivo TEXT,
            FOREIGN KEY (empleado_id) REFERENCES empleados(id)
        );


    // 4. Tabla de créditos de feria
        CREATE TABLE IF NOT EXISTS creditos_feria (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empleado_id INTEGER NOT NULL,
            dias_generados INTEGER NOT NULL,
            concepto TEXT,
            fecha_carga DATE DEFAULT (date('now')),
            FOREIGN KEY (empleado_id) REFERENCES empleados(id)
        );
