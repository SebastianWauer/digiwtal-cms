<?php echo flash_render($flash ?? null); ?>

<h1 class="ss-title">Einstellungen</h1>

<form method="post" action="/settings">
  <?= admin_csrf_field() ?>

  <div class="ss-layout">

    <!-- LEFT -->
    <div>

      <!-- Basisdaten -->
      <div class="ss-card">
        <div class="ss-cardhead">
          <h2>Basisdaten</h2>
          <div class="ss-sub">Name &amp; Domain.</div>
        </div>

        <div class="ss-row2">
          <div>
            <div class="ss-label">Seitentitel</div>
            <input class="ss-input" type="text" name="site_title" value="<?= h($data['site_title'] ?? '') ?>">
          </div>

          <div>
            <div class="ss-label">Domain</div>
            <input class="ss-input" type="text" name="domain" value="<?= h($data['domain'] ?? '') ?>" placeholder="digiwtal.de">
          </div>
        </div>
      </div>

      <!-- Branding -->
      <div class="ss-card">
        <div class="ss-cardhead">
          <h2>Branding</h2>
          <div class="ss-sub">Assets f&uuml;r CMS und Frontend.</div>
        </div>

        <div class="ss-assets-grid">

          <div class="ss-asset">
            <?php media_picker_render('CMS Logo (hell)', 'cms_logo_light_media_id', $data['cms_logo_light_media_id'] ?? '', ['showPreview' => false]); ?>
            <div class="ss-preview">
              <?php $id = (int)($data['cms_logo_light_media_id'] ?? 0); ?>
              <?php if ($id > 0): ?>
                <img src="/media/thumb?id=<?= $id ?>" alt="">
              <?php endif; ?>
            </div>
          </div>

          <div class="ss-asset">
            <?php media_picker_render('CMS Logo (dunkel)', 'cms_logo_dark_media_id', $data['cms_logo_dark_media_id'] ?? '', ['showPreview' => false]); ?>
            <div class="ss-preview">
              <?php $id = (int)($data['cms_logo_dark_media_id'] ?? 0); ?>
              <?php if ($id > 0): ?>
                <img src="/media/thumb?id=<?= $id ?>" alt="">
              <?php endif; ?>
            </div>
          </div>

          <div class="ss-asset">
            <?php media_picker_render('Favicon', 'favicon_media_id', $data['favicon_media_id'] ?? '', ['showPreview' => false]); ?>
            <div class="ss-preview">
              <?php $id = (int)($data['favicon_media_id'] ?? 0); ?>
              <?php if ($id > 0): ?>
                <img src="/media/thumb?id=<?= $id ?>" alt="">
              <?php endif; ?>
            </div>
          </div>

        </div>
      </div>

      <!-- Kontakt & Rechtliches -->
      <div class="ss-card">
        <div class="ss-cardhead">
          <h2>Kontakt &amp; Rechtliches</h2>
          <div class="ss-sub">Angaben f&uuml;r Impressum, Kontakt &amp; Datenschutz.</div>
        </div>

        <div class="ss-row2">
          <div>
            <div class="ss-label">Kontakt-Name / Firma</div>
            <input class="ss-input" type="text" name="contact_name" value="<?= h($data['contact_name'] ?? '') ?>">
          </div>

          <div>
            <div class="ss-label">Kontakt-E-Mail</div>
            <input class="ss-input" type="email" name="contact_email" value="<?= h($data['contact_email'] ?? '') ?>">
          </div>

          <div>
            <div class="ss-label">Telefon</div>
            <input class="ss-input" type="text" name="contact_phone" value="<?= h($data['contact_phone'] ?? '') ?>">
          </div>

          <div>
            <div class="ss-label">Anschrift</div>
            <input class="ss-input" type="text" name="contact_address" value="<?= h($data['contact_address'] ?? '') ?>">
          </div>

          <div>
            <div class="ss-label">PLZ und Ort</div>
            <input class="ss-input" type="text" name="contact_postal_city" value="<?= h($data['contact_postal_city'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- Social Media -->
      <div class="ss-card">
        <div class="ss-cardhead">
          <h2>Social Media</h2>
          <div class="ss-sub">Profile, die im Theme (z. B. im Footer) genutzt werden k&ouml;nnen.</div>
        </div>

        <div class="ss-row2">
          <div>
            <div class="ss-label">Facebook</div>
            <input class="ss-input" type="text" name="social_facebook" value="<?= h($data['social_facebook'] ?? '') ?>" placeholder="https://facebook.com/...">
          </div>

          <div>
            <div class="ss-label">Instagram</div>
            <input class="ss-input" type="text" name="social_instagram" value="<?= h($data['social_instagram'] ?? '') ?>" placeholder="https://instagram.com/...">
          </div>

          <div>
            <div class="ss-label">YouTube</div>
            <input class="ss-input" type="text" name="social_youtube" value="<?= h($data['social_youtube'] ?? '') ?>" placeholder="https://youtube.com/...">
          </div>

          <div>
            <div class="ss-label">TikTok</div>
            <input class="ss-input" type="text" name="social_tiktok" value="<?= h($data['social_tiktok'] ?? '') ?>" placeholder="https://tiktok.com/@...">
          </div>

          <div>
            <div class="ss-label">X / Twitter</div>
            <input class="ss-input" type="text" name="social_x" value="<?= h($data['social_x'] ?? '') ?>" placeholder="https://x.com/...">
          </div>
        </div>
      </div>

      <!-- SEO Defaults -->
      <div class="ss-card">
        <div class="ss-cardhead">
          <h2>SEO &ndash; Globale Defaults</h2>
          <div class="ss-sub">Werden verwendet, wenn eine Seite keinen eigenen SEO-Override hat.</div>
        </div>

        <div class="ss-row2">
          <div>
            <div class="ss-label">Meta-Titel (Default)</div>
            <input class="ss-input" type="text" name="seo_meta_title_default" value="<?= h($data['seo_meta_title_default'] ?? '') ?>" placeholder="z.B. Meine Website">
          </div>

          <div>
            <div class="ss-label">Canonical-Base-URL</div>
            <input class="ss-input" type="text" name="seo_canonical_base" value="<?= h($data['seo_canonical_base'] ?? '') ?>" placeholder="https://example.com">
          </div>

          <div style="grid-column: 1 / -1">
            <div class="ss-label">Meta-Description (Default)</div>
            <textarea class="ss-input" name="seo_meta_description_default" rows="3" placeholder="Kurzbeschreibung der Website ..."><?= h($data['seo_meta_description_default'] ?? '') ?></textarea>
          </div>

          <div>
            <div class="ss-label">Robots (Default)</div>
            <select class="ss-input" name="seo_robots_default">
              <?php foreach (['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'] as $rv): ?>
                <option value="<?= h($rv) ?>" <?= ($data['seo_robots_default'] ?? 'index,follow') === $rv ? 'selected' : '' ?>><?= h($rv) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <div class="ss-label">OG-Bild (URL, Default)</div>
            <input class="ss-input" type="text" name="seo_og_image_url" value="<?= h($data['seo_og_image_url'] ?? '') ?>" placeholder="https://example.com/og.jpg">
          </div>
        </div>
      </div>

    </div>

    <!-- RIGHT -->
    <div>
      <div class="ss-card">
        <div class="ss-cardhead">
          <h2>Farben</h2>
          <div class="ss-sub">Prim&auml;r-, Sekund&auml;r- und Akzentfarbe f&uuml;r Frontend.</div>
        </div>

        <div class="ss-row2">
          <div>
            <div class="ss-label">Prim&auml;rfarbe</div>
            <input class="ss-input" type="color" name="brand_color_primary" value="<?= h($data['brand_color_primary'] ?? '#2563eb') ?>">
          </div>

          <div>
            <div class="ss-label">Sekund&auml;rfarbe</div>
            <input class="ss-input" type="color" name="brand_color_secondary" value="<?= h($data['brand_color_secondary'] ?? '#64748b') ?>">
          </div>

          <div>
            <div class="ss-label">Akzentfarbe</div>
            <input class="ss-input" type="color" name="brand_color_tertiary" value="<?= h($data['brand_color_tertiary'] ?? '#f59e0b') ?>">
          </div>
        </div>
      </div>

      <div class="ss-card">
        <div class="ss-cardhead">
          <h2>Site-&Uuml;berblick</h2>
          <div class="ss-sub">Schneller Blick auf die wichtigsten Eckdaten.</div>
        </div>

        <div class="ss-kv">
          <div>
            <div class="k">Site-Name</div>
            <div class="v"><?= h($data['site_title'] ?? '') ?></div>
          </div>
          <div>
            <div class="k">Domain</div>
            <div class="v"><?= h($data['domain'] ?? '') ?></div>
          </div>
          <div>
            <div class="k">Kontakt</div>
            <div class="v"><?= h($data['contact_email'] ?? '') ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="ss-sticky-save">
    <button type="submit" class="btn btn--primary">Speichern</button>
  </div>

</form>
