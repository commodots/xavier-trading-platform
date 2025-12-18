<h2>Contract Note</h2>
<hr>

<p><strong>Client:</strong> {{ $user->name }}</p>
<p><strong>Stock:</strong> {{ $order->symbol }}</p>
<p><strong>Quantity:</strong> {{ $trade->quantity }}</p>
<p><strong>Price:</strong> ₦{{ number_format($trade->price,2) }}</p>
<p><strong>Total:</strong> ₦{{ number_format($trade->quantity * $trade->price,2) }}</p>
<p><strong>Date:</strong> {{ $trade->created_at }}</p>
