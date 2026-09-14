<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Item Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Order Item Details</h2>
        <ul class="list-group">
            <li class="list-group-item">ID: {{ $orderItem->id }}</li>
            <li class="list-group-item">Order ID: {{ $orderItem->order_id }}</li>
            <li class="list-group-item">Product: {{ $orderItem->product->name }}</li>
            <li class="list-group-item">Quantity: {{ $orderItem->quantity }}</li>
            <li class="list-group-item">Price: {{ $orderItem->price }}</li>
        </ul>
    </div>
</body>
</html>
