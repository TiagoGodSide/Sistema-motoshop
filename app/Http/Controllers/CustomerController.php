<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
  public function find(Request $r){
    $q = trim((string)$r->get('q',''));
    $list = Customer::query()
      ->when($q, fn($w)=>$w->where('name','like',"%$q%")
                           ->orWhere('phone','like',"%$q%")
                           ->orWhere('document','like',"%$q%"))
      ->orderBy('name')->limit(10)->get();
    return response()->json($list);
  }

  public function quick(Request $r){
    $data = $r->validate([
      'name' => ['required','string','max:120'],
      'phone'=> ['nullable','string','max:40'],
      'document'=>['nullable','string','max:40'],
      'email'=> ['nullable','email','max:120'],
    ]);
    $c = Customer::create($data);
    return response()->json($c, 201);
  }
}
