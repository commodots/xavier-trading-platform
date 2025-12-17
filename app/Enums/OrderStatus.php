<?php 
// app/Enums/OrderStatus.php
enum OrderStatus: string {
    case NEW = 'new';
    case PARTIAL = 'partial';
    case FILLED = 'filled';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';
}
