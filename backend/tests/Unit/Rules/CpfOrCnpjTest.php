<?php

namespace Tests\Unit\Rules;

use App\Rules\CpfOrCnpj;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CpfOrCnpjTest extends TestCase
{
    public static function validDocuments(): array
    {
        return [
            'cpf' => ['52998224725'],
            'cpf with punctuation' => ['529.982.247-25'],
            'cnpj' => ['11222333000181'],
            'cnpj with punctuation' => ['11.222.333/0001-81'],
        ];
    }

    public static function invalidDocuments(): array
    {
        return [
            'cpf with a wrong check digit' => ['52998224724'],
            'cnpj with a wrong check digit' => ['11222333000180'],
            'repeated digits are never a real cpf' => ['11111111111'],
            'repeated digits are never a real cnpj' => ['11111111111111'],
            'too short' => ['1234'],
            'letters' => ['abcdefghijk'],
            'empty' => [''],
        ];
    }

    #[DataProvider('validDocuments')]
    public function test_it_accepts_valid_documents(string $document): void
    {
        $this->assertTrue($this->passes($document));
    }

    #[DataProvider('invalidDocuments')]
    public function test_it_rejects_invalid_documents(string $document): void
    {
        $this->assertFalse($this->passes($document));
    }

    public function test_it_strips_punctuation_to_a_canonical_form(): void
    {
        $this->assertSame('11222333000181', CpfOrCnpj::digits('11.222.333/0001-81'));
        $this->assertSame('52998224725', CpfOrCnpj::digits('529.982.247-25'));
    }

    private function passes(string $document): bool
    {
        $failed = false;

        (new CpfOrCnpj)->validate('document', $document, function () use (&$failed): void {
            $failed = true;
        });

        return ! $failed;
    }
}
