@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Criar conta</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('register') }}">
                        @csrf

                        <div class="row mb-3">
                            <label for="name" class="col-md-4 col-form-label text-md-end">Nome</label>
                            <div class="col-md-6">
                                <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>
                                @error('name')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="email" class="col-md-4 col-form-label text-md-end">E-mail</label>
                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">
                                @error('email')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="cpf" class="col-md-4 col-form-label text-md-end">CPF</label>
                            <div class="col-md-6">
                                <input id="cpf" type="text" class="form-control @error('cpf') is-invalid @enderror" name="cpf" value="{{ old('cpf') }}" required>
                                @error('cpf')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="oab" class="col-md-4 col-form-label text-md-end">OAB</label>
                            <div class="col-md-6">
                                <input id="oab" type="text" class="form-control @error('oab') is-invalid @enderror" name="oab" value="{{ old('oab') }}" required>
                                @error('oab')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                                <small class="text-muted">Se você não tiver empresa, sua OAB será usada como identificador do tenant.</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="password" class="col-md-4 col-form-label text-md-end">Senha</label>
                            <div class="col-md-6">
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
                                @error('password')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="password-confirm" class="col-md-4 col-form-label text-md-end">Confirmar senha</label>
                            <div class="col-md-6">
                                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                            </div>
                        </div>

                        <hr>

                        <div class="row mb-3">
                            <div class="col-md-6 offset-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" name="possui_empresa" id="possui_empresa"
                                        {{ old('possui_empresa') ? 'checked' : '' }}
                                        onchange="document.getElementById('empresa-fields').style.display = this.checked ? 'block' : 'none'">
                                    <label class="form-check-label" for="possui_empresa">
                                        Possuo empresa / escritório
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div id="empresa-fields" style="display: {{ old('possui_empresa') ? 'block' : 'none' }};">
                            <div class="row mb-3">
                                <label for="empresa_nome" class="col-md-4 col-form-label text-md-end">Nome da empresa</label>
                                <div class="col-md-6">
                                    <input id="empresa_nome" type="text" class="form-control @error('empresa_nome') is-invalid @enderror" name="empresa_nome" value="{{ old('empresa_nome') }}">
                                    @error('empresa_nome')
                                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="cnpj" class="col-md-4 col-form-label text-md-end">CNPJ</label>
                                <div class="col-md-6">
                                    <input id="cnpj" type="text" class="form-control @error('cnpj') is-invalid @enderror" name="cnpj" value="{{ old('cnpj') }}">
                                    @error('cnpj')
                                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                    <small class="text-muted">O CNPJ será o identificador do tenant.</small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row mb-3">
                            <label for="gemini_chave" class="col-md-4 col-form-label text-md-end">
                                Chave Gemini
                                <small class="d-block text-muted fw-normal">(opcional)</small>
                            </label>
                            <div class="col-md-6">
                                <input id="gemini_chave" type="password"
                                       class="form-control @error('gemini_chave') is-invalid @enderror"
                                       name="gemini_chave"
                                       autocomplete="off"
                                       placeholder="AIzaSy...">
                                @error('gemini_chave')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                                <small class="text-muted">
                                    Chave de API do Google Gemini para integrações com IA.
                                    Ficará salva como <em>"Principal"</em> e pode ser alterada depois em
                                    <strong>Chaves Gemini</strong>.
                                </small>
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-md-6 offset-md-4">
                                <button type="submit" class="btn btn-primary">Criar conta</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
