<?php
declare(strict_types=1);
namespace Garden\Progress;

use Garden\Schema\Schema;
use Garden\Schema\ValidationException;
use Garden\Schema\ValidationField;

/**
 * Top level progess tracking class in the library.
 *
 * This is what you should generally be constructing.
 */
class Progress implements \JsonSerializable
{
    /** @var string The name of the progress. */
    public string $name;

    /**
     * The associative array of progress steps.
     * @var array<string, ProgressStep>
     * @internal Use `step()` to access steps.
     */
    public array $steps;

    /**
     * Constructor.
     *
     * @param string $name The name of the progress.
     * @param ProgressStep[] $steps
     */
    public function __construct(string $name, array $steps = [])
    {
        $this->name = $name;
        $this->steps = $steps;
    }

    /**
     * Create an instance from an array. Generally used if progress has been json_serialized previously.
     *
     * @param array $from The array to load from.
     * @return Progress The progress instance.
     *
     * @throws \Garden\Schema\ValidationException If the data is corrupted in some way.
     */
    public static function fromArray(array $from): Progress
    {
        $validated = @self::schema()->validate($from);

        $steps = $validated["steps"] ?? [];

        foreach ($steps as &$step) {
            // Suppress warning of deprecated "multiple schema types"
            // This deprecation will be removed in a future garden-schema version.
            $step = ProgressStep::fromArray($step);
        }

        return new Progress($validated["name"], $steps);
    }

    /**
     * Schema used to validate a progress.
     *
     * @return Schema
     */
    public static function schema(): Schema
    {
        $stepSchema = ProgressStep::schema();

        // Suppress deprecation from schema 3.x about requiring a full path (eg /properties/steps)
        // We will be using this with vanilla-cloud which is still on schema 1.x
        return @Schema::parse(["name:s", "steps:o|a"])->addValidator("steps", function (
            array $steps,
            ValidationField $field
        ) use ($stepSchema) {
            foreach ($steps as $stepName => &$step) {
                try {
                    $step = @$stepSchema->validate($step);
                } catch (ValidationException $exception) {
                    $field->getValidation()->merge($exception->getValidation(), "{$field->getName()}/{$stepName}");
                    return false;
                }
            }
            return $steps;
        });
    }

    /**
     * Get a step with a particular name, creating it if it doesn't exist.
     *
     * @param string $name
     *
     * @return ProgressStep
     */
    public function step(string $name): ProgressStep
    {
        if (!isset($this->steps[$name])) {
            $this->steps[$name] = new ProgressStep($name);
        }
        return $this->steps[$name];
    }

    /**
     * Merge multiple progresses together.
     *
     * - The name of the current progress will be kept.
     * - Steps with the same name will be merged together.
     * - Transient data in steps will be truncated after merge if there is too much.
     *
     * @param Progress ...$otherProgresses The progesses to merge.
     *
     * @return Progress
     */
    public function merge(Progress ...$otherProgresses): Progress
    {
        /** @var array<string, ProgressStep[]> $allStepsByName */
        $allStepsByName = [];
        $allProgresses = array_merge([$this], $otherProgresses);
        foreach ($allProgresses as $progress) {
            foreach ($progress->steps as $stepName => $step) {
                $allStepsByName[$stepName][] = $step;
            }
        }

        $finalSteps = [];
        foreach ($allStepsByName as $stepName => $steps) {
            $firstStep = array_splice($steps, 0, 1)[0];
            $finalSteps[$stepName] = $firstStep->merge(...$steps);
        }

        return new Progress($this->name, $finalSteps);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            "name" => $this->name,
            "steps" => $this->steps,
        ];
    }
}
