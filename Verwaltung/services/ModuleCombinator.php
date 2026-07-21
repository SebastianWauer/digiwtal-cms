<?php
declare(strict_types=1);

/**
 * Bestimmt welche Dateien für einen spezifischen Kunden deployed werden sollen.
 * Kombiniert CMS-Core mit den lizenzierten Modulen des Kunden.
 *
 * WICHTIG: Plugin-Verzeichnisnamen in CMS/plugins/ müssen mit den
 * key_name-Werten aus der modules-Tabelle übereinstimmen.
 *
 * Beispiel:
 *   DB: modules.key_name = 'pagebuilder'
 *   Verzeichnis: CMS/plugins/pagebuilder/
 *
 * Wenn ein Kunde Modul 'pagebuilder' lizenziert hat, wird
 * CMS/plugins/pagebuilder/ in seinen Deploy eingeschlossen.
 * Nicht lizenzierte Plugin-Verzeichnisse werden übersprungen.
 */
class ModuleCombinator
{
    public function __construct(
        private CustomerModuleRepository $customerModuleRepo,
        private ModuleRepository $moduleRepo
    ) {}

    /**
     * Gibt die Deploy-Dateiliste für einen Kunden zurück.
     * Format: [absoluterPfad => relativerPfad]
     */
    public function buildFileList(int $customerId, string $cmsSourceDir, array $ignoreList = []): array
    {
        $result = [];
        $pluginsDir = rtrim($cmsSourceDir, '/') . '/plugins';
        $licensedSlugs = $this->getLicensedModuleSlugs($customerId);

        $defaultIgnore = ['.git', '.env', '.claude', 'node_modules', 'storage', 'upload.sh'];
        $ignore = array_unique(array_merge($defaultIgnore, $ignoreList));

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cmsSourceDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;

            $absPath = $file->getPathname();
            $relPath = ltrim(str_replace($cmsSourceDir, '', $absPath), '/\\');
            $parts   = explode('/', str_replace('\\', '/', $relPath));
            $filename = basename($absPath);

            if ($this->isIgnoredMacArtifact($filename, $parts)) {
                continue;
            }

            $skip = false;
            foreach ($ignore as $ign) {
                if (in_array($ign, $parts, true)) { $skip = true; break; }
            }
            if ($skip) continue;

            if (str_starts_with(str_replace('\\', '/', $absPath), str_replace('\\', '/', $pluginsDir))) {
                $moduleSlug = $parts[1] ?? null;
                if ($moduleSlug === null || !in_array($moduleSlug, $licensedSlugs, true)) {
                    continue;
                }
            }

            $result[$absPath] = $relPath;
        }

        return $result;
    }

    /**
     * Gibt die Slugs aller für diesen Kunden lizenzierten Module zurück.
     * @return string[]
     */
    public function getLicensedModuleSlugs(int $customerId): array
    {
        $assignments = $this->customerModuleRepo->listForCustomer($customerId);
        $slugs = [];
        foreach ($assignments as $assignment) {
            $moduleId = (int)($assignment['module_id'] ?? 0);
            if ($moduleId === 0) continue;

            $module = $this->moduleRepo->findById($moduleId);
            if ($module !== null) {
                $slug = (string)($module['slug'] ?? $module['key_name'] ?? '');
                if ($slug !== '') {
                    $slugs[] = $slug;
                }
            }
        }
        return $slugs;
    }

    private function isIgnoredMacArtifact(string $filename, array $parts): bool
    {
        $macFiles = ['.DS_Store', '.AppleDouble', '.LSOverride'];
        $macDirs = ['__MACOSX', '.Spotlight-V100', '.Trashes', '.fseventsd'];

        if (in_array($filename, $macFiles, true) || str_starts_with($filename, '._')) {
            return true;
        }

        return count(array_intersect($parts, $macDirs)) > 0;
    }
}
