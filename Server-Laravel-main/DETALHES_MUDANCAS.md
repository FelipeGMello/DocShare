# 📝 Detalhes das Mudanças - Arquivo por Arquivo

## 1️⃣ `.env` - Ativação do Broadcasting

### Local: `/collab-crdt-laravel/.env`

```diff
  SESSION_DOMAIN=null

- BROADCAST_CONNECTION=log
+ BROADCAST_CONNECTION=reverb
+ REVERB_APP_ID=collab-crdt
+ REVERB_APP_KEY=collab-key
+ REVERB_APP_SECRET=collab-secret
+ REVERB_HOST=0.0.0.0
+ REVERB_PORT=8080
+ REVERB_SCHEME=http

  FILESYSTEM_DISK=local
```

**Impacto**: 
- Broadcasting agora usa WebSocket (Reverb) em vez de apenas logar eventos
- Eventos são propagados em tempo real para todos os clientes conectados
- Configuração do Reverb lê do `.env`, facilitando mudanças

---

## 2️⃣ `resources/js/app.js` - Envio de Dados e Filtro de Eco

### Local: `/collab-crdt-laravel/resources/js/app.js`

#### Mudança A: Listener do Reverb (Filtro de Eco Próprio)

```diff
  // Recebe atualizações dos outros usuários
  echo.channel('document').listen('.update', ({ content, site }) => {
-     if (site === config.username) return // ignora eco próprio
+     // Compara o site enviado com o hash do usuário atual
+     const currentSiteHash = 'user-' + config.usernameHash
+     if (site === currentSiteHash) return // ignora eco próprio
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
```

**Impacto**:
- Comparação agora funciona (hash === hash)
- Eco próprio é corretamente filtrado
- Elimina duplicação de edições

#### Mudança B: Função de Envio (Adição do Site)

```diff
  async function enviar(content) {
      try {
          await fetch(config.autosaveUrl, {
              method:  'POST',
              headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-TOKEN': config.csrfToken,
              },
-             body: JSON.stringify({ content }),
+             body: JSON.stringify({ 
+                 content,
+                 site: 'user-' + config.usernameHash
+             }),
          })
          setSaveStatus('Todas as alterações foram salvas')
      } catch {
          setSaveStatus('Erro ao salvar')
      }
  }
```

**Impacto**:
- Backend recebe identificação do editor (`site`)
- Possibilita rastreamento de quem editou
- Necessário para filtro de eco próprio

---

## 3️⃣ `resources/views/show.blade.php` - Configuração Dinâmica do Reverb

### Local: `/collab-crdt-laravel/resources/views/show.blade.php`

```diff
  @push('scripts')
  <script>
+     // Gera hash do username para sincronização
+     const usernameRaw = "{{ $username }}";
+     const usernameHash = Array.from(usernameRaw).reduce((h, c) => 
+         ((h << 5) - h) + c.charCodeAt(0) | 0, 0).toString(16);
+     
+     // Extrai IP/host do servidor da URL atual
+     const reverbHost = window.location.hostname === 'localhost' 
+         ? window.location.hostname 
+         : window.location.hostname; // usa o mesmo host da página
+     
      window.DocShare = {
          autosaveUrl: "{{ route('document.autosave') }}",
          saveUrl:     "{{ route('document.save') }}",
-         opsUrl:      "{{ route('document.ops', ['id' => $document['id'] ?? 1]) }}", // ← ADICIONAR
+         opsUrl:      "{{ route('document.ops', ['id' => $document['id'] ?? 1]) }}",
          csrfToken:   "{{ csrf_token() }}",
          documentId:  "{{ $document['id'] ?? 1 }}",
          username:    "{{ $username }}",
+         usernameHash: usernameHash,
-         reverbKey:   "collab-key",
-         reverbHost:  "localhost",
-         reverbPort:  8080,
+         reverbKey:   "{{ config('broadcasting.connections.reverb.key') }}",
+         reverbHost:  reverbHost,
+         reverbPort:  {{ config('broadcasting.connections.reverb.options.port') }},
      };
  </script>
  @endpush
```

**Impacto**:
- `usernameHash`: Gerado dinamicamente, usado para comparações
- `reverbHost`: Usa IP/hostname do servidor em vez de "localhost"
- Configurações lêem do `config/broadcasting.php` em vez de hardcoded
- Funciona em rede local (outro computador)

---

## 4️⃣ `app/Http/Controllers/DocumentController.php` - Aceitar Site

### Local: `/collab-crdt-laravel/app/Http/Controllers/DocumentController.php`

```diff
  // Recebe keystroke do browser → Rust → broadcast
  public function update(Request $request)
  {
      $request->validate(['content' => 'required|string']);

      $username = $request->cookie('username', 'Anônimo');
-     $site     = 'user-' . md5($username);
+     $site     = $request->input('site', 'user-' . md5($username));

      $this->crdt->applyText($request->content, $site);

      $content = $this->crdt->getContent();

      Document::where('id', 1)->update(['content' => $content]);

-     broadcast(new DocumentUpdated($content, $username));
+     broadcast(new DocumentUpdated($content, $site));

      return response()->noContent();
  }
```

**Impacto**:
- Agora aceita `site` do frontend
- Usa o valor enviado se disponível, senão calcula o padrão
- Broadcast usa `site` (hash) em vez de username (texto puro)
- Mantém compatibilidade com clientes antigos

---

## 5️⃣ `routes/web.php` - Rota de Ops com Broadcast Correto

### Local: `/collab-crdt-laravel/routes/web.php`

