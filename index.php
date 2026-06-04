<?php
// Splash page: redirects to login after 5 seconds
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bienvenido - Coosanandresito</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <script>
    // Redirect to login after 5 seconds
    setTimeout(function(){ window.location.href = 'login.php'; }, 5000);
  </script>
</head>
<body>
  <div class="splash-container">
    <div class="splash-card">
      <div class="logo-circle">
        <img src="assets/img/logo.jpg" alt="Logo Coosanandresito">
      </div>
      <h1>Bienvenido a Coosanandresito</h1>
      <div class="loader"></div>
    </div>
  </div>
</body>
</html>
