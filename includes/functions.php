<?php
require_once __DIR__ . '/../config/db.php';

// ---------------------------------------------------------
// Segurança / utilitários gerais
// ---------------------------------------------------------

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Token de segurança inválido. Recarregue a página e tente novamente.');
    }
}

function flash_set(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function redirect(string $path): void {
    header('Location: ' . BASE_URL . $path);
    exit;
}

// ---------------------------------------------------------
// Autenticação (clientes)
// ---------------------------------------------------------

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cache = null;
    if ($cache !== null) return $cache;
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND status = "ativo"');
    $stmt->execute([$_SESSION['user_id']]);
    $cache = $stmt->fetch() ?: null;
    return $cache;
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        flash_set('error', 'Faça login para continuar.');
        redirect('/login.php');
    }
    return $user;
}

function is_admin(): bool {
    return !empty($_SESSION['admin_id']);
}

function require_admin(): array {
    if (empty($_SESSION['admin_id'])) {
        redirect('/admin/login.php');
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND role = "admin"');
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    if (!$admin) {
        session_destroy();
        redirect('/admin/login.php');
    }
    return $admin;
}

function admin_log(int $adminId, string $action): void {
    $stmt = db()->prepare('INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)');
    $stmt->execute([$adminId, $action]);
}

// ---------------------------------------------------------
// Código único de pedido
// ---------------------------------------------------------

function generate_order_code(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem caracteres ambíguos (0,O,1,I)
    do {
        $code = 'VISZ-';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = db()->prepare('SELECT id FROM orders WHERE order_code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    return $code;
}

// ---------------------------------------------------------
// Motor de recomendação de tamanho
// ---------------------------------------------------------

function get_settings(): array {
    static $settings = null;
    if ($settings === null) {
        $settings = db()->query('SELECT * FROM settings WHERE id = 1')->fetch() ?: [];
    }
    return $settings;
}

/**
 * Calcula a taxa de entrega com base no subtotal do aluguel.
 * Retorna 0 se o subtotal atingir o valor de frete grátis configurado.
 */
function calculate_delivery_fee(float $subtotal): float {
    $s = get_settings();
    $fee = (float)($s['delivery_fee'] ?? 0);
    $freeAbove = (float)($s['delivery_free_above'] ?? 0);
    if ($freeAbove > 0 && $subtotal >= $freeAbove) {
        return 0.0;
    }
    return $fee;
}

function get_size_tolerance(): int {
    static $tolerance = null;
    if ($tolerance === null) {
        $stmt = db()->query('SELECT size_tolerance_cm FROM settings WHERE id = 1');
        $row = $stmt->fetch();
        $tolerance = $row ? (int)$row['size_tolerance_cm'] : 3;
    }
    return $tolerance;
}

/**
 * Compara as medidas do usuário com a faixa de medidas de um tamanho de peça.
 * Retorna null se alguma medida essencial estiver ausente.
 */
function calculate_size_match(array $userMeasurements, array $productSize): ?array {
    $tolerance = get_size_tolerance();
    $fields = [
        'bust'   => ['bust_min', 'bust_max'],
        'waist'  => ['waist_min', 'waist_max'],
        'hip'    => ['hip_min', 'hip_max'],
    ];

    $totalFields = 0;
    $scoreSum = 0;
    $outOfRange = false;

    foreach ($fields as $userKey => [$minKey, $maxKey]) {
        $userVal = $userMeasurements[$userKey] ?? null;
        $min = $productSize[$minKey] ?? null;
        $max = $productSize[$maxKey] ?? null;
        if ($userVal === null || $min === null || $max === null) {
            continue;
        }
        $totalFields++;
        $userVal = (float)$userVal;
        $min = (float)$min;
        $max = (float)$max;

        if ($userVal >= $min && $userVal <= $max) {
            // dentro da faixa: quanto mais perto do centro, maior o score
            $center = ($min + $max) / 2;
            $halfRange = max(($max - $min) / 2, 1);
            $distanceFromCenter = abs($userVal - $center) / $halfRange;
            $fieldScore = 1 - ($distanceFromCenter * 0.15); // pequena penalização por estar na borda
        } elseif ($userVal >= $min - $tolerance && $userVal <= $max + $tolerance) {
            // dentro da tolerância configurada, mas fora da faixa ideal
            $diff = $userVal < $min ? ($min - $userVal) : ($userVal - $max);
            $fieldScore = 0.75 - (0.25 * ($diff / max($tolerance, 1)));
        } else {
            $fieldScore = 0;
            $outOfRange = true;
        }
        $scoreSum += max($fieldScore, 0);
    }

    if ($totalFields === 0) {
        return null;
    }

    $percentage = round(($scoreSum / $totalFields) * 100);

    if ($outOfRange && $percentage < 40) {
        $label = 'Fora das suas medidas';
    } elseif ($percentage >= 90) {
        $label = 'Excelente ajuste';
    } elseif ($percentage >= 75) {
        $label = 'Bom ajuste';
    } elseif ($percentage >= 50) {
        $label = 'Ajuste possível';
    } else {
        $label = 'Fora das suas medidas';
    }

    return [
        'percentage' => (int)$percentage,
        'label' => $label,
    ];
}

/**
 * Dado um produto (com seus tamanhos) e as medidas do usuário,
 * retorna o melhor tamanho recomendado e sua pontuação.
 */
function get_best_size_for_product(array $productSizes, ?array $userMeasurements): ?array {
    if (!$userMeasurements) {
        return null;
    }
    $best = null;
    foreach ($productSizes as $size) {
        $match = calculate_size_match($userMeasurements, $size);
        if ($match === null) continue;
        if ($best === null || $match['percentage'] > $best['percentage']) {
            $best = array_merge($match, ['size' => $size['size']]);
        }
    }
    return $best;
}

function get_user_measurements(int $userId): ?array {
    $stmt = db()->prepare('SELECT * FROM measurements WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

// ---------------------------------------------------------
// Disponibilidade
// ---------------------------------------------------------

/**
 * Status de disponibilidade que efetivamente BLOQUEIAM a peça no período.
 * "reservado" (pedido aguardando confirmação do admin) NÃO bloqueia:
 * a peça só fica indisponível depois que o administrador confirma o pedido.
 */
function blocking_availability_statuses(): array {
    return ['confirmado', 'em_aluguel', 'manutencao', 'indisponivel'];
}

function blocking_statuses_sql(): string {
    return "'" . implode("','", blocking_availability_statuses()) . "'";
}

/**
 * Verifica se um produto está disponível no período informado.
 * Considera ocupado apenas quem já teve o pedido confirmado pelo admin
 * (ou peças em manutenção/indisponíveis marcadas manualmente).
 */
function is_product_available(int $productId, string $startDate, string $endDate, ?int $ignoreAvailabilityId = null): bool {
    $blocking = blocking_statuses_sql();
    $sql = "SELECT id FROM availability
            WHERE product_id = ?
              AND status IN ($blocking)
              AND start_date <= ?
              AND end_date >= ?";
    $params = [$productId, $endDate, $startDate];
    if ($ignoreAvailabilityId) {
        $sql .= ' AND id != ?';
        $params[] = $ignoreAvailabilityId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return !$stmt->fetch();
}

function get_product_busy_ranges(int $productId): array {
    $blocking = blocking_statuses_sql();
    $stmt = db()->prepare("SELECT start_date, end_date, status FROM availability
                            WHERE product_id = ? AND status IN ($blocking)
                            ORDER BY start_date");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

// ---------------------------------------------------------
// Formatação
// ---------------------------------------------------------

function format_price(float $value): string {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function format_date_br(string $date): string {
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : $date;
}

function status_label(string $status): string {
    $labels = [
        'aguardando_confirmacao' => 'Aguardando confirmação',
        'confirmado'             => 'Confirmado',
        'preparando'             => 'Preparando',
        'disponivel_retirada'    => 'Saiu para entrega',
        'em_aluguel'             => 'Em aluguel',
        'devolucao_pendente'     => 'Devolução pendente',
        'finalizado'             => 'Finalizado',
        'cancelado'              => 'Cancelado',
    ];
    return $labels[$status] ?? $status;
}

function strftime_pt(int $month): string {
    $meses = [1=>'janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    return $meses[$month] ?? '';
}

function slugify(string $text): string {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));
    return $text ?: 'item';
}
