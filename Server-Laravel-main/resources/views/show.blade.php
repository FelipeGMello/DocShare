@extends('app')

@section('title', $document['title'] ?? 'Documento sem título')

@push('scripts')
<script>
    // Gera hash do username para sincronização
    const usernameRaw = "{{ $username }}";
    const usernameHash = Array.from(usernameRaw).reduce((h, c) => 
        ((h << 5) - h) + c.charCodeAt(0) | 0, 0).toString(16);
    
    // Extrai IP/host do servidor da URL atual
    const reverbHost = window.location.hostname === 'localhost' 
        ? window.location.hostname 
        : window.location.hostname; // usa o mesmo host da página
    
    window.DocShare = {
        autosaveUrl: "{{ route('document.autosave') }}",
        saveUrl:     "{{ route('document.save') }}",
        opsUrl:      "{{ route('document.ops', ['id' => $document['id'] ?? 1]) }}",
        csrfToken:   "{{ csrf_token() }}",
        documentId:  "{{ $document['id'] ?? 1 }}",
        username:    "{{ $username }}",
        usernameHash: usernameHash,
        reverbKey:   "{{ config('broadcasting.connections.reverb.key') }}",
        reverbHost:  reverbHost,
        reverbPort:  {{ config('broadcasting.connections.reverb.options.port') }},
    };
</script>
@endpush

@section('content')

{{-- ─── Header ─── --}}
<div id="header">
    <div class="logo">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 1.5L18.5 9H13V3.5zM6 20V4h5v7h7v9H6z"/></svg>
    </div>

    <input
        id="doc-title"
        type="text"
        value="{{ $document['title'] ?? 'Documento sem título' }}"
        spellcheck="false"
    />

    <div class="header-spacer"></div>

    <div id="online-users">
        @foreach ($collaborators as $user)
            @php
                $avatarStyle = 'background:' . $user['color'] . ';' . (!$loop->first ? 'margin-left:-8px' : '');
            @endphp
            <div
                class="avatar"
                style="{{ $avatarStyle }}"
                data-tip="{{ $user['name'] }}"
            >{{ $user['initials'] }}</div>
        @endforeach
    </div>

    <button id="btn-share" onclick="document.getElementById('modal-overlay').classList.add('open')">
        <svg viewBox="0 0 24 24"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>
        Compartilhar
    </button>
</div>

{{-- ─── Menu bar ─── --}}
<!--
    <div id="menubar">
    @foreach (['Arquivo', 'Editar', 'Ver', 'Inserir', 'Formatar', 'Ferramentas', 'Extensões', 'Ajuda'] as $menu)
        <span class="menu-item">{{ $menu }}</span>
    @endforeach
</div> 
-->

