<?php
/** @var array $block */
/** @var array|null $data */
/** @var array $publicSettings */

$p = [];
if (isset($data) && is_array($data)) {
    $p = $data;
} elseif (isset($block['data']) && is_array($block['data'])) {
    $p = array_merge($block, $block['data']);
} elseif (is_array($block)) {
    $p = $block;
}

$settings = is_array($publicSettings ?? null) ? $publicSettings : [];

$headline = trim((string)($p['headline'] ?? 'Impressum'));
$companyName = trim((string)($p['company_name'] ?? ''));
if ($companyName === '') {
    $companyName = trim((string)($settings['contact_name'] ?? ($settings['site_name'] ?? '')));
}
$legalForm = trim((string)($p['legal_form'] ?? ''));
$representative = trim((string)($p['representative'] ?? ''));
$address = trim((string)($p['address'] ?? ''));
if ($address === '') {
    $address = trim((string)($settings['contact_address'] ?? ''));
}
$postalCity = trim((string)($p['postal_city'] ?? ''));
if ($postalCity === '') {
    $postalCity = trim((string)($settings['contact_postal_city'] ?? ''));
}
$country = trim((string)($p['country'] ?? ''));
$phone = trim((string)($p['phone'] ?? ''));
if ($phone === '') {
    $phone = trim((string)($settings['contact_phone'] ?? ''));
}
$email = trim((string)($p['email'] ?? ''));
if ($email === '') {
    $email = trim((string)($settings['contact_email'] ?? ''));
}
$website = trim((string)($p['website'] ?? ''));
if ($website === '') {
    $website = trim((string)($settings['domain'] ?? ''));
}
$registerEntry = trim((string)($p['register_entry'] ?? ''));
$vatId = trim((string)($p['vat_id'] ?? ''));
$responsiblePerson = trim((string)($p['responsible_person'] ?? ''));
$disputeText = trim((string)($p['dispute_text'] ?? ''));
$additionalInfo = trim((string)($p['additional_info'] ?? ''));

if ($companyName === '' && $address === '' && $email === '' && $phone === '' && $registerEntry === '' && $vatId === '' && $responsiblePerson === '' && $disputeText === '' && $additionalInfo === '') {
    return;
}

$emailHref = '';
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $emailHref = 'mailto:' . $email;
}

$websiteHref = '';
if ($website !== '') {
    if (preg_match('#^https?://#i', $website) === 1) {
        $websiteHref = $website;
    } else {
        $websiteHref = 'https://' . ltrim($website, '/');
    }
}
?>
<section class="block block-imprint">
  <div class="block-imprint__inner">
    <?php if ($headline !== ''): ?>
      <h2><?= htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php endif; ?>

    <?php if ($companyName !== '' || $legalForm !== ''): ?>
      <p><strong><?= htmlspecialchars(trim($companyName . ($legalForm !== '' ? (' - ' . $legalForm) : '')), ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php endif; ?>

    <?php if ($representative !== ''): ?>
      <p>Vertreten durch: <?= htmlspecialchars($representative, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($address !== '' || $postalCity !== '' || $country !== ''): ?>
      <p>
        <?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?><br>
        <?= htmlspecialchars($postalCity, ENT_QUOTES, 'UTF-8') ?><br>
        <?= htmlspecialchars($country, ENT_QUOTES, 'UTF-8') ?>
      </p>
    <?php endif; ?>

    <?php if ($phone !== '' || $email !== '' || $website !== ''): ?>
      <p>
        <?php if ($phone !== ''): ?>Telefon: <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?><br><?php endif; ?>
        <?php if ($email !== ''): ?>
          E-Mail:
          <?php if ($emailHref !== ''): ?>
            <a href="<?= htmlspecialchars($emailHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></a>
          <?php else: ?>
            <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>
          <?php endif; ?>
          <br>
        <?php endif; ?>
        <?php if ($website !== ''): ?>
          Website:
          <?php if ($websiteHref !== ''): ?>
            <a href="<?= htmlspecialchars($websiteHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($website, ENT_QUOTES, 'UTF-8') ?></a>
          <?php else: ?>
            <?= htmlspecialchars($website, ENT_QUOTES, 'UTF-8') ?>
          <?php endif; ?>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <?php if ($registerEntry !== ''): ?>
      <p>Registereintrag: <?= htmlspecialchars($registerEntry, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($vatId !== ''): ?>
      <p>USt-IdNr.: <?= htmlspecialchars($vatId, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($responsiblePerson !== ''): ?>
      <p>Inhaltlich verantwortlich: <?= htmlspecialchars($responsiblePerson, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <?php if ($disputeText !== ''): ?>
      <p><?= nl2br(htmlspecialchars($disputeText, ENT_QUOTES, 'UTF-8')) ?></p>
    <?php endif; ?>

    <?php if ($additionalInfo !== ''): ?>
      <div class="block-imprint__extra"><?= nl2br(htmlspecialchars($additionalInfo, ENT_QUOTES, 'UTF-8')) ?></div>
    <?php endif; ?>
  </div>
</section>
