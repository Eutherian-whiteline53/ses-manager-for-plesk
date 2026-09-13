<?php

namespace Library\Repository;

class MailTestRepository
{
    public function create(array $data)
    {
        $db = \pm_Bootstrap::getDbAdapter();
        $data['created_at'] = date('Y-m-d H:i:s');

        $db->insert('ses_mail_tests', $data);

        return (int) $db->lastInsertId();
    }

    public function findRecent($limit = 20)
    {
        $db = \pm_Bootstrap::getDbAdapter();

        return $db->fetchAll('SELECT * FROM ses_mail_tests ORDER BY created_at DESC LIMIT ' . (int) $limit);
    }
}
