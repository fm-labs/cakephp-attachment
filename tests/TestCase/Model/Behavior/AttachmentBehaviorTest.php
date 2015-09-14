<?php
namespace Attachment\Test\TestCase\Model\Behavior;

use Attachment\Test\TestCase\AttachmentPluginTestCase;
use Attachment\Model\Entity\AttachmentFile;
use Cake\ORM\TableRegistry;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class AttachmentBehaviorTest extends AttachmentPluginTestCase
{
    /**
     * fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.attachment.attachment_tests'
    ];

    /**
     * @var Table
     */
    public $table;

    /**
     * @var
     */
    public $testStorageDir;

    public function setUp()
    {
        parent::setUp();

        $this->table = TableRegistry::get('AttachmentTests');
        $this->table->primaryKey(['id']);
        $this->table->entityClass('\\Attachment\\Test\\TestCase\\Model\\Entity\\ExampleEntity');
        $this->table->addBehavior('Attachment.Attachment', [
            'dataDir' => static::$attachmentsDir,
            'fields' => [
                'file' => [

                ],
                'files' => [
                    'multiple' => true,
                ]
            ]
        ]);
        //$this->table->validator();
    }

    public function tearDown()
    {
        parent::tearDown();
        TableRegistry::clear();
    }

    public function testTestSetup()
    {
        $this->assertTrue(is_file($this->getTestFilePath('attachment01.txt')));
        $this->assertEquals('Test', $this->getTestFileContents('attachment01.txt'));
    }

    public function testGetEntityWithInlineAttachmentFile()
    {
        $entity = $this->table->get(1);

        $this->assertArrayHasKey('file', $entity);
        $this->assertInstanceOf('\\Attachment\\Model\\Entity\\AttachmentFile', $entity['file']);

        //$this->assertEquals((string)$entity['file'], $entity['file']['source']);
        //$this->assertEquals((string)$entity->file, $entity['file']->source);
    }

    public function testGetEntityWithMultipleInlineAttachmentFiles()
    {
        $entity = $this->table->get(4);

        $this->assertArrayHasKey('files', $entity);
        $this->assertInternalType('array', $entity['files']);
        $this->assertEquals(2, count($entity['files']));

        $this->assertInstanceOf('\\Attachment\\Model\\Entity\\AttachmentFile', $entity['files'][0]);
        $this->assertInstanceOf('\\Attachment\\Model\\Entity\\AttachmentFile', $entity['files'][1]);

    }

    public function testCreateEntityWithInlineAttachmentFile()
    {
        $filePath = 'attachment03.txt';

        $entity = $this->table->newEntity();
        $entity->title = 'Test Create';
        $entity->file = $filePath;

        if ($entity->errors()) {
            debug($entity->errors());
        }

        $this->table->save($entity);
        $this->assertNotEmpty($entity['id']);

        // @todo clear cache

        $entity = $this->table->get($entity['id']);
        //debug($entity);
        $this->assertEquals($filePath, (string) $entity['file']);
    }

}
