<?php
$title = 'Configurações';
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
        flash('success', 'Ocasião cadastrada.');
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
        redirect_to('configuracoes.php?environment_id=' . (int)$_POST['environment_id']);
    }

    if ($action === 'update_table') {
        $stmt = $pdo->prepare('UPDATE tables_map SET label = ?, shape = ?, seats = ?, status = ? WHERE id = ?');
        $stmt->execute(array(trim($_POST['label']), $_POST['shape'], (int)$_POST['seats'], $_POST['status'], (int)$_POST['table_id']));
        flash('success', 'Mesa atualizada.');
        redirect_to('configuracoes.php?environment_id=' . (int)$_POST['environment_id']);
    }

    if ($action === 'delete_table') {
        $stmt = $pdo->prepare('DELETE FROM tables_map WHERE id = ? AND environment_id = ?');
        $stmt->execute(array((int)$_POST['table_id'], (int)$_POST['environment_id']));
        flash('success', 'Mesa excluída.');
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

function table_visual_layout($table)
{
    if ($table['shape'] === 'round') {
        return 'round';
    }
    return (int)$table['seats'] >= 4 ? 'rectangle' : 'square';
}
?>
<section class="dashboard-hero compact-hero">
    <div>
        <p class="eyebrow">Configurações</p>
        <h1>Organize questionários, ocasiões e o mapa de mesas.</h1>
    </div>
</section>

<section class="config-tabs">
    <a href="#layout">Layout de mesas</a>
    <a href="#questionario">Questionário</a>
    <a href="#ocasioes">Ocasiões</a>
</section>

<section class="panel layout-console" id="layout">
    <div class="section-title">
        <div>
            <p class="eyebrow">Mapa operacional</p>
            <h2>Ambientes e mesas</h2>
            <?php if ($selectedEnvironment): ?>
                <p class="muted-line"><?php echo e($selectedEnvironment['restaurant_name']); ?> - <?php echo e($selectedEnvironment['name']); ?></p>
            <?php endif; ?>
        </div>
        <form method="get" class="inline-form environment-switcher">
            <label>Ambiente
                <select name="environment_id" onchange="this.form.submit()">
                    <?php foreach ($environments as $environment): ?>
                        <option value="<?php echo (int)$environment['id']; ?>" <?php echo $selectedEnvironmentId === (int)$environment['id'] ? 'selected' : ''; ?>><?php echo e($environment['restaurant_name'] . ' - ' . $environment['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
    </div>

    <?php if ($selectedEnvironment): ?>
        <div class="layout-summary">
            <div><span>Restaurante</span><strong><?php echo e($selectedEnvironment['restaurant_name']); ?></strong></div>
            <div><span>Ambiente</span><strong><?php echo e($selectedEnvironment['name']); ?></strong></div>
            <div><span>Mesas</span><strong><?php echo count($tables); ?></strong></div>
            <div><span>Dimensao</span><strong><?php echo (int)$selectedEnvironment['width']; ?> x <?php echo (int)$selectedEnvironment['height']; ?></strong></div>
        </div>
    <?php endif; ?>

    <div class="settings-grid layout-actions">
        <?php if ($selectedEnvironment): ?>
            <form method="post" class="config-card">
                <div class="card-heading"><span>01</span><h3>Editar ambiente</h3></div>
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
                <label>Descrição <textarea name="description" rows="2"><?php echo e($selectedEnvironment['description']); ?></textarea></label>
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

        <form method="post" class="config-card">
            <div class="card-heading"><span>02</span><h3>Novo ambiente</h3></div>
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
                <label>Descrição <textarea name="description" rows="2"></textarea></label>
            <div class="grid two">
                <label>Largura <input type="number" name="width" value="960"></label>
                <label>Altura <input type="number" name="height" value="520"></label>
            </div>
            <button class="button primary" type="submit">Criar ambiente</button>
        </form>

        <form method="post" class="config-card accent-card">
            <div class="card-heading"><span>03</span><h3>Nova mesa</h3></div>
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="table">
            <input type="hidden" name="environment_id" value="<?php echo (int)$selectedEnvironmentId; ?>">
            <label>Identificação <input type="text" name="label" required placeholder="M01"></label>
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
        <div class="map-heading">
            <div>
                <h3>Editor visual do salão</h3>
                <p class="muted-line">Arraste as mesas para reposicionar. A posição é salva automaticamente.</p>
            </div>
        </div>
        <div class="floor-map" data-save-url="configuracoes.php" data-csrf="<?php echo e(csrf_token()); ?>" style="width: <?php echo (int)$selectedEnvironment['width']; ?>px; height: <?php echo (int)$selectedEnvironment['height']; ?>px;">
            <?php foreach ($tables as $table): ?>
                <?php $chairCount = min(max((int)$table['seats'], 1), 8); ?>
                <button class="map-table layout-<?php echo e(table_visual_layout($table)); ?> chair-count-<?php echo $chairCount; ?> <?php echo $table['status'] === 'inactive' ? 'inactive' : ''; ?>" data-id="<?php echo (int)$table['id']; ?>" style="left: <?php echo (int)$table['position_x']; ?>px; top: <?php echo (int)$table['position_y']; ?>px;">
                    <?php for ($chair = 1; $chair <= $chairCount; $chair++): ?>
                        <span class="chair chair-<?php echo $chair; ?>" aria-hidden="true"></span>
                    <?php endfor; ?>
                    <span class="table-surface">
                        <strong><?php echo e($table['label']); ?></strong>
                        <em><?php echo (int)$table['seats']; ?> lugares</em>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="section-title table-editor-title">
            <div>
                <p class="eyebrow">Cadastro fino</p>
                <h2>Editar mesas cadastradas</h2>
            </div>
        </div>
        <div class="table-editor-grid">
            <?php foreach ($tables as $table): ?>
                <form method="post" class="table-editor-card">
                    <div class="table-card-title">
                        <div class="table-title-group">
                            <span class="table-shape-preview <?php echo e(table_visual_layout($table)); ?>"></span>
                            <h3>Mesa <?php echo e($table['label']); ?></h3>
                        </div>
                        <span class="badge"><?php echo (int)$table['seats']; ?> lugares</span>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="environment_id" value="<?php echo (int)$selectedEnvironment['id']; ?>">
                    <input type="hidden" name="table_id" value="<?php echo (int)$table['id']; ?>">
                    <label>Identificação <input type="text" name="label" required value="<?php echo e($table['label']); ?>"></label>
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
                    <div class="table-card-actions">
                        <button class="button ghost" type="submit" name="action" value="update_table">Salvar mesa</button>
                        <button class="button danger" type="submit" name="action" value="delete_table" formnovalidate onclick="return confirm('Excluir esta mesa? Reservas vinculadas ficarão sem mesa definida.');">Excluir</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="settings-grid" id="questionario">
    <div class="panel">
        <div class="section-title"><div><p class="eyebrow">Experiência do cliente</p><h2>Questionário</h2></div></div>
        <form method="post" class="config-form">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="question">
            <label>Pergunta <input type="text" name="label" required></label>
            <div class="grid two">
                <label>Tipo
                    <select name="field_type">
                        <option value="text">Texto curto</option>
                        <option value="textarea">Texto longo</option>
                        <option value="select">Seleção</option>
                        <option value="checkbox">Sim/Não</option>
                    </select>
                </label>
                <label>Ordem <input type="number" name="sort_order" value="0"></label>
            </div>
            <label>Opções para seleção <textarea name="options_text" rows="3" placeholder="Uma opção por linha"></textarea></label>
            <label class="check"><input type="checkbox" name="is_required" value="1"> Obrigatória</label>
            <button class="button primary" type="submit">Adicionar pergunta</button>
        </form>
        <div class="pill-list">
            <?php foreach ($questions as $question): ?>
                <span><?php echo e($question['label']); ?> - <?php echo e($question['field_type']); ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="panel" id="ocasioes">
        <div class="section-title"><div><p class="eyebrow">Momentos especiais</p><h2>Ocasiões</h2></div></div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="action" value="occasion">
            <label>Nome <input type="text" name="name" required placeholder="Aniversário, celebração..."></label>
            <label class="check"><input type="checkbox" name="asks_birthday" value="1"> Solicitar dia e mês do aniversário</label>
            <button class="button primary" type="submit">Adicionar ocasião</button>
        </form>
        <div class="pill-list">
            <?php foreach ($occasions as $occasion): ?>
                <span><?php echo e($occasion['name']); ?><?php echo $occasion['asks_birthday'] ? ' - aniversário' : ''; ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<script src="../assets/js/admin.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
