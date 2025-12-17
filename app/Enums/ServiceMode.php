<?php 

// app/Enums/ServiceMode.php
enum ServiceMode: string {
    case LIVE = 'live';
    case TEST = 'test';
    case DUMMY = 'dummy';
}
