# 📋 Relatório de Correção de Bugs - Sincronização Colaborativa CRDT

**Data**: 30 de maio de 2026  
**Problema**: Edições em um computador não sincronizavam com outro computador na mesma rede

---

## 🔴 Bugs Encontrados (6 Críticos)

### BUG #1: Broadcasting Desativado
**Localização**: `.env`  
**Severidade**: 🔴 CRÍTICO

```php
// ❌ ANTES (linha não sincroniza)
BROADCAST_CONNECTION=log

// ✅ DEPOIS (broadcast via WebSocket)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=collab-crdt
REVERB_APP_KEY=collab-key
REVERB_APP_SECRET=collab-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http
```

**Problema**: O evento `DocumentUpdated` estava sendo apenas logado, não propagado aos clientes WebSocket.

**Impacto**: Nenhuma sincronização em tempo real era possível.

---

### BUG #2: Frontend Enviando Dados Incompletos
**Localização**: `resources/js/app.js` (função `enviar`)  
**Severidade**: 🔴 CRÍTICO

```javascript
// ❌ ANTES
body: JSON.stringify({ content })

// ✅ DEPOIS
body: JSON.stringify({ 
    content,
    site: 'user-' + config.usernameHash
})
```

**Problema**: O servidor espera `{ content, site }` mas o frontend enviava apenas `{ content }`, impossibilitando identificar quem fez a edição.

**Código relevante no servidor**:
```php
// No Controller: DocumentController::update()
$site = $request->input('site', 'user-' . md5($username)); // Esperava 'site'
```

---

### BUG #3: Filtro de Eco Próprio Quebrado
**Localização**: `resources/js/app.js` (listener do Reverb)  
**Severidade**: 🔴 CRÍTICO

```javascript
// ❌ ANTES - Comparação sempre falha
if (site === config.username) return
// site = "user-a1b2c3d4" (hash do servidor)
// config.username = "João" (nome puro)
// Nunca são iguais! → edições próprias sempre sincronizadas de volta

// ✅ DEPOIS - Comparação correta
const currentSiteHash = 'user-' + config.usernameHash
if (site === currentSiteHash) return
```

**Problema**: O servidor envia `site` como hash (`user-{md5}`), mas o frontend comparava com o nome em texto puro. Resultado: edições próprias eram recebidas novamente, causando duplicação.

**Cenário**: Você digita "A" → servidor envia broadcast → você recebe de volta → digita "A" novamente → loop de sincronização.

---

### BUG #4: URL do Reverb Hardcoded para Localhost
**Localização**: `resources/views/show.blade.php`  
**Severidade**: 🔴 CRÍTICO

```javascript
// ❌ ANTES
reverbHost: "localhost",

// ✅ DEPOIS
reverbHost: window.location.hostname,  // Usa o mesmo host da página
```

**Problema**: Quando você acessa de outro computador (ex: `http://192.168.1.100:8000`), o navegador tenta conectar ao WebSocket em `ws://localhost:8080` (local), não no servidor.

**Erro no browser**:
```
WebSocket connection to 'ws://localhost:8080/...' failed
```

---

### BUG #5: Configuração do Reverb Incompleta
**Localização**: `resources/views/show.blade.php`  
**Severidade**: 🟡 ALTO

```javascript
// ❌ ANTES
reverbKey: "collab-key",
reverbPort: 8080,
// Valores hardcoded, não refletem .env

// ✅ DEPOIS
reverbKey: "{{ config('broadcasting.connections.reverb.key') }}",
reverbPort: {{ config('broadcasting.connections.reverb.options.port') }},
// Lê do config/broadcasting.php (que lê do .env)
```

**Problema**: Se você mudar a porta ou a key no `.env`, o frontend não usa a nova configuração.

---

### BUG #6: Falta de Hash do Username no Frontend
**Localização**: `resources/views/show.blade.php`  
**Severidade**: 🔴 CRÍTICO

```javascript
// ❌ ANTES
username: "{{ $username }}",
// Apenas o nome, sem hash

// ✅ DEPOIS
usernameHash: usernameHash, // Hash gerado em tempo de execução
// usernameHash = hashCode("João") = "1a2b3c4d"
```

**Problema**: O servidor usa `'user-' . md5($username)` mas o frontend não tinha essa transformação para comparar.

---

## 📊 Fluxo de Sincronização Corrigido

### Antes (Quebrado):
```
Computador A digita "Olá"
    ↓
enviar({ content: "Olá" })  ❌ Falta 'site'
    ↓
Controller recebe site=undefined
    ↓
broadcast(DocumentUpdated("Olá", undefined))
    ↓
Computador B recebe atualizacao
    ↓
site === config.username → undefined === "João" → FALSE
    ↓
Atualiza conteúdo
    ↓
Computador B digita "+ Mundo"
    ↓
Computador A recebe "Olá" novamente ← LOOP!
```

