<?php

namespace App\Http\Controllers;

use App\Models\Order_Item;
use App\Models\Order;
use App\Models\Product;
use App\Http\Requests\OrderItemRequest;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $orderItems = Order_Item::all();
        return view('order_items.index', compact('orderItems'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        $orders = Order::all();
        $products = Product::all();
        return view('order_items.create', compact('orders', 'products'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(OrderItemRequest $request)
    {
        //
        Order_Item::create($request->validated());
        return redirect()->route('order-items.index');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        //
        $orderItem = Order_Item::findOrFail($id);
        return view('order_items.show', compact('orderItem'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        //
        $orderItem = Order_Item::findOrFail($id);
        $orders = Order::all();
        $products = Product::all();
        return view('order_items.edit', compact('orderItem', 'orders', 'products'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(OrderItemRequest $request, $id)
    {
        $orderItem = Order_Item::findOrFail($id);
        $orderItem->update($request->validated());
        return redirect()->route('order-items.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $orderItem = Order_Item::findOrFail($id);
        $orderItem->delete();
        return redirect()->route('order-items.index');
    }
}
