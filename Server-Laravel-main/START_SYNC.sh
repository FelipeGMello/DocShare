#!/bin/bash
# QUICK START - Sincronização Collab-CRDT
# Execute este script para testar a aplicação com todas as correções

set -e

PROJECT_DIR="/home/arthur-alexandrino/Codigos/collab-crdt-laravel"

echo "╔════════════════════════════════════════════════════════════╗"
echo "║   🚀 INICIANDO COLLAB-CRDT COM SINCRONIZAÇÃO CORRIGIDA   ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Step 1: Limpar cache
echo "📋 Passo 1: Limpando cache..."
cd "$PROJECT_DIR"
php artisan config:clear
php artisan cache:clear  
php artisan view:clear
echo "✅ Cache limpo"
echo ""

# Step 2: Informações de rede
echo "📡 Passo 2: Configuração de rede"
echo "   IP local: $(hostname -I | awk '{print $1}')"
echo "   Porta Laravel: 8000"
echo "   Porta Reverb: 8080"
echo ""

# Step 3: Reverb
echo "🔌 Passo 3: Iniciando Reverb (WebSocket Server)"
echo "   Comando: php artisan reverb:start --host=0.0.0.0 --port=8080"
echo ""
echo "   ⚠️  MANTENHA ESTE TERMINAL ABERTO"
echo ""
echo "   Quando ver a mensagem de sucesso, abra OUTRO terminal e execute:"
echo "   └─> cd $PROJECT_DIR"
echo "   └─> php artisan serve --host=0.0.0.0 --port=8000"
echo ""
echo "   Pressione Enter para iniciar Reverb..."
read

php artisan reverb:start --host=0.0.0.0 --port=8080

# Nunca deve chegar aqui (Reverb fica em loop)
