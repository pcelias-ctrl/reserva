<?php
$title = 'Ocupação';
$isAdmin = true;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

function occupancy_table_layout($table)
{
    if ($table['shape'] === 'round') {
        return 'round';
    }
    return (int)$table['seats'] >= 4 ? 'rectangle' : 'square';
}

function occupancy_url($params)
{
    $base = array(
        'date' => isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'),
        'restaurant_id' => isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0,
        'environment_id' => isset($_GET['environment_id']) ? (int)$_GET['environment_id'] : 0
    );
    return 'ocupacao.php?' . http_build_query(array_merge($base, $params));
}

function valid_date_or_today($date)
{
    $time = strtotime($date);
    return $time ? date('Y-m-d', $time) : date('Y-m-d');
}

$selectedDate = valid_date_or_today(isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'move_daily_table') {
        header('Content-Type: application/json');
        $stmt = $pdo->prepare(
            'INSERT INTO occupancy_layouts (layout_date, environment_id, table_id, position_x, position_y)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE position_x = VALUES(position_x), position_y = VALUES(position_y)'
        );
        $stmt->execute(array(
            valid_date_or_today($_POST['layout_date']),
            (int)$_POST['environment_id'],
            (int)$_POST['table_id'],
            max(0, (int)$_POST['x']),
            max(0, (int)$_POST['y'])
        ));
        echo json_encode(array('ok' => true));
        exit;
    }

    if ($action === 'save_assignment') {
        $reservationId = (int)$_POST['reservation_id'];
        $environmentId = (int)$_POST['environment_id'];
        $layoutDate = valid_date_or_today($_POST['layout_date']);
        $tableIds = isset($_POST['table_ids']) && is_array($_POST['table_ids']) ? array_values(array_filter(array_map('intval', $_POST['table_ids']))) : array();

        $stmt = $pdo->prepare('SELECT id FROM reservations WHERE id = ? AND reservation_date = ?');
        $stmt->execute(array($reservationId, $layoutDate));
        if (!$stmt->fetch()) {
            flash('error', 'Reserva não encontrada para este dia.');
            redirect_to(occupancy_url(array('date' => $layoutDate, 'environment_id' => $environmentId)));
        }

        $pdo->prepare('DELETE FROM occupancy_assignments WHERE reservation_id = ? AND layout_date = ?')->execute(array($reservationId, $layoutDate));
        if ($tableIds) {
            $placeholders = implode(',', array_fill(0, count($tableIds), '?'));
            $params = array_merge(array($layoutDate), $tableIds);
            $stmt = $pdo->prepare('DELETE FROM occupancy_assignments WHERE layout_date = ? AND table_id IN (' . $placeholders . ')');
            $stmt->execute($params);

            $insert = $pdo->prepare('INSERT INTO occupancy_assignments (layout_date, environment_id, reservation_id, table_id) VALUES (?, ?, ?, ?)');
            foreach ($tableIds as $tableId) {
                $insert->execute(array($layoutDate, $environmentId, $reservationId, $tableId));
            }
            $pdo->prepare('UPDATE reservations SET environment_id = ?, table_id = ? WHERE id = ?')->execute(array($environmentId, $tableIds[0], $reservationId));
            flash('success', 'Ocupação salva. Mesas alocadas para a reserva.');
        } else {
            $pdo->prepare('UPDATE reservations SET table_id = NULL WHERE id = ?')->execute(array($reservationId));
            flash('success', 'Alocação removida da reserva.');
        }

        redirect_to(occupancy_url(array('date' => $layoutDate, 'environment_id' => $environmentId)));
    }

    if ($action === 'clear_assignment') {
        $reservationId = (int)$_POST['reservation_id'];
        $layoutDate = valid_date_or_today($_POST['layout_date']);
        $environmentId = (int)$_POST['environment_id'];
        $pdo->prepare('DELETE FROM occupancy_assignments WHERE reservation_id = ? AND layout_date = ?')->execute(array($reservationId, $layoutDate));
        $pdo->prepare('UPDATE reservations SET table_id = NULL WHERE id = ?')->execute(array($reservationId));
        flash('success', 'Alocação removida.');
        redirect_to(occupancy_url(array('date' => $layoutDate, 'environment_id' => $environmentId)));
    }
}

require_once __DIR__ . '/../includes/header.php';

$restaurants = $pdo->query("SELECT * FROM restaurants WHERE status = 'active' ORDER BY name")->fetchAll();
$selectedRestaurantId = isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : (isset($restaurants[0]) ? (int)$restaurants[0]['id'] : 0);
if (!$selectedRestaurantId && isset($restaurants[0])) {
    $selectedRestaurantId = (int)$restaurants[0]['id'];
}

