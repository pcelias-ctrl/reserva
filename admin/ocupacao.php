<?php
$title = 'Ocupação';
$isAdmin = true;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

function occupancy_table_layout($table)
{
    return $table['shape'] === 'round' ? 'round' : ((int)$table['seats'] >= 4 ? 'rectangle' : 'square');
}

function occupancy_url($params)
{
    global $selectedDate, $selectedRestaurantId, $selectedEnvironmentId;

    $base = array(
        'date' => isset($selectedDate) ? $selectedDate : (isset($_GET['date']) ? $_GET['date'] : date('Y-m-d')),
        'restaurant_id' => isset($selectedRestaurantId) ? (int)$selectedRestaurantId : (isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0),
        'environment_id' => isset($selectedEnvironmentId) ? (int)$selectedEnvironmentId : (isset($_GET['environment_id']) ? (int)$_GET['environment_id'] : 0)
    );
    return 'ocupacao.php?' . http_build_query(array_merge($base, $params));
}

function valid_date_or_today($date)
{
    $time = strtotime($date);
    return $time ? date('Y-m-d', $time) : date('Y-m-d');
}

function table_key($kind, $id)
{
    return $kind . ':' . (int)$id;
}

function parse_table_keys($values)
{
    $result = array('base' => array(), 'extra' => array());
    foreach ($values as $value) {
        $parts = explode(':', (string)$value, 2);
        if (count($parts) === 2 && isset($result[$parts[0]]) && (int)$parts[1] > 0) {
            $result[$parts[0]][] = (int)$parts[1];
        }
    }
    $result['base'] = array_values(array_unique($result['base']));
    $result['extra'] = array_values(array_unique($result['extra']));
    return $result;
}

function refresh_source_table_override($pdo, $layoutDate, $environmentId, $sourceTableId)
{
    if (!$sourceTableId) {
        return;
    }

    $stmt = $pdo->prepare('SELECT seats FROM tables_map WHERE id = ? AND environment_id = ?');
    $stmt->execute(array($sourceTableId, $environmentId));
    $source = $stmt->fetch();
    if (!$source) {
        return;
    }

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(seats), 0) split_seats FROM occupancy_extra_tables WHERE layout_date = ? AND environment_id = ? AND source_table_id = ?');
    $stmt->execute(array($layoutDate, $environmentId, $sourceTableId));
    $splitSeats = (int)$stmt->fetch()['split_seats'];

    if ($splitSeats > 0) {
        $adjustedSeats = max(1, (int)$source['seats'] - $splitSeats);
        $stmt = $pdo->prepare(
            'INSERT INTO occupancy_table_overrides (layout_date, environment_id, table_id, seats)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE seats = VALUES(seats)'
        );
        $stmt->execute(array($layoutDate, $environmentId, $sourceTableId, $adjustedSeats));
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM occupancy_table_overrides WHERE layout_date = ? AND environment_id = ? AND table_id = ?');
    $stmt->execute(array($layoutDate, $environmentId, $sourceTableId));
}

