<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DocShare — Entrar</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Roboto', sans-serif;
            background: #f0f4f8;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 2.5rem;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
            text-align: center;
        }
        .logo { font-size: 2rem; margin-bottom: .5rem; }
        h1 { font-size: 1.4rem; color: #1a73e8; margin-bottom: .25rem; }
        p  { color: #666; font-size: .9rem; margin-bottom: 1.5rem; }
        input {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: .65rem 1rem;
            font-size: 1rem;
            margin-bottom: 1rem;
            outline: none;
        }
        input:focus { border-color: #1a73e8; }
        button {
            width: 100%;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 6px;
            padding: .7rem;
            font-size: 1rem;
            cursor: pointer;
        }
        button:hover { background: #1558b0; }
        .error { color: #d32f2f; font-size: .85rem; margin-top: .5rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">📄</div>
        <h1>DocShare</h1>
        <p>Documento colaborativo em tempo real</p>

        <form method="POST" action="{{ route('salvar-nome') }}">
            @csrf
            <input
                type="text"
                name="name"
                placeholder="Seu nome"
                autofocus
                value="{{ old('name') }}"
                maxlength="50"
            >
            @error('name')
                <p class="error">{{ $message }}</p>
            @enderror
            <button type="submit">Entrar no documento</button>
        </form>
    </div>
</body>
</html>