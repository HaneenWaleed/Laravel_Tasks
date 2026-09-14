<?php

namespace App\Http\Controllers;

use App\Models\Order_Item;
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
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
    public function edit(Order_Item $order_Item)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order_Item $order_Item)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order_Item $order_Item)
    {
        //
    }
}
