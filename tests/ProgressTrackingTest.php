<?php

namespace Garden\Progress\Tests;

use Garden\Progress\Progress;
use Garden\Progress\ProgressCompletion;
use Garden\Progress\ProgressStep;
use Garden\Progress\ProgressTransientData;
use PHPUnit\Framework\TestCase;

/**
 * Test that we can track progress.
 */
class ProgressTrackingTest extends TestCase
{
    /**
     * Test tracking of success values and increments.
     */
    public function testTrackSuccess(): void
    {
        $progress = new Progress("my progress");
        $step1 = $progress->step("step1");
        $step1->incrementSuccess(10);
        $step1->trackSuccessID(5);
        $step1->trackSuccessID("thing1");
        $step1->incrementSuccess(5);
        $this->assertEquals(17, $progress->steps["step1"]->completion->countSuccess);
        $this->assertSame([5, "thing1"], $progress->steps["step1"]->transientData->successIDs);
    }

    /**
     * Test tracking of failed values and increments.
     */
    public function testTrackingFailed(): void
    {
        $progress = new Progress("my progress");
        $step1 = $progress->step("step1");
        $step1->incrementFailed(3);
        $step1->trackFailedID(10);
        $step1->trackFailedID("badThing", new \Exception("Boom!", 501));
        $step1->incrementFailed(6);
        $this->assertEquals(11, $progress->steps["step1"]->completion->countFailed);
        $this->assertSame([10, "badThing"], $progress->steps["step1"]->transientData->failedIDs);
        $this->assertSame(
            [
                "badThing" => [
                    "message" => "Boom!",
                    "code" => 501,
                ],
            ],
            $progress->steps["step1"]->transientData->errorsByID,
        );
    }

    /**
     * Test merging of steps from multiple progresses.
     */
    public function testMergeSteps(): void
    {
        $progress1 = new Progress("my progress");
        $progress1
            ->step("step1")
            ->setTotal(15)
            ->incrementSuccess(2)
            ->incrementFailed(10)
            ->trackSuccessID("item1")
            ->trackFailedID("bad1");
        $progress1->step("step2")->incrementSuccess(20);
        $progress2 = new Progress("other progress");
        $progress2
            ->step("step2")
            ->setTotal(40)
            ->incrementSuccess(20);
        $progress2
            ->step("step3")
            ->setTotal(50)
            ->incrementFailed(2);
        $progress3 = new Progress("another");
        $progress3->step("step1")->trackSuccessID("item2");
        $progress3->step("step3")->incrementSuccess(48);

        $merged = $progress1->merge($progress2, $progress3);
        $this->assertEquals("my progress", $merged->name, "The original name is kept.");
        $this->assertEquals(new ProgressCompletion(15, 4, 11), $merged->step("step1")->completion);
        $this->assertEquals(
            new ProgressTransientData(["item1", "item2"], ["bad1"]),
            $merged->step("step1")->transientData,
        );
        $this->assertEquals(new ProgressCompletion(40, 40, 0), $merged->step("step2")->completion);
        $this->assertEquals(new ProgressCompletion(50, 48, 2), $merged->step("step3")->completion);
    }

    /**
     * Test that transient data is truncated at a certain point.
     */
    public function testTransientDataLimit(): void
    {
        // Test that we only keep a certain amount of transient data during tracking and merging.
        $progress = new Progress("Will Hit Limit");
        $step1 = $progress->step("step1");
        $ex = new \Exception("Boom!");
        for ($i = 1; $i <= 101; $i++) {
            $step1->trackFailedID("bad" . $i, $ex);
        }
        $this->assertNotContains("bad1", $step1->transientData->failedIDs);
        $this->assertArrayNotHasKey("bad1", $step1->transientData->errorsByID);
        $this->assertContains("bad2", $step1->transientData->failedIDs);
        $this->assertArrayHasKey("bad2", $step1->transientData->errorsByID);
        $this->assertContains("bad101", $step1->transientData->failedIDs);
        $this->assertArrayHasKey("bad101", $step1->transientData->errorsByID);

        // Test that things are properly limited in a merge.
        $progress2 = new Progress("Will Hit Limit");
        $step1 = $progress2->step("step1");
        $ex = new \Exception("Boom!");
        for ($i = 102; $i <= 200; $i++) {
            $step1->trackFailedID("bad" . $i, $ex);
        }

        $progress3 = new Progress("Will Hit Limit");
        $step1 = $progress3->step("step1");
        $ex = new \Exception("Boom!");
        for ($i = 201; $i <= 300; $i++) {
            $step1->trackFailedID("bad" . $i, $ex);
        }

        $merged = $progress->merge($progress2, $progress3);
        $step1 = $merged->step("step1");
        $this->assertNotContains("bad200", $step1->transientData->failedIDs);
        $this->assertArrayNotHasKey("bad200", $step1->transientData->errorsByID);
        $this->assertContains("bad201", $step1->transientData->failedIDs);
        $this->assertArrayHasKey("bad201", $step1->transientData->errorsByID);
        $this->assertContains("bad300", $step1->transientData->failedIDs);
        $this->assertArrayHasKey("bad300", $step1->transientData->errorsByID);
    }
}
