<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                CREATE TABLE cohort_learner_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cohort_id CHAR(36) NOT NULL REFERENCES cohorts(id) ON DELETE CASCADE,
                    learner_id CHAR(36) NOT NULL REFERENCES learners(id) ON DELETE CASCADE,
                    enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    status VARCHAR(10) CHECK(status IN ('actif','retrait','diplome','deplace')) NOT NULL DEFAULT 'actif',
                    UNIQUE(cohort_id, learner_id)
                )
            ");
            DB::statement("INSERT INTO cohort_learner_new SELECT * FROM cohort_learner");
            DB::statement("DROP TABLE cohort_learner");
            DB::statement("ALTER TABLE cohort_learner_new RENAME TO cohort_learner");
            DB::statement("CREATE INDEX cohort_learner_learner_id_index ON cohort_learner (learner_id)");
        } else {
            DB::statement("ALTER TABLE cohort_learner MODIFY COLUMN status ENUM('actif','retrait','diplome','deplace') NOT NULL DEFAULT 'actif'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                CREATE TABLE cohort_learner_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cohort_id CHAR(36) NOT NULL REFERENCES cohorts(id) ON DELETE CASCADE,
                    learner_id CHAR(36) NOT NULL REFERENCES learners(id) ON DELETE CASCADE,
                    enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    status VARCHAR(10) CHECK(status IN ('actif','retrait','diplome')) NOT NULL DEFAULT 'actif',
                    UNIQUE(cohort_id, learner_id)
                )
            ");
            DB::statement("INSERT INTO cohort_learner_new SELECT id, cohort_id, learner_id, enrolled_at, CASE WHEN status = 'deplace' THEN 'retrait' ELSE status END FROM cohort_learner");
            DB::statement("DROP TABLE cohort_learner");
            DB::statement("ALTER TABLE cohort_learner_new RENAME TO cohort_learner");
            DB::statement("CREATE INDEX cohort_learner_learner_id_index ON cohort_learner (learner_id)");
        } else {
            DB::statement("ALTER TABLE cohort_learner MODIFY COLUMN status ENUM('actif','retrait','diplome') NOT NULL DEFAULT 'actif'");
        }
    }
};
