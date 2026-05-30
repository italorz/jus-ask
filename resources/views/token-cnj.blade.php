@extends('layouts.app')
@section('content')
<div>
    <form method="POST" action="{{ route('postTokenCnj') }}">
        @csrf
    <input type="text" name="token" class="input input-bordered w-full max-w-xs" placeholder="Token CNJ" />
    <button type="submit" class="btn btn-primary mt-2">Salvar Token</button>
    </form>
</div>
@endsection