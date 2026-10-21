<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Question = 'question';
    case Problem = 'problem';
    case Request = 'request';
    case Billing = 'billing';
    case Other = 'other';
}
