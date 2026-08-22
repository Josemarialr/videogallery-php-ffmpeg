
<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($titulo_pagina) ? htmlspecialchars($titulo_pagina) . " - Mi Stream" : "Plataforma de Videos" ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- jQuery CDN -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap 5 JS Bundle (con Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Estilos Personalizados Globales -->
    <style>
        body {
            background-color: #0f0f0f;
            color: #f1f1f1;
        }
        .card-custom {
            background-color: #1f1f1f;
            border: 1px solid #333;
            border-radius: 8px;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .card-custom:hover {
            border-color: #0d6efd;
            transform: translateY(-2px);
        }
        .thumb-container {
            position: relative;
            width: 100%;
            padding-top: 56.25%; /* Relacion de aspecto 16:9 */
            background-color: #000;
            overflow: hidden;
            border-radius: 6px 6px 0 0;
        }
        .thumb-container img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .duration-badge {
            position: absolute;
            bottom: 6px;
            right: 6px;
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            font-size: 0.75rem;
            padding: 2px 5px;
            border-radius: 4px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<!-- BARRA DE NAVEGACIÓN -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary sticky-top shadow-sm">
    <div class="container">
        <!-- LOGO / NOMBRE -->
        <a class="navbar-brand fw-bold text-primary fs-4" href="index.php">
            ▶️ StreamGallery
        </a>

        <!-- BOTÓN TOGGLE MOBILE -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse mt-2 mt-lg-0" id="navbarContent">
            <!-- ENLACES PRINCIPALES -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active fw-bold' : '' ?>" href="index.php">Inicio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'masvistos.php') ? 'active fw-bold text-warning' : '' ?>" href="masvistos.php">
                        🔥 Más Vistos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'masvotados.php') ? 'active fw-bold text-warning' : '' ?>" href="masvotados.php">
                        🔥 Más Votados
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'fechas.php') ? 'active fw-bold text-warning' : '' ?>" href="fechas.php">
                        🔥 Por Fechas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'tags.php') ? 'active fw-bold text-warning' : '' ?>" href="tags.php">
                        🔥 Tags
                    </a>
                </li>
            </ul>



            <!-- CONTROLES DE USUARIO -->
            <div class="d-flex align-items-center gap-2 ms-auto mt-2 mt-lg-0">
                <?php if (estaAutenticado()): ?>
                    <a href="user.php" class="btn btn-outline-info btn-sm fw-semibold">
                        👤 Mi Cuenta / Playlist
                    </a>

                    <?php if (function_exists('esAdmin') && esAdmin()): ?>
                        <a href="admin.php" class="btn btn-warning btn-sm fw-bold">
                            ⚙️ Admin
                        </a>
                    <?php endif; ?>

                    <a href="logout.php" class="btn btn-outline-danger btn-sm">
                        Salir
                    </a>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#modalLogin">
                        Ingresar
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRegister">
                        Registrarse
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- MODAL LOGIN GLOBAL -->
<div class="modal fade" id="modalLogin" tabindex="-1" aria-labelledby="modalLoginLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title" id="modalLoginLabel">🔑 Iniciar Sesión</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form action="login.php" method="POST">
        <div class="modal-body">
            <div class="mb-3">
                <label for="login_email" class="form-label">Correo Electrónico</label>
                <input type="email" name="email" id="login_email" class="form-control bg-secondary text-white border-0" placeholder="tu@email.com" required>
            </div>
            <div class="mb-3">
                <label for="login_password" class="form-label">Contraseña</label>
                <input type="password" name="password" id="login_password" class="form-control bg-secondary text-white border-0" placeholder="••••••••" required>
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Ingresar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL REGISTRO GLOBAL -->
<div class="modal fade" id="modalRegister" tabindex="-1" aria-labelledby="modalRegisterLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-white border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title" id="modalRegisterLabel">📝 Crear una Cuenta</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form action="registro.php" method="POST">
        <div class="modal-body">
            <div class="mb-3">
                <label for="reg_nombre" class="form-label">Nombre Completo</label>
                <input type="text" name="nombre" id="reg_nombre" class="form-control bg-secondary text-white border-0" placeholder="Ej: Juan Pérez" required>
            </div>
            <div class="mb-3">
                <label for="reg_email" class="form-label">Correo Electrónico</label>
                <input type="email" name="email" id="reg_email" class="form-control bg-secondary text-white border-0" placeholder="tu@email.com" required>
            </div>
            <div class="mb-3">
                <label for="reg_password" class="form-label">Contraseña</label>
                <input type="password" name="password" id="reg_password" class="form-control bg-secondary text-white border-0" placeholder="Mínimo 6 caracteres" required minlength="6">
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Registrarse</button>
        </div>
      </form>
    </div>
  </div>
</div>
