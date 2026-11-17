<?php

namespace App\Support;

use InvalidArgumentException;
use Stringable;

/**
 * Decimal class for safe financial and inventory quantity calculations using ext-bcmath.
 * Avoids precision loss inherent to PHP native floats.
 */
class Decimal implements Stringable
{
    protected string $value;

    protected int $scale;

    public function __construct(string|int|float|Decimal $value, int $scale = 2)
    {
        $this->scale = $scale;
        $this->value = $this->normalize($value, $scale);
    }

    public static function of(string|int|float|Decimal $value, int $scale = 2): self
    {
        return new self($value, $scale);
    }

    public static function zero(int $scale = 2): self
    {
        return new self('0', $scale);
    }

    public function add(string|int|float|Decimal $addend): self
    {
        $other = $this->toDecimalString($addend);

        return new self(bcadd($this->value, $other, $this->scale), $this->scale);
    }

    public function sub(string|int|float|Decimal $subtrahend): self
    {
        $other = $this->toDecimalString($subtrahend);

        return new self(bcsub($this->value, $other, $this->scale), $this->scale);
    }

    public function mul(string|int|float|Decimal $multiplier, ?int $resultScale = null): self
    {
        $scale = $resultScale ?? $this->scale;
        $other = $this->toDecimalString($multiplier);

        return new self(bcmul($this->value, $other, $scale), $scale);
    }

    public function div(string|int|float|Decimal $divisor, ?int $resultScale = null): self
    {
        $scale = $resultScale ?? $this->scale;
        $other = $this->toDecimalString($divisor);

        if (bccomp($other, '0', $scale) === 0) {
            throw new InvalidArgumentException('Division by zero in Decimal calculation.');
        }

        return new self(bcdiv($this->value, $other, $scale), $scale);
    }

    public function compareTo(string|int|float|Decimal $other): int
    {
        return bccomp($this->value, $this->toDecimalString($other), $this->scale);
    }

    public function isZero(): bool
    {
        return $this->compareTo('0') === 0;
    }

    public function isPositive(): bool
    {
        return $this->compareTo('0') > 0;
    }

    public function isNegative(): bool
    {
        return $this->compareTo('0') < 0;
    }

    public function isGreaterThan(string|int|float|Decimal $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isGreaterThanOrEqualTo(string|int|float|Decimal $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    public function isLessThan(string|int|float|Decimal $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isLessThanOrEqualTo(string|int|float|Decimal $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    public function equals(string|int|float|Decimal $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function toFloat(): float
    {
        return (float) $this->value;
    }

    public function getScale(): int
    {
        return $this->scale;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    protected function normalize(string|int|float|Decimal $value, int $scale): string
    {
        $str = $this->toDecimalString($value);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $str)) {
            throw new InvalidArgumentException("Invalid decimal format: [{$str}].");
        }

        return bcadd($str, '0', $scale);
    }

    protected function toDecimalString(string|int|float|Decimal $value): string
    {
        if ($value instanceof self) {
            return $value->toString();
        }

        if (is_float($value)) {
            return number_format($value, 8, '.', '');
        }

        return (string) $value;
    }
}