### Depois (Correto):
```
Computador A (João) digita "Olá"
    ↓
enviar({ 
    content: "Olá",
    site: "user-" + hash("João")  ✅ Com site
})
    ↓
Controller recebe site="user-1a2b3c4d"
    ↓
broadcast(DocumentUpdated("Olá", "user-1a2b3c4d"))
    ↓
Computador A listener verifica:
    currentSiteHash = "user-1a2b3c4d"
    site === currentSiteHash → TRUE
    return // Ignora eco próprio ✅
    ↓
Computador B listener verifica:
    currentSiteHash = "user-4d3c2b1a"
    site === currentSiteHash → FALSE
    Atualiza conteúdo ✅
```

---

## 🛠️ Como Testar as Correções

### Pré-requisitos:
- Dois computadores na mesma rede (ou via localhost se testar no mesmo PC)
- IP do servidor (ex: `192.168.1.104`)

### Passo 1: Limpar cache e config
```bash
cd /home/arthur-alexandrino/Codigos/collab-crdt-laravel

php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Passo 2: Iniciar Reverb (com IP 0.0.0.0 para aceitar conexões externas)
```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Saída esperada:
```
Starting Reverb server
Reverb server started successfully
Listening on ws://0.0.0.0:8080
```

### Passo 3: Em outro terminal, iniciar Laravel
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Saída esperada:
```
Laravel development server started: http://0.0.0.0:8000
```

### Passo 4: Abrir em dois computadores
- **Computador 1**: Navegador → `http://192.168.1.104:8000` (seu IP)
- **Computador 2**: Navegador → `http://192.168.1.104:8000`

### Passo 5: Definir nomes diferentes
- **Computador 1**: Digite "João"
- **Computador 2**: Digite "Maria"

### Passo 6: Testar sincronização
1. Em Computador 1, escreva algo (ex: "Olá")
2. Verifique se aparece em Computador 2 em tempo real (< 500ms)
3. Em Computador 2, edite ou adicione (ex: "Olá, Mundo!")
4. Verifique se volta para Computador 1

### Verificação no Browser
Abra as DevTools (F12) e confira:
```javascript
// No console:
window.DocShare

// Esperado:
{
  username: "João",
  usernameHash: "..." // deve ter valor
  reverbHost: "192.168.1.104" // seu IP, não "localhost"
  reverbPort: 8080
  reverbKey: "collab-key"
}
```

---

## 🔍 Verificações de Rede

### Portas esperadas:
- **8000**: Laravel (HTTP)
- **8080**: Reverb (WebSocket)

### Testar conectividade:
```bash
# Do Computador 2, testar acesso ao servidor
curl http://192.168.1.104:8000          # Laravel
curl http://192.168.1.104:8080          # Reverb (pode retornar erro, é ok)
```

### Monitorar logs:
```bash
# Terminal do Laravel
tail -f storage/logs/laravel.log

# Deve aparecer eventos de broadcast:
# [2026-05-30 14:23:45] local.INFO: Broadcasting [Illuminate\Broadcasting\Broadcasters\ReverbBroadcaster]
```

---

## 📝 Arquivos Modificados

1. **`.env`**
   - Adicionado: `BROADCAST_CONNECTION=reverb` e configurações do Reverb

2. **`resources/js/app.js`**
   - Linha ~40: Adicionado `site: 'user-' + config.usernameHash` no fetch
   - Linha ~20: Corrigido filtro de eco próprio

3. **`resources/views/show.blade.php`**
   - Linhas ~6-18: Adicionado gerador de `usernameHash` e configuração dinâmica

4. **`app/Http/Controllers/DocumentController.php`**
   - Linha ~64: Agora lê `site` do request

5. **`routes/web.php`**
   - Linha ~22: Corrigido broadcast para usar `site` correto

---

## 🎯 Resultado Esperado

Após essas correções, a aplicação deve:
- ✅ Sincronizar edições em tempo real entre computadores
- ✅ Não duplicar edições próprias
- ✅ Funcionar em rede local (não apenas localhost)
- ✅ Preservar o histórico CRDT (sem conflitos de merge)

---

## ⚠️ Notas Importantes

1. **Firewall**: Se a sincronização não funcionar, verifique se as portas 8000 e 8080 estão abertas no firewall.

2. **IP dinâmico**: Se o IP do servidor mudar, execute os passos de novo.

3. **Redis** (opcional): Para produção, configure Redis no `.env` para melhor broadcast:
   ```
   CACHE_STORE=redis
   QUEUE_CONNECTION=redis
   ```

4. **Sessões**: As sessões agora usam banco de dados (`SESSION_DRIVER=database`). Migre se necessário:
   ```bash
   php artisan session:table
   php artisan migrate
   ```

---

**Status**: ✅ Todos os bugs corrigidos e testáveis
