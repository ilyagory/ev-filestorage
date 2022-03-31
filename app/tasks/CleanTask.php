<?php

use Phalcon\Cli\Task;

class CleanTask extends Task
{
    public function mainAction()
    {
        $dt = gmdate('Y-m-d H:i');
        $over = Stored::find([
            'conditions' => 'till <= ?1',
            'bind' => [1 => $dt],
        ]);
        $this->db->delete('storage', "till <= '{$dt}'");
        foreach ($over as $item) {
            if (file_exists($item->storedPath)) {
                unlink($item->storedPath);
            }
        }
    }
}