<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Order Item</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Edit Order Item</h2>

        @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form action="{{ route('order-items.update', $orderItem->id) }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label">Order</label>
                <select name="order_id" class="form-control">
                    @foreach ($orders as $order)
                    <option value="{{ $order->id }}" @selected(old('order_id', $orderItem->order_id) == $order->id)>Order #{{ $order->id }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Product</label>
                <select name="product_id" class="form-control">
                    @foreach ($products as $product)
                    <option value="{{ $product->id }}" @selected(old('product_id', $orderItem->product_id) == $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity" class="form-control" value="{{ old('quantity', $orderItem->quantity) }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Price</label>
                <input type="number" step="0.01" name="price" class="form-control" value="{{ old('price', $orderItem->price) }}">
            </div>
            <button type="submit" class="btn btn-success">Update</button>
            <a href="{{ route('order-items.index') }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>
