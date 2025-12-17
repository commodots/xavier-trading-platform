<ul class="timeline">
  <li>Order Submitted</li>
  <li v-if="order.filled_qty">Matched</li>
  <li v-if="settlement">Settled</li>
</ul>
