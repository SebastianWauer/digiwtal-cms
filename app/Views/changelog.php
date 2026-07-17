<?php
declare(strict_types=1);

/** @var array  $entries  Zeilen aus changelogs */
/** @var int    $page     Aktuelle Seite */
/** @var int    $pages    Anzahl Seiten gesamt */
/** @var int    $total    Gesamtanzahl Einträge */

// -------------------------------------------------------
// Minimaler Markdown-Renderer (nur für Changelog-Inhalte)
// Erlaubte Tags: h2, h3, ul, ol, li, p, strong, em, code, pre, a
// Alles andere wird HTML-escaped.
// -------------------------------------------------------
if (!function_exists('cl_inline_md')) {
    function cl_inline_md(string $text): string
    {
        // Links [text](url) – nur https?-URLs – vor HTML-Escaping extrahieren
        $tokens = [];
        $text = (string)preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/',
            static function (array $m) use (&$tokens): string {
                $key          = "\x01" . count($tokens) . "\x01";
                $tokens[$key] = '<a href="' . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8')
                              . '" rel="noopener noreferrer">'
                              . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8')
                              . '</a>';
                return $key;
            },
            $text
        );

        // HTML escapen (*, `, \x01 sind keine HTML-Sonderzeichen → bleiben erhalten)
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // **fett**
        $text = (string)preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $text);
        // *kursiv* (nicht neben *)
        $text = (string)preg_replace('/(?<!\*)\*(?!\*)([^*\n]+)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text);
        // `code`
        $text = (string)preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $text);

        // Links wiederherstellen
        foreach ($tokens as $k => $v) {
            $text = str_replace($k, $v, $text);
        }

        return $text;
    }

    function cl_render_md(string $md): string
    {
        $html   = '';
        $lines  = explode("\n", str_replace(["\r\n", "\r"], "\n", $md));
        $inList = false;
        $inPre  = false;

        foreach ($lines as $line) {
            // Fenced code block ```
            if (str_starts_with(ltrim($line), '```')) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html  .= $inPre ? '</code></pre>' : '<pre><code>';
                $inPre  = !$inPre;
                continue;
            }

            if ($inPre) {
                $html .= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "\n";
                continue;
            }

            // ### Heading
            if (preg_match('/^### (.+)/', $line, $m)) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<h3>' . cl_inline_md(trim($m[1])) . '</h3>';
                continue;
            }
            // ## Heading
            if (preg_match('/^## (.+)/', $line, $m)) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<h2>' . cl_inline_md(trim($m[1])) . '</h2>';
                continue;
            }
            // Listenpunkt: - oder *
            if (preg_match('/^[*-] (.+)/', $line, $m)) {
                if (!$inList) { $html .= '<ul>'; $inList = true; }
                $html .= '<li>' . cl_inline_md(trim($m[1])) . '</li>';
                continue;
            }
            // Leerzeile
            if (trim($line) === '') {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                continue;
            }
            // Normaler Absatz
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<p>' . cl_inline_md($line) . '</p>';
        }

        if ($inList) $html .= '</ul>';
        if ($inPre)  $html .= '</code></pre>';

        return $html;
    }
}
?>

<?php if (empty($entries)): ?>
  <p class="cl-empty">Noch keine Changelog-Einträge vorhanden.</p>
