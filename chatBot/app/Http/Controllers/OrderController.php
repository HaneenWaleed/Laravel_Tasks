<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Models\Category;
use App\Http\Requests\OrderRequest;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $orders = Order::all();
        return view('orders.index', compact('orders'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        $users = User::all();
        $categories = Category::all();
        return view('orders.create', compact('users', 'categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(OrderRequest $request)
    {
        //
        Order::create($request->validated());
        return redirect()->route('orders.index');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //
        $order = Order::findOrFail($id);
        return view('orders.show', compact('order'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        //
        $order = Order::findOrFail($id);
        $users = User::all();
        $categories = Category::all();
        return view('orders.edit', compact('order', 'users', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(OrderRequest $request, $id)
    {
        //
        $order = Order::findOrFail($id);
        $order->update($request->validated());
        return redirect()->route('orders.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
        $order = Order::findOrFail($id);
        $order->delete();
        return redirect()->route('orders.index');
    }
}