```diff
  // ─── Nova Rota Proxy CRDT (Operações) ─────────────────────────────────
  // Captura os deltas/ops enviados pelo front, encaminha ao Rust, 
  // persiste o estado consolidado no SQLite e dispara o broadcast via Reverb.
  Route::post('/document/{id}/ops', function (Request $request, $id) {
      // Repassa as operações originais para o Rust aplicar o CRDT
      $response = Http::timeout(3)->post(
          env('CRDT_SERVICE_URL', 'http://127.0.0.1:9000') . "/document/{$id}/ops",
          $request->all()
      );

      if ($response->successful()) {
          $content = $response->json('content', '');
+         $site    = $request->input('site', 'Anônimo');

          // Persiste no SQLite
          Document::where('id', $id)->update(['content' => $content]);

-         // BUG 2 RESOLVIDO NO BACKEND: Encaminha o 'site' enviado (clientId) 
-         // para que o Reverb informe aos navegadores quem gerou o input
-         broadcast(new DocumentUpdated($content, $request->input('site', 'Anônimo')));
+         // Broadcast com o site correto para evitar eco próprio
+         broadcast(new DocumentUpdated($content, $site));
      }

      return response()->noContent();
  })->middleware('web')->name('document.ops');
```

**Impacto**:
- Rota agora faz broadcast com `site` correto
- Consistência com função `update()` do controller
- Possibilita futuro uso da rota de ops granulares

---

## 📊 Diagrama de Fluxo - Antes vs Depois

### ANTES (Quebrado)

```
┌─────────────────────────────────────────────────────────────┐
│ Frontend JavaScript                                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  page.addEventListener('input', () => {                    │
│      fetch({content: "Olá"})  ← Falta 'site'!             │
│  })                                                          │
│                                                              │
│  echo.listen('.update', ({content, site}) => {            │
│      if (site === "João")  ← Compara com username         │
│      // site = "user-a1b2c3" !== "João" → NUNCA TRUE      │
│  })                                                          │
│                                                              │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ Backend PHP (Controller)                                    │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  $site = 'user-' . md5($username);  ← Calcula, ignora      │
│  broadcast(DocumentUpdated($content, $username))           │
│  // broadcast("Olá", "João")  ← Envia username            │
│                                                              │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ Reverb WebSocket                                            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  emit('update', {content: "Olá", site: "João"})          │
│  // Problema: 'site' é username, não hash                  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ Receptor (outro navegador)                                  │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  if (site === config.username)  ← Compara "João" com       │
│  // "João" === "Maria" → FALSE                             │
│  // Atualiza conteúdo (mesmo que seja eco próprio!)       │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### DEPOIS (Correto)

```
┌─────────────────────────────────────────────────────────────┐
│ Frontend JavaScript                                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  usernameHash = hash("João") = "1a2b3c4d"                │
│                                                              │
│  page.addEventListener('input', () => {                    │
│      fetch({content: "Olá", site: "user-1a2b3c4d"})  ✅   │
│  })                                                          │
│                                                              │
│  echo.listen('.update', ({content, site}) => {            │
│      const currentSiteHash = 'user-' + config.usernameHash │
│      if (site === currentSiteHash)  ← Compara hash        │
│      // site = "user-1a2b3c4d" === "user-1a2b3c4d" → TRUE │
│  })                                                          │
│                                                              │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ Backend PHP (Controller)                                    │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  $site = $request->input('site', 'user-...');  ← Usa valor │
│  broadcast(DocumentUpdated($content, $site))  ✅           │
│  // broadcast("Olá", "user-1a2b3c4d")  ← Envia site       │
│                                                              │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ Reverb WebSocket (BROADCAST_CONNECTION=reverb)             │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  emit('update', {                                           │
│      content: "Olá",                                       │
│      site: "user-1a2b3c4d"  ← Hash correto              │
│  })  ✅                                                     │
│                                                              │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ Receptor (Computador B - "Maria")                           │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  currentSiteHash = "user-4d3c2b1a"  (hash de "Maria")     │
│  if (site === currentSiteHash)                             │
│  // "user-1a2b3c4d" === "user-4d3c2b1a" → FALSE  ✅      │
│  // Atualiza conteúdo! Sincronização OK!                  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## ✨ Resumo das Mudanças

| Arquivo | Tipo | O que mudou | Por quê |
|---------|------|------------|---------|
| `.env` | Config | Ativado Reverb | Broadcasting estava desativado |
| `app.js` | JS | Envia `site` + Filtra eco | Identificação do editor |
| `show.blade.php` | View | Config dinâmica + Hash | Funciona em rede local |
| `DocumentController` | PHP | Aceita `site` do request | Respeita valor do cliente |
| `web.php` | Routes | Broadcast com `site` | Consistência |

---

## 🔍 Como Testar Cada Correção

### Teste 1: Verificar Broadcasting Ativado
```bash
# No terminal do Laravel
php artisan tinker
>>> config('broadcasting.default')
# Saída esperada: "reverb"
```

### Teste 2: Verificar Hash no Frontend
```javascript
// No console do browser
console.log(window.DocShare.usernameHash)
// Saída esperada: hash string (ex: "1a2b3c4d")
```

### Teste 3: Verificar URL do Reverb
```javascript
// No console do browser
console.log(window.DocShare.reverbHost)
// Saída esperada: seu IP (ex: "192.168.1.104")
```

### Teste 4: Monitorar Broadcast
```bash
# No Laravel, listar eventos transmitidos
tail -f storage/logs/laravel.log | grep -i broadcast
```

### Teste 5: Sincronização End-to-End
1. Abrir dois navegadores
2. Digitar em um
3. Ver aparecer no outro (<500ms)
4. Não deve duplicar

---

**Documento de referência para todas as mudanças realizadas**
