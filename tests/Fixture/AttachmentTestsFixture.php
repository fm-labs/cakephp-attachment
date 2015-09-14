<?php
namespace Attachment\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * AttachmentTestsFixture
 *
 */
class AttachmentTestsFixture extends TestFixture
{

    /**
     * Fields
     *
     * @var array
     */
    // @codingStandardsIgnoreStart
    public $fields = [
        'id' => ['type' => 'integer', 'length' => 11, 'unsigned' => true, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        'title' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'fixed' => null],
        'file' => ['type' => 'text', 'length' => null, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null, 'fixed' => null],
        'files' => ['type' => 'text', 'length' => null, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB', 'collation' => 'utf8_general_ci'
        ],
    ];
    // @codingStandardsIgnoreEnd

    /**
     * Records
     *
     * @var array
     */
    public $records = [
        [
            'id' => 1,
            'title' => 'Test 1',
            'file' => 'attachment01.txt',
            'files' => ''
        ],
        [
            'id' => 2,
            'title' => 'Test 2',
            'file' => '',
            'files' => 'attachment01.txt'
        ],
        [
            'id' => 3,
            'title' => 'Test 3',
            'file' => 'attachment01.txt',
            'files' => 'attachment01.txt'
        ],
        [
            'id' => 4,
            'title' => 'Test 4',
            'file' => 'attachment01.txt',
            'files' => 'attachment01.txt,attachment02.txt'
        ]
    ];
}
