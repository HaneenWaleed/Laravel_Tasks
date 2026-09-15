<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Product Details</h2>
        <ul class="list-group">
            <li class="list-group-item">ID: {{ $product->id }}</li>
            <li class="list-group-item">Name: {{ $product->name }}</li>
            <li class="list-group-item">Description: {{ $product->description }}</li>
            <li class="list-group-item">Price: {{ $product->price }}</li>
            <li class="list-group-item">Quantity: {{ $product->quantity }}</li>
            <li class="list-group-item">Category: {{ $product->category->name }}</li>
        </ul>
    </div>
</body>
</html>
