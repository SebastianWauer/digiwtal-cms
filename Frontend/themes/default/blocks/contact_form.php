<?php
/** @var array $block */
/** @var array $data */
/** @var array $contactFormStates */
/** @var string $currentSlug */
/** @var string $contactTurnstileSiteKey */

$payload = [];
if (is_array($block ?? null)) {
    $payload = $block;
}
if (is_array($data ?? null)) {
    $payload = array_merge($payload, $data);
}

$headline = trim((string)($payload['headline'] ?? 'Kontakt'));
$intro = trim((string)($payload['intro'] ?? ''));
$submitLabel = trim((string)($payload['submit_label'] ?? 'Nachricht senden'));
if ($submitLabel === '') {
    $submitLabel = 'Nachricht senden';
}
$showName = ((string)($payload['show_name'] ?? '1') !== '0');
$showEmail = ((string)($payload['show_email'] ?? '1') !== '0');
$showPhone = ((string)($payload['show_phone'] ?? '1') !== '0');
$showMessage = ((string)($payload['show_message'] ?? '1') !== '0');
if (!$showName && !$showEmail && !$showPhone && !$showMessage) {
    $showMessage = true;
}
$enabledFields = [];
if ($showName) $enabledFields[] = 'name';
if ($showEmail) $enabledFields[] = 'email';
if ($showPhone) $enabledFields[] = 'phone';
if ($showMessage) $enabledFields[] = 'message';

$candidateFormId = trim((string)($payload['form_id'] ?? ''));
if ($candidateFormId === '' || preg_match('/^[a-z0-9_-]{1,64}$/i', $candidateFormId) !== 1) {
    $candidateFormId = 'contact-' . ((int)($payload['_render_index'] ?? 0) + 1);
}
$formId = strtolower($candidateFormId);

$stateMap = is_array($contactFormStates ?? null) ? $contactFormStates : [];
$state = $stateMap[$formId] ?? ($stateMap['__global'] ?? null);
$stateStatus = is_array($state) ? (string)($state['status'] ?? '') : '';
$stateMessage = is_array($state) ? trim((string)($state['message'] ?? '')) : '';
$values = is_array($state) && is_array($state['values'] ?? null) ? $state['values'] : [];

$nameValue = trim((string)($values['name'] ?? ''));
$emailValue = trim((string)($values['email'] ?? ''));
$phoneValue = trim((string)($values['phone'] ?? ''));
$messageValue = trim((string)($values['message'] ?? ''));
$captchaAnswerValue = trim((string)($values['captcha_answer'] ?? ''));
$privacyConsentChecked = ((string)($values['privacy_consent'] ?? '') === '1');
$turnstileSiteKey = trim((string)($contactTurnstileSiteKey ?? ''));

$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$formAction = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/';

$tokenTs = time();
$tokenSig = '';
$tokenRobotSig = '';
$captchaA = random_int(2, 9);
$captchaB = random_int(1, 9);
$captchaSig = '';
if (function_exists('contactFormCreateSig')) {
    $tokenTs = time();
    $tokenSig = (string)contactFormCreateSig((string)($currentSlug ?? ''), $formId, $tokenTs);
    if (function_exists('contactFormCreateRobotSig')) {
        $tokenRobotSig = (string)contactFormCreateRobotSig((string)($currentSlug ?? ''), $formId, $tokenTs);
    }
    if (function_exists('contactFormCreateCaptchaSig')) {
        $captchaSig = (string)contactFormCreateCaptchaSig((string)($currentSlug ?? ''), $formId, $tokenTs, $captchaA, $captchaB);
    }
}
?>
<section class="block block-contact-form" id="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>">
  <div class="block-contact-form__inner">
    <?php if ($headline !== ''): ?>
      <h2><?= htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>
    <?php if ($intro !== ''): ?>
      <p class="block-contact-form__intro"><?= nl2br(htmlspecialchars($intro, ENT_QUOTES, 'UTF-8')) ?></p>
    <?php endif; ?>

    <?php if ($stateMessage !== ''): ?>
      <div class="block-contact-form__notice block-contact-form__notice--<?= $stateStatus === 'ok' ? 'ok' : 'error' ?>">
        <?= htmlspecialchars($stateMessage, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="block-contact-form__form" novalidate>
      <input type="hidden" name="_cf" value="1">
      <input type="hidden" name="_cf_form_id" value="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="_cf_fields" value="<?= htmlspecialchars(implode(',', $enabledFields), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="_cf_ts" value="<?= (int)$tokenTs ?>">
      <input type="hidden" name="_cf_sig" value="<?= htmlspecialchars($tokenSig, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="_cf_robot_sig" value="<?= htmlspecialchars($tokenRobotSig, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="_cf_cap_a" value="<?= (int)$captchaA ?>">
      <input type="hidden" name="_cf_cap_b" value="<?= (int)$captchaB ?>">
      <input type="hidden" name="_cf_cap_sig" value="<?= htmlspecialchars($captchaSig, ENT_QUOTES, 'UTF-8') ?>">

      <div class="block-contact-form__hp" aria-hidden="true">
        <label for="<?= htmlspecialchars($formId . '-website', ENT_QUOTES, 'UTF-8') ?>">Website</label>
        <input id="<?= htmlspecialchars($formId . '-website', ENT_QUOTES, 'UTF-8') ?>" type="text" name="website" tabindex="-1" autocomplete="off">
      </div>

      <?php if ($showName || $showEmail): ?>
      <div class="block-contact-form__grid">
        <?php if ($showName): ?>
        <label>
          <span>Name</span>
          <input type="text" name="name" required maxlength="120" value="<?= htmlspecialchars($nameValue, ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <?php endif; ?>
        <?php if ($showEmail): ?>
        <label>
          <span>E-Mail</span>
          <input type="email" name="email" required maxlength="190" value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($showPhone): ?>
      <label>
        <span>Telefon (optional)</span>
        <input type="text" name="phone" maxlength="80" value="<?= htmlspecialchars($phoneValue, ENT_QUOTES, 'UTF-8') ?>">
      </label>
      <?php endif; ?>

      <?php if ($showMessage): ?>
      <label>
        <span>Nachricht</span>
        <textarea name="message" rows="6" required maxlength="4000"><?= htmlspecialchars($messageValue, ENT_QUOTES, 'UTF-8') ?></textarea>
      </label>
      <?php endif; ?>

      <label class="block-contact-form__captcha">
        <span>Sicherheitsfrage: <?= (int)$captchaA ?> + <?= (int)$captchaB ?> = ?</span>
        <input type="text" name="captcha_answer" inputmode="numeric" pattern="[0-9-]*" required maxlength="4" value="<?= htmlspecialchars($captchaAnswerValue, ENT_QUOTES, 'UTF-8') ?>">
      </label>

      <?php if ($turnstileSiteKey !== ''): ?>
      <div class="block-contact-form__turnstile">
        <div class="cf-turnstile"
             data-sitekey="<?= htmlspecialchars($turnstileSiteKey, ENT_QUOTES, 'UTF-8') ?>"
             data-theme="light"
             data-language="de"></div>
      </div>
      <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
      <?php endif; ?>

      <label class="block-contact-form__consent">
        <input type="checkbox" name="privacy_consent" value="1" <?= $privacyConsentChecked ? 'checked' : '' ?> required>
        <span>Ihre Anfrage wird nach dem Absenden verschlüsselt via SSL übertragen. Wir werden Ihre Daten ausschliesslich zur Beantwortung Ihrer Anfrage verwenden.</span>
      </label>

      <button type="submit" class="block-contact-form__submit"><?= htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8') ?></button>
    </form>
  </div>
</section>
