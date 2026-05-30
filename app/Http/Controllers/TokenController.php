<?php

namespace App\Http\Controllers;

use App\Models\TokenCnj;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function telaCnj(string $data)
    {
        $hoje = Carbon::now()->toDateString();
        if ($data !== $hoje) {
            abort(404);
        }
        
        
        return view('token-cnj');
    }

    public function store(Request $request)
    {
        // dd($request->all());
        TokenCnj::create([
            'token' => $request->input('token'),
        ]);
        return back();
    }
}