$stmt = $pdo->prepare("SELECT * FROM environments WHERE restaurant_id = ? AND status = 'active' ORDER BY name");
$stmt->execute(array($selectedRestaurantId));
$environments = $stmt->fetchAll();
$selectedEnvironmentId = isset($_GET['environment_id']) ? (int)$_GET['environment_id'] : (isset($environments[0]) ? (int)$environments[0]['id'] : 0);
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
    $stmt = $pdo->prepare(
        "SELECT reservation_date, COUNT(*) total
         FROM reservations
         WHERE restaurant_id = ? AND reservation_date BETWEEN ? AND ? AND status NOT IN ('cancelled','no_show')
         GROUP BY reservation_date"
    );
    $stmt->execute(array($selectedRestaurantId, $calendarStart, $calendarEnd));
    foreach ($stmt->fetchAll() as $row) {
        $monthCounts[$row['reservation_date']] = (int)$row['total'];
    }
}

$reservations = array();
if ($selectedRestaurantId) {
    $stmt = $pdo->prepare(
        "SELECT r.*, COALESCE(o.name, 'Nenhuma') occasion_name
         FROM reservations r
         LEFT JOIN occasions o ON o.id = r.occasion_id
         WHERE r.restaurant_id = ? AND r.reservation_date = ? AND r.status NOT IN ('cancelled','no_show')
         ORDER BY r.reservation_time, r.customer_name"
    );
    $stmt->execute(array($selectedRestaurantId, $selectedDate));
    $reservations = $stmt->fetchAll();
}

$tables = array();
if ($selectedEnvironmentId) {
    $stmt = $pdo->prepare(
        "SELECT t.*, COALESCE(ol.position_x, t.position_x) daily_x, COALESCE(ol.position_y, t.position_y) daily_y
         FROM tables_map t
         LEFT JOIN occupancy_layouts ol ON ol.table_id = t.id AND ol.environment_id = t.environment_id AND ol.layout_date = ?
         WHERE t.environment_id = ? AND t.status = 'active'
         ORDER BY t.label"
    );
    $stmt->execute(array($selectedDate, $selectedEnvironmentId));
    $tables = $stmt->fetchAll();
}