<?php else: ?>

  <div class="cl-list">
    <?php foreach ($entries as $entry): ?>
      <?php
        $relDate  = (string)($entry['released_at'] ?? '');
        $dispDate = $relDate !== '' ? date('d.m.Y', (int)strtotime($relDate)) : '–';
        $isModule = ((string)($entry['type'] ?? 'cms')) !== 'cms';
        $label    = $isModule
            ? htmlspecialchars((string)($entry['module_key'] ?? 'Modul'), ENT_QUOTES, 'UTF-8')
            : 'CMS';
      ?>
      <article class="cl-entry">
        <header class="cl-head">
          <span class="cl-version"><?= htmlspecialchars((string)($entry['version'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="cl-badge cl-badge--<?= $isModule ? 'module' : 'cms' ?>"><?= $label ?></span>
          <time class="cl-date" datetime="<?= htmlspecialchars($relDate, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dispDate, ENT_QUOTES, 'UTF-8') ?></time>
        </header>
        <div class="cl-body">
          <?= cl_render_md((string)($entry['content_md'] ?? '')) ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="cl-pager" aria-label="Pagination">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <?php if ($p === $page): ?>
          <span class="cl-pager__item cl-pager__item--active"><?= $p ?></span>
        <?php else: ?>
          <a class="cl-pager__item" href="?page=<?= $p ?>"><?= $p ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>

<?php endif; ?>

<style>
/* Changelog – scoped styles (kein externes CSS erforderlich) */
.cl-list        { display: flex; flex-direction: column; gap: 1.25rem; max-width: 860px; }
.cl-empty       { color: var(--c-text-muted, #888); font-size: .9rem; }

.cl-entry       { background: var(--c-card, rgba(255,255,255,.04));
                  border: 1px solid var(--c-border, #2a2a2a);
                  border-radius: 8px; padding: 1.25rem 1.5rem; }

.cl-head        { display: flex; align-items: center; gap: .75rem; margin-bottom: .875rem;
                  flex-wrap: wrap; }
.cl-version     { font-weight: 700; font-size: 1rem;
                  color: var(--c-text, #f0f0f0); font-family: monospace; }
.cl-badge       { font-size: .7rem; font-weight: 600; text-transform: uppercase;
                  letter-spacing: .06em; padding: .2em .6em; border-radius: 4px; }
.cl-badge--cms  { background: var(--c-primary, #2563eb22);
                  color: var(--c-primary-text, #60a5fa);
                  border: 1px solid var(--c-primary, #2563eb44); }
.cl-badge--module { background: var(--c-accent, #f59e0b22);
                    color: var(--c-accent-text, #fbbf24);
                    border: 1px solid var(--c-accent, #f59e0b44); }
.cl-date        { margin-left: auto; font-size: .8rem;
                  color: var(--c-text-muted, #888); white-space: nowrap; }

/* Rendered Markdown */
.cl-body h2,
.cl-body h3     { margin: .875rem 0 .375rem; font-size: .925rem; font-weight: 700;
                  color: var(--c-text, #f0f0f0); }
.cl-body h3     { font-size: .875rem; }
.cl-body p      { margin: .25rem 0 .5rem; font-size: .875rem;
                  color: var(--c-text-secondary, #ccc); line-height: 1.6; }
.cl-body ul     { margin: .25rem 0 .5rem 1.25rem; padding: 0;
                  font-size: .875rem; color: var(--c-text-secondary, #ccc); }
.cl-body li     { margin-bottom: .2rem; line-height: 1.55; }
.cl-body code   { background: var(--c-code-bg, rgba(255,255,255,.08));
                  padding: .1em .4em; border-radius: 3px;
                  font-family: monospace; font-size: .8125rem; }
.cl-body pre    { background: var(--c-code-bg, rgba(0,0,0,.3));
                  padding: .75rem 1rem; border-radius: 6px; overflow-x: auto;
                  margin: .5rem 0; }
.cl-body pre code { background: none; padding: 0; font-size: .8rem; }
.cl-body a      { color: var(--c-link, #60a5fa); text-decoration: underline; }
.cl-body strong { font-weight: 700; color: var(--c-text, #f0f0f0); }
.cl-body em     { font-style: italic; }

/* Pagination */
.cl-pager       { display: flex; gap: .375rem; margin-top: 1.25rem; flex-wrap: wrap; }
.cl-pager__item { display: inline-flex; align-items: center; justify-content: center;
                  min-width: 2rem; height: 2rem; padding: 0 .5rem;
                  border-radius: 5px; font-size: .8rem; font-weight: 500;
                  border: 1px solid var(--c-border, #333);
                  color: var(--c-text-muted, #888); text-decoration: none; }
.cl-pager__item:not(.cl-pager__item--active):hover {
                  background: var(--c-row-hover, rgba(255,255,255,.06));
                  color: var(--c-text, #f0f0f0); }
.cl-pager__item--active {
                  background: var(--c-primary, #2563eb);
                  border-color: var(--c-primary, #2563eb);
                  color: #fff; cursor: default; }
</style>
