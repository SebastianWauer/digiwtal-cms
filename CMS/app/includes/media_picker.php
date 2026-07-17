<?php
declare(strict_types=1);

/**
 * Reusable Media Picker (simple, ID-based)
 *
 * @param string $label Visible label
 * @param string $name  Input name (e.g. "cms_logo_light_media_id")
 * @param mixed  $value Current value (media id)
 * @param array  $opts  Optional: ['placeholder' => '...', 'openHref' => '/media']
 */
function media_picker_render(string $label, string $name, $value, array $opts = []): void
{
    $placeholder = (string)($opts['placeholder'] ?? 'Media-ID');
    $openHref    = (string)($opts['openHref'] ?? '/media');
    $showPreview = (bool)($opts['showPreview'] ?? false);

    $raw = (string)($value ?? '');
    $id  = (int)$raw;
    ?>
    <div class="mp">
      <div class="mp__meta">
        <div class="mp__label"><?= h($label) ?></div>
        <div class="mp__actions">
          <input class="ss-input mp__input" type="text" name="<?= h($name) ?>" value="<?= h($raw) ?>" placeholder="<?= h($placeholder) ?>">
          <button
            type="button"
            class="btn btn--ghost btn--sm mp__open"
            data-mp-input="<?= h($name) ?>"
            data-mp-title="<?= h($label) ?>"
          >
            Aus Medien wählen
          </button>

        </div>
      </div>

      <?php if ($showPreview): ?>
        <div class="mp__preview">
          <?php if ($id > 0): ?>
            <img src="/media/thumb?id=<?= $id ?>" alt="">
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}
