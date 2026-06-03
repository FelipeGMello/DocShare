// Aguarda o DOM carregar
document.addEventListener('DOMContentLoaded', () => {

    const page   = document.getElementById('page')
    const config = window.DocShare

    if (!page || !config) return

    // ── Reverb / Echo ──────────────────────────────────────────────────
    // Carregado via CDN no layout
    const echo = new Echo({
        broadcaster:       'reverb',
        key:               config.reverbKey,
        wsHost:            config.reverbHost,
        wsPort:            config.reverbPort,
        forceTLS:          false,
        enabledTransports: ['ws'],
    })

    // Recebe atualizações dos outros usuários
    echo.channel('document').listen('.update', ({ content, site }) => {
        // Compara o site enviado com o hash do usuário atual
        const currentSiteHash = 'user-' + config.usernameHash
        if (site === currentSiteHash) return // ignora eco próprio
        // Salva posição do cursor
        const sel   = window.getSelection()
        const range = sel.rangeCount ? sel.getRangeAt(0) : null
        page.innerHTML = content
        // Tenta restaurar cursor (melhor esforço)
        if (range) {
            try { sel.removeAllRanges(); sel.addRange(range) } catch {}
        }
        updateStatusBar()
    })

    // ── Envia alterações ao servidor ───────────────────────────────────
    let timer = null

    page.addEventListener('input', () => {
        clearTimeout(timer)
        setSaveStatus('Salvando…')
        timer = setTimeout(() => enviar(page.innerHTML), 500)
    })

    async function enviar(content) {
        try {
            await fetch(config.autosaveUrl, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                body: JSON.stringify({ 
                    content,
                    site: 'user-' + config.usernameHash
                }),
            })
            setSaveStatus('Todas as alterações foram salvas')
        } catch {
            setSaveStatus('Erro ao salvar')
        }
    }

    // ── Status bar ─────────────────────────────────────────────────────
    function setSaveStatus(msg) {
        const el = document.getElementById('save-status')
        if (el) el.textContent = msg
    }

    function updateStatusBar() {
        const text  = page.innerText || ''
        const words = text.trim() ? text.trim().split(/\s+/).length : 0
        const chars = text.length
        const wc    = document.getElementById('word-count')
        const cc    = document.getElementById('char-count')
        if (wc) wc.textContent = words + ' palavras'
        if (cc) cc.textContent = chars + ' caracteres'
    }

    page.addEventListener('input', updateStatusBar)
    updateStatusBar()

    // ── Funções da toolbar (usadas pelo show.blade.php) ────────────────
    window.applyStyle = (tag) => {
        document.execCommand('formatBlock', false, tag)
        page.focus()
    }

    window.toggleActive = (btn) => {
        btn.classList.toggle('active')
    }

    window.insertLink = () => {
        const url = prompt('URL:')
        if (url) document.execCommand('createLink', false, url)
    }

    // Find & Replace
    let findMatches = [], findIndex = 0

    window.toggleFindBar = () => {
        const bar = document.getElementById('find-bar')
        if (bar) {
            const open = bar.style.display === 'flex'
            bar.style.display = open ? 'none' : 'flex'
            if (!open) document.getElementById('find-input')?.focus()
        }
    }

    window.doFind = () => {
        const term = document.getElementById('find-input')?.value
        document.getElementById('find-count').textContent = ''
        if (!term) return
        const text = page.innerText
        const matches = [...text.matchAll(new RegExp(term, 'gi'))]
        document.getElementById('find-count').textContent =
            matches.length ? `${matches.length} resultado(s)` : 'Nenhum resultado'
        findMatches = matches
        findIndex   = 0
    }

    window.stepFind = (dir) => {
        if (!findMatches.length) return
        findIndex = (findIndex + dir + findMatches.length) % findMatches.length
    }

    window.doReplace = (all) => {
        const find    = document.getElementById('find-input')?.value
        const replace = document.getElementById('replace-input')?.value ?? ''
        if (!find) return
        if (all) {
            page.innerHTML = page.innerHTML.replaceAll(find, replace)
        } else {
            const html = page.innerHTML
            page.innerHTML = html.replace(find, replace)
        }
        enviar(page.innerHTML)
    }

    // Share modal — copiar link
    window.copyLink = (btn) => {
        const url = document.querySelector('.link-url')?.value
        if (url) navigator.clipboard.writeText(url)
        btn.textContent = 'Copiado!'
        setTimeout(() => btn.textContent = 'Copiar link', 2000)
    }

    window.addCollaborator = () => {
        alert('Compartilhe o link com qualquer pessoa na mesma rede.')
    }
})