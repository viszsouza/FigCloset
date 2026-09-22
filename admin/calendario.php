<?php
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$pageTitle = 'Calendário';
$activeMenu = 'calendario';

$view = $_GET['view'] ?? 'mes';
$refDate = $_GET['data'] ?? date('Y-m-d');
$ref = strtotime($refDate) ?: time();

function fetch_events(string $start, string $end): array {
    // Mostra também as reservas pendentes ("reservado"), úteis para o admin acompanhar,
    // mesmo que elas ainda não bloqueiem a agenda.
    $stmt = db()->prepare("SELECT a.*, p.name AS product_name, o.order_code, o.status AS order_status
                            FROM availability a
                            JOIN products p ON p.id = a.product_id
                            LEFT JOIN orders o ON o.id = a.order_id
                            WHERE a.status != 'devolvido'
                              AND ((a.start_date BETWEEN ? AND ?) OR (a.end_date BETWEEN ? AND ?))
                            ORDER BY a.start_date");
    $stmt->execute([$start, $end, $start, $end]);
    return $stmt->fetchAll();
}

require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-toolbar">
    <div style="display:flex; gap:8px;">
        <a href="?view=dia&data=<?= date('Y-m-d', $ref) ?>" class="btn btn-sm <?= $view === 'dia' ? 'btn-primary' : 'btn-outline-ink' ?>">Dia</a>
        <a href="?view=semana&data=<?= date('Y-m-d', $ref) ?>" class="btn btn-sm <?= $view === 'semana' ? 'btn-primary' : 'btn-outline-ink' ?>">Semana</a>
        <a href="?view=mes&data=<?= date('Y-m-d', $ref) ?>" class="btn btn-sm <?= $view === 'mes' ? 'btn-primary' : 'btn-outline-ink' ?>">Mês</a>
    </div>
</div>

<?php if ($view === 'mes'):
    $monthStart = date('Y-m-01', $ref);
    $monthEnd = date('Y-m-t', $ref);
    $events = fetch_events($monthStart, $monthEnd);
    $byDay = [];
    foreach ($events as $ev) {
        $byDay[$ev['start_date']][] = ['type' => 'entrega', 'ev' => $ev];
        $byDay[$ev['end_date']][] = ['type' => 'devolucao', 'ev' => $ev];
    }
    $firstWeekday = (int)date('N', strtotime($monthStart)); // 1=Mon..7=Sun
    $daysInMonth = (int)date('t', $ref);
    $prevMonth = date('Y-m-d', strtotime('-1 month', $ref));
    $nextMonth = date('Y-m-d', strtotime('+1 month', $ref));
?>
<div class="admin-card">
    <div class="admin-calendar-nav">
        <a href="?view=mes&data=<?= $prevMonth ?>" class="btn-ghost">← Mês anterior</a>
        <strong><?= e(ucfirst(strftime_pt((int)date('n', $ref)))) ?> de <?= date('Y', $ref) ?></strong>
        <a href="?view=mes&data=<?= $nextMonth ?>" class="btn-ghost">Próximo mês →</a>
    </div>
    <div class="admin-calendar-scroll">
        <div class="admin-calendar-head">
            <div>Seg</div><div>Ter</div><div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div><div>Dom</div>
        </div>
        <div class="admin-calendar-grid">
            <?php for ($i = 1; $i < $firstWeekday; $i++): ?><div></div><?php endfor; ?>
            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                $dateStr = date('Y-m-', $ref) . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
                $dayEvents = $byDay[$dateStr] ?? [];
            ?>
            <div style="border:1px solid var(--line); border-radius:4px; min-height:80px; padding:6px; background:var(--white);">
                <div style="font-size:0.78rem; color:var(--graphite-soft);"><?= $d ?></div>
                <?php foreach (array_slice($dayEvents, 0, 3) as $de): ?>
                    <div style="font-size:0.7rem; margin-top:3px; padding:2px 4px; border-radius:2px; background:<?= $de['type'] === 'entrega' ? '#FBF3F4' : '#EDF1F8' ?>;">
                        <?= $de['type'] === 'entrega' ? '↗' : '↘' ?> <?= e($de['ev']['product_name']) ?>
                    </div>
                <?php endforeach; ?>
                <?php if (count($dayEvents) > 3): ?><div style="font-size:0.68rem; color:var(--graphite-soft);">+<?= count($dayEvents) - 3 ?> mais</div><?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<?php else:
    if ($view === 'dia') {
        $start = date('Y-m-d', $ref);
        $end = $start;
    } else { // semana
        $start = date('Y-m-d', strtotime('monday this week', $ref));
        $end = date('Y-m-d', strtotime('sunday this week', $ref));
    }
    $events = fetch_events($start, $end);
?>
<div class="admin-card">
    <h3 style="margin-top:0;"><?= format_date_br($start) ?><?= $start !== $end ? ' a ' . format_date_br($end) : '' ?></h3>
    <table class="admin-table">
        <thead><tr><th>Peça</th><th>Pedido</th><th>Retirada</th><th>Devolução</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($events as $ev): ?>
            <tr>
                <td><?= e($ev['product_name']) ?></td>
                <td><?= e($ev['order_code'] ?? '—') ?></td>
                <td><?= format_date_br($ev['start_date']) ?></td>
                <td><?= format_date_br($ev['end_date']) ?></td>
                <td>
                    <span class="badge badge-status"><?= e(ucfirst(str_replace('_',' ',$ev['status']))) ?></span>
                    <?php if ($ev['status'] === 'reservado'): ?>
                        <span style="font-size:0.72rem; color:var(--graphite-soft); display:block;">não bloqueia a agenda</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($events)): ?>
            <tr><td colspan="5" style="text-align:center; color:var(--graphite-soft); padding:24px;">Nenhuma movimentação neste período.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
