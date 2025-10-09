<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

/** Admin o Gerencia = todo visible */
function isSuper(): bool {
  $r = $_SESSION['user']['rol'] ?? '';
  return in_array($r, ['Admin','Gerencia'], true);
}

/** Verifica permiso salvo que sea Admin/Gerencia */
function can(string $perm): bool {
  if (isSuper()) return true;
  return in_array($perm, $_SESSION['perms'] ?? []);
}

$usuarioNombre = 'Usuario';
if (!empty($_SESSION['user']['nombre'])) {
  $usuarioNombre = trim(($_SESSION['user']['nombre'] ?? '') . ' ' . ($_SESSION['user']['apellido'] ?? ''));
}
?>
<aside class="main-sidebar">
  <section class="sidebar">

    <!-- Usuario -->
    <div class="user-panel">
      <div class="pull-left image">
        <img src="Views/Images/Users/images.test.jpg" class="img-circle" alt="User Image">
      </div>
      <div class="pull-left info">
        <p><?php echo htmlspecialchars($usuarioNombre); ?></p>
        <a href="#"><i class="fa fa-circle text-success"></i> Online</a>
      </div>
    </div>

    <!-- Buscar -->
    <form action="#" method="get" class="sidebar-form">
      <div class="input-group">
        <input type="text" name="q" class="form-control" placeholder="Buscar...">
        <span class="input-group-btn">
          <button type="submit" name="search" id="search-btn" class="btn btn-flat">
            <i class="fa fa-search"></i>
          </button>
        </span>
      </div>
    </form>

    <!-- Menú -->
    <ul class="sidebar-menu" data-widget="tree">

      <!-- Dashboard -->
      <?php if (isSuper() || can('dashboard.ver')): ?>
      <li class="header">PANEL PRINCIPAL</li>
      <li><a href="Dashboard"><i class="fa fa-dashboard"></i> <span>Dashboard</span></a></li>
      <?php endif; ?>

      <!-- Ventas -->
      <?php if (isSuper() || can('ventas.ver') || can('pagos.ver')): ?>
      <li class="header">VENTAS</li>
      <li class="treeview">
        <a href="#"><i class="fa fa-shopping-cart"></i> <span>Gestión de Ventas</span>
          <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
        </a>
        <ul class="treeview-menu">
          <?php if (isSuper() || can('ventas.ver')): ?>
          <li><a href="Expedientes"><i class="fa fa-file-text-o"></i> Expedientes (ventas)</a></li>
          <?php endif; ?>
          <?php if (isSuper() || can('pagos.ver')): ?>
          <li><a href="Pagos"><i class="fa fa-money"></i> Pagos</a></li>
          <?php endif; ?>
          <?php if (isSuper() || can('ventas.ver')): ?>
          <li><a href="Calendario"><i class="fa fa-calendar"></i> Calendario de Ventas</a></li>
          <?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <!-- Operaciones y Logística -->
      <?php if (isSuper() || can('operaciones.ver') || can('programacion.ver') || can('inventario.ver')): ?>
      <li class="header">OPERACIONES Y LOGÍSTICA</li>

      <?php if (isSuper() || can('operaciones.ver') || can('programacion.ver')): ?>
      <li class="treeview">
        <a href="#"><i class="fa fa-cogs"></i> <span>Operaciones</span>
          <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
        </a>
        <ul class="treeview-menu">
          <?php if (isSuper() || can('operaciones.ver')): ?>
          <li><a href="ServiciosOperaciones"><i class="fa fa-list-alt"></i> Servicios / Reservas</a></li>
          <?php endif; ?>
          <?php if (isSuper() || can('programacion.ver')): ?>
          <li><a href="Programacion"><i class="fa fa-calendar-check-o"></i> Programación diaria</a></li>
          <?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <?php if (isSuper() || can('inventario.ver')): ?>
      <li class="treeview">
        <a href="#"><i class="fa fa-archive"></i> <span>Inventario</span>
          <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
        </a>
        <ul class="treeview-menu">
          <li><a href="InventarioBienes"><i class="fa fa-cube"></i> Bienes</a></li>
          <li><a href="InventarioMovimientos"><i class="fa fa-exchange"></i> Movimientos</a></li>
        </ul>
      </li>
      <?php endif; ?>

      <?php endif; ?>

      <!-- Contabilidad -->
      <?php if (isSuper() || can('contabilidad.ver') || can('caja.ver')): ?>
      <li class="header">CONTABILIDAD</li>
      <li class="treeview">
        <a href="#"><i class="fa fa-calculator"></i> <span>Contabilidad</span>
          <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
        </a>
        <ul class="treeview-menu">
          <?php if (isSuper() || can('contabilidad.ver') || can('contabilidad.movimientos')): ?>
          <li><a href="ContabilidadMovimientos"><i class="fa fa-list"></i> Movimientos</a></li>
          <?php endif; ?>
          <?php if (isSuper() || can('contabilidad.ver')): ?>
          <li><a href="ContabilidadCategorias"><i class="fa fa-tags"></i> Categorías</a></li>
          <?php endif; ?>
          <?php if (isSuper() || can('caja.ver')): ?>
          <li class="treeview">
            <a href="#"><i class="fa fa-briefcase"></i> Caja Chica
              <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
            </a>
            <ul class="treeview-menu">
              <li><a href="CajaChica"><i class="fa fa-briefcase"></i> Cajas</a></li>
              <li><a href="CajaChicaMovimientos"><i class="fa fa-list-ul"></i> Movimientos de caja</a></li>
            </ul>
          </li>
          <?php endif; ?>
          <?php if (isSuper() || can('contabilidad.ver')): ?>
          <li><a href="ReportesContables"><i class="fa fa-line-chart"></i> Reportes</a></li>
          <?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <!-- Marketing -->
      <?php if (isSuper() || can('marketing.ver') || can('marketing.tareas') || can('marketing.campanas')): ?>
      <li class="header">MARKETING</li>
      <li class="treeview">
        <a href="#"><i class="fa fa-bullhorn"></i> <span>Marketing</span>
          <span class="pull-right-container"><i class="fa fa-angle-left pull-right"></i></span>
        </a>
        <ul class="treeview-menu">
          <?php if (isSuper() || can('marketing.ver') || can('marketing.tareas')): ?>
          <li><a href="MarketingTareas"><i class="fa fa-check-square-o"></i> Tareas</a></li>
          <?php endif; ?>
          <?php if (isSuper() || can('marketing.ver')): ?>
          <li><a href="Plantillas"><i class="fa fa-file-text-o"></i> Plantillas</a></li>
          <?php endif; ?>
          <?php if (isSuper() || can('marketing.campanas') || can('marketing.ver')): ?>
          <li><a href="Campanas"><i class="fa fa-envelope"></i> Campañas</a></li>
          <?php endif; ?>
          <?php if (isSuper() || can('marketing.ver')): ?>
          <li><a href="CalendarioMarketing"><i class="fa fa-calendar"></i> Calendario</a></li>
          <?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <!-- Postventa -->
      <?php if (isSuper() || can('postventa.ver')): ?>
      <li class="header">POSTVENTA</li>
      <li><a href="Postventa"><i class="fa fa-comments"></i> <span>Interacciones</span></a></li>
      <?php endif; ?>

      <!-- Maestros -->
      <?php if (isSuper() || can('maestros.ver') || can('clientes.ver') || can('agencias.ver') || can('proveedores.ver')): ?>
      <li class="header">MAESTROS</li>
      <?php if (isSuper() || can('clientes.ver')): ?>
      <li><a href="Clientes"><i class="fa fa-user"></i> <span>Clientes</span></a></li>
      <?php endif; ?>
      <?php if (isSuper() || can('agencias.ver')): ?>
      <li><a href="Agencias"><i class="fa fa-building"></i> <span>Agencias</span></a></li>
      <?php endif; ?>
      <?php if (isSuper() || can('proveedores.ver')): ?>
      <li><a href="Proveedores"><i class="fa fa-truck"></i> <span>Proveedores</span></a></li>
      <?php endif; ?>
      <?php endif; ?>

      <!-- Gerencia -->
      <?php if (isSuper() || can('gerencia.ver') || can('auditoria.ver')): ?>
      <li class="header">GERENCIA</li>
      <?php if (isSuper() || can('gerencia.ver')): ?>
      <li><a href="Gerencia"><i class="fa fa-eye"></i> <span>Panel Gerencial</span></a></li>
      <?php endif; ?>
      <?php if (isSuper() || can('auditoria.ver')): ?>
      <li><a href="Auditoria"><i class="fa fa-shield"></i> <span>Auditoría</span></a></li>
      <?php endif; ?>
      <?php endif; ?>

      <!-- Sistema -->
      <?php if (isSuper() || can('usuarios.gestionar') || can('roles.gestionar')): ?>
      <li class="header">SISTEMA</li>
      <?php if (isSuper() || can('usuarios.gestionar')): ?>
      <li><a href="Users"><i class="fa fa-users"></i> <span>Usuarios</span></a></li>
      <?php endif; ?>
      <?php if (isSuper() || can('roles.gestionar')): ?>
      <li><a href="Role"><i class="fa fa-key"></i> <span>Roles</span></a></li>
      <li><a href="Permisos"><i class="fa fa-lock"></i> <span>Permisos</span></a></li>
      <?php endif; ?>
      <?php endif; ?>

    </ul>
  </section>
</aside>
