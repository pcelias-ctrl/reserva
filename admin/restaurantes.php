<?php
$title = 'Restaurantes';
$isAdmin = true;
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;

    if (isset($_POST['action']) && $_POST['action'] === 'test_email' && $id) {
        $stmt = $pdo->prepare('SELECT * FROM restaurants WHERE id = ?');
        $stmt->execute(array($id));
        $restaurant = $stmt->fetch();
        if (!$restaurant) {
            flash('error', 'Restaurante não encontrado para teste de e-mail.');
            redirect_to('restaurantes.php');
        }

        $target = !empty($restaurant['smtp_admin_email']) ? $restaurant['smtp_admin_email'] : $restaurant['email'];
        $message = "Teste de e-mail do i-Reserva\n\nSe você recebeu esta mensagem, o SMTP do restaurante está configurado corretamente.";
        if (send_reservation_email($target, 'Teste de SMTP - i-Reserva', $message, $restaurant)) {
            flash('success', 'E-mail de teste enviado para ' . $target . '.');
        } else {
            flash('error', 'Falha no teste de e-mail: ' . last_email_error());
        }
        redirect_to('restaurantes.php?id=' . $id);
    }

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
        $_POST['status'],
        isset($_POST['smtp_enabled']) ? 1 : 0,
        trim($_POST['smtp_host']),
        (int)$_POST['smtp_port'],
        trim($_POST['smtp_username']),
        trim($_POST['smtp_encryption']),
        trim($_POST['smtp_from_email']),
        trim($_POST['smtp_from_name']),
        trim($_POST['smtp_admin_email'])
    );
    $smtpPassword = trim($_POST['smtp_password']);

    if ($id) {
        if ($smtpPassword !== '') {
            $payload[] = $smtpPassword;
            $smtpPasswordSql = ', smtp_password = ?';
        } else {
            $smtpPasswordSql = '';
        }

        if ($hasUpload) {
            $payload[] = $logoMime;
            $payload[] = $logoData;
            $payload[] = $id;
            $stmt = $pdo->prepare(
                'UPDATE restaurants
                 SET name = ?, legal_name = ?, document_number = ?, email = ?, phone = ?, whatsapp = ?, logo_url = ?, address = ?, reservation_message = ?, status = ?, smtp_enabled = ?, smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_encryption = ?, smtp_from_email = ?, smtp_from_name = ?, smtp_admin_email = ?' . $smtpPasswordSql . ', logo_mime = ?, logo_data = ?
                 WHERE id = ?'
            );
        } elseif (!empty($_POST['remove_logo'])) {
            $payload[] = $id;
            $stmt = $pdo->prepare(
                'UPDATE restaurants
                 SET name = ?, legal_name = ?, document_number = ?, email = ?, phone = ?, whatsapp = ?, logo_url = ?, address = ?, reservation_message = ?, status = ?, smtp_enabled = ?, smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_encryption = ?, smtp_from_email = ?, smtp_from_name = ?, smtp_admin_email = ?' . $smtpPasswordSql . ', logo_mime = NULL, logo_data = NULL
                 WHERE id = ?'
            );
        } else {
            $payload[] = $id;
            $stmt = $pdo->prepare(
                'UPDATE restaurants
                 SET name = ?, legal_name = ?, document_number = ?, email = ?, phone = ?, whatsapp = ?, logo_url = ?, address = ?, reservation_message = ?, status = ?, smtp_enabled = ?, smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_encryption = ?, smtp_from_email = ?, smtp_from_name = ?, smtp_admin_email = ?' . $smtpPasswordSql . '
                 WHERE id = ?'
            );
        }
        $stmt->execute($payload);
        flash('success', $hasUpload ? 'Restaurante atualizado. Logo novo salvo no banco de dados.' : 'Restaurante atualizado.');
        redirect_to('restaurantes.php?id=' . $id);
    } else {
        $payload[] = $smtpPassword;
        $payload[] = $logoMime;
        $payload[] = $logoData;
        $stmt = $pdo->prepare(
            'INSERT INTO restaurants (name, legal_name, document_number, email, phone, whatsapp, logo_url, address, reservation_message, status, smtp_enabled, smtp_host, smtp_port, smtp_username, smtp_encryption, smtp_from_email, smtp_from_name, smtp_admin_email, smtp_password, logo_mime, logo_data)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute($payload);
        flash('success', 'Restaurante cadastrado.');
        redirect_to('restaurantes.php?id=' . (int)$pdo->lastInsertId());
    }
}

