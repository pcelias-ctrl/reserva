<?php
$title = 'Configuracoes';
$isAdmin = true;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'];

    if ($action === 'occasion') {
        $stmt = $pdo->prepare('INSERT INTO occasions (name, asks_birthday) VALUES (?, ?)');
        $stmt->execute(array(trim($_POST['name']), isset($_POST['asks_birthday']) ? 1 : 0));
        flash('success', 'Ocasiao cadastrada.');
    }

    if ($action === 'question') {
        $stmt = $pdo->prepare('INSERT INTO questionnaire_questions (label, field_type, options_text, is_required, sort_order) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute(array(trim($_POST['label']), $_POST['field_type'], trim($_POST['options_text']), isset($_POST['is_required']) ? 1 : 0, (int)$_POST['sort_order']));
        flash('success', 'Pergunta cadastrada.');
    }

    if ($action === 'environment') {
        $stmt = $pdo->prepare('INSERT INTO environments (restaurant_id, name, description, width, height) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute(array((int)$_POST['restaurant_id'], trim($_POST['name']), trim($_POST['description']), (int)$_POST['width'], (int)$_POST['height']));
        flash('success', 'Ambiente cadastrado.');
    }

    if ($action === 'update_environment') {
        $stmt = $pdo->prepare('UPDATE environments SET restaurant_id = ?, name = ?, description = ?, width = ?, height = ?, status = ? WHERE id = ?');
        $stmt->execute(array(
            (int)$_POST['restaurant_id'],
            trim($_POST['name']),
            trim($_POST['description']),
            (int)$_POST['width'],
            (int)$_POST['height'],
            $_POST['status'],
            (int)$_POST['environment_id']
        ));
        flash('success', 'Ambiente atualizado.');
        redirect_to('configuracoes.php?environment_id=' . (int)$_POST['environment_id']);
    }

    if ($action === 'table') {
        $stmt = $pdo->prepare('INSERT INTO tables_map (environment_id, label, shape, seats, position_x, position_y) VALUES (?, ?, ?, ?, 40, 40)');
        $stmt->execute(array((int)$_POST['environment_id'], trim($_POST['label']), $_POST['shape'], (int)$_POST['seats']));
        flash('success', 'Mesa cadastrada.');
    }

    if ($action === 'update_table') {
        $stmt = $pdo->prepare('UPDATE tables_map SET label = ?, shape = ?, seats = ?, status = ? WHERE id = ?');
        $stmt->execute(array(trim($_POST['label']), $_POST['shape'], (int)$_POST['seats'], $_POST['status'], (int)$_POST['table_id']));
        flash('success', 'Mesa atualizada.');
        redirect_to('configuracoes.php?environment_id=' . (int)$_POST['environment_id']);
    }

    if ($action === 'move_table') {
        header('Content-Type: application/json');
        $stmt = $pdo->prepare('UPDATE tables_map SET position_x = ?, position_y = ? WHERE id = ?');
        $stmt->execute(array((int)$_POST['x'], (int)$_POST['y'], (int)$_POST['table_id']));
        echo json_encode(array('ok' => true));
        exit;
    }

    redirect_to('configuracoes.php');
}

require_once __DIR__ . '/../includes/header.php';

$occasions = $pdo->query('SELECT * FROM occasions ORDER BY status, name')->fetchAll();
$questions = $pdo->query('SELECT * FROM questionnaire_questions ORDER BY sort_order, id')->fetchAll();
$restaurants = $pdo->query("SELECT * FROM restaurants WHERE status = 'active' ORDER BY name")->fetchAll();
$environments = $pdo->query('SELECT e.*, r.name restaurant_name FROM environments e INNER JOIN restaurants r ON r.id = e.restaurant_id ORDER BY r.name, e.name')->fetchAll();
$selectedEnvironmentId = isset($_GET['environment_id']) ? (int)$_GET['environment_id'] : (isset($environments[0]) ? (int)$environments[0]['id'] : 0);
$tables = array();
$selectedEnvironment = null;
if ($selectedEnvironmentId) {
    $stmt = $pdo->prepare('SELECT e.*, r.name restaurant_name FROM environments e INNER JOIN restaurants r ON r.id = e.restaurant_id WHERE e.id = ?');
    $stmt->execute(array($selectedEnvironmentId));
    $selectedEnvironment = $stmt->fetch();
    $stmt = $pdo->prepare('SELECT * FROM tables_map WHERE environment_id = ? ORDER BY label');
    $stmt->execute(array($selectedEnvironmentId));
    $tables = $stmt->fetchAll();
}
?>
<section class="settings-grid">
    <div class="panel">
        <h2>Ocasioes</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="occasion">
            <label>Nome <input type="text" name="name" required placeholder="Aniversario, celebracao..."></label>
            <label class="check"><input type="checkbox" name="asks_birthday" value="1"> Solicitar dia e mes do aniversario</label>
            <button class="button primary" type="submit">Adicionar</button>
        </form>
        <ul class="compact-list">
            <?php foreach ($occasions as $occasion): ?>
                <li><?php echo e($occasion['name']); ?><?php echo $occasion['asks_birthday'] ? ' · aniversario' : ''; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="panel">
        <h2>Questionario</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="question">
            <label>Pergunta <input type="text" name="label" required></label>
            <div class="grid two">
                <label>Tipo
                    <select name="field_type">
                        <option value="text">Texto curto</option>
                        <option value="textarea">Texto longo</option>
                        <option value="select">Selecao</option>
                        <option value="checkbox">Sim/Nao</option>
                    </select>
                </label>
                <label>Ordem <input type="number" name="sort_order" value="0"></label>
            </div>
            <label>Opcoes para selecao <textarea name="options_text" rows="3" placeholder="Uma opcao por linha"></textarea></label>
            <label class="check"><input type="checkbox" name="is_required" value="1"> Obrigatoria</label>
            <button class="button primary" type="submit">Adicionar</button>
        </form>
        <ul class="compact-list">
            <?php foreach ($questions as $question): ?>
                <li><?php echo e($question['label']); ?> · <?php echo e($question['field_type']); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="panel">
    <div class="section-title">
        <h2>Ambientes e layout de mesas</h2>
        <form method="get" class="inline-form">
            <select name="environment_id" onchange="this.form.submit()">
                <?php foreach ($environments as $environment): ?>
                    <option value="<?php echo (int)$environment['id']; ?>" <?php echo $selectedEnvironmentId === (int)$environment['id'] ? 'selected' : ''; ?>><?php echo e($environment['restaurant_name'] . ' - ' . $environment['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="settings-grid">
        <?php if ($selectedEnvironment): ?>
            <form method="post">
                <h3>Editar ambiente selecionado</h3>
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="action" value="update_environment">
                <input type="hidden" name="environment_id" value="<?php echo (int)$selectedEnvironment['id']; ?>">
                <label>Restaurante
                    <select name="restaurant_id" required>
                        <?php foreach ($restaurants as $restaurant): ?>
                            <option value="<?php echo (int)$restaurant['id']; ?>" <?php echo (int)$selectedEnvironment['restaurant_id'] === (int)$restaurant['id'] ? 'selected' : ''; ?>><?php echo e($restaurant['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Nome <input type="text" name="name" required value="<?php echo e($selectedEnvironment['name']); ?>"></label>
                <label>Descricao <textarea name="description" rows="2"><?php echo e($selectedEnvironment['description']); ?></textarea></label>
                <div class="grid two">
                    <label>Largura <input type="number" name="width" value="<?php echo (int)$selectedEnvironment['width']; ?>"></label>
                    <label>Altura <input type="number" name="height" value="<?php echo (int)$selectedEnvironment['height']; ?>"></label>
                </div>
                <label>Status
                    <select name="status">
                        <option value="active" <?php echo $selectedEnvironment['status'] === 'active' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inactive" <?php echo $selectedEnvironment['status'] === 'inactive' ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </label>
                <button class="button primary" type="submit">Salvar ambiente</button>
            </form>
        <?php endif; ?>

        <form method="post">
            <h3>Novo ambiente</h3>
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="environment">
            <label>Restaurante
                <select name="restaurant_id" required>
                    <?php foreach ($restaurants as $restaurant): ?>
                        <option value="<?php echo (int)$restaurant['id']; ?>"><?php echo e($restaurant['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Nome <input type="text" name="name" required></label>
            <label>Descricao <textarea name="description" rows="2"></textarea></label>
            <div class="grid two">
                <label>Largura <input type="number" name="width" value="960"></label>
                <label>Altura <input type="number" name="height" value="520"></label>
            </div>
            <button class="button primary" type="submit">Criar ambiente</button>
        </form>

        <form method="post">
            <h3>Nova mesa</h3>
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="table">
            <input type="hidden" name="environment_id" value="<?php echo (int)$selectedEnvironmentId; ?>">
            <label>Identificacao <input type="text" name="label" required placeholder="M01"></label>
            <div class="grid two">
                <label>Formato
                    <select name="shape">
                        <option value="square">Quadrada</option>
                        <option value="round">Redonda</option>
                    </select>
                </label>
                <label>Lugares <input type="number" name="seats" value="2" min="1"></label>
            </div>
            <button class="button primary" type="submit">Adicionar mesa</button>
        </form>
    </div>

    <?php if ($selectedEnvironment): ?>
        <div class="floor-map" data-save-url="configuracoes.php" data-csrf="<?php echo e(csrf_token()); ?>" style="width: <?php echo (int)$selectedEnvironment['width']; ?>px; height: <?php echo (int)$selectedEnvironment['height']; ?>px;">
            <?php foreach ($tables as $table): ?>
                <button class="map-table <?php echo e($table['shape']); ?> <?php echo $table['status'] === 'inactive' ? 'inactive' : ''; ?>" data-id="<?php echo (int)$table['id']; ?>" style="left: <?php echo (int)$table['position_x']; ?>px; top: <?php echo (int)$table['position_y']; ?>px;">
                    <strong><?php echo e($table['label']); ?></strong>
                    <span><?php echo (int)$table['seats']; ?> lugares</span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="table-editor-grid">
            <?php foreach ($tables as $table): ?>
                <form method="post" class="table-editor-card">
                    <h3>Mesa <?php echo e($table['label']); ?></h3>
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="update_table">
                    <input type="hidden" name="environment_id" value="<?php echo (int)$selectedEnvironment['id']; ?>">
                    <input type="hidden" name="table_id" value="<?php echo (int)$table['id']; ?>">
                    <label>Identificacao <input type="text" name="label" required value="<?php echo e($table['label']); ?>"></label>
                    <div class="grid two">
                        <label>Formato
                            <select name="shape">
                                <option value="square" <?php echo $table['shape'] === 'square' ? 'selected' : ''; ?>>Quadrada</option>
                                <option value="round" <?php echo $table['shape'] === 'round' ? 'selected' : ''; ?>>Redonda</option>
                            </select>
                        </label>
                        <label>Lugares <input type="number" name="seats" min="1" value="<?php echo (int)$table['seats']; ?>"></label>
                    </div>
                    <label>Status
                        <select name="status">
                            <option value="active" <?php echo $table['status'] === 'active' ? 'selected' : ''; ?>>Ativa</option>
                            <option value="inactive" <?php echo $table['status'] === 'inactive' ? 'selected' : ''; ?>>Inativa</option>
                        </select>
                    </label>
                    <button class="button ghost" type="submit">Salvar mesa</button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<script src="../assets/js/admin.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
