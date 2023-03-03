<?php
declare(strict_types=1);

namespace Garden\Progress;

use Garden\Schema\Schema;

/**
 * Represents one step in a progress.
 */
class ProgressStep implements \JsonSerializable
{
    /** @var string Name of the step. */
    public string $name;

    /** @var ProgressCompletion Track completion of the step. */
    public ProgressCompletion $completion;

    /** @var ProgressTransientData Transient data about completion of the step. */
    public ProgressTransientData $transientData;

    /**
     * @param string $name
     * @param ProgressCompletion|null $completion
     * @param ProgressTransientData|null $transientData
     * @internal Use Progress::step().
     */
    public function __construct(
        string $name,
        ?ProgressCompletion $completion = null,
        ?ProgressTransientData $transientData = null
    ) {
        $this->name = $name;
        $this->completion = $completion ?? new ProgressCompletion();
        $this->transientData = $transientData ?? new ProgressTransientData();
    }

    /**
     * @return Schema
     */
    public static function schema(): Schema
    {
        return Schema::parse([
            "name:s",
            "completion" => ProgressCompletion::schema(),
            "transientData" => ProgressTransientData::schema(),
        ]);
    }

    /**
     * Load after jsonSerialize().
     *
     * @param array $from
     *
     * @return ProgressStep
     * @internal
     */
    public static function fromArray(array $from): ProgressStep
    {
        return new ProgressStep(
            $from["name"],
            ProgressCompletion::fromArray($from["completion"] ?? []),
            ProgressTransientData::fromArray($from["transientData"] ?? []),
        );
    }

    /**
     * Set the total number of units that are completable in the step.
     *
     * @param int|null $countTotal
     *
     * @return $this
     */
    public function setTotal(?int $countTotal): ProgressStep
    {
        $this->completion->countTotal = $countTotal;
        return $this;
    }

    /**
     * Increment the number of completed units.
     *
     * @param int $incBy The amount to increment by.
     *
     * @return $this For chaining.
     */
    public function incrementSuccess(int $incBy = 1): ProgressStep
    {
        $this->completion->countSuccess += $incBy;
        return $this;
    }

    /**
     * Track a particular successful ID in transient data.
     * Also increments successIDs.
     *
     * @param int|string $successID
     *
     * @return $this For chaining.
     */
    public function trackSuccessID($successID): ProgressStep
    {
        $this->incrementSuccess();
        $this->transientData = $this->transientData->merge(new ProgressTransientData([$successID]));
        return $this;
    }

    /**
     * Increment the number of failed units that will not be retried.
     *
     * @param int $incBy The amount to increment by.
     *
     * @return $this For chaining.
     */
    public function incrementFailed(int $incBy = 1): ProgressStep
    {
        $this->completion->countFailed += $incBy;
        return $this;
    }

    /**
     * Track a particular failed ID in transient data.
     * Also increments failedIDs.
     *
     * @param int|string $failedID The failedID.
     * @param \Throwable|null $throwable Optionally a throwable to track in transientData.
     *
     * @return $this For chaining.
     */
    public function trackFailedID($failedID, \Throwable $throwable = null): ProgressStep
    {
        $this->incrementFailed();
        $errorsByID = [];
        if ($throwable) {
            $errorsByID[$failedID] = [
                "message" => $throwable->getMessage(),
                "code" => (int) $throwable->getCode(),
            ];
        }
        $this->transientData = $this->transientData->merge(new ProgressTransientData([], [$failedID], $errorsByID));
        return $this;
    }

    /**
     * Merge with other progress steps.
     *
     * @param ProgressStep ...$steps
     *
     * @return ProgressStep
     */
    public function merge(ProgressStep ...$steps): ProgressStep
    {
        $otherCompletions = [];
        $otherTransients = [];

        foreach ($steps as $step) {
            $otherCompletions[] = $step->completion;
            $otherTransients[] = $step->transientData;
        }

        $result = new ProgressStep(
            $this->name,
            $this->completion->merge(...$otherCompletions),
            $this->transientData->merge(...$otherTransients),
        );
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            "name" => $this->name,
            "completion" => $this->completion,
            "transientData" => $this->transientData,
        ];
    }
}