require_once __DIR__ . '/../includes/header.php';

$restaurants = $pdo->query('SELECT id, name, legal_name, document_number, email, phone, whatsapp, logo_url, logo_mime, logo_data IS NOT NULL AS has_logo, IF(logo_data IS NULL, NULL, MD5(logo_data)) AS logo_version, address, reservation_message, status, smtp_enabled, smtp_host, smtp_port, smtp_username, smtp_encryption, smtp_from_email, smtp_from_name, smtp_admin_email, created_at FROM restaurants ORDER BY status, name')->fetchAll();
$edit = null;
if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT id, name, legal_name, document_number, email, phone, whatsapp, logo_url, logo_mime, logo_data IS NOT NULL AS has_logo, IF(logo_data IS NULL, NULL, MD5(logo_data)) AS logo_version, address, reservation_message, status, smtp_enabled, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, smtp_from_email, smtp_from_name, smtp_admin_email, created_at FROM restaurants WHERE id = ?');
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
            <div class="smtp-box">
                <div class="section-title">
                    <div>
                        <p class="eyebrow">E-mail</p>
                        <h2>SMTP do restaurante</h2>
                    </div>
                </div>
                <label class="check"><input type="checkbox" name="smtp_enabled" value="1" <?php echo $edit && !empty($edit['smtp_enabled']) ? 'checked' : ''; ?>> Usar SMTP próprio para este restaurante</label>
                <div class="grid two">
                    <label>Servidor SMTP
                        <input type="text" name="smtp_host" placeholder="smtp.gmail.com" value="<?php echo e($edit ? $edit['smtp_host'] : ''); ?>">
                    </label>
                    <label>Porta
                        <input type="number" name="smtp_port" value="<?php echo e($edit && $edit['smtp_port'] ? $edit['smtp_port'] : 587); ?>">
                    </label>
                </div>
                <div class="grid two">
                    <label>Usuário
                        <input type="text" name="smtp_username" placeholder="reservas@restaurante.com" value="<?php echo e($edit ? $edit['smtp_username'] : ''); ?>">
                    </label>
                    <label>Senha
                        <input type="password" name="smtp_password" placeholder="<?php echo $edit && !empty($edit['smtp_password']) ? 'Deixe vazio para manter a senha atual' : 'Senha SMTP ou senha de app'; ?>">
                    </label>
                </div>
                <div class="grid two">
                    <label>Segurança
                        <select name="smtp_encryption">
                            <?php $smtpEncryption = $edit && $edit['smtp_encryption'] ? $edit['smtp_encryption'] : 'tls'; ?>
                            <option value="tls" <?php echo $smtpEncryption === 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo $smtpEncryption === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo $smtpEncryption === 'none' ? 'selected' : ''; ?>>Nenhuma</option>
                        </select>
                    </label>
                    <label>E-mail de destino administrativo
                        <input type="email" name="smtp_admin_email" placeholder="reservas@restaurante.com" value="<?php echo e($edit ? $edit['smtp_admin_email'] : ''); ?>">
                    </label>
                </div>
                <div class="grid two">
                    <label>Remetente
                        <input type="email" name="smtp_from_email" placeholder="reservas@restaurante.com" value="<?php echo e($edit ? $edit['smtp_from_email'] : ''); ?>">
                    </label>
                    <label>Nome do remetente
                        <input type="text" name="smtp_from_name" placeholder="Nome do restaurante" value="<?php echo e($edit ? $edit['smtp_from_name'] : ''); ?>">
                    </label>
                </div>
                <p class="muted-line">Para Gmail ou Google Workspace, use senha de app. A senha preenchida substitui a anterior; em branco, ela permanece igual.</p>
            </div>
            <button class="button primary" type="submit"><?php echo $edit ? 'Salvar restaurante' : 'Cadastrar restaurante'; ?></button>
        </form>
        <?php if ($edit): ?>
            <form method="post" class="email-test-form">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="id" value="<?php echo (int)$edit['id']; ?>">
                <input type="hidden" name="action" value="test_email">
                <button class="button ghost" type="submit">Enviar e-mail de teste SMTP</button>
                <p class="muted-line">O teste usa a configuração salva do restaurante e envia para o e-mail administrativo, ou para o e-mail do restaurante.</p>
            </form>
        <?php endif; ?>
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
