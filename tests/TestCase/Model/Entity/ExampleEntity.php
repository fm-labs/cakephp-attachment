<?php
/**
 * Created by PhpStorm.
 * User: flow
 * Date: 1/27/15
 * Time: 3:10 PM
 */

namespace Attachment\Test\TestCase\Model\Entity;

use Attachment\Model\Entity\AttachmentTrait;
use Cake\ORM\Entity;

class ExampleEntity extends Entity
{
    use AttachmentTrait;

    protected $_accessible = [
        '*' => true,
    ];
}
