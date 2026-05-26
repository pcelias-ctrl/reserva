<?php
$title = 'Restaurantes';
$isAdmin = true;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $payload = array(
        trim($_POST['name']),
        trim($_POST['legal_name']),
        trim($_POST['document_number']),
        trim($_POST['email']),
        trim($_POST['phone']),
        only_digits($_POST['whatsapp']),
        trim($_POST['logo_url']),
        trim($_POST['address']),
        trim($_POST['reservation_message']),
        $_POST['status']
    );

    if ($id) {
        $payload[] = $id;
        $stmt = $pdo->prepare(
            'UPDATE restaurants
             SET name = ?, legal_name = ?, document_number = ?, email = ?, phone = ?, whatsapp = ?, logo_url = ?, address = ?, reservation_message = ?, status = ?
             WHERE id = ?'
        );
        $stmt->execute($payload);
        flash('success', 'Restaurante atualizado.');
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO restaurants (name, legal_name, document_number, email, phone, whatsapp, logo_url, address, reservation_message, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute($payload);
        flash('success', 'Restaurante cadastrado.');
    }

    redirect_to('restaurantes.php');
}

require_once __DIR__ . '/../includes/header.php';

$restaurants = $pdo->query('SELECT * FROM restaurants ORDER BY status, name')->fetchAll();
$edit = null;
if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM restaurants WHERE id = ?');
    $stmt->execute(array((int)$_GET['id']));
    $edit = $stmt->fetch();
}
?>
<section class="dashboard-hero compact-hero">
    <div>
        <p class="eyebrow">Multi-restaurante</p>
        <h1>Cadastre restaurantes, marcas, WhatsApp e identidade visual.</h1>
    </div>
</section>

<section class="settings-grid">
    <div class="panel">
        <h2><?php echo $edit ? 'Editar restaurante' : 'Novo restaurante'; ?></h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int)$edit['id']; ?>"><?php endif; ?>
            <label>Nome comercial
                <input type="text" name="name" required value="<?php echo e($edit ? $edit['name'] : ''); ?>">
            </label>
            <label>Razao social
                <input type="text" name="legal_name" value="<?php echo e($edit ? $edit['legal_name'] : ''); ?>">
            </label>
            <div class="grid two">
                <label>CNPJ/Documento
                    <input type="text" name="document_number" value="<?php echo e($edit ? $edit['document_number'] : ''); ?>">
                </label>
                <label>Status
                    <select name="status">
                        <option value="active" <?php echo !$edit || $edit['status'] === 'active' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inactive" <?php echo $edit && $edit['status'] === 'inactive' ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </label>
            </div>
            <div class="grid two">
                <label>Email
                    <input type="email" name="email" value="<?php echo e($edit ? $edit['email'] : ''); ?>">
                </label>
                <label>Telefone
                    <input type="text" name="phone" value="<?php echo e($edit ? $edit['phone'] : ''); ?>">
                </label>
            </div>
            <label>WhatsApp do restaurante
                <input type="text" name="whatsapp" required placeholder="5511999999999" value="<?php echo e($edit ? $edit['whatsapp'] : ''); ?>">
            </label>
            <label>URL do logo
                <input type="url" name="logo_url" placeholder="https://..." value="<?php echo e($edit ? $edit['logo_url'] : ''); ?>">
            </label>
            <label>Endereco
                <textarea name="address" rows="3"><?php echo e($edit ? $edit['address'] : ''); ?></textarea>
            </label>
            <label>Mensagem padrao da reserva
                <textarea name="reservation_message" rows="3"><?php echo e($edit ? $edit['reservation_message'] : 'Nova reserva recebida pelo Reserva On-line.'); ?></textarea>
            </label>
            <button class="button primary" type="submit"><?php echo $edit ? 'Salvar restaurante' : 'Cadastrar restaurante'; ?></button>
        </form>
    </div>

    <div class="restaurant-stack">
        <?php foreach ($restaurants as $restaurant): ?>
            <article class="restaurant-card">
                <div class="restaurant-logo">
                    <?php if (!empty($restaurant['logo_url'])): ?>
                        <img src="<?php echo e($restaurant['logo_url']); ?>" alt="<?php echo e($restaurant['name']); ?>">
                    <?php else: ?>
                        <span><?php echo e(substr($restaurant['name'], 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <h2><?php echo e($restaurant['name']); ?></h2>
                    <p><?php echo e($restaurant['email']); ?> · WhatsApp <?php echo e($restaurant['whatsapp']); ?></p>
                    <p><?php echo e($restaurant['address']); ?></p>
                    <span class="badge"><?php echo e($restaurant['status']); ?></span>
                </div>
                <a class="button ghost" href="restaurantes.php?id=<?php echo (int)$restaurant['id']; ?>">Editar</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
