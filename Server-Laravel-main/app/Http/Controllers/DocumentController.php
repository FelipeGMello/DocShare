<?php
namespace App\Http\Controllers;

use App\Events\DocumentUpdated;
use App\Models\Document;
use App\Services\CrdtService;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(private CrdtService $crdt) {}

    // Tela de entrada — pede o nome se não tiver cookie
    public function entrada()
    {
        if (request()->cookie('username')) {
            return redirect()->route('documento');
        }
        return view('nome');
    }

    // Salva o nome no cookie e redireciona
    public function salvarNome(Request $request)
    {
        $request->validate(['name' => 'required|string|max:50']);

        return redirect()->route('documento')
            ->cookie('username', $request->name, 60 * 24 * 7); // 7 dias
    }

    // Abre o documento
    public function show(Request $request)
    {
        $username = $request->cookie('username', 'Anônimo');
        $document = Document::findOrFail(1);

        // Busca conteúdo atual do servidor Rust
        $content = $this->crdt->getContent();
        if ($content) {
            $document->content = $content;
        }

        // Gera uma cor consistente para o usuário baseada no nome
        $color = '#' . substr(md5($username), 0, 6);

        return view('show', [
            'document'      => [
                'id'    => 1,
                'title' => $document->title,
                'body'  => $document->content ?? '',
            ],
            'collaborators' => [[
                'name'     => $username,
                'initials' => strtoupper(substr($username, 0, 2)),
                'color'    => $color,
                'email'    => '',
                'role'     => 'editor',
            ]],
            'username'      => $username,
            'shareUrl'      => url('/'),
        ]);
    }

    // Recebe keystroke do browser → Rust → broadcast
    public function update(Request $request)
    {
        $request->validate(['content' => 'required|string']);

        $username = $request->cookie('username', 'Anônimo');
        $site     = $request->input('site', 'user-' . md5($username));

        $this->crdt->applyText($request->content, $site);

        $content = $this->crdt->getContent();

        Document::where('id', 1)->update(['content' => $content]);

        broadcast(new DocumentUpdated($content, $site));

        return response()->noContent();
    }
}