$selectedDate = valid_date_or_today(isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $layoutDate = valid_date_or_today(isset($_POST['layout_date']) ? $_POST['layout_date'] : $selectedDate);
    $environmentId = isset($_POST['environment_id']) ? (int)$_POST['environment_id'] : 0;

    if ($action === 'move_daily_table') {
        header('Content-Type: application/json');
        if (isset($_POST['table_kind']) && $_POST['table_kind'] === 'extra') {
            $stmt = $pdo->prepare('UPDATE occupancy_extra_tables SET position_x = ?, position_y = ? WHERE id = ? AND layout_date = ? AND environment_id = ?');
            $stmt->execute(array(max(0, (int)$_POST['x']), max(0, (int)$_POST['y']), (int)$_POST['table_id'], $layoutDate, $environmentId));
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO occupancy_layouts (layout_date, environment_id, table_id, position_x, position_y)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE position_x = VALUES(position_x), position_y = VALUES(position_y)'
            );
            $stmt->execute(array($layoutDate, $environmentId, (int)$_POST['table_id'], max(0, (int)$_POST['x']), max(0, (int)$_POST['y'])));
        }
        echo json_encode(array('ok' => true));
        exit;
    }

    if ($action === 'create_extra_table') {
        $sourceTableId = !empty($_POST['source_table_id']) ? (int)$_POST['source_table_id'] : null;
        $extraSeats = max(1, (int)$_POST['seats']);
        $stmt = $pdo->prepare(
            'INSERT INTO occupancy_extra_tables (layout_date, environment_id, label, shape, seats, position_x, position_y, source_table_id)
             VALUES (?, ?, ?, ?, ?, 80, 80, ?)'
        );
        $stmt->execute(array(
            $layoutDate,
            $environmentId,
            trim($_POST['label']),
            $_POST['shape'] === 'round' ? 'round' : 'square',
            $extraSeats,
            $sourceTableId
        ));
        refresh_source_table_override($pdo, $layoutDate, $environmentId, $sourceTableId);
        flash('success', 'Mesa separada criada para este dia.');
        redirect_to(occupancy_url(array('date' => $layoutDate, 'environment_id' => $environmentId)));
    }

    if ($action === 'delete_extra_table') {
        $extraTableId = (int)$_POST['extra_table_id'];
        $stmt = $pdo->prepare('SELECT source_table_id FROM occupancy_extra_tables WHERE id = ? AND layout_date = ? AND environment_id = ?');
        $stmt->execute(array($extraTableId, $layoutDate, $environmentId));
        $extraTable = $stmt->fetch();
        $sourceTableId = $extraTable ? (int)$extraTable['source_table_id'] : null;
        $pdo->prepare('DELETE FROM occupancy_assignments WHERE extra_table_id = ? AND layout_date = ?')->execute(array($extraTableId, $layoutDate));
        $pdo->prepare('DELETE FROM occupancy_extra_tables WHERE id = ? AND layout_date = ? AND environment_id = ?')->execute(array($extraTableId, $layoutDate, $environmentId));
        refresh_source_table_override($pdo, $layoutDate, $environmentId, $sourceTableId);
        flash('success', 'Mesa separada removida deste dia.');
        redirect_to(occupancy_url(array('date' => $layoutDate, 'environment_id' => $environmentId)));
    }

    if ($action === 'hide_base_table') {
        $tableId = (int)$_POST['table_id'];
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO occupancy_hidden_tables (layout_date, environment_id, table_id)
             VALUES (?, ?, ?)'
        );
        $stmt->execute(array($layoutDate, $environmentId, $tableId));
        $pdo->prepare('DELETE FROM occupancy_assignments WHERE layout_date = ? AND environment_id = ? AND table_id = ?')->execute(array($layoutDate, $environmentId, $tableId));
        $pdo->prepare('DELETE FROM occupancy_table_overrides WHERE layout_date = ? AND environment_id = ? AND table_id = ?')->execute(array($layoutDate, $environmentId, $tableId));
        $pdo->prepare('DELETE FROM occupancy_layouts WHERE layout_date = ? AND environment_id = ? AND table_id = ?')->execute(array($layoutDate, $environmentId, $tableId));
        $pdo->prepare('UPDATE reservations SET table_id = NULL WHERE reservation_date = ? AND environment_id = ? AND table_id = ?')->execute(array($layoutDate, $environmentId, $tableId));
        flash('success', 'Mesa removida do layout deste dia.');
        redirect_to(occupancy_url(array('date' => $layoutDate, 'environment_id' => $environmentId)));
    }

    if ($action === 'restore_base_table') {
        $tableId = (int)$_POST['table_id'];
        $pdo->prepare('DELETE FROM occupancy_hidden_tables WHERE layout_date = ? AND environment_id = ? AND table_id = ?')->execute(array($layoutDate, $environmentId, $tableId));
        refresh_source_table_override($pdo, $layoutDate, $environmentId, $tableId);
        flash('success', 'Mesa restaurada no layout deste dia.');
        redirect_to(occupancy_url(array('date' => $layoutDate, 'environment_id' => $environmentId)));
    }

    if ($action === 'clear_daily_layout') {
        $pdo->prepare('DELETE FROM occupancy_assignments WHERE layout_date = ? AND environment_id = ?')->execute(array($layoutDate, $environmentId));
        $pdo->prepare('DELETE FROM occupancy_extra_tables WHERE layout_date = ? AND environment_id = ?')->execute(array($layoutDate, $environmentId));
        $pdo->prepare('DELETE FROM occupancy_table_overrides WHERE layout_date = ? AND environment_id = ?')->execute(array($layoutDate, $environmentId));
        $pdo->prepare('DELETE FROM occupancy_hidden_tables WHERE layout_date = ? AND environment_id = ?')->execute(array($layoutDate, $environmentId));
        $pdo->prepare('DELETE FROM occupancy_layouts WHERE layout_date = ? AND environment_id = ?')->execute(array($layoutDate, $environmentId));
        $pdo->prepare('UPDATE reservations SET environment_id = NULL, table_id = NULL WHERE reservation_date = ? AND environment_id = ?')->execute(array($layoutDate, $environmentId));
        flash('success', 'Layout do dia limpo. O ambiente voltou ao desenho original.');
        redirect_to(occupancy_url(array('date' => $layoutDate, 'environment_id' => $environmentId)));
    }

    if ($action === 'save_assignment') {
        $reservationId = (int)$_POST['reservation_id'];
        $selectedTables = isset($_POST['table_keys']) && is_array($_POST['table_keys']) ? parse_table_keys($_POST['table_keys']) : array('base' => array(), 'extra' => array());

        $stmt = $pdo->prepare('SELECT id FROM reservations WHERE id = ? AND reservation_date = ?');
        $stmt->execute(array($reservationId, $layoutDate));
        if (!$stmt->fetch()) {
            flash('error', 'Reserva não encontrada para este dia.');
            redirect_to(occupancy_url(array('date' => $layoutDate, 'environment_id' => $environmentId)));
        }

        $pdo->prepare('DELETE FROM occupancy_assignments WHERE reservation_id = ? AND layout_date = ?')->execute(array($reservationId, $layoutDate));
        if ($selectedTables['base']) {
            $placeholders = implode(',', array_fill(0, count($selectedTables['base']), '?'));
            $pdo->prepare('DELETE FROM occupancy_assignments WHERE layout_date = ? AND table_id IN (' . $placeholders . ')')->execute(array_merge(array($layoutDate), $selectedTables['base']));
        }
        if ($selectedTables['extra']) {
            $placeholders = implode(',', array_fill(0, count($selectedTables['extra']), '?'));
            $pdo->prepare('DELETE FROM occupancy_assignments WHERE layout_date = ? AND extra_table_id IN (' . $placeholders . ')')->execute(array_merge(array($layoutDate), $selectedTables['extra']));
        }

        $insert = $pdo->prepare('INSERT INTO occupancy_assignments (layout_date, environment_id, reservation_id, table_id, extra_table_id) VALUES (?, ?, ?, ?, ?)');
        foreach ($selectedTables['base'] as $tableId) {
            $insert->execute(array($layoutDate, $environmentId, $reservationId, $tableId, null));
        }
        foreach ($selectedTables['extra'] as $extraTableId) {
            $insert->execute(array($layoutDate, $environmentId, $reservationId, null, $extraTableId));
        }

        $firstBaseTable = isset($selectedTables['base'][0]) ? (int)$selectedTables['base'][0] : null;
        $pdo->prepare('UPDATE reservations SET environment_id = ?, table_id = ? WHERE id = ?')->execute(array($environmentId, $firstBaseTable, $reservationId));
        flash('success', ($selectedTables['base'] || $selectedTables['extra']) ? 'Ocupação salva.' : 'Alocação removida.');
        redirect_to(occupancy_url(array('date' => $layoutDate, 'environment_id' => $environmentId)));
    }

    if ($action === 'clear_assignment') {
        $reservationId = (int)$_POST['reservation_id'];
        $pdo->prepare('DELETE FROM occupancy_assignments WHERE reservation_id = ? AND layout_date = ?')->execute(array($reservationId, $layoutDate));
        $pdo->prepare('UPDATE reservations SET table_id = NULL WHERE id = ?')->execute(array($reservationId));
        flash('success', 'Alocação removida.');
        redirect_to(occupancy_url(array('date' => $layoutDate, 'environment_id' => $environmentId)));
    }
}

require_once __DIR__ . '/../includes/header.php';

$restaurants = $pdo->query("SELECT * FROM restaurants WHERE status = 'active' ORDER BY name")->fetchAll();
$selectedRestaurantId = isset($_GET['restaurant_id']) && (int)$_GET['restaurant_id'] > 0 ? (int)$_GET['restaurant_id'] : (isset($restaurants[0]) ? (int)$restaurants[0]['id'] : 0);
$stmt = $pdo->prepare("SELECT * FROM environments WHERE restaurant_id = ? AND status = 'active' ORDER BY name");
$stmt->execute(array($selectedRestaurantId));
$environments = $stmt->fetchAll();
$selectedEnvironmentId = isset($_GET['environment_id']) && (int)$_GET['environment_id'] > 0 ? (int)$_GET['environment_id'] : (isset($environments[0]) ? (int)$environments[0]['id'] : 0);
$selectedEnvironment = null;
foreach ($environments as $environment) {
    if ((int)$environment['id'] === $selectedEnvironmentId) {
        $selectedEnvironment = $environment;
        break;
    }
}
if (!$selectedEnvironment && isset($environments[0])) {
    $selectedEnvironment = $environments[0];
    $selectedEnvironmentId = (int)$selectedEnvironment['id'];
}

$monthStart = date('Y-m-01', strtotime($selectedDate));
$monthEnd = date('Y-m-t', strtotime($selectedDate));
$calendarStart = date('Y-m-d', strtotime('sunday this week', strtotime($monthStart)));
$calendarEnd = date('Y-m-d', strtotime('saturday this week', strtotime($monthEnd)));
$previousMonth = date('Y-m-d', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m-d', strtotime($monthStart . ' +1 month'));

$monthCounts = array();
if ($selectedRestaurantId) {
    $stmt = $pdo->prepare("SELECT reservation_date, COUNT(*) total FROM reservations WHERE restaurant_id = ? AND reservation_date BETWEEN ? AND ? AND status NOT IN ('cancelled','no_show') GROUP BY reservation_date");
    $stmt->execute(array($selectedRestaurantId, $calendarStart, $calendarEnd));
    foreach ($stmt->fetchAll() as $row) {
        $monthCounts[$row['reservation_date']] = (int)$row['total'];
    }
}

$stmt = $pdo->prepare("SELECT r.*, COALESCE(o.name, 'Nenhuma') occasion_name FROM reservations r LEFT JOIN occasions o ON o.id = r.occasion_id WHERE r.restaurant_id = ? AND r.reservation_date = ? AND r.status NOT IN ('cancelled','no_show') ORDER BY r.reservation_time, r.customer_name");
$stmt->execute(array($selectedRestaurantId, $selectedDate));
$reservations = $stmt->fetchAll();

$baseTables = array();
$allBaseTables = array();
$hiddenTableIds = array();
$extraTables = array();
if ($selectedEnvironmentId) {
    $stmt = $pdo->prepare('SELECT table_id FROM occupancy_hidden_tables WHERE layout_date = ? AND environment_id = ?');
    $stmt->execute(array($selectedDate, $selectedEnvironmentId));
    foreach ($stmt->fetchAll() as $hiddenTable) {
        $hiddenTableIds[] = (int)$hiddenTable['table_id'];
    }

    $stmt = $pdo->prepare("SELECT * FROM tables_map WHERE environment_id = ? AND status = 'active' ORDER BY label");
    $stmt->execute(array($selectedEnvironmentId));
    $allBaseTables = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT t.*, COALESCE(oto.seats, t.seats) seats, COALESCE(ol.position_x, t.position_x) daily_x, COALESCE(ol.position_y, t.position_y) daily_y, 'base' table_kind
         FROM tables_map t
         LEFT JOIN occupancy_layouts ol ON ol.table_id = t.id AND ol.environment_id = t.environment_id AND ol.layout_date = ?
         LEFT JOIN occupancy_table_overrides oto ON oto.table_id = t.id AND oto.environment_id = t.environment_id AND oto.layout_date = ?
         LEFT JOIN occupancy_hidden_tables oht ON oht.table_id = t.id AND oht.environment_id = t.environment_id AND oht.layout_date = ?
         WHERE t.environment_id = ? AND t.status = 'active' AND oht.id IS NULL
         ORDER BY t.label"
    );
    $stmt->execute(array($selectedDate, $selectedDate, $selectedDate, $selectedEnvironmentId));
    $baseTables = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT *, position_x daily_x, position_y daily_y, 'extra' table_kind FROM occupancy_extra_tables WHERE layout_date = ? AND environment_id = ? ORDER BY label");
    $stmt->execute(array($selectedDate, $selectedEnvironmentId));
    $extraTables = $stmt->fetchAll();
}
$tables = array_merge($baseTables, $extraTables);

$assignmentsByTable = array();
$assignmentsByReservation = array();
if ($selectedEnvironmentId) {
    $stmt = $pdo->prepare(
        "SELECT oa.*, r.customer_name, r.party_size, r.reservation_time,
                COALESCE(t.label, et.label) table_label,
                COALESCE(oto.seats, t.seats, et.seats) seats,
                CASE WHEN oa.extra_table_id IS NULL THEN 'base' ELSE 'extra' END table_kind,
                COALESCE(oa.table_id, oa.extra_table_id) table_ref_id
         FROM occupancy_assignments oa
         INNER JOIN reservations r ON r.id = oa.reservation_id
         LEFT JOIN tables_map t ON t.id = oa.table_id
         LEFT JOIN occupancy_table_overrides oto ON oto.table_id = t.id AND oto.environment_id = oa.environment_id AND oto.layout_date = oa.layout_date
         LEFT JOIN occupancy_extra_tables et ON et.id = oa.extra_table_id
         WHERE oa.layout_date = ? AND oa.environment_id = ?"
    );
    $stmt->execute(array($selectedDate, $selectedEnvironmentId));
    foreach ($stmt->fetchAll() as $assignment) {
        $key = table_key($assignment['table_kind'], $assignment['table_ref_id']);
        $assignmentsByTable[$key] = $assignment;
        if (!isset($assignmentsByReservation[(int)$assignment['reservation_id']])) {
            $assignmentsByReservation[(int)$assignment['reservation_id']] = array('tables' => array(), 'capacity' => 0);
        }
        $assignmentsByReservation[(int)$assignment['reservation_id']]['tables'][] = $assignment;
        $assignmentsByReservation[(int)$assignment['reservation_id']]['capacity'] += (int)$assignment['seats'];
    }
}

$weekdays = array('Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb');
$restaurantName = '';
foreach ($restaurants as $restaurant) {
    if ((int)$restaurant['id'] === $selectedRestaurantId) {
        $restaurantName = $restaurant['name'];
        break;
    }
}
?>
<section class="dashboard-hero compact-hero occupancy-hero">
    <div>
        <p class="eyebrow">Ocupação</p>
        <h1>Monte o salão do dia e encaixe reservas com mesas agrupadas.</h1>
        <p>Separe mesas do dia, junte mesas para grupos maiores e imprima o layout com os nomes.</p>
    </div>
    <button class="button primary print-button" type="button" onclick="window.print()">Imprimir layout</button>
</section>

<section class="panel no-print">
    <div class="section-title">
        <div><p class="eyebrow">Agenda do mês</p><h2><?php echo e(date('m/Y', strtotime($selectedDate))); ?></h2></div>
        <div class="filters">
            <a href="<?php echo e(occupancy_url(array('date' => $previousMonth))); ?>">Mês anterior</a>
            <a href="<?php echo e(occupancy_url(array('date' => date('Y-m-d')))); ?>">Hoje</a>
            <a href="<?php echo e(occupancy_url(array('date' => $nextMonth))); ?>">Próximo mês</a>
        </div>
    </div>
    <form method="get" class="occupancy-filters">
        <input type="hidden" name="date" value="<?php echo e($selectedDate); ?>">
        <label>Restaurante
            <select name="restaurant_id" onchange="this.form.submit()">
                <?php foreach ($restaurants as $restaurant): ?>
                    <option value="<?php echo (int)$restaurant['id']; ?>" <?php echo (int)$restaurant['id'] === $selectedRestaurantId ? 'selected' : ''; ?>><?php echo e($restaurant['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Ambiente
            <select name="environment_id" onchange="this.form.submit()">
                <?php foreach ($environments as $environment): ?>
                    <option value="<?php echo (int)$environment['id']; ?>" <?php echo (int)$environment['id'] === $selectedEnvironmentId ? 'selected' : ''; ?>><?php echo e($environment['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
    <div class="month-calendar">
        <?php foreach ($weekdays as $weekday): ?><strong><?php echo e($weekday); ?></strong><?php endforeach; ?>
        <?php for ($time = strtotime($calendarStart); $time <= strtotime($calendarEnd); $time = strtotime('+1 day', $time)): ?>
            <?php $date = date('Y-m-d', $time); $count = isset($monthCounts[$date]) ? $monthCounts[$date] : 0; ?>
            <a class="calendar-day <?php echo date('m', $time) !== date('m', strtotime($selectedDate)) ? 'muted' : ''; ?> <?php echo $date === $selectedDate ? 'selected' : ''; ?> <?php echo $count ? 'has-reservations' : ''; ?>" href="<?php echo e(occupancy_url(array('date' => $date))); ?>">
                <span><?php echo e(date('d', $time)); ?></span>
                <?php if ($count): ?><em><?php echo $count; ?> reserva<?php echo $count > 1 ? 's' : ''; ?></em><?php endif; ?>
            </a>
        <?php endfor; ?>
    </div>
</section>

<section class="occupancy-workspace">
    <aside class="panel occupancy-sidebar no-print">
        <div class="section-title"><div><p class="eyebrow"><?php echo e(date('d/m/Y', strtotime($selectedDate))); ?></p><h2>Reservas do dia</h2><p class="muted-line"><?php echo e($restaurantName); ?></p></div></div>

        <?php if ($selectedEnvironment): ?>
            <form method="post" class="clear-layout-form" onsubmit="return confirm('Limpar o layout deste dia? As mesas separadas, posições e alocações deste ambiente serão removidas.');">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="clear_daily_layout">
                <input type="hidden" name="layout_date" value="<?php echo e($selectedDate); ?>">
                <input type="hidden" name="environment_id" value="<?php echo (int)$selectedEnvironmentId; ?>">
                <button class="button danger" type="submit">Limpar layout do dia</button>
            </form>
            <details class="extra-table-drawer">
                <summary>Adicionar, remover ou ajustar mesas do dia</summary>
            <form method="post" class="extra-table-form">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="create_extra_table">
                <input type="hidden" name="layout_date" value="<?php echo e($selectedDate); ?>">
                <input type="hidden" name="environment_id" value="<?php echo (int)$selectedEnvironmentId; ?>">
                <h3>Mesa operacional para esta data</h3>
                <p class="muted-line">Use quando precisar dividir uma mesa, criar uma composição temporária ou ajustar o mapa sem mexer no cadastro fixo.</p>
                <div class="grid two">
                    <label>Mesa origem
                        <select name="source_table_id">
                            <option value="">Mesa extra sem origem</option>
                            <?php foreach ($baseTables as $table): ?><option value="<?php echo (int)$table['id']; ?>"><?php echo e($table['label']); ?> · <?php echo (int)$table['seats']; ?> lugares</option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>Novo número <input type="text" name="label" required placeholder="M04A"></label>
                    <label>Lugares <input type="number" name="seats" min="1" value="2"></label>
                    <label>Formato
                        <select name="shape"><option value="square">Quadrada</option><option value="round">Redonda</option></select>
                    </label>
                </div>
                <button class="button ghost" type="submit">Criar mesa do dia</button>
            </form>
                <div class="daily-table-tools">
                    <h3>Mesas fixas deste ambiente</h3>
                    <p class="muted-line">Remova apenas do layout desta data. O cadastro original continua intacto.</p>
                    <div class="daily-table-list">
                        <?php foreach ($allBaseTables as $table): ?>
                            <?php $isHidden = in_array((int)$table['id'], $hiddenTableIds, true); ?>
                            <form method="post" class="<?php echo $isHidden ? 'is-hidden' : ''; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="action" value="<?php echo $isHidden ? 'restore_base_table' : 'hide_base_table'; ?>">
                                <input type="hidden" name="layout_date" value="<?php echo e($selectedDate); ?>">
                                <input type="hidden" name="environment_id" value="<?php echo (int)$selectedEnvironmentId; ?>">
                                <input type="hidden" name="table_id" value="<?php echo (int)$table['id']; ?>">
                                <span><strong><?php echo e($table['label']); ?></strong><em><?php echo (int)$table['seats']; ?> lugares</em></span>
                                <button class="button <?php echo $isHidden ? 'ghost' : 'danger'; ?>" type="submit"><?php echo $isHidden ? 'Restaurar' : 'Remover do dia'; ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        <?php endif; ?>

        <div class="reservation-list occupancy-reservations">
            <?php if (!$reservations): ?><div class="empty-state"><strong>Sem reservas nesta data.</strong><p>Escolha outro dia na agenda.</p></div><?php endif; ?>
            <?php foreach ($reservations as $reservation): ?>
                <?php
                $reservationAssignments = isset($assignmentsByReservation[(int)$reservation['id']]) ? $assignmentsByReservation[(int)$reservation['id']] : array('tables' => array(), 'capacity' => 0);
                $selectedKeys = array();
                foreach ($reservationAssignments['tables'] as $assignedTable) {
                    $selectedKeys[] = table_key($assignedTable['table_kind'], $assignedTable['table_ref_id']);
                }
                ?>
                <form method="post" class="occupancy-reservation-card">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="save_assignment">
                    <input type="hidden" name="layout_date" value="<?php echo e($selectedDate); ?>">
                    <input type="hidden" name="environment_id" value="<?php echo (int)$selectedEnvironmentId; ?>">
                    <input type="hidden" name="reservation_id" value="<?php echo (int)$reservation['id']; ?>">
                    <div class="reservation-card-title">
                        <div><strong><?php echo e(substr($reservation['reservation_time'], 0, 5)); ?> · <?php echo e($reservation['customer_name']); ?></strong><p><?php echo (int)$reservation['party_size']; ?> pessoas · <?php echo e($reservation['occasion_name']); ?></p></div>
                        <span class="badge"><?php echo e(reservation_status_label($reservation['status'])); ?></span>
                    </div>
                    <div class="capacity-meter <?php echo $reservationAssignments['capacity'] >= (int)$reservation['party_size'] ? 'ok' : 'attention'; ?>">
                        <span>Capacidade alocada</span><strong data-capacity-total><?php echo (int)$reservationAssignments['capacity']; ?></strong><em>necessário: <?php echo (int)$reservation['party_size']; ?></em>
                    </div>
                    <div class="table-picker" data-party-size="<?php echo (int)$reservation['party_size']; ?>">
                        <?php foreach ($tables as $table): ?>
                            <?php
                            $kind = $table['table_kind'];
                            $key = table_key($kind, $table['id']);
                            $assigned = isset($assignmentsByTable[$key]) ? $assignmentsByTable[$key] : null;
                            $isChecked = in_array($key, $selectedKeys, true);
                            $isTakenByOther = $assigned && (int)$assigned['reservation_id'] !== (int)$reservation['id'];
                            ?>
                            <label class="<?php echo $isTakenByOther ? 'taken' : ''; ?>">
                                <input type="checkbox" name="table_keys[]" value="<?php echo e($key); ?>" data-seats="<?php echo (int)$table['seats']; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                <span><?php echo e($table['label']); ?><?php echo $kind === 'extra' ? ' · separada' : ''; ?></span>
                                <em><?php echo (int)$table['seats']; ?> lugares</em>
                                <?php if ($isTakenByOther): ?><small><?php echo e($assigned['customer_name']); ?></small><?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="table-card-actions"><button class="button primary" type="submit">Salvar mesas</button><button class="button ghost" type="submit" name="action" value="clear_assignment" formnovalidate>Limpar</button></div>
                </form>
            <?php endforeach; ?>
        </div>
    </aside>

    <section class="panel occupancy-map-panel">
        <div class="section-title print-title"><div><p class="eyebrow">Layout do dia</p><h2><?php echo e($restaurantName); ?> · <?php echo e($selectedEnvironment ? $selectedEnvironment['name'] : 'Ambiente'); ?></h2><p class="muted-line"><?php echo e(date('d/m/Y', strtotime($selectedDate))); ?> · arraste as mesas para ajustar esta data.</p></div></div>
        <?php if ($selectedEnvironment): ?>
            <div class="floor-map occupancy-map" data-save-url="ocupacao.php" data-csrf="<?php echo e(csrf_token()); ?>" data-layout-date="<?php echo e($selectedDate); ?>" data-environment-id="<?php echo (int)$selectedEnvironmentId; ?>" style="width: <?php echo (int)$selectedEnvironment['width']; ?>px; height: <?php echo (int)$selectedEnvironment['height']; ?>px;">
                <?php foreach ($tables as $table): ?>
                    <?php $chairCount = min(max((int)$table['seats'], 1), 8); $key = table_key($table['table_kind'], $table['id']); $assignment = isset($assignmentsByTable[$key]) ? $assignmentsByTable[$key] : null; ?>
                    <button class="map-table occupancy-table layout-<?php echo e(occupancy_table_layout($table)); ?> chair-count-<?php echo $chairCount; ?> <?php echo $assignment ? 'assigned' : ''; ?> <?php echo $table['table_kind'] === 'extra' ? 'extra-table' : ''; ?>" data-id="<?php echo (int)$table['id']; ?>" data-kind="<?php echo e($table['table_kind']); ?>" style="left: <?php echo (int)$table['daily_x']; ?>px; top: <?php echo (int)$table['daily_y']; ?>px;">
                        <?php for ($chair = 1; $chair <= $chairCount; $chair++): ?><span class="chair chair-<?php echo $chair; ?>" aria-hidden="true"></span><?php endfor; ?>
                        <span class="table-surface">
                            <strong><?php echo e($table['label']); ?></strong><em><?php echo (int)$table['seats']; ?> lugares<?php echo $table['table_kind'] === 'extra' ? ' · dia' : ''; ?></em>
                            <?php if ($assignment): ?><span class="table-reservation-name"><?php echo e($assignment['customer_name']); ?></span><span class="table-reservation-size"><?php echo (int)$assignment['party_size']; ?> pessoas · <?php echo e(substr($assignment['reservation_time'], 0, 5)); ?></span><?php endif; ?>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php if ($extraTables): ?>
                <div class="extra-table-list no-print">
                    <?php foreach ($extraTables as $table): ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                            <input type="hidden" name="action" value="delete_extra_table">
                            <input type="hidden" name="layout_date" value="<?php echo e($selectedDate); ?>">
                            <input type="hidden" name="environment_id" value="<?php echo (int)$selectedEnvironmentId; ?>">
                            <input type="hidden" name="extra_table_id" value="<?php echo (int)$table['id']; ?>">
                            <button class="button danger" type="submit">Remover <?php echo e($table['label']); ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state"><strong>Nenhum ambiente ativo.</strong><p>Cadastre um ambiente em Configurações.</p></div>
        <?php endif; ?>
    </section>
</section>
<script src="../assets/js/ocupacao.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
