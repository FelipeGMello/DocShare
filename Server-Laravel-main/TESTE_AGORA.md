# ✅ CORRIGIDO - Instruções de Teste

## 🔧 O Que Foi Corrigido

**Problema**: Frontend enviava `{ ops }` mas backend esperava `{ content }`

**Solução**: Alterado `public/app.js` para enviar `{ content, site }` (texto puro)

---

## 🚀 AGORA TESTE!

### Passo 1: Recarregar Navegadores
```
Em ambos os navegadores:
Ctrl+Shift+R (Windows/Linux)
ou
Cmd+Shift+R (Mac)
```

### Passo 2: Abrir DevTools
```
F12 → Aba "Console"

Procure por erros (linha vermelha)
Se aparecer erro, relate aqui!
```

### Passo 3: Testar Digitação

**Em um navegador (João)**:
1. Digita: "Olá"
2. Verifica se aparece "Salvando…"
3. Verifica se depois aparece "Todas as alterações foram salvas"
4. Se sim → ✅ FUNCIONA LOCALMENTE

**Depois testa sincronização**:
5. Vê se aparece em outro navegador (Maria) em < 1 segundo
6. Se sim → ✅ SINCRONIZA

### Passo 4: Testar Persistência
```
1. Digita algo em um navegador
2. Espera "Salvo!" aparecer
3. Aperta F5 (refresh)
4. Verifica se o texto continua lá
5. Se sim → ✅ PERSISTE
```

---

## ✨ Resultado Esperado

| Ação | Esperado | Status |
|------|----------|--------|
| Digita em A | Status "Salvando..." → "Salvo!" | ? |
| Aparece em B | < 1 segundo | ? |
| F5 em ambos | Texto continua | ? |
| Edições simultâneas | Sem perder dados | ? |

---

## 🐛 Se Ainda Não Funcionar

1. **Abre o Console (F12)**
   - Que erros aparecem?
   - Qual é o status do fetch?

2. **Verifica se está salvando**
   - Abre DevTools → Network → XHR
   - Digita algo
   - Ve se POST aparece em Network?
   - Qual é o status (200, 422, etc)?

3. **Verifica banco**
   ```bash
   php artisan tinker << 'EOF'
   $doc = \App\Models\Document::find(1);
   echo "Banco tem: " . $doc->content . "\n";
   exit;
   EOF
   ```

---

## 🔄 Se Precisar Resetar Tudo Novamente

```bash
cd /home/arthur-alexandrino/Codigos/collab-crdt-laravel

# 1. Limpar
php artisan cache:clear && php artisan view:clear

# 2. Resetar banco
rm database/database.sqlite
php artisan migrate --force

# 3. Recriar doc
php artisan tinker << 'EOF'
\App\Models\Document::firstOrCreate(['id' => 1], ['title' => 'Documento Compartilhado', 'content' => '']);
exit;
EOF

# 4. Recarregar navegadores (Ctrl+Shift+R em ambos)
```

---

**Agora testa e me relata:**
1. Digita algo?
2. Salva ("Salvo!" aparece)?
3. Sincroniza para outro navegador?
4. Persiste (F5)?

Qual é o resultado? 🎯
