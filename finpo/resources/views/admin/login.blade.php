<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Connexion — FINPO Admin</title>
    <link href="{{ asset('assets/vendor/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/finpo.css') }}" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
<div class="fp-card p-4 w-100" style="max-width: 400px;">
    <div class="text-center mb-4">
        <span class="fp-brand fs-2">FIN<span>PO</span></span>
        <p class="fp-muted small mb-0">Espace administration</p>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
    @endif
    <form method="post" action="{{ route('admin.login.post') }}" class="d-grid gap-3">
        @csrf
        <div>
            <label class="form-label" for="email">Email</label>
            <input id="email" type="email" name="email" class="form-control" required autofocus value="{{ old('email') }}">
        </div>
        <div>
            <label class="form-label" for="password">Mot de passe</label>
            <input id="password" type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-fp-primary">Se connecter</button>
        <a href="{{ route('home') }}" class="text-center small fp-muted">← Retour au site</a>
    </form>
</div>
</body>
</html>
