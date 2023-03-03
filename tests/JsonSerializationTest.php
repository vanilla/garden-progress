<?php
namespace Garden\Progress\Tests;

use Garden\Progress\Progress;
use Garden\Schema\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Test JSON serialization and deserialization.
 */
class JsonSerializationTest extends TestCase
{
    /**
     * Test that we can serialize to and from JSON.
     *
     * @return void
     */
    public function testSerializeToAndFromJson(): void
    {
        $progress = new Progress("My Progress");
        $progress
            ->step("my step")
            ->setTotal(10)
            ->incrementSuccess(4)
            ->trackSuccessID("goodID")
            ->incrementFailed(4)
            ->trackFailedID("badID", new \Exception("Boom!", 500));

        $json = <<<JSON
        {
            "name": "My Progress",
            "steps": {
                "my step": {
                    "name": "my step",
                    "completion": {
                        "countTotal": 10,
                        "countSuccess": 5,
                        "countFailed": 5
                    },
                    "transientData": {
                        "successIDs": ["goodID"],
                        "failedIDs": ["badID"],
                        "errorsByID": {
                            "badID": {
                                "message": "Boom!",
                                "code": 500
                            }
                        }
                    }
                }
            }
        }

        JSON;

        $this->assertJsonStringEqualsJsonString($json, json_encode($progress));

        // Now decode it and recreate it.
        $fromJson = json_decode($json, true);
        $fromJson = Progress::fromArray($fromJson);
        $this->assertEquals($progress, $fromJson);
    }

    /**
     * Test that fromArray() throws if we get a bad array.
     */
    public function testThrowsOnBadData(): void
    {
        // Let's say the steps got badly encoded.
        $arr = [
            "name" => "My name",
            "steps" => [["name" => "my step"]],
        ];
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("steps/0/completion: completion is required");
        Progress::fromArray($arr);
    }
}
