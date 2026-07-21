(function () {
    'use strict';

    var textEncoder = new TextEncoder();

    function padOctal(value, width) {
        var octal = value.toString(8);
        if (octal.length > width - 1) {
            octal = octal.slice(-(width - 1));
        }
        return octal.padStart(width - 1, '0') + '\0';
    }

    function writeString(view, offset, length, value) {
        var bytes = textEncoder.encode(value);
        for (var i = 0; i < length; i++) {
            view[offset + i] = i < bytes.length ? bytes[i] : 0;
        }
    }

    function splitTarPath(path) {
        if (path.length <= 100) {
            return { name: path, prefix: '' };
        }

        var idx = path.lastIndexOf('/');
        while (idx > 0) {
            var prefix = path.slice(0, idx);
            var name = path.slice(idx + 1);
            if (prefix.length <= 155 && name.length <= 100) {
                return { name: name, prefix: prefix };
            }
            idx = path.lastIndexOf('/', idx - 1);
        }

        throw new Error('Pfad zu lang fuer TAR: ' + path);
    }

    function buildTarHeader(path, size, mtimeMs) {
        var split = splitTarPath(path);
        var header = new Uint8Array(512);

        writeString(header, 0, 100, split.name);
        writeString(header, 100, 8, padOctal(420, 8));
        writeString(header, 108, 8, padOctal(0, 8));
        writeString(header, 116, 8, padOctal(0, 8));
        writeString(header, 124, 12, padOctal(size, 12));
        writeString(header, 136, 12, padOctal(Math.floor(mtimeMs / 1000), 12));
        writeString(header, 148, 8, '        ');
        writeString(header, 156, 1, '0');
        writeString(header, 257, 6, 'ustar');
        writeString(header, 263, 2, '00');
        writeString(header, 345, 155, split.prefix);

        var checksum = 0;
        for (var i = 0; i < header.length; i++) {
            checksum += header[i];
        }

        writeString(header, 148, 8, padOctal(checksum, 8).replace('\0', ' '));
        return header;
    }

    function shouldIgnore(path) {
        var normalized = path.replace(/\\/g, '/');
        var parts = normalized.split('/');
        var filename = parts[parts.length - 1] || '';
        var macFiles = ['.DS_Store', '.AppleDouble', '.LSOverride'];
        var macDirs = ['__MACOSX', '.Spotlight-V100', '.Trashes', '.fseventsd'];
        return macFiles.indexOf(filename) !== -1
            || filename.indexOf('._') === 0
            || macDirs.some(function (dir) { return parts.indexOf(dir) !== -1; });
    }

    function stripLeadingDirectory(path) {
        var normalized = path.replace(/\\/g, '/').replace(/^\/+/, '');
        var parts = normalized.split('/').filter(Boolean);
        if (parts.length <= 1) {
            return parts[0] || '';
        }
        parts.shift();
        return parts.join('/');
    }

    async function buildTarGzFromFiles(files) {
        if (typeof CompressionStream === 'undefined') {
            throw new Error('Dein Browser unterstuetzt keine clientseitige Komprimierung (CompressionStream).');
        }

        var chunks = [];

        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            var relativePath = stripLeadingDirectory(file.webkitRelativePath || file.name || '');
            if (!relativePath || shouldIgnore(relativePath)) {
                continue;
            }

            var data = new Uint8Array(await file.arrayBuffer());
            chunks.push(buildTarHeader(relativePath, data.length, file.lastModified || Date.now()));
            chunks.push(data);

            var padding = (512 - (data.length % 512)) % 512;
            if (padding > 0) {
                chunks.push(new Uint8Array(padding));
            }
        }

        chunks.push(new Uint8Array(1024));

        var tarBlob = new Blob(chunks, { type: 'application/x-tar' });
        var compressedStream = tarBlob.stream().pipeThrough(new CompressionStream('gzip'));
        return await new Response(compressedStream).blob();
    }

    function setBusy(form, busy, label) {
        var button = form.querySelector('button[type="submit"]');
        if (!button) {
            return;
        }
        if (!button.dataset.originalLabel) {
            button.dataset.originalLabel = button.textContent;
        }
        button.disabled = busy;
        button.textContent = busy ? label : button.dataset.originalLabel;
    }

    function setAgentStatus(message, isError) {
        var el = document.getElementById('localAgentStatus');
        if (!el) {
            return;
        }
        el.textContent = 'Status: ' + message;
        el.style.color = isError ? '#ffb3b3' : '#9fe9be';
    }

    function getValidationErrors(form) {
        var raw = form.getAttribute('data-validation-errors') || '[]';
        try {
            var errors = JSON.parse(raw);
            return Array.isArray(errors) ? errors.filter(Boolean) : [];
        } catch (error) {
            console.error(error);
            return [];
        }
    }

    async function handleArchiveForm(event) {
        event.preventDefault();

        var form = event.currentTarget;
        var input = form.querySelector('input[data-frontend-picker]');
        if (!input || !input.files || input.files.length === 0) {
            window.alert('Bitte einen Frontend-Ordner auswaehlen.');
            return;
        }

        try {
            setBusy(form, true, 'Frontend wird komprimiert...');
            var archive = await buildTarGzFromFiles(input.files);
            var formData = new FormData(form);
            formData.delete('frontend_files[]');
            formData.append('frontend_archive', archive, 'frontend.tar.gz');

            setBusy(form, true, 'Frontend wird hochgeladen...');
            var response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            window.location.href = response.url;
        } catch (error) {
            console.error(error);
            window.alert(error instanceof Error ? error.message : 'Frontend konnte nicht komprimiert werden.');
            setBusy(form, false, '');
        }
    }

    async function requestAgentPayload(form) {
        var response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin'
        });
        var data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.error || 'Deploy-Payload konnte nicht geladen werden.');
        }
        return data.payload;
    }

    async function pingLocalAgent() {
        var response = await fetch('https://127.0.0.1:8765/health', {
            method: 'GET',
            mode: 'cors'
        });
        var data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.error || 'Lokaler Agent antwortet nicht.');
        }
        setAgentStatus('lokaler Agent erreichbar (' + (data.mode || 'unknown') + ', PHP ' + (data.php || '?') + ')', false);
        return data;
    }

    async function handleLocalAgentForm(event) {
        event.preventDefault();

        var form = event.currentTarget;
        var typeInput = form.querySelector('input[name="type"]');
        var type = typeInput ? typeInput.value : 'cms';
        var needsFrontend = type === 'frontend' || type === 'combined';
        var archive = null;
        var validationErrors = getValidationErrors(form);

        try {
            if (validationErrors.length > 0) {
                throw new Error('Deploy nicht bereit: ' + validationErrors.join(', ') + '. Bitte zuerst den Serverzugang vervollständigen.');
            }

            setBusy(form, true, 'Lokalen Agenten prüfen...');
            await pingLocalAgent();

            if (needsFrontend) {
                var input = form.querySelector('input[data-frontend-picker]');
                if (!input || !input.files || input.files.length === 0) {
                    throw new Error('Bitte einen Frontend-Ordner auswaehlen.');
                }

                setBusy(form, true, 'Frontend wird komprimiert...');
                archive = await buildTarGzFromFiles(input.files);
            }

            setBusy(form, true, 'Deploy-Payload wird geladen...');
            var payload = await requestAgentPayload(form);

            var agentFormData = new FormData();
            agentFormData.append('payload', JSON.stringify(payload));
            if (archive) {
                agentFormData.append('frontend_archive', archive, 'frontend.tar.gz');
            }

            setBusy(form, true, 'Lokaler Agent deployt...');
            var response = await fetch('https://127.0.0.1:8765/deploy', {
                method: 'POST',
                body: agentFormData
            });
            var result = await response.json();
            if (!response.ok || !result.ok) {
                throw new Error(result.error || 'Lokaler Agent-Deploy fehlgeschlagen.');
            }

            setAgentStatus(result.message || 'Deploy erfolgreich.', false);
            window.alert(result.message || 'Deploy erfolgreich.');
        } catch (error) {
            console.error(error);
            var message = error instanceof Error ? error.message : 'Lokaler Agent nicht erreichbar.';
            if (message === 'Failed to fetch') {
                message = 'Lokaler Agent blockiert oder nicht erreichbar. Prüfe Browser-Konsole, Private-Network-Access und ob der Agent noch läuft.';
            }
            setAgentStatus(message, true);
            window.alert(message);
        } finally {
            setBusy(form, false, '');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        pingLocalAgent().catch(function (error) {
            console.error(error);
            var message = error instanceof Error ? error.message : 'lokaler Agent nicht erreichbar';
            if (message === 'Failed to fetch') {
                message = 'lokaler Agent nicht erreichbar oder Zertifikat im Browser nicht vertraut';
            }
            setAgentStatus(message, true);
        });

        document.querySelectorAll('form[data-frontend-archive-form="1"]').forEach(function (form) {
            form.addEventListener('submit', handleArchiveForm);
        });

        document.querySelectorAll('form[data-local-agent-form="1"]').forEach(function (form) {
            form.addEventListener('submit', handleLocalAgentForm);
        });
    });
})();
