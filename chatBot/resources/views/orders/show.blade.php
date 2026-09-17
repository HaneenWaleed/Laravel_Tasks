<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Order Details</h2>
        <ul class="list-group mb-4">
            <li class="list-group-item">ID: {{ $order->id }}</li>
            <li class="list-group-item">User: {{ $order->user->name }}</li>
            <li class="list-group-item">Category: {{ $order->category->name ?? '-' }}</li>
            <li class="list-group-item">Created At: {{ $order->created_at }}</li>
        </ul>

        <h3>Order Items</h3>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->orderItems as $item)
                <tr>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $item->price }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