$assignmentsByTable = array();
$assignmentsByReservation = array();
if ($selectedEnvironmentId) {
    $stmt = $pdo->prepare(
        "SELECT oa.*, r.customer_name, r.party_size, r.reservation_time, t.label table_label, t.seats
         FROM occupancy_assignments oa
         INNER JOIN reservations r ON r.id = oa.reservation_id
         INNER JOIN tables_map t ON t.id = oa.table_id
         WHERE oa.layout_date = ? AND oa.environment_id = ?"
    );
    $stmt->execute(array($selectedDate, $selectedEnvironmentId));
    foreach ($stmt->fetchAll() as $assignment) {
        $assignmentsByTable[(int)$assignment['table_id']] = $assignment;
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
        <p>Escolha a data, junte mesas para grupos maiores e imprima o layout com os nomes das reservas.</p>
    </div>
    <button class="button primary print-button" type="button" onclick="window.print()">Imprimir layout</button>
</section>

<section class="panel no-print">
    <div class="section-title">
        <div>
            <p class="eyebrow">Agenda do mês</p>
            <h2><?php echo e(date('m/Y', strtotime($selectedDate))); ?></h2>
        </div>
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
        <div class="section-title">
            <div>
                <p class="eyebrow"><?php echo e(date('d/m/Y', strtotime($selectedDate))); ?></p>
                <h2>Reservas do dia</h2>
                <p class="muted-line"><?php echo e($restaurantName); ?></p>
            </div>
        </div>

        <div class="reservation-list occupancy-reservations">
            <?php if (!$reservations): ?>
                <div class="empty-state">
                    <strong>Sem reservas nesta data.</strong>
                    <p>Escolha outro dia na agenda para montar a ocupação.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($reservations as $reservation): ?>
                <?php
                $reservationAssignments = isset($assignmentsByReservation[(int)$reservation['id']]) ? $assignmentsByReservation[(int)$reservation['id']] : array('tables' => array(), 'capacity' => 0);
                $selectedTableIds = array();
                foreach ($reservationAssignments['tables'] as $assignedTable) {
                    $selectedTableIds[] = (int)$assignedTable['table_id'];
                }
                ?>
                <form method="post" class="occupancy-reservation-card">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="save_assignment">
                    <input type="hidden" name="layout_date" value="<?php echo e($selectedDate); ?>">
                    <input type="hidden" name="environment_id" value="<?php echo (int)$selectedEnvironmentId; ?>">
                    <input type="hidden" name="reservation_id" value="<?php echo (int)$reservation['id']; ?>">

                    <div class="reservation-card-title">
                        <div>
                            <strong><?php echo e(substr($reservation['reservation_time'], 0, 5)); ?> · <?php echo e($reservation['customer_name']); ?></strong>
                            <p><?php echo (int)$reservation['party_size']; ?> pessoas · <?php echo e($reservation['occasion_name']); ?></p>
                        </div>
                        <span class="badge"><?php echo e(reservation_status_label($reservation['status'])); ?></span>
                    </div>

                    <div class="capacity-meter <?php echo $reservationAssignments['capacity'] >= (int)$reservation['party_size'] ? 'ok' : 'attention'; ?>">
                        <span>Capacidade alocada</span>
                        <strong data-capacity-total><?php echo (int)$reservationAssignments['capacity']; ?></strong>
                        <em>necessário: <?php echo (int)$reservation['party_size']; ?></em>
                    </div>

                    <div class="table-picker" data-party-size="<?php echo (int)$reservation['party_size']; ?>">
                        <?php foreach ($tables as $table): ?>
                            <?php
                            $assigned = isset($assignmentsByTable[(int)$table['id']]) ? $assignmentsByTable[(int)$table['id']] : null;
                            $isChecked = in_array((int)$table['id'], $selectedTableIds, true);
                            $isTakenByOther = $assigned && (int)$assigned['reservation_id'] !== (int)$reservation['id'];
                            ?>
                            <label class="<?php echo $isTakenByOther ? 'taken' : ''; ?>">
                                <input type="checkbox" name="table_ids[]" value="<?php echo (int)$table['id']; ?>" data-seats="<?php echo (int)$table['seats']; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                <span><?php echo e($table['label']); ?></span>
                                <em><?php echo (int)$table['seats']; ?> lugares</em>
                                <?php if ($isTakenByOther): ?><small><?php echo e($assigned['customer_name']); ?></small><?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="table-card-actions">
                        <button class="button primary" type="submit">Salvar mesas</button>
                        <button class="button ghost" type="submit" name="action" value="clear_assignment" formnovalidate>Limpar</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </aside>

    <section class="panel occupancy-map-panel">
        <div class="section-title print-title">
            <div>
                <p class="eyebrow">Layout do dia</p>
                <h2><?php echo e($restaurantName); ?> · <?php echo e($selectedEnvironment ? $selectedEnvironment['name'] : 'Ambiente'); ?></h2>
                <p class="muted-line"><?php echo e(date('d/m/Y', strtotime($selectedDate))); ?> · arraste as mesas para ajustar esta data.</p>
            </div>
        </div>

        <?php if ($selectedEnvironment): ?>
            <div class="floor-map occupancy-map" data-save-url="ocupacao.php" data-csrf="<?php echo e(csrf_token()); ?>" data-layout-date="<?php echo e($selectedDate); ?>" data-environment-id="<?php echo (int)$selectedEnvironmentId; ?>" style="width: <?php echo (int)$selectedEnvironment['width']; ?>px; height: <?php echo (int)$selectedEnvironment['height']; ?>px;">
                <?php foreach ($tables as $table): ?>
                    <?php
                    $chairCount = min(max((int)$table['seats'], 1), 8);
                    $assignment = isset($assignmentsByTable[(int)$table['id']]) ? $assignmentsByTable[(int)$table['id']] : null;
                    ?>
                    <button class="map-table occupancy-table layout-<?php echo e(occupancy_table_layout($table)); ?> chair-count-<?php echo $chairCount; ?> <?php echo $assignment ? 'assigned' : ''; ?>" data-id="<?php echo (int)$table['id']; ?>" style="left: <?php echo (int)$table['daily_x']; ?>px; top: <?php echo (int)$table['daily_y']; ?>px;">
                        <?php for ($chair = 1; $chair <= $chairCount; $chair++): ?>
                            <span class="chair chair-<?php echo $chair; ?>" aria-hidden="true"></span>
                        <?php endfor; ?>
                        <span class="table-surface">
                            <strong><?php echo e($table['label']); ?></strong>
                            <em><?php echo (int)$table['seats']; ?> lugares</em>
                            <?php if ($assignment): ?>
                                <span class="table-reservation-name"><?php echo e($assignment['customer_name']); ?></span>
                                <span class="table-reservation-size"><?php echo (int)$assignment['party_size']; ?> pessoas · <?php echo e(substr($assignment['reservation_time'], 0, 5)); ?></span>
                            <?php endif; ?>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <strong>Nenhum ambiente ativo.</strong>
                <p>Cadastre um ambiente em Configurações para montar a ocupação.</p>
            </div>
        <?php endif; ?>
    </section>
</section>
<script src="../assets/js/ocupacao.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
