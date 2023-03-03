# vanilla/garden-progress

When you have long-running operations it can be useful for both UX and debugging
to know at any given point in time what part of the operation is currently being executed,
how much work has been completed, and how much work remains.

This package is a standardized structure of storing such data, with an easy API for tracking it over time and merging multiple types of progress together.

## Installation

```sh
composer require vanilla/garden-progress
```

## Usage

**Tracking Progress**

```php
use Garden\Progress\Progress;

$progress = new Progress();

$step1 = $progress->step("step1");

// Optional but completion is generally more easily tracked if you know how much work there is to do.
$step1->setTotal(count($thingsToDo));

// Track a bunch of work at once.
try {
    $thing->doWorkOnItems($thingsToDo);
    $step1->incrementSuccess(count($thingsToDo));
} catch (Exception $failed) {
    // Handle your error.
    $step1->incrementFailed(count($thingsToDo));
}

// Track specific IDs.
foreach ($thingsToDo as $thingID) {
    try {
        $thing->doThing($thingID);
        $step1->trackSuccessID($thingID);
    } catch (Throwable $e) {
        $step1->trackFailedID($thingID, $e);
    }
}

// Access current completion
$progress->step("step1")->completion->countFailed;
$progress->step("step1")->completion->countTotal;
$progress->step("step1")->completion->countSuccess;

$progress->step("step1")->transientData->successIDs;
$progress->step("step1")->transientData->failedIDs;
$progress->step("step1")->transientData->errorsByID[$someID]["message"] ?? null;
```

**Serializing and Deserializing as JSON**

```php
use Garden\Progress\Progress;

// You dump it down to an array/json
$progress = new Progress();
$progress
    ->step("step1")
    ->setTotal(10)
    ->incrementSuccess(10);
$progress
    ->step("step2")
    ->setTotal(20)
    ->incrementFailed(20);
$json = json_encode($progress);

// And pull it back out again.
$fromJson = Progress::fromArray(json_decode($progress, true));
```

**Merging Progress**

Progresses are meant to merged. Completion and transient data for each step will be merged.

```php
use Garden\Progress\Progress;

$progress1 = new Progress();
$progress1->step("step1");

$progress2 = new Progress();
$progress2->step("step2");

$progress3 = new Progress();
$progress3->step("step2");
$progress3->step("step3");

$merged = $progress1->merge($progress2, $progress3);
// All these were merged together.
$merged->step("step1");
$merged->step("step2");
$merged->step("step3");
```

### Job Progress Complex Example

```json5
{
    // General label for the thing being progress.
    name: "Doing the job",
    // Many jobs may only have 1 step being tracked, but a more complex job may have multiple.
    steps: {
        // Steps are named
        "A step name": {
            name: "A step name",
            // Each step tracks progress.
            // Progress accumulates over time.
            completion: {
                countTotal: 54103,
                countComplete: 4200,
                countFailed: 4,
            },
            // Transient IDs can be useful for something watching a job progress intimately.
            // Only a small amount of these should ever accumulate in storage systems
            // As they could easily become too much in a large process.
            // Many types of jobs will not return this data at all.
            transientData: {
                successIDs: [51, 590, 131, 32],
                failedIDs: [12, 15, 43, 452],
                errorsByID: {
                    "452": {
                        message: "You do not have permission to modify this article",
                        code: 403,
                    },
                },
            },
        },
    },
}
```
