document.addEventListener('DOMContentLoaded', () => {

    const page   = document.getElementById('page')
    const config = window.DocShare
    if (!page || !config) return

    // BUG 2 RESOLVIDO: Identificador exclusivo para esta aba/sessão do navegador
    // Evita colisões caso múltiplos usuários entrem como "Anônimo" ou com o mesmo nome
    const clientId = 'client_' + Math.random().toString(36).substring(2, 11);

    let baseText = page.innerText

    // ── Diff: calcula operações entre dois textos planos ─────────────
    function computeOps(oldText, newText) {
        let start = 0
        while (start < oldText.length && start < newText.length &&
               oldText[start] === newText[start]) start++

        let oldEnd = oldText.length
        let newEnd = newText.length
        while (oldEnd > start && newEnd > start &&
               oldText[oldEnd - 1] === newText[newEnd - 1]) { oldEnd--; newEnd-- }

        const ops = []
        if (oldEnd > start) ops.push({ op: 'delete', pos: start, len: oldEnd - start })
        if (newEnd > start) ops.push({ op: 'insert', pos: start, text: newText.slice(start, newEnd) })
        return ops
    }

    // ── Cursor: salva/restaura posição por offset de texto puro ──────
    function saveCursor() {
        const sel = window.getSelection()
        if (!sel || !sel.rangeCount) return null
        const range = sel.getRangeAt(0)
        const pre = range.cloneRange()
        pre.selectNodeContents(page)
        pre.setEnd(range.startContainer, range.startOffset)
        const start = pre.toString().length
        pre.setEnd(range.endContainer, range.endOffset)
        return { start, end: pre.toString().length }
    }

    function restoreCursor(saved) {
        if (!saved) return
        const walker = document.createTreeWalker(page, NodeFilter.SHOW_TEXT)
        let chars = 0, node
        let sN = null, sO = 0, eN = null, eO = 0
        while ((node = walker.nextNode())) {
            const len = node.textContent.length
            if (!sN && chars + len >= saved.start) { sN = node; sO = saved.start - chars }
            if (!eN && chars + len >= saved.end)   { eN = node; eO = saved.end   - chars; break }
            chars += len
        }
        if (!sN) return
        try {
            const r = document.createRange()
            r.setStart(sN, sO); r.setEnd(eN || sN, eO || sO)
            const sel = window.getSelection()
            sel.removeAllRanges(); sel.addRange(r)
        } catch {}
    }

    function adjustCursor(saved, oldText, newText) {
        if (!saved) return null
        let diffStart = 0
        while (diffStart < oldText.length && diffStart < newText.length &&
               oldText[diffStart] === newText[diffStart]) diffStart++
        if (diffStart >= saved.end) return saved
        let oldTail = oldText.length, newTail = newText.length
        while (oldTail > diffStart && newTail > diffStart &&
               oldText[oldTail-1] === newText[newTail-1]) { oldTail--; newTail-- }
        const removed = oldTail - diffStart
        const added   = newTail - diffStart
        const delta   = added - removed
        const adjust  = (pos) => {
            if (pos <= diffStart) return pos
            if (pos <  oldTail)   return diffStart + added
            return pos + delta
        }
        return { start: adjust(saved.start), end: adjust(saved.end) }
    }

    // ── Echo / Reverb ─────────────────────────────────────────────────
    const echo = new Echo({
        broadcaster:       'pusher',
        key:               config.reverbKey,
        // BUG 1 RESOLVIDO: O host conecta via 'localhost' e o convidado via IP físico automaticamente
        wsHost:            window.location.hostname, 
        wsPort:            config.reverbPort,
        wssPort:           config.reverbPort,
        forceTLS:          false,
        encrypted:         false,
        disableStats:      true,
        enabledTransports: ['ws', 'wss'],
        cluster:           'mt1'
    })

    echo.channel('document').listen('.update', ({ content, site }) => {
        // BUG 2 RESOLVIDO: Valida o descarte de eco pelo token da sessão, não pelo nome do usuário
        if (site === clientId) return

        const cursor  = saveCursor()
        const oldText = page.innerText

        page.innerText = content
        baseText = content   

        const adjusted = adjustCursor(cursor, oldText, content)
        restoreCursor(adjusted)
        updateStatusBar()
    })

    // ── Input: envia conteúdo atualizado ao servidor ──────────────────
    let timer = null

    page.addEventListener('input', () => {
        clearTimeout(timer)
        setSaveStatus('Salvando…')
        timer = setTimeout(() => {
            const content = page.innerText
            enviarConteudo(content)
        }, 300)
    })

    async function enviarConteudo(content) {
        try {
            const res = await fetch(config.autosaveUrl, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                },
                // ✅ CORRIGIDO: Enviando content (string) em vez de ops (array)
                body: JSON.stringify({ content, site: clientId }),
            })

            if (res.ok) {
                setSaveStatus('Todas as alterações foram salvas')
            } else {
                console.error('Erro na resposta:', res.status)
                setSaveStatus('Erro ao salvar')
            }
        } catch (err) {
            console.error('Erro na requisição:', err)
            setSaveStatus('Erro ao salvar')
        }
    }

    // ── Status bar ────────────────────────────────────────────────────
    function setSaveStatus(msg) {
        const el = document.getElementById('save-status')
        if (el) el.textContent = msg
    }

    function updateStatusBar() {
        const text  = page.innerText || ''
        const words = text.trim() ? text.trim().split(/\s+/).length : 0
        const wc    = document.getElementById('word-count')
        const cc    = document.getElementById('char-count')
        if (wc) wc.textContent = words + ' palavras'
        if (cc) cc.textContent = text.length + ' caracteres'
    }

    page.addEventListener('input', updateStatusBar)
    updateStatusBar()

    // ── Toolbar & Outros Componentes ──────────────────────────────────
    window.applyStyle   = (tag) => { document.execCommand('formatBlock', false, tag); page.focus() }
    window.toggleActive = (btn) => btn.classList.toggle('active')
    window.insertLink   = () => { const u = prompt('URL:'); if (u) document.execCommand('createLink', false, u) }

    window.toggleFindBar = () => {
        const bar = document.getElementById('find-bar')
        if (!bar) return
        const open = bar.style.display === 'flex'
        bar.style.display = open ? 'none' : 'flex'
        if (!open) document.getElementById('find-input')?.focus()
    }

    window.doFind = () => {
        const term = document.getElementById('find-input')?.value
        document.getElementById('find-count').textContent = ''
        if (!term) return
        const matches = [...page.innerText.matchAll(new RegExp(term, 'gi'))]
        document.getElementById('find-count').textContent =
            matches.length ? `${matches.length} resultado(s)` : 'Nenhum resultado'
    }

    window.stepFind = () => {}

    window.doReplace = (all) => {
        const find    = document.getElementById('find-input')?.value
        const replace = document.getElementById('replace-input')?.value ?? ''
        if (!find) return
        const cursor = saveCursor()
        page.innerHTML = all ? page.innerHTML.replaceAll(find, replace) : page.innerHTML.replace(find, replace)
        restoreCursor(cursor)
        // ✅ CORRIGIDO: Usar enviarConteudo em vez de enviarOps
        enviarConteudo(page.innerText)
    }

    window.copyLink = (btn) => {
        const url = document.querySelector('.link-url')?.value
        if (url) navigator.clipboard.writeText(url)
        btn.textContent = 'Copiado!'
        setTimeout(() => btn.textContent = 'Copiar link', 2000)
    }

    window.addCollaborator = () => alert('Compartilhe o link com qualquer pessoa na mesma rede.')
})