<?php
$title = 'Reservar mesa';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/header.php';

$occasions = $pdo->query("SELECT * FROM occasions WHERE status = 'active' ORDER BY name")->fetchAll();
$questions = $pdo->query("SELECT * FROM questionnaire_questions WHERE status = 'active' ORDER BY sort_order, id")->fetchAll();
$environments = $pdo->query("SELECT * FROM environments WHERE status = 'active' ORDER BY name")->fetchAll();
$customer = current_customer();
?>

<section class="hero">
    <div>
        <p class="eyebrow">Reserva inteligente</p>
        <h1>Escolha data, horario e conte o que o restaurante precisa saber.</h1>
        <p>Voce pode reservar sem login ou entrar para acompanhar suas reservas.</p>
    </div>
    <div class="hero-panel">
        <strong>Status rapido</strong>
        <span>Confirmacao por email</span>
        <span>Perguntas personalizadas</span>
        <span>Consentimento LGPD</span>
    </div>
</section>

<form class="form-grid" action="reservar.php" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">

    <section class="panel">
        <h2>Dados da reserva</h2>
        <div class="grid two">
            <label>Data
                <input type="date" name="reservation_date" required min="<?php echo date('Y-m-d'); ?>">
            </label>
            <label>Horario
                <input type="time" name="reservation_time" required>
            </label>
            <label>Numero de pessoas
                <input type="number" name="party_size" min="1" max="40" required>
            </label>
            <label>Ambiente preferido
                <select name="environment_id">
                    <option value="">Sem preferencia</option>
                    <?php foreach ($environments as $environment): ?>
                        <option value="<?php echo (int)$environment['id']; ?>"><?php echo e($environment['name']); ?></option>
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
            <label>Email
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
        <h2>Preferencias</h2>
        <div class="grid two">
            <label>Ocasiao especial
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
                <label>Dia do aniversario
                    <input type="number" name="birthday_day" min="1" max="31">
                </label>
                <label>Mes
                    <input type="number" name="birthday_month" min="1" max="12">
                </label>
            </div>
        </div>
        <label>Restricao alimentar
            <textarea name="dietary_restrictions" rows="3" placeholder="Alergias, lactose, frutos do mar, gluten..."></textarea>
        </label>
        <label>Algo mais que devemos saber?
            <textarea name="notes" rows="3"></textarea>
        </label>
    </section>

    <?php if ($questions): ?>
        <section class="panel">
            <h2>Questionario</h2>
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
