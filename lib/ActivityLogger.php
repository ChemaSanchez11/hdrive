<?php

class ActivityLogger
{
    private ?int $userId;

    public function __construct(?int $userId = null)
    {

        $this->userId = $userId;
    }

    public function log(string $action, string $target): void
    {
        global $DB;

        try {
            $DB->execute(
                'INSERT INTO activity_log (user_id, action, target)
                 VALUES (:uid, :action, :target)',
                [
                    'uid'    => $this->userId,
                    'action' => $action,
                    'target' => $target
                ]
            );
        } catch (PDOException $e) {
            // En producción quizá solo registrar en un archivo
            error_log('ActivityLogger error: ' . $e->getMessage());
        }
    }
}