<?php
declare(strict_types=1);
namespace Garden\Progress;

use Garden\Schema\Schema;

/**
 * Represents how much of is completed in a progress.
 */
class ProgressCompletion implements \JsonSerializable
{
    /** @var int|null Total count of units in the progress. */
    public ?int $countTotal;

    /** @var int Total count of units completed successfully. */
    public int $countSuccess;

    /** @var int Total count of units that encountered an error and will not be retried. */
    public int $countFailed;

    /**
     * @param int|null $countTotal
     * @param int|null $countSuccess
     * @param int|null $countFailed
     * @internal Use Progress::step().
     */
    public function __construct(?int $countTotal = null, ?int $countSuccess = null, ?int $countFailed = null)
    {
        $this->countTotal = $countTotal;
        $this->countSuccess = $countSuccess ?? 0;
        $this->countFailed = $countFailed ?? 0;
    }

    /**
     * Used to rehydrate from after jsonSerialize().
     *
     * @param array $from
     *
     * @return ProgressCompletion
     * @internal
     */
    public static function fromArray(array $from): ProgressCompletion
    {
        return new ProgressCompletion(
            $from["countTotal"] ?? null,
            $from["countSuccess"] ?? null,
            $from["countFailed"] ?? null,
        );
    }

    /**
     * @return Schema
     */
    public static function schema(): Schema
    {
        return Schema::parse(["countTotal:i?", "countSuccess:i", "countFailed:i"]);
    }

    /**
     * Merge with other completions.
     *
     * @param ProgressCompletion ...$completions
     *
     * @return ProgressCompletion
     * @internal
     */
    public function merge(ProgressCompletion ...$completions): ProgressCompletion
    {
        $result = new ProgressCompletion($this->countTotal, $this->countSuccess, $this->countFailed);
        foreach ($completions as $completion) {
            $result->countTotal = $result->countTotal ?? $completion->countTotal;
            $result->countSuccess += $completion->countSuccess;
            $result->countFailed += $completion->countFailed;
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            "countTotal" => $this->countTotal,
            "countSuccess" => $this->countSuccess,
            "countFailed" => $this->countFailed,
        ];
    }
}
