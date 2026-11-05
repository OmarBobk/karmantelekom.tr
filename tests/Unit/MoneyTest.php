<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\Purchasing\InvalidAmountException;
use App\Services\Purchasing\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function validInputs(): array
    {
        return [
            'whole' => ['8000', 800_000],
            'two decimals' => ['12.50', 1_250],
            'one decimal' => ['12.5', 1_250],
            'thousands separator' => ['8,000.00', 800_000],
            'decimal comma' => ['12,50', 1_250],
            'arabic digits and decimal mark' => ['١٢٫٥٠', 1_250],
            'surrounding spaces' => ['  50.00 ', 5_000],
            'trailing zeros beyond cents' => ['1.500', 150],
            'leading zeros' => ['0007.05', 705],
        ];
    }

    #[Test]
    #[DataProvider('validInputs')]
    public function it_parses_operator_input_into_exact_minor_units(string $input, int $expected): void
    {
        $this->assertSame($expected, Money::parse($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'three decimals' => ['12.505', InvalidAmountException::PRECISION],
            'negative' => ['-5', InvalidAmountException::FORMAT],
            'letters' => ['12a', InvalidAmountException::FORMAT],
            'misplaced grouping' => ['80,00.00', InvalidAmountException::FORMAT],
            'zero' => ['0.00', InvalidAmountException::NOT_POSITIVE],
            'over the ceiling' => ['10000000000.00', InvalidAmountException::TOO_LARGE],
            'too many digits' => ['99999999999999999999', InvalidAmountException::TOO_LARGE],
        ];
    }

    #[Test]
    #[DataProvider('invalidInputs')]
    public function it_rejects_unsupported_precision_format_and_overflow(string $input, string $reason): void
    {
        try {
            Money::parse($input);
            $this->fail("Expected {$input} to be rejected.");
        } catch (InvalidAmountException $exception) {
            $this->assertSame($reason, $exception->reason);
        }
    }

    #[Test]
    public function zero_is_allowed_only_when_requested(): void
    {
        $this->assertSame(0, Money::parse('0', allowZero: true));
    }

    #[Test]
    public function multiplication_and_sums_are_exact_and_bounded(): void
    {
        $this->assertSame(12_500, Money::multiply(10, 1_250));
        $this->assertSame(817_500, Money::sum([12_500, 800_000, 5_000]));

        $this->expectException(InvalidAmountException::class);
        Money::multiply(10_000, Money::MAX_UNIT_AMOUNT);
    }

    #[Test]
    public function sums_reject_totals_above_the_ceiling(): void
    {
        $this->expectException(InvalidAmountException::class);
        Money::sum([Money::MAX_TOTAL_AMOUNT, 1]);
    }

    #[Test]
    public function it_round_trips_database_decimals_and_formats_for_display(): void
    {
        $this->assertSame(817_500, Money::fromDecimal('8175.00'));
        $this->assertSame(-300_000, Money::fromDecimal('-3000.00'));
        $this->assertSame('8175.00', Money::toDecimal(817_500));
        $this->assertSame('-0.05', Money::toDecimal(-5));
        $this->assertSame('8,175.00', Money::format(817_500));
        $this->assertSame('-3,000.00', Money::format(-300_000));
    }
}
