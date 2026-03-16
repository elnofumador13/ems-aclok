<?php
$user = getenv('PANEL_USER') ?: 'admin';
$pass = getenv('PANEL_PASS') ?: 'change-me';

if (!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] !== $user ||
    $_SERVER['PHP_AUTH_PW'] !== $pass) {
    header('WWW-Authenticate: Basic realm="Traffic Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Autenticación requerida.';
    exit;
}

$logFile = __DIR__ . '/logs/visits.jsonl';
$rows = [];

if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $lines = array_reverse(array_slice($lines, -200));

    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            $rows[] = $decoded;
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel de Tráfico</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <section>
      <h2>Panel de Registros de Tráfico</h2>
      <div class="card">
        <p><strong>Tip:</strong> cambia credenciales con variables de entorno <code>PANEL_USER</code> y <code>PANEL_PASS</code>.</p>
      </div>

      <div class="card">
        <table>
          <thead>
            <tr>
              <th>Fecha</th>
              <th>IP</th>
              <th>País</th>
              <th>Bot</th>
              <th>Redirigir</th>
              <th>Motivo</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="6">Sin registros todavía.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['timestamp'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($row['ip'] ?? '-') ?></td>
                  <td><?= htmlspecialchars(($row['country_code'] ?? '-') . ' / ' . ($row['country_name'] ?? '-')) ?></td>
                  <td><?= !empty($row['is_bot']) ? 'Sí' : 'No' ?></td>
                  <td><?= !empty($row['allow_redirect']) ? 'Sí' : 'No' ?></td>
                  <td><?= htmlspecialchars($row['reason'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</body>
</html>
