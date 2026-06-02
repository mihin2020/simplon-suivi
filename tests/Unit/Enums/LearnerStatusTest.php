<?php

namespace Tests\Unit\Enums;

use App\Enums\LearnerStatus;
use Tests\TestCase;

class LearnerStatusTest extends TestCase
{
    public function test_all_statuses_have_labels()
    {
        foreach (LearnerStatus::cases() as $status) {
            $this->assertNotNull($status->label());
            $this->assertIsString($status->label());
        }
    }

    public function test_all_statuses_have_colors()
    {
        foreach (LearnerStatus::cases() as $status) {
            $this->assertNotNull($status->color());
            $this->assertIsString($status->color());
        }
    }

    public function test_in_progress_status()
    {
        $status = LearnerStatus::InProgress;
        
        $this->assertEquals('in_progress', $status->value);
        $this->assertIsString($status->label());
        $this->assertIsString($status->color());
    }

    public function test_completed_status()
    {
        $status = LearnerStatus::Completed;
        
        $this->assertEquals('completed', $status->value);
        $this->assertIsString($status->label());
        $this->assertIsString($status->color());
    }

    public function test_withdrawn_status()
    {
        $status = LearnerStatus::Withdrawn;
        
        $this->assertEquals('withdrawn', $status->value);
        $this->assertIsString($status->label());
        $this->assertIsString($status->color());
    }

    public function test_moved_status()
    {
        $status = LearnerStatus::Moved;
        
        $this->assertEquals('moved', $status->value);
        $this->assertIsString($status->label());
        $this->assertIsString($status->color());
    }
}
