<?php
$title = 'Restaurantes';
$isAdmin = true;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $logoMime = null;
    $logoData = null;
    $hasUpload = !empty($_FILES['logo_file']['tmp_name']) && is_uploaded_file($_FILES['logo_file']['tmp_name']);

    if ($hasUpload) {
        if ($_FILES['logo_file']['size'] > 4000000) {
            flash('error', 'A imagem deve ter no máximo 4MB.');
            redirect_to('restaurantes.php' . ($id ? '?id=' . $id : ''));
        }

        $info = @getimagesize($_FILES['logo_file']['tmp_name']);
        $allowed = array('image/jpeg', 'image/png', 'image/webp', 'image/gif');
        if (!$info || !in_array($info['mime'], $allowed, true)) {
            flash('error', 'Envie uma imagem JPG, PNG, WEBP ou GIF.');
            redirect_to('restaurantes.php' . ($id ? '?id=' . $id : ''));
        }

        $logoMime = $info['mime'];
        $logoData = file_get_contents($_FILES['logo_file']['tmp_name']);
    }

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
        if ($hasUpload) {
            $payload[] = $logoMime;
            $payload[] = $logoData;
            $payload[] = $id;
            $stmt = $pdo->prepare(
                'UPDATE restaurants
                 SET name = ?, legal_name = ?, document_number = ?, email = ?, phone = ?, whatsapp = ?, logo_url = ?, address = ?, reservation_message = ?, status = ?, logo_mime = ?, logo_data = ?
                 WHERE id = ?'
            );
        } elseif (!empty($_POST['remove_logo'])) {
            $payload[] = $id;
            $stmt = $pdo->prepare(
                'UPDATE restaurants
                 SET name = ?, legal_name = ?, document_number = ?, email = ?, phone = ?, whatsapp = ?, logo_url = ?, address = ?, reservation_message = ?, status = ?, logo_mime = NULL, logo_data = NULL
                 WHERE id = ?'
            );
        } else {
            $payload[] = $id;
            $stmt = $pdo->prepare(
                'UPDATE restaurants
                 SET name = ?, legal_name = ?, document_number = ?, email = ?, phone = ?, whatsapp = ?, logo_url = ?, address = ?, reservation_message = ?, status = ?
                 WHERE id = ?'
            );
        }
        $stmt->execute($payload);
        flash('success', $hasUpload ? 'Restaurante atualizado. Logo novo salvo no banco de dados.' : 'Restaurante atualizado.');
        redirect_to('restaurantes.php?id=' . $id);
    } else {
        $payload[] = $logoMime;
        $payload[] = $logoData;
        $stmt = $pdo->prepare(
            'INSERT INTO restaurants (name, legal_name, document_number, email, phone, whatsapp, logo_url, address, reservation_message, status, logo_mime, logo_data)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute($payload);
        flash('success', 'Restaurante cadastrado.');
        redirect_to('restaurantes.php?id=' . (int)$pdo->lastInsertId());
    }
}

require_once __DIR__ . '/../includes/header.php';

$restaurants = $pdo->query('SELECT id, name, legal_name, document_number, email, phone, whatsapp, logo_url, logo_mime, logo_data IS NOT NULL AS has_logo, IF(logo_data IS NULL, NULL, MD5(logo_data)) AS logo_version, address, reservation_message, status, created_at FROM restaurants ORDER BY status, name')->fetchAll();
$edit = null;
if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT id, name, legal_name, document_number, email, phone, whatsapp, logo_url, logo_mime, logo_data IS NOT NULL AS has_logo, IF(logo_data IS NULL, NULL, MD5(logo_data)) AS logo_version, address, reservation_message, status, created_at FROM restaurants WHERE id = ?');
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
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int)$edit['id']; ?>"><?php endif; ?>
            <label>Nome comercial
                <input type="text" name="name" required value="<?php echo e($edit ? $edit['name'] : ''); ?>">
            </label>
            <label>Razão social
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
                <label>E-mail
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
            <label>Upload da foto/logo
                <input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/gif">
            </label>
            <?php if ($edit && !empty($edit['has_logo'])): ?>
                <div class="logo-preview-row">
                    <span class="restaurant-logo">
                        <strong><?php echo e(substr($edit['name'], 0, 1)); ?></strong>
                        <img src="<?php echo e(restaurant_logo_src($edit)); ?>" alt="<?php echo e($edit['name']); ?>" onerror="this.style.display='none'; this.parentElement.classList.add('logo-fallback');">
                    </span>
                    <p>Logo salvo no banco de dados.</p>
                </div>
                <label class="check"><input type="checkbox" name="remove_logo" value="1"> Remover logo salvo no banco</label>
            <?php endif; ?>
            <label>Endereço
                <textarea name="address" rows="3"><?php echo e($edit ? $edit['address'] : ''); ?></textarea>
            </label>
            <label>Mensagem padrão da reserva
                <textarea name="reservation_message" rows="3"><?php echo e($edit ? $edit['reservation_message'] : 'Nova reserva recebida pelo Reserva On-line.'); ?></textarea>
            </label>
            <button class="button primary" type="submit"><?php echo $edit ? 'Salvar restaurante' : 'Cadastrar restaurante'; ?></button>
        </form>
    </div>

    <div class="restaurant-stack">
        <?php foreach ($restaurants as $restaurant): ?>
            <article class="restaurant-card">
                <div class="restaurant-logo">
                    <?php if ($logo = restaurant_logo_src($restaurant)): ?>
                        <strong><?php echo e(substr($restaurant['name'], 0, 1)); ?></strong>
                        <img src="<?php echo e($logo); ?>" alt="<?php echo e($restaurant['name']); ?>" onerror="this.style.display='none'; this.parentElement.classList.add('logo-fallback');">
                    <?php else: ?>
                        <strong><?php echo e(substr($restaurant['name'], 0, 1)); ?></strong>
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
