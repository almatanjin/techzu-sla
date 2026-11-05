<?php

namespace App\Search;

final class TicketSearchTerm
{
    /**
     * @param list<string> $words
     */
    private function __construct(
        public readonly string $type,
        public readonly ?string $number = null,
        public readonly array $words = [],
        public readonly ?string $expression = null,
    ) {}

    public static function none(): self
    {
        return new self('none');
    }

    public static function number(string $number): self
    {
        return new self('number', number: $number);
    }

    /**
     * @param list<string> $words
     */
    public static function keywords(array $words, ?string $expression = null): self
    {
        return new self('keywords', words: $words, expression: $expression);
    }

    public function isNone(): bool
    {
        return $this->type === 'none';
    }

    public function isNumber(): bool
    {
        return $this->type === 'number';
    }

    public function isKeywords(): bool
    {
        return $this->type === 'keywords';
    }

    public static function parse(?string $input): self
    {
        if ($input === null) {
            return self::none();
        }

        $trimmed = trim($input);
        if ($trimmed === '') {
            return self::none();
        }

        if (preg_match('/^TKT-\d{4}-\d{6}$/i', $trimmed) === 1) {
            return self::number(strtoupper($trimmed));
        }

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = [];
        foreach ($tokens as $token) {
            $lower = mb_strtolower($token, 'UTF-8');
            if (mb_strlen($lower, 'UTF-8') >= 3) {
                $words[] = $lower;
            }
        }

        $uniqueWords = array_values(array_unique($words));
        $limitedWords = array_slice($uniqueWords, 0, 10);

        if (empty($limitedWords)) {
            return self::keywords([]);
        }

        $expression = implode(' ', array_map(fn (string $w) => "+{$w}*", $limitedWords));

        return self::keywords($limitedWords, $expression);
    }
}

