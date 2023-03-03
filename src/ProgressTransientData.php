<?php
declare(strict_types=1);
namespace Garden\Progress;

use Garden\Schema\Schema;

/**
 * Used to temporarily hold IDs and errors related to step progress.
 * The total capacity of data here is intentionally limited to limit storage and memory requirements.
 *
 * As more transient data is pushed in, older transient data will be removed.
 */
class ProgressTransientData implements \JsonSerializable
{
    public const MAX_TRACKED = 100;

    /** @var array */
    public array $successIDs;

    /** @var array */
    public array $failedIDs;

    /** @var array<array-key, array{message: string, code: int}> */
    public array $errorsByID;

    /**
     * @param array $successIDs
     * @param array $failedIDs
     * @param array<array-key, array{message: string, code: int}> $errorsByID
     * @internal
     */
    public function __construct(array $successIDs = [], array $failedIDs = [], array $errorsByID = [])
    {
        $this->successIDs = $successIDs;
        $this->failedIDs = $failedIDs;
        $this->errorsByID = $errorsByID;
    }

    /**
     * @return Schema
     */
    public static function schema(): Schema
    {
        return Schema::parse([
            "successIDs:a" => [
                "items" => [
                    "type" => ["string", "number"],
                ],
            ],
            "failedIDs:a" => [
                "items" => [
                    "type" => ["string", "number"],
                ],
            ],
            "errorsByID:o",
        ]);
    }

    /**
     * @param array $from
     * @return ProgressTransientData
     * @internal
     */
    public static function fromArray(array $from): ProgressTransientData
    {
        return new ProgressTransientData(
            $from["successIDs"] ?? [],
            $from["failedIDs"] ?? [],
            $from["errorsByID"] ?? [],
        );
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            "successIDs" => $this->successIDs,
            "failedIDs" => $this->failedIDs,
            "errorsByID" => $this->errorsByID,
        ];
    }

    /**
     * Merge with other transient data.
     *
     * - Duplicate IDs will be de-duplicated.
     * - Only 100 of each type of transient data will be preserved.
     *
     * @param ProgressTransientData ...$transientDatas
     * @return ProgressTransientData
     */
    public function merge(ProgressTransientData ...$transientDatas): ProgressTransientData
    {
        // Merge transient datas together.
        // Notably we are limiting these.

        $newSuccessIDs = [$this->successIDs];
        $newFailedIDs = [$this->failedIDs];
        $newErrorsByID = [$this->errorsByID];

        foreach ($transientDatas as $transientData) {
            $newSuccessIDs[] = $transientData->successIDs;
            $newFailedIDs[] = $transientData->failedIDs;
            $newErrorsByID[] = $transientData->errorsByID;
        }

        $newSuccessIDs = $this->limitToMaxEntries(array_unique(array_merge(...$newSuccessIDs)));
        $newFailedIDs = $this->limitToMaxEntries(array_unique(array_merge(...$newFailedIDs)));
        $newErrorsByID = $this->limitToMaxEntries(array_merge(...$newErrorsByID));

        return new ProgressTransientData(array_values($newSuccessIDs), array_values($newFailedIDs), $newErrorsByID);
    }

    /**
     * Trim down an array to a limited number of entries.
     *
     * @param array $toLimit
     * @return array
     */
    private function limitToMaxEntries(array $toLimit): array
    {
        // Ensure we start at least at 0.
        $start = max(count($toLimit) - self::MAX_TRACKED, 0);

        return array_slice($toLimit, $start, self::MAX_TRACKED, true);
    }
}
