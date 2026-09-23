<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Pix = 'pix';
    case Boleto = 'boleto';
    case BankTransfer = 'bank_transfer';
    case CreditCard = 'credit_card';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'Pix',
            self::Boleto => 'Boleto',
            self::BankTransfer => 'Transferência bancária',
            self::CreditCard => 'Cartão de crédito',
            self::Cash => 'Dinheiro',
        };
    }
}
