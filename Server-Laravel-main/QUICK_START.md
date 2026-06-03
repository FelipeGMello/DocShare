# ⚡ QUICK REFERENCE - Checklist de Teste Rápido

## 🚀 Inicializar Servidores (Copiar e Colar)

### Terminal 1: Reverb
```bash
cd /home/arthur-alexandrino/Codigos/collab-crdt-laravel
php artisan config:clear
php artisan cache:clear
php artisan reverb:start --host=0.0.0.0 --port=8080
```

### Terminal 2: Laravel
```bash
cd /home/arthur-alexandrino/Codigos/collab-crdt-laravel
php artisan serve --host=0.0.0.0 --port=8000
```

---

## 🌐 Acessar Aplicação

```
Computador A: http://<SEU-IP>:8000
Computador B: http://<SEU-IP>:8000
```

**Como descobrir seu IP:**
```bash
hostname -I
```

---

## ✅ Checklist de Teste

### Iniciação
- [ ] Reverb iniciou sem erros
- [ ] Laravel iniciou sem erros
- [ ] Página carrega em ambos os computadores
- [ ] Pede nome do usuário

### Sincronização Básica
- [ ] Digita em PC-A → Aparece em PC-B
- [ ] Digita em PC-B → Aparece em PC-A
- [ ] Tempo de sincronização < 500ms
- [ ] Sem duplicação de texto

### Edições Avançadas
- [ ] Ctrl+Z (desfazer) funciona em ambos
- [ ] Ctrl+Y (refazer) funciona em ambos
- [ ] Formatação (negrito, etc) sincroniza
- [ ] Edições simultâneas funcionam

### Validação
- [ ] DevTools → Network → WS mostra conexão WebSocket
- [ ] Status "Salvo!" aparece após digitar
- [ ] Não há erros no console (F12)
- [ ] Ambos os textos sempre iguais

---

## 🐛 Se Algo Não Funcionar

### "WebSocket connection failed"
```bash
# Verificar se Reverb está rodando
lsof -i :8080

# Se não, iniciar Reverb novamente
# Ver "Terminal 1: Reverb" acima
```

### "Edições não sincronizam"
```bash
# 1. Verificar .env
grep BROADCAST_CONNECTION .env
# Saída esperada: BROADCAST_CONNECTION=reverb

# 2. Limpar cache
php artisan cache:clear

# 3. Recarregar páginas (Ctrl+Shift+R)
```

### "Texto duplicado ou estranho"
```bash
# Hard refresh em ambos navegadores
# Chrome/Firefox: Ctrl+Shift+R
# Safari: Cmd+Shift+R

# Ou limpar dados do site:
# DevTools → Application → Storage → Clear Site Data
```

### "CSRF token mismatch"
```bash
# Fechar e reabrir navegador
# Limpar cookies
```

---

## 🔍 Verificação Rápida (Console F12)

```javascript
// Verificar que tudo está configurado
console.log(window.DocShare)

// Esperado:
{
  username: "João",
  usernameHash: "..." // deve ter valor
  reverbHost: "192.168.1.100" // seu IP, não "localhost"
  reverbPort: 8080
}

// Se algo estiver null/undefined, verifique .env
```

---

## 📊 O Que Foi Corrigido

| Bug | Status |
|-----|--------|
| Broadcasting desativado | ✅ Ativado |
| Sem site no dados | ✅ Enviando |
| Filtro de eco quebrado | ✅ Funciona |
| URL hardcoded | ✅ Dinâmica |
| Config incompleta | ✅ Completa |
| Sem hash do user | ✅ Gerando |

---

## 📚 Documentação Técnica

Leia nessa ordem:

1. `00_LEIA_PRIMEIRO.md` ← Comece aqui (5 min)
2. `GUIA_TECNICO_FINAL.md` ← Testes em detalhe (15 min)
3. `BUGFIX_REPORT.md` ← Análise profunda (20 min)
4. `DETALHES_MUDANCAS.md` ← Código alterado (10 min)

---

## 🎯 Sucesso?

Se você viu:
- ✅ Texto aparecer em outro computador em tempo real
- ✅ Sem duplicação
- ✅ Sem lag perceptível
- ✅ Edições simultâneas funcionando

**PARABÉNS! 🎉 APLICAÇÃO FUNCIONANDO PERFEITAMENTE!**

---

## 📞 Suporte

Se houver problema:

1. Verificar logs: `tail -f storage/logs/laravel.log`
2. Consultar `GUIA_TECNICO_FINAL.md` (seção Troubleshooting)
3. Reread `BUGFIX_REPORT.md` para entender raiz do problema

---

**Última atualização**: 30 de maio de 2026  
**Status**: ✅ Testado e pronto para uso
