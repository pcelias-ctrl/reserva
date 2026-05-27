<?php
$title = 'Reservar mesa';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';

$occasions = $pdo->query("SELECT * FROM occasions WHERE status = 'active' ORDER BY name")->fetchAll();
$questions = $pdo->query("SELECT * FROM questionnaire_questions WHERE status = 'active' ORDER BY sort_order, id")->fetchAll();
$restaurants = $pdo->query("SELECT id, name, logo_url, logo_mime, logo_data IS NOT NULL AS has_logo, IF(logo_data IS NULL, NULL, MD5(logo_data)) AS logo_version, address FROM restaurants WHERE status = 'active' ORDER BY name")->fetchAll();
$environments = $pdo->query("SELECT e.*, r.name restaurant_name FROM environments e INNER JOIN restaurants r ON r.id = e.restaurant_id WHERE e.status = 'active' AND r.status = 'active' ORDER BY r.name, e.name")->fetchAll();
$customer = current_customer();
?>

<section class="hero">
    <div>
        <p class="eyebrow">i-Reserva</p>
        <h1>Reserve sua mesa com confirmação direta pelo restaurante.</h1>
        <p>Escolha o restaurante, informe data, horário e preferências. Ao finalizar, os dados ficam prontos para envio por WhatsApp.</p>
    </div>
    <div class="hero-panel">
        <strong>Sua mesa, sem espera</strong>
        <span>Reserva enviada direto ao restaurante</span>
        <span>Atendimento preparado antes da chegada</span>
        <span>Confirmação rápida pelo WhatsApp</span>
    </div>
</section>

<form class="form-grid" action="reservar.php" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

    <section class="panel">
        <h2>Dados da reserva</h2>
        <div class="restaurant-choice">
            <?php foreach ($restaurants as $index => $restaurant): ?>
                <label class="restaurant-option">
                    <input type="radio" name="restaurant_id" value="<?php echo (int)$restaurant['id']; ?>" <?php echo $index === 0 ? 'checked' : ''; ?> required>
                    <span class="restaurant-logo small">
                        <?php if ($logo = restaurant_logo_src($restaurant)): ?>
                            <strong><?php echo e(substr($restaurant['name'], 0, 1)); ?></strong>
                            <img src="<?php echo e($logo); ?>" alt="<?php echo e($restaurant['name']); ?>" onerror="this.style.display='none'; this.parentElement.classList.add('logo-fallback');">
                        <?php else: ?>
                            <strong><?php echo e(substr($restaurant['name'], 0, 1)); ?></strong>
                        <?php endif; ?>
                    </span>
                    <span>
                        <strong><?php echo e($restaurant['name']); ?></strong>
                        <em><?php echo e($restaurant['address']); ?></em>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="grid two">
            <label>Data
                <input type="date" name="reservation_date" required min="<?php echo date('Y-m-d'); ?>">
            </label>
            <label>Horário
                <input type="time" name="reservation_time" required>
            </label>
            <label>Número de pessoas
                <input type="number" name="party_size" min="1" max="40" required>
            </label>
            <label>Ambiente preferido
                <select name="environment_id">
                    <option value="">Sem preferência</option>
                    <?php foreach ($environments as $environment): ?>
                        <option value="<?php echo (int)$environment['id']; ?>" data-restaurant="<?php echo (int)$environment['restaurant_id']; ?>">
                            <?php echo e($environment['restaurant_name'] . ' - ' . $environment['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </section>

    <section class="panel">
        <h2>Seus dados</h2>
        <div class="grid two">
            <label>Nome
                <input type="text" name="customer_name" required value="<?php echo e($customer ? $customer['name'] : ''); ?>">
            </label>
            <label>E-mail
                <input type="email" name="customer_email" required value="<?php echo e($customer ? $customer['email'] : ''); ?>">
            </label>
            <label>Telefone/WhatsApp
                <input type="text" name="customer_phone" required value="<?php echo e($customer ? $customer['phone'] : ''); ?>">
            </label>
            <?php if (!$customer): ?>
                <label>Criar senha para acompanhar depois
                    <input type="password" name="customer_password" placeholder="Opcional">
                </label>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
            <h2>Preferências</h2>
        <div class="grid two">
            <label>Ocasião especial
                <select name="occasion_id" id="occasionSelect">
                    <option value="">Nenhuma</option>
                    <?php foreach ($occasions as $occasion): ?>
                        <option value="<?php echo (int)$occasion['id']; ?>" data-birthday="<?php echo (int)$occasion['asks_birthday']; ?>">
                            <?php echo e($occasion['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="birthday-fields" id="birthdayFields">
                <label>Dia do aniversário
                    <input type="number" name="birthday_day" min="1" max="31">
                </label>
                <label>Mês
                    <input type="number" name="birthday_month" min="1" max="12">
                </label>
            </div>
        </div>
        <label>Restrição alimentar
            <textarea name="dietary_restrictions" rows="3" placeholder="Alergias, lactose, frutos do mar, glúten..."></textarea>
        </label>
        <label>Algo mais que devemos saber?
            <textarea name="notes" rows="3"></textarea>
        </label>
    </section>

    <?php if ($questions): ?>
        <section class="panel">
            <h2>Questionário</h2>
            <?php foreach ($questions as $question): ?>
                <label><?php echo e($question['label']); ?>
                    <?php if ($question['field_type'] === 'textarea'): ?>
                        <textarea name="answers[<?php echo (int)$question['id']; ?>]" rows="3" <?php echo $question['is_required'] ? 'required' : ''; ?>></textarea>
                    <?php elseif ($question['field_type'] === 'select'): ?>
                        <select name="answers[<?php echo (int)$question['id']; ?>]" <?php echo $question['is_required'] ? 'required' : ''; ?>>
                            <option value="">Selecione</option>
                            <?php foreach (preg_split('/\r\n|\r|\n/', (string)$question['options_text']) as $option): ?>
                                <?php if (trim($option) !== ''): ?>
                                    <option value="<?php echo e(trim($option)); ?>"><?php echo e(trim($option)); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($question['field_type'] === 'checkbox'): ?>
                        <input type="checkbox" name="answers[<?php echo (int)$question['id']; ?>]" value="Sim">
                    <?php else: ?>
                        <input type="text" name="answers[<?php echo (int)$question['id']; ?>]" <?php echo $question['is_required'] ? 'required' : ''; ?>>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="panel">
        <h2>Consentimentos</h2>
        <label class="check">
            <input type="checkbox" name="lgpd_terms_consent" value="1" required>
            Autorizo o uso dos meus dados para processamento desta reserva, conforme a LGPD.
        </label>
        <label class="check">
            <input type="checkbox" name="lgpd_share_consent" value="1">
            Autorizo o compartilhamento interno dos dados com a equipe do restaurante para personalizar o atendimento.
        </label>
        <button class="button primary" type="submit">Enviar reserva</button>
    </section>
</form>

<script src="../assets/js/public.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
