<?php
namespace Attachment\Model\Entity;

use Cake\Filesystem\File;
use Cake\ORM\Entity;

class AttachmentFile extends Entity
{
    protected $_accessible = [
        '*' => true
    ];

    /**
     * Import Attachment File from file system
     *
     * @param $filePath Path to file
     * @return Entity
     */
    public static function import($filePath)
    {
        $file = new File($filePath, false);

        $entity = new self();
        $entity->set('is_import', true);
        $entity->set('import_source', $filePath);

        $entity->set('name', $file->name());
        $entity->set('source', null);
        $entity->set('basename', $file->info()['basename']);
        $entity->set('ext', $file->ext());

        return $entity;
    }

    public function __toString()
    {
        return (string) $this->get('name');
    }
}
