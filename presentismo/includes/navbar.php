<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
    
        <a class="navbar-brand fw-bold" href="index.php"><img style="height: 40px; width: auto; padding: 4px; border-radius: 6px" src="logo-judicial.png"></img>Control de Presentismo</a>
        <div class="navbar-nav align-items-center">
            <?php if (isset($pagina_activa) && $pagina_activa === 'index'): ?>
                <a class="nav-link text-white small me-2" 
                   style="opacity: 0; transition: opacity 0.3s ease;" 
                   onmouseover="this.style.opacity='1';" 
                   onmouseout="this.style.opacity='0';" 
                   href="../database/migrate.php">
                   ⚙️ Actualizar base
                </a>
            <?php endif; ?>
            <a class="nav-link <?= (isset($pagina_activa) && $pagina_activa == 'index') ? 'active fw-bold' : ''; ?>" href="index.php">Inicio</a>
            <a class="nav-link <?= (isset($pagina_activa) && $pagina_activa == 'empleados') ? 'active fw-bold' : ''; ?>" href="empleados.php">Empleados</a>
            <a class="nav-link <?= (isset($pagina_activa) && $pagina_activa == 'motivos') ? 'active fw-bold' : ''; ?>" href="motivos.php">Motivos</a>
            <a class="nav-link <?= (isset($pagina_activa) && $pagina_activa == 'cargar_ausencia') ? 'active fw-bold' : ''; ?>" href="cargar_ausencia.php">Cargar Ausencia</a>
            <a class="nav-link <?= (isset($pagina_activa) && $pagina_activa == 'cargar_feria') ? 'active fw-bold' : ''; ?>" href="cargar_feria.php">Cargar Feria</a>
            <a class="nav-link <?= (isset($pagina_activa) && $pagina_activa == 'feriados') ? 'active fw-bold' : ''; ?>" href="feriados.php">Feriados / Asuetos</a>
            <a class="nav-link <?= (isset($pagina_activa) && $pagina_activa == 'generar_oficio') ? 'active fw-bold' : ''; ?>" href="generar_oficio.php">Generar Oficio</a>
        </div>
    </div>
</nav>