{{-- ─── Toolbar ─── --}}
<div id="toolbar">

    <button class="tb-btn" id="btn-undo" data-tip="Desfazer (Ctrl+Z)" onclick="document.execCommand('undo')">
        <svg viewBox="0 0 24 24"><path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg>
    </button>
    <button class="tb-btn" id="btn-redo" data-tip="Refazer (Ctrl+Y)" onclick="document.execCommand('redo')">
        <svg viewBox="0 0 24 24"><path d="M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z"/></svg>
    </button>
    <button class="tb-btn" data-tip="Imprimir (Ctrl+P)" onclick="window.print()">
        <svg viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
    </button>

    <div class="tb-sep"></div>

    <select class="tb-select" style="width:60px"
        onchange="document.getElementById('page').style.transform='scale('+this.value+')';document.getElementById('page').style.transformOrigin='top center'">
        <option value="0.75">75%</option>
        <option value="1" selected>100%</option>
        <option value="1.25">125%</option>
        <option value="1.5">150%</option>
    </select>

    <div class="tb-sep"></div>

    <select class="tb-select" id="sel-style" style="width:130px" onchange="applyStyle(this.value)">
        <option value="p">Texto normal</option>
        <option value="h1">Título 1</option>
        <option value="h2">Título 2</option>
        <option value="h3">Título 3</option>
    </select>

    <div class="tb-sep"></div>

    <select class="tb-select" style="width:110px" onchange="document.execCommand('fontName',false,this.value)">
        <option value="Roboto" selected>Roboto</option>
        <option value="Arial">Arial</option>
        <option value="Georgia">Georgia</option>
        <option value="Courier New">Courier New</option>
        <option value="Times New Roman">Times New Roman</option>
    </select>

    <select class="tb-select" style="width:50px" onchange="document.execCommand('fontSize',false,this.value)">
        <option value="1">8</option>
        <option value="2">10</option>
        <option value="3" selected>12</option>
        <option value="4">14</option>
        <option value="5">18</option>
        <option value="6">24</option>
        <option value="7">36</option>
    </select>

    <div class="tb-sep"></div>

    <button class="tb-btn" id="btn-bold"      data-tip="Negrito (Ctrl+B)"    onclick="document.execCommand('bold');toggleActive(this)">
        <svg viewBox="0 0 24 24"><path d="M15.6 10.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H7v14h7.04c2.09 0 3.71-1.7 3.71-3.79 0-1.52-.86-2.82-2.15-3.42zM10 6.5h3c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-3v-3zm3.5 9H10v-3h3.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5z"/></svg>
    </button>
    <button class="tb-btn" id="btn-italic"    data-tip="Itálico (Ctrl+I)"    onclick="document.execCommand('italic');toggleActive(this)">
        <svg viewBox="0 0 24 24"><path d="M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4z"/></svg>
    </button>
    <button class="tb-btn" id="btn-underline" data-tip="Sublinhado (Ctrl+U)" onclick="document.execCommand('underline');toggleActive(this)">
        <svg viewBox="0 0 24 24"><path d="M12 17c3.31 0 6-2.69 6-6V3h-2.5v8c0 1.93-1.57 3.5-3.5 3.5S8.5 12.93 8.5 11V3H6v8c0 3.31 2.69 6 6 6zm-7 2v2h14v-2H5z"/></svg>
    </button>
    <button class="tb-btn" data-tip="Tachado" onclick="document.execCommand('strikeThrough')">
        <svg viewBox="0 0 24 24"><path d="M10 19h4v-3h-4v3zM5 4v3h5v3h4V7h5V4H5zM3 14h18v-2H3v2z"/></svg>
    </button>

    <div class="tb-sep"></div>

    <button class="tb-btn" data-tip="Cor do texto" style="position:relative" onclick="document.getElementById('color-pick').click()">
        <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zm4.24 16L12 15.45 7.77 18l1.12-4.81-3.73-3.23 4.92-.42L12 5l1.92 4.53 4.92.42-3.73 3.23L16.23 18z"/></svg>
        <input type="color" id="color-pick" style="position:absolute;opacity:0;width:0;height:0" onchange="document.execCommand('foreColor',false,this.value)" />
    </button>
    <button class="tb-btn" data-tip="Cor de destaque" style="position:relative" onclick="document.getElementById('bg-pick').click()">
        <svg viewBox="0 0 24 24"><path d="M16.56 8.94L7.62 0 6.21 1.41l2.38 2.38-5.15 5.15a1.49 1.49 0 0 0 0 2.12l5.5 5.5c.29.29.68.44 1.06.44s.77-.15 1.06-.44l5.5-5.5c.59-.58.59-1.53 0-2.12zM5.21 10L10 5.21 14.79 10H5.21zM19 11.5s-2 2.17-2 3.5c0 1.1.9 2 2 2s2-.9 2-2c0-1.33-2-3.5-2-3.5z"/><path fill-opacity=".36" d="M0 20h24v4H0z"/></svg>
        <input type="color" id="bg-pick" style="position:absolute;opacity:0;width:0;height:0" onchange="document.execCommand('backColor',false,this.value)" />
    </button>

    <div class="tb-sep"></div>

    <button class="tb-btn" data-tip="Lista com marcadores" onclick="document.execCommand('insertUnorderedList')">
        <svg viewBox="0 0 24 24"><path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5S3.17 7.5 4 7.5 5.5 6.83 5.5 6 4.83 4.5 4 4.5zm0 12c-.83 0-1.5.68-1.5 1.5s.68 1.5 1.5 1.5 1.5-.68 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z"/></svg>
    </button>
    <button class="tb-btn" data-tip="Lista numerada" onclick="document.execCommand('insertOrderedList')">
        <svg viewBox="0 0 24 24"><path d="M2 17h2v.5H3v1h1v.5H2v1h3v-4H2v1zm1-9h1V4H2v1h1v3zm-1 3h1.8L2 13.1v.9h3v-1H3.2L5 10.9V10H2v1zm5-6v2h14V5H7zm0 14h14v-2H7v2zm0-6h14v-2H7v2z"/></svg>
    </button>

    <div class="tb-sep"></div>

    <button class="tb-btn" data-tip="Diminuir recuo" onclick="document.execCommand('outdent')">
        <svg viewBox="0 0 24 24"><path d="M11 17h10v-2H11v2zm-8-5l4 4V8l-4 4zm0 9h18v-2H3v2zM3 3v2h18V3H3zm8 6h10V7H11v2zm0 4h10v-2H11v2z"/></svg>
    </button>
    <button class="tb-btn" data-tip="Aumentar recuo" onclick="document.execCommand('indent')">
        <svg viewBox="0 0 24 24"><path d="M3 21h18v-2H3v2zM3 8v8l4-4-4-4zm8 9h10v-2H11v2zM3 3v2h18V3H3zm8 6h10V7H11v2zm0 4h10v-2H11v2z"/></svg>
    </button>

    <div class="tb-sep"></div>

    <button class="tb-btn" data-tip="Inserir link (Ctrl+K)" onclick="insertLink()">
        <svg viewBox="0 0 24 24"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>
    </button>
    <button class="tb-btn" data-tip="Localizar e substituir (Ctrl+H)" onclick="toggleFindBar()">
        <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
    </button>

    <div class="tb-sep"></div>

    <button class="tb-btn" data-tip="Limpar formatação" onclick="document.execCommand('removeFormat')">
        <svg viewBox="0 0 24 24"><path d="M3.27 5L2 6.27l6.97 6.97L6.5 19h3l1.57-3.66L16.73 21 18 19.73 3.27 5zM6 5v.18L8.82 8h2.4l-.72 1.68 2.1 2.1L14.21 8H20V5H6z"/></svg>
    </button>

</div>

{{-- ─── Find & Replace Bar ─── --}}
<div id="find-bar">
    <div class="find-row">
        <input class="find-input" id="find-input" placeholder="Localizar…" oninput="doFind()" />
        <span class="find-label" id="find-count"></span>
        <button class="find-btn" onclick="stepFind(-1)">‹</button>
        <button class="find-btn" onclick="stepFind(1)">›</button>
        <button class="find-close" onclick="toggleFindBar()">✕</button>
    </div>
    <div class="find-row">
        <input class="find-input" id="replace-input" placeholder="Substituir por…" />
        <button class="find-btn" onclick="doReplace(false)">Substituir</button>
        <button class="find-btn" onclick="doReplace(true)">Substituir tudo</button>
    </div>
</div>

{{-- ─── Main ─── --}}
<div id="main">

    <nav id="sidebar">
        <h3>Estrutura</h3>
        <div id="outline-list"></div>
    </nav>

    <div id="doc-area">

        <div id="ruler"></div>

        <div
            id="page"
            contenteditable="true"
            spellcheck="true"
            data-document-id="{{ $document['id'] ?? 1 }}"
        >{!! $document['body'] ?? '<h1>Bem-vindo ao DocShare</h1>
<p>Este é um documento compartilhado em tempo real. Você pode <strong>editar</strong>, <em>formatar</em> e <u>colaborar</u> com outras pessoas diretamente nesta página.</p>
<h2>Como usar</h2>
<p>Use a barra de ferramentas acima para aplicar formatação ao texto selecionado. Você pode inserir listas, alterar o estilo dos títulos, adicionar links e muito mais.</p>
<ul>
  <li>Selecione o texto e clique em <strong>N</strong> para negrito</li>
  <li>Use <em>Ctrl+I</em> para itálico</li>
  <li>Pressione <em>Ctrl+U</em> para sublinhado</li>
  <li>Use Ctrl+Z para desfazer alterações</li>
</ul>
<h2>Colaboração</h2>
<p>Todos os participantes com acesso podem editar este documento simultaneamente. As alterações são salvas automaticamente.</p>
<blockquote>💡 Dica: Use o botão "Compartilhar" no canto superior direito para convidar outras pessoas.</blockquote>
<h3>Notas adicionais</h3>
<p>Adicione aqui qualquer conteúdo que desejar. Este editor suporta títulos, parágrafos, listas ordenadas e não ordenadas, além de formatação básica de texto.</p>' !!}</div>

    </div>
</div>

{{-- ─── Status bar ─── --}}
<div id="statusbar">
    <div class="saved-indicator">
        <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
        <span id="save-status">Todas as alterações foram salvas</span>
    </div>
    <div class="dot"></div>
    <span id="word-count">0 palavras</span>
    <div class="dot"></div>
    <span id="char-count">0 caracteres</span>
    <div class="dot"></div>
    <span>Editando</span>
</div>

{{-- ─── Share Modal ─── --}}
<div id="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
    <div id="modal">
        <header>
            <h2>Compartilhar "{{ $document['title'] ?? 'Documento sem título' }}"</h2>
            <button id="modal-close" onclick="document.getElementById('modal-overlay').classList.remove('open')">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
        </header>
        <div class="modal-body">
            <div class="share-input-row">
                <input class="share-input" type="email" placeholder="Adicionar pessoas e grupos" id="invite-email" />
                <button class="share-send-btn" onclick="addCollaborator()">Convidar</button>
            </div>
            <div class="access-section">
                <h4>Pessoas com acesso</h4>
                <div id="access-list">
                    @foreach ($collaborators as $user)
                        <div class="access-row">
                            @php $avStyle = 'background:' . $user['color']; @endphp
                            <div class="av" style="{{ $avStyle }}">{{ $user['initials'] }}</div>
                            <div class="info">
                                <div class="name">{{ $user['name'] }}</div>
                                <div class="email">{{ $user['email'] }}</div>
                            </div>
                            <select class="role-sel">
                                @if ($loop->first)
                                    <option>Proprietário</option>
                                @else
                                    <option @selected($user['role'] === 'editor')>Editor</option>
                                    <option @selected($user['role'] === 'leitor')>Leitor</option>
                                @endif
                            </select>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="link-section">
                <h4>Link de acesso</h4>
                <div class="link-row">
                    <input class="link-url" readonly value="{{ $shareUrl ?? url('/document/share/xK9mP2qRvTz') }}" />
                    <button class="copy-btn" onclick="copyLink(this)">Copiar link</button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection