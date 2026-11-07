<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\Purchasing\InvalidAmountException;
use App\Services\Purchasing\LocalizedMoney;
use App\Services\Purchasing\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LocalizedMoneyTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function canonicalConversions(): array
    {
        return [
            'blank' => ['', ''],
            'whitespace only' => ['   ', ''],
            'whole' => ['10000', '10000'],
            'grouped' => ['10.000', '10000'],
            'grouped with kuruş' => ['10.000,50', '10000.50'],
            'several groups' => ['1.234.567,89', '1234567.89'],
            'one kuruş digit' => ['12,5', '12.5'],
            'trailing decimal comma' => ['12,', '12'],
            'zero' => ['0', '0'],
            'zero kuruş' => ['0,50', '0.50'],
            'arabic digits and separators' => ['١٠٬٠٠٠٫٥٠', '10000.50'],
            'persian digits' => ['۱۰.۰۰۰,۵۰', '10000.50'],
            'pasted with bidi marks and spaces' => ["\u{200F} 10.000,50\u{00A0}", '10000.50'],
        ];
    }

    #[Test]
    #[DataProvider('canonicalConversions')]
    public function it_converts_localized_text_into_the_canonical_decimal(string $input, string $expected): void
    {
        $this->assertSame($expected, LocalizedMoney::toCanonical($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rejectedInputs(): array
    {
        return [
            'dot decimal' => ['12.50', InvalidAmountException::FORMAT],
            'single dot digit' => ['12.5', InvalidAmountException::FORMAT],
            'comma grouping' => ['1,234.56', InvalidAmountException::FORMAT],
            'short group' => ['1.00.000', InvalidAmountException::FORMAT],
            'long first group' => ['1000.000', InvalidAmountException::FORMAT],
            'grouped leading zero' => ['0.500', InvalidAmountException::FORMAT],
            'trailing period' => ['10.', InvalidAmountException::FORMAT],
            'two decimal commas' => ['1,2,3', InvalidAmountException::FORMAT],
            'grouping after the comma' => ['10,000.50', InvalidAmountException::FORMAT],
            'no whole part' => [',50', InvalidAmountException::FORMAT],
            'space inside the amount' => ['12 50', InvalidAmountException::FORMAT],
            'space grouping' => ['1 000,50', InvalidAmountException::FORMAT],
            'no-break space inside the amount' => ["12\u{00A0}50", InvalidAmountException::FORMAT],
            'narrow no-break space grouping' => ["1\u{202F}000", InvalidAmountException::FORMAT],
            'space between arabic digits' => ['١٢ ٥٠', InvalidAmountException::FORMAT],
            'space before the decimal comma' => ['10.000 ,50', InvalidAmountException::FORMAT],
            'negative' => ['-5', InvalidAmountException::FORMAT],
            'letters' => ['10a', InvalidAmountException::FORMAT],
            'three kuruş digits' => ['10,505', InvalidAmountException::PRECISION],
            'ambiguous thousands comma' => ['1,500', InvalidAmountException::PRECISION],
        ];
    }

    #[Test]
    #[DataProvider('rejectedInputs')]
    public function it_rejects_ambiguous_or_malformed_text_instead_of_guessing(string $input, string $reason): void
    {
        try {
            LocalizedMoney::toCanonical($input);
            $this->fail("Expected {$input} to be rejected.");
        } catch (InvalidAmountException $exception) {
            $this->assertSame($reason, $exception->reason);
        }
    }

    #[Test]
    public function parsing_keeps_the_exact_money_bounds_and_zero_rule(): void
    {
        $this->assertSame(1_000_050, LocalizedMoney::parse('10.000,50'));
        $this->assertSame(Money::MAX_UNIT_AMOUNT, LocalizedMoney::parse('99.999.999,99', Money::MAX_UNIT_AMOUNT));
        $this->assertSame(0, LocalizedMoney::parse('0', allowZero: true));

        foreach ([['100.000.000,00', Money::MAX_UNIT_AMOUNT, InvalidAmountException::TOO_LARGE], ['0,00', Money::MAX_TOTAL_AMOUNT, InvalidAmountException::NOT_POSITIVE]] as [$input, $max, $reason]) {
            try {
                LocalizedMoney::parse($input, $max);
                $this->fail("Expected {$input} to be rejected.");
            } catch (InvalidAmountException $exception) {
                $this->assertSame($reason, $exception->reason);
            }
        }
    }

    #[Test]
    public function display_and_editable_forms_round_trip_through_parsing(): void
    {
        $this->assertSame('10.000,50', LocalizedMoney::format(1_000_050));
        $this->assertSame('-3.000,00', LocalizedMoney::format(-300_000));
        $this->assertSame('0,05', LocalizedMoney::format(5));
        $this->assertSame('10.000', LocalizedMoney::input(1_000_000));
        $this->assertSame('10.000,50', LocalizedMoney::input(1_000_050));

        foreach ([1, 1_000_000, 1_000_050, Money::MAX_TOTAL_AMOUNT] as $minor) {
            $this->assertSame($minor, LocalizedMoney::parse(LocalizedMoney::format($minor)));
            $this->assertSame($minor, LocalizedMoney::parse(LocalizedMoney::input($minor)));
        }
    }
}
