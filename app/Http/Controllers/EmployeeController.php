<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->get('q',''));
        $users = User::query()
            ->when($q, fn($qry)=>$qry->where('name','like',"%{$q}%")->orWhere('email','like',"%{$q}%"))
            ->with('roles')
            ->paginate(20)
            ->appends($request->query());

        return view('employees.index', compact('users','q'));
        //return response()->json($users);
    }

    public function store(Request $request)
{
    $data = $request->validate([
        'name'  => ['required','string','max:120'],
        'email' => ['required','email','unique:users,email'],
        'password' => ['required', \Illuminate\Validation\Rules\Password::min(6)],
        'role' => ['required','string'],
    ]);

    $user = \App\Models\User::create([
        'name' => $data['name'],
        'email'=> $data['email'],
        'password' => bcrypt($data['password']),
    ]);

    if ($role = \Spatie\Permission\Models\Role::where('name',$data['role'])->first()) {
        $user->assignRole($role);
    }

    return redirect()->route('employees.index')->with('ok','Funcionário criado.');
}

public function update(Request $request, \App\Models\User $employee)
{
    $data = $request->validate([
        'name'  => ['required','string','max:120'],
        'email' => ["required","email","unique:users,email,{$employee->id}"],
        'password' => ['nullable', \Illuminate\Validation\Rules\Password::min(6)],
        'role' => ['nullable','string'],
    ]);

    $employee->update([
        'name' => $data['name'],
        'email'=> $data['email'],
        'password' => !empty($data['password']) ? bcrypt($data['password']) : $employee->password,
    ]);

    if (!empty($data['role'])) {
        $employee->syncRoles([$data['role']]);
    }

    return redirect()->route('employees.index')->with('ok','Dados atualizados.');
}

